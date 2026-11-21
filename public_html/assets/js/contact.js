(function () {
  'use strict';

  /* Email forms (Support ticket, Discount request): Cloudflare Turnstile check, then POST to /api/contact
     (the contact Worker, see /workers/contact). Each message goes to its own random address: a new one is made on
     every keystroke (and on every change), so the one that is sent is whichever was showing at a random moment.
     The visitor picks its domain; every address on those domains reaches us. The subject is locked. */

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
  const localField = form.querySelector('[data-local]');
  const domainField = form.elements.domain;
  const LETTERS = 'abcdefghijklmnopqrstuvwxyz0123456789';
  const LENGTH = 12; // must match LOCAL_PATTERN in the Worker
  let local = '';

  // A random local part: 12 letters and digits from the browser's secure random numbers (no bias: values past
  // the last full set of 36 are drawn again)
  function newLocal() {
    let text = '';

    while (text.length < LENGTH) {
      const bytes = window.crypto.getRandomValues(new Uint8Array(LENGTH * 2));

      for (let i = 0; i < bytes.length && text.length < LENGTH; i++) {
        if (bytes[i] < 252) {
          text += LETTERS[bytes[i] % 36];
        }
      }
    }
    return text;
  }

  function address() {
    return local + '@' + domainField.value;
  }

  /* Subject (Email form): the department, from a list built from the (hidden) <select>, which still holds the
     value, so form.reset() and the request work as before. The locked To address follows it. The Discount
     request form has no list: its department is fixed. */
  const domainLabel = form.querySelector('[data-domain]');
  const listed = domainField.tagName === 'SELECT';
  const picker = document.createElement('button');
  const pickerName = document.createElement('span');
  const pickerDesc = document.createElement('span');
  const menu = document.createElement('div');
  const items = [];
  let active = -1;

  if (listed) {
    const row = domainField.closest('.compose-field');
    const wrap = domainField.parentNode;

    picker.type = 'button';
    picker.className = 'compose-picker';
    picker.id = 'ticket-subject';
    picker.setAttribute('aria-haspopup', 'listbox');
    picker.setAttribute('aria-expanded', 'false');
    pickerName.className = 'compose-picker-name';
    pickerDesc.className = 'compose-picker-desc';
    picker.append(pickerName, pickerDesc, wrap.querySelector('.compose-chevron'));
    menu.className = 'compose-menu';
    menu.id = 'ticket-menu';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', 'Subject');
    menu.hidden = true;
    picker.setAttribute('aria-controls', menu.id);

    Array.prototype.forEach.call(domainField.options, function (option, index) {
      const item = document.createElement('div');
      const name = document.createElement('span');
      const desc = document.createElement('span');

      item.className = 'compose-option';
      item.id = 'ticket-option-' + index;
      item.setAttribute('role', 'option');
      name.className = 'compose-option-name';
      name.textContent = option.getAttribute('data-department');
      desc.className = 'compose-option-desc';
      desc.textContent = option.getAttribute('data-description');
      item.append(name, desc);
      item.addEventListener('click', function () {
        choose(index);
      });
      item.addEventListener('pointermove', function () {
        focusItem(index, false);
      });
      menu.appendChild(item);
      items.push(item);
    });
    domainField.hidden = true;
    domainField.tabIndex = -1;
    row.querySelector('label').setAttribute('for', picker.id);
    wrap.appendChild(picker);
    row.appendChild(menu);
  }

  function showDomain() {
    domainLabel.textContent = domainField.value;

    if (!listed) {
      return;
    }
    const option = domainField.options[domainField.selectedIndex];
    pickerName.textContent = option.getAttribute('data-department');
    pickerDesc.textContent = option.getAttribute('data-description');
    items.forEach(function (item, index) {
      item.setAttribute('aria-selected', index === domainField.selectedIndex ? 'true' : 'false');
    });
  }

  function focusItem(index, scroll) {
    active = Math.max(0, Math.min(items.length - 1, index));
    items.forEach(function (item, i) {
      item.classList.toggle('is-active', i === active);
    });
    menu.setAttribute('aria-activedescendant', items[active].id);
    if (scroll) {
      items[active].scrollIntoView({block: 'nearest'});
    }
  }

  function openMenu() {
    if (!menu.hidden) {
      return;
    }
    menu.hidden = false;
    picker.setAttribute('aria-expanded', 'true');
    // Never taller than the window below the row
    const row = picker.closest('.compose-field').getBoundingClientRect();
    menu.style.maxHeight = Math.max(160, window.innerHeight - row.bottom - 16) + 'px';
    focusItem(domainField.selectedIndex, true);
    menu.tabIndex = -1;
    menu.focus({preventScroll: true});
  }

  function closeMenu(refocus) {
    if (menu.hidden) {
      return;
    }
    menu.hidden = true;
    picker.setAttribute('aria-expanded', 'false');
    if (refocus) {
      picker.focus({preventScroll: true});
    }
  }

  function choose(index) {
    const changed = index !== domainField.selectedIndex;
    domainField.selectedIndex = index;
    closeMenu(true);
    showDomain();
    if (changed) {
      domainField.dispatchEvent(new Event('input', {bubbles: true}));
    }
  }

  picker.addEventListener('click', function () {
    if (menu.hidden) {
      openMenu();
    } else {
      closeMenu(true);
    }
  });

  picker.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      openMenu();
    }
  });

  menu.addEventListener('keydown', function (event) {
    const keys = {ArrowDown: active + 1, ArrowUp: active - 1, Home: 0, End: items.length - 1, PageDown: active + 5, PageUp: active - 5};

    if (event.key in keys) {
      event.preventDefault();
      focusItem(keys[event.key], true);
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      choose(active);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      closeMenu(true);
    } else if (event.key === 'Tab') {
      closeMenu(false);
    }
  });

  // A tap or click anywhere else closes it
  document.addEventListener('pointerdown', function (event) {
    if (!menu.hidden && !menu.contains(event.target) && !picker.contains(event.target)) {
      closeMenu(false);
    }
  });
  window.addEventListener('blur', function () {
    closeMenu(false);
  });

  function nextAddress() {
    local = newLocal();
    localField.textContent = local;
    localField.parentNode.title = address();
    showDomain();
  }
  nextAddress();

  // The reply address only sends replies: show its hint once, a moment after the form opens, until the visitor
  // starts writing (or for a few seconds); it shows again on hover, focus or tap
  const intro = form.querySelector('[data-hint-intro]');

  if (intro) {
    const hideIntro = function () {
      intro.classList.remove('is-hinting');
    };
    setTimeout(function () {
      intro.classList.add('is-hinting');
      setTimeout(hideIntro, 6000);
    }, 700);
    form.addEventListener('input', hideIntro);
    form.addEventListener('pointerdown', hideIntro);
  }

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

    data.to = address();

    if (kind === 'discount') {
      data.product = form.elements.product.value;
      data.reason = form.elements.reason.value;
    }
    const sentTo = address();
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

        // Keep the address of this ticket or request in view (form.reset() put the domain back, so make a new one)
        say('Sent to ' + sentTo + '. A human will reply from ' + form.getAttribute('data-reply') + '.', 'ok');
        nextAddress();
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

  // Every keystroke and every change makes a new address; writing again after a sent message clears the status
  form.addEventListener('input', function (event) {
    if (event.target !== form.elements.website) {
      nextAddress();
    }
    if (form.classList.contains('is-sent')) {
      form.classList.remove('is-sent');
      say('');
    }
  });
})();
