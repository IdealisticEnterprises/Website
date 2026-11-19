(function () {
  'use strict';

  /* Email forms (Contact, Discount request): Cloudflare Turnstile check, then POST to /api/contact
     (the contact Worker, see /workers/contact). A form with data-form="discount" has a locked subject that
     ends in a random request ID, made here; the Worker checks it and builds the subject itself. */

  const form = document.getElementById('contact-form');

  if (!form) {
    return;
  }
  const status = form.querySelector('.contact-status');
  const submit = form.querySelector('button[type="submit"]');
  let waiting = false;
  const holder = document.getElementById('turnstile');
  const siteKey = form.getAttribute('data-sitekey');
  let token = '';
  let widget = null;
  const kind = form.getAttribute('data-form') || 'contact';
  const uuidField = form.querySelector('[data-uuid]');
  let requestId = '';

  // A random version 4 UUID (crypto.randomUUID needs a secure page; the fallback builds the same format)
  function newId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.prototype.map.call(bytes, function (byte) {
      return byte.toString(16).padStart(2, '0');
    }).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  }

  function nextId() {
    if (uuidField) {
      requestId = newId();
      uuidField.textContent = requestId;
    }
  }
  nextId();

  function say(text, kind) {
    status.textContent = text;
    status.className = 'contact-status' + (kind ? ' is-' + kind : '');
  }

  function themeName() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function render() {
    if (!window.turnstile || widget !== null) {
      return;
    }
    widget = window.turnstile.render(holder, {
      sitekey: siteKey,
      theme: themeName(),
      appearance: 'interaction-only',
      callback: function (value) {
        token = value;

        // Send was pressed before the check finished: send now
        if (waiting) {
          waiting = false;
          send();
        }
      },
      'expired-callback': function () {
        token = '';
      },
      'error-callback': function () {
        token = '';
        say('The check could not load. Please reload the page.', 'error');
      }
    });
  }

  // The check follows the site's theme when it changes
  new MutationObserver(function () {
    if (window.turnstile && widget !== null) {
      window.turnstile.remove(widget);
      widget = null;
      token = '';
      render();
    }
  }).observe(document.documentElement, {attributes: true, attributeFilter: ['data-theme']});

  // Load Turnstile only on this page, after the callback exists
  window.onTurnstileLoad = render;
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad';
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);

  function reset() {
    token = '';

    if (window.turnstile && widget !== null) {
      window.turnstile.reset(widget);
    }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    if (!form.reportValidity()) {
      return;
    }
    if (!token) {
      waiting = true;
      say('Verifying…');
      return;
    }
    send();
  });

  function send() {
    const data = {
      form: kind,
      email: form.elements.email.value,
      message: form.elements.message.value,
      website: form.elements.website.value,
      token: token
    };

    if (kind === 'discount') {
      data.uuid = requestId;
      data.product = form.elements.product.value;
      data.reason = form.elements.reason.value;
    } else {
      data.subject = form.elements.subject.value;
    }
    const sentId = requestId;
    submit.disabled = true;
    say('Sending…');

    fetch(form.getAttribute('action'), {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data)
    }).then(function (response) {
      return response.json().catch(function () {
        return {ok: false, error: 'Your message could not be sent right now. Please try again.'};
      });
    }).then(function (result) {
      if (result.ok) {
        form.reset();
        form.classList.add('is-sent');

        if (kind === 'discount') {
          // Keep the ID of this request in view; the next request gets a new one
          say('Sent. Your request ID is ' + sentId + '. A human will answer you by email.', 'ok');
          nextId();
        } else {
          say('Sent. A human will answer you by email.', 'ok');
        }
      } else {
        say(result.error || 'Your message could not be sent. Please try again.', 'error');
      }
      reset();
    }).catch(function () {
      say('Your message could not be sent. Check your connection and try again.', 'error');
      reset();
    }).then(function () {
      submit.disabled = false;
    });
  }

  // Writing again after a sent message
  form.addEventListener('input', function () {
    if (form.classList.contains('is-sent')) {
      form.classList.remove('is-sent');
      say('');
    }
  });
})();
