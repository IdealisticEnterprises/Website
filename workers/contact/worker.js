// Contact form backend for idealistic.enterprises (Cloudflare Worker).
// POST /api/contact with JSON {email, subject, message, token, website} (name optional):
//   1. checks the request comes from the site and the fields are valid,
//   2. verifies the Cloudflare Turnstile token (secret: TURNSTILE_SECRET),
//   3. sends the message to TO_ADDRESS through Cloudflare Email Routing (binding: CONTACT_EMAIL),
//      with Reply-To set to the visitor, so replying in the mailbox answers them directly.

import { EmailMessage } from "cloudflare:email";

const LIMITS = { name: 100, email: 254, subject: 150, message: 5000 };
const EMAIL_PATTERN = /^[^\s@<>()",;:]+@[^\s@<>()",;:]+\.[^\s@<>()",;:]{2,}$/;

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
    const subject = clean(data.subject);
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
      message,
      "",
      "--",
      `Sent with the contact form on idealistic.enterprises (country: ${country}).`,
      "Reply to this email to answer the sender.",
    ].join("\n");

    const raw = [
      `From: ${encodeWords("iDEALISTIC contact form")} <${env.FROM_ADDRESS}>`,
      `To: <${env.TO_ADDRESS}>`,
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
      return reply(502, "Your message could not be sent right now. Please try again, or write to " + env.TO_ADDRESS + ".", cors);
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
