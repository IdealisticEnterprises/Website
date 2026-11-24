// Contact form backend for idealistic.enterprises (Cloudflare Worker).
// POST /api/contact with JSON {form, to, email, message, token, website} (name optional), plus:
//   - to: the ticket's own address, made at random in the browser: 12 letters and digits @ one of the domains in DEPARTMENTS (every
//     address on them reaches us through Email Routing), each a department of the company. It is the email's To header and is in the subject;
//   - form "contact" (the default, the Email page): the visitor picks the department as the subject (the address
//     follows it), and this Worker builds the subject from it: "<department> (<to>)", e.g. "Billing (…@idealistic.shop)";
//   - form "discount": product and reason, always to the Discounts department (idealistic.discount). The subject is
//     locked: "Discount request (<to>)".
//   Subjects are never taken from the page, so they cannot be changed there.
//   1. checks the request comes from the site and the fields are valid,
//   2. verifies the Cloudflare Turnstile token (secret: TURNSTILE_SECRET),
//   3. delivers the message to the inbox TO_ADDRESS through Cloudflare Email Routing (binding: CONTACT_EMAIL),
//      with To: set to the ticket's address and Reply-To set to the visitor, so replying answers them directly.

import { EmailMessage } from "cloudflare:email";

const LIMITS = { name: 100, email: 254, subject: 150, message: 5000 };
const EMAIL_PATTERN = /^[^\s@<>()",;:]+@[^\s@<>()",;:]+\.[^\s@<>()",;:]{2,}$/;
const LOCAL_PATTERN = /^[a-z0-9]{12}$/;

// The domains the forms offer, each a department of the company. Must match TICKET_DOMAINS in the site generator.
const DEPARTMENTS = {
  "idealistic.email": "Support",
  "idealistic.help": "Help",
  "doremi.chat": "doremi.chat",
  "idealistic.chat": "Messaging",
  "idealistic.party": "Party",
  "idealistic.app": "Apps",
  "idealistic.download": "Downloads",
  "idealistic.club": "Membership",
  "idealistic.plus": "plus",
  "idealistic.pro": "pro",
  "idealistic.vip": "vip",
  "idealistic.red": "red",
  "idealistic.shop": "Billing",
  "idealistic.sale": "Sales",
  "idealistic.discount": "Discounts",
  "idealistic.gives": "Giveaways",
  "idealistic.click": "Advertising",
  "idealistic.business": "Partnerships",
  "idealistic.news": "Press",
  "idealistic.work": "Careers",
  "idealistic.ai": "AI",
  "idealistic.trade": "Trademark",
  "idealistic.navy": "Brand",
  "idealistic.company": "Legal",
  "idealistic.enterprises": "Management",
  "idealistic.contact": "Reception",
};


// Must match DISCOUNT_* in the site generator (the choices on the Discount request form)
const DISCOUNT = {
  title: "Discount request",
  domain: "idealistic.discount",
  products: ["doremi.chat", "Spartan AntiCheat"],
  reasons: ["Student", "Teacher or school", "Non-profit organisation", "Open-source project", "Content creator", "Financial hardship", "Other"],
};

export default {
  async fetch(request, env) {
    const origin = request.headers.get("Origin") || "";
    const allowed = (env.ALLOWED_ORIGINS || "https://idealistic.enterprises").split(",").map((value) => value.trim());
    const cors = allowed.includes(origin)
      ? { "Access-Control-Allow-Origin": origin, "Access-Control-Allow-Methods": "POST, OPTIONS", "Access-Control-Allow-Headers": "Content-Type", "Vary": "Origin" }
      : {};

    if (request.method === "OPTIONS") {
      return new Response(null, { status: 204, headers: cors });
    }
    if (request.method !== "POST") {
      return reply(405, "Method not allowed.", cors);
    }
    if (!allowed.includes(origin)) {
      return reply(403, "Forbidden.", cors);
    }

    let data;
    try {
      data = await request.json();
    } catch (error) {
      return reply(400, "The form could not be read. Please try again.", cors);
    }

    // Honeypot: a hidden field that only bots fill in. Pretend it worked.
    if (typeof data.website === "string" && data.website.trim() !== "") {
      return reply(200, null, cors);
    }

    const name = clean(data.name);
    const email = clean(data.email);
    const kind = data.form === "discount" ? "discount" : "contact";
    const to = clean(data.to).toLowerCase();
    const at = to.indexOf("@");
    const department = DEPARTMENTS[to.slice(at + 1)] || "";
    let subject = "";
    let details = [];

    if (at < 0 || !LOCAL_PATTERN.test(to.slice(0, at)) || !Object.prototype.hasOwnProperty.call(DEPARTMENTS, to.slice(at + 1))) {
      return reply(400, "The form is out of date. Please reload the page and try again.", cors);
    }
    if (kind === "contact") {
      subject = `${department} (${to})`;
      details = [`Department: ${department}`, `Ticket address: ${to}`, ""];
    } else {
      const product = clean(data.product);
      const reason = clean(data.reason);

      if (to.slice(at + 1) !== DISCOUNT.domain) {
        return reply(400, "The form is out of date. Please reload the page and try again.", cors);
      }
      if (!DISCOUNT.products.includes(product) || !DISCOUNT.reasons.includes(reason)) {
        return reply(400, "Please choose a product and a reason, then try again.", cors);
      }
      subject = `${DISCOUNT.title} (${to})`;
      details = [`Department: ${department}`, `Product: ${product}`, `Reason: ${reason}`, `Request address: ${to}`, ""];
    }
    const message = typeof data.message === "string" ? data.message.replace(/\r\n?/g, "\n").trim() : "";
    const token = typeof data.token === "string" ? data.token : "";

    if (!email || !subject || !message) {
      return reply(400, "Please fill in every field.", cors);
    }
    if (name.length > LIMITS.name || email.length > LIMITS.email || subject.length > LIMITS.subject || message.length > LIMITS.message) {
      return reply(400, "One of the fields is too long.", cors);
    }
    if (!EMAIL_PATTERN.test(email)) {
      return reply(400, "Please enter a valid email address.", cors);
    }
    if (!token) {
      return reply(400, "Please complete the check.", cors);
    }

    // Verify the captcha
    const form = new FormData();
    form.append("secret", env.TURNSTILE_SECRET || "");
    form.append("response", token);
    const ip = request.headers.get("CF-Connecting-IP");
    if (ip) {
      form.append("remoteip", ip);
    }
    let check = { success: false };
    try {
      const response = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", { method: "POST", body: form });
      check = await response.json();
    } catch (error) {
      return reply(502, "The check could not be verified right now. Please try again.", cors);
    }
    if (!check.success) {
      return reply(400, "The check failed. Please try again.", cors);
    }

    // Build and send the email
    const fromDomain = env.FROM_ADDRESS.split("@")[1];
    const country = request.cf && request.cf.country ? request.cf.country : "unknown";
    const body = [
      `From: ${name ? name + " <" + email + ">" : email}`,
      `Subject: ${subject}`,
      "",
      ...details,
      message,
      "",
      "--",
      `Sent with the ${kind === "discount" ? "discount request" : "support ticket"} form on idealistic.enterprises (country: ${country}).`,
      "Reply to this email to answer the sender.",
    ].join("\n");

    const raw = [
      `From: ${encodeWords(kind === "discount" ? "iDEALISTIC discount request" : "iDEALISTIC support ticket")} <${env.FROM_ADDRESS}>`,
      `To: <${to}>`,
      name ? `Reply-To: ${encodeWords(name)} <${email}>` : `Reply-To: <${email}>`,
      `Subject: ${encodeWords(subject)}`,
      `Date: ${new Date().toUTCString()}`,
      `Message-ID: <${crypto.randomUUID()}@${fromDomain}>`,
      "MIME-Version: 1.0",
      "Content-Type: text/plain; charset=UTF-8",
      "Content-Transfer-Encoding: base64",
      "",
      wrap(base64(body)),
    ].join("\r\n");

    try {
      await env.CONTACT_EMAIL.send(new EmailMessage(env.FROM_ADDRESS, env.TO_ADDRESS, raw));
    } catch (error) {
      return reply(502, "Your message could not be sent right now. Please try again in a few minutes.", cors);
    }
    return reply(200, null, cors);
  },
};

function reply(status, error, headers) {
  return new Response(JSON.stringify(error ? { ok: false, error } : { ok: true }), {
    status,
    headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store", ...headers },
  });
}

// One line, no control characters (stops header injection)
function clean(value) {
  return typeof value === "string" ? value.replace(/[\u0000-\u001f\u007f]+/g, " ").replace(/\s+/g, " ").trim() : "";
}

function base64(text) {
  const bytes = new TextEncoder().encode(text);
  let binary = "";
  for (let i = 0; i < bytes.length; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
}

function wrap(text) {
  return text.replace(/.{1,76}/g, "$&\r\n").trimEnd();
}

// RFC 2047 encoded words, split so no single word gets too long and no character is cut in half
function encodeWords(text) {
  const chars = Array.from(text);
  const words = [];
  for (let i = 0; i < chars.length; i += 12) {
    words.push(`=?UTF-8?B?${base64(chars.slice(i, i + 12).join(""))}?=`);
  }
  return words.join("\r\n ") || '""';
}
