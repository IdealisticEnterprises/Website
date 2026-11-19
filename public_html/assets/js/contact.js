(function () {
  'use strict';

  /* Contact form: Cloudflare Turnstile check, then POST to /api/contact (the contact Worker, see /workers/contact). */

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
      email: form.elements.email.value,
      subject: form.elements.subject.value,
      message: form.elements.message.value,
      website: form.elements.website.value,
      token: token
    };
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
        say('Sent. A human will answer you by email.', 'ok');
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
