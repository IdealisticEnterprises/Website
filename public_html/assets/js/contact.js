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

  // Empty until a subject is picked (Email form): the address is on the subject's domain
  function address() {
    return domainField.value ? local + '@' + domainField.value : '';
  }

  /* Lists (Subject in the Email form, Product and Reason in the Discount request): the browser's own list is rounded
     and does not follow the site's theme, so each <select> gets a square list of ours, built from it. The hidden
     <select> still holds the value, so form.reset() and the request work as before. An option without a value is
     the placeholder ("Choose a product"): shown on the button until something is chosen, never in the list.
     In the Email form the locked To address follows the Subject. */
  const domainLabel = form.querySelector('[data-domain]');
  const pickers = [];

  function makePicker(select) {
    const row = select.closest('.compose-field');
    const button = document.createElement('button');
    const name = document.createElement('span');
    const desc = document.createElement('span');
    const menu = document.createElement('div');
    const items = []; // {item, index}: index is the option's index in the <select>
    let active = 0;

    button.type = 'button';
    button.className = 'compose-picker';
    button.id = select.id + '-picker';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    name.className = 'compose-picker-name';
    desc.className = 'compose-picker-desc';
    button.append(name, desc, row.querySelector('.compose-chevron'));
    menu.className = 'compose-menu';
    menu.id = select.id + '-menu';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', row.querySelector('label').textContent.replace(/:\s*$/, ''));
    menu.tabIndex = -1;
    menu.hidden = true;
    button.setAttribute('aria-controls', menu.id);

    Array.prototype.forEach.call(select.options, function (option, index) {
      if (!option.value) {
        return;
      }
      const item = document.createElement('div');
      const itemName = document.createElement('span');
      const position = items.length;

      item.className = 'compose-option';
      item.id = select.id + '-option-' + index;
      item.setAttribute('role', 'option');
      itemName.className = 'compose-option-name';
      itemName.textContent = option.getAttribute('data-name') || option.getAttribute('data-department') || option.textContent;
      item.appendChild(itemName);

      if (option.hasAttribute('data-description')) {
        const itemDesc = document.createElement('span');
        itemDesc.className = 'compose-option-desc';
        itemDesc.textContent = option.getAttribute('data-description');
        item.appendChild(itemDesc);
      }
      item.addEventListener('click', function () {
        choose(position);
      });
      item.addEventListener('pointermove', function () {
        focusItem(position, false);
      });
      menu.appendChild(item);
      items.push({item: item, index: index});
    });
    select.hidden = true;
    select.tabIndex = -1;
    row.querySelector('label').setAttribute('for', button.id);
    row.classList.add('has-menu');
    select.after(button);
    row.appendChild(menu);

    function sync() {
      const option = select.options[select.selectedIndex];
      const empty = !option || !option.value;

      button.classList.toggle('is-empty', empty);
      name.textContent = option ? option.getAttribute('data-name') || option.getAttribute('data-department') || option.textContent : '';
      desc.textContent = option && option.getAttribute('data-description') || '';
      items.forEach(function (entry) {
        entry.item.setAttribute('aria-selected', entry.index === select.selectedIndex ? 'true' : 'false');
      });
    }

    function focusItem(position, scroll) {
      active = Math.max(0, Math.min(items.length - 1, position));
      items.forEach(function (entry, i) {
        entry.item.classList.toggle('is-active', i === active);
      });
      menu.setAttribute('aria-activedescendant', items[active].item.id);

      if (scroll) {
        items[active].item.scrollIntoView({block: 'nearest'});
      }
    }

    function open() {
      if (!menu.hidden) {
        return;
      }
      pickers.forEach(function (other) {
        other.close(false);
      });
      menu.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      // Never taller than the window below the row
      menu.style.maxHeight = Math.max(160, window.innerHeight - row.getBoundingClientRect().bottom - 16) + 'px';
      const chosen = items.findIndex(function (entry) {
        return entry.index === select.selectedIndex;
      });
      focusItem(chosen < 0 ? 0 : chosen, true);
      menu.focus({preventScroll: true});
    }

    function close(refocus) {
      if (menu.hidden) {
        return;
      }
      menu.hidden = true;
      button.setAttribute('aria-expanded', 'false');

      if (refocus) {
        button.focus({preventScroll: true});
      }
    }

    function choose(position) {
      const index = items[position].index;
      const changed = index !== select.selectedIndex;

      select.selectedIndex = index;
      close(true);
      sync();

      if (changed) {
        select.dispatchEvent(new Event('input', {bubbles: true}));
        select.dispatchEvent(new Event('change', {bubbles: true}));
      }
    }

    button.addEventListener('click', function () {
      if (menu.hidden) {
        open();
      } else {
        close(true);
      }
    });

    button.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open();
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
        close(true);
      } else if (event.key === 'Tab') {
        close(false);
      }
    });

    const picker = {select: select, button: button, menu: menu, sync: sync, open: open, close: close};
    pickers.push(picker);
    sync();
    return picker;
  }

  Array.prototype.forEach.call(form.querySelectorAll('select'), makePicker);

  function syncPickers() {
    pickers.forEach(function (picker) {
      picker.sync();
    });
  }

  // A tap or click anywhere else closes the open list
  document.addEventListener('pointerdown', function (event) {
    pickers.forEach(function (picker) {
      // (the To row of the Email form opens and closes the subject list itself)
      const linked = picker.select === domainField && localField.closest('.is-linked');

      if (!picker.menu.contains(event.target) && !picker.button.contains(event.target) && !(linked && linked.contains(event.target))) {
        picker.close(false);
      }
    });
  });
  window.addEventListener('blur', function () {
    pickers.forEach(function (picker) {
      picker.close(false);
    });
  });

  // The To row: locked in every case. Until a subject is picked it says so (.is-pending) and shows no address.
  const toRow = localField.closest('.compose-field');

  function showDomain() {
    domainLabel.textContent = domainField.value;
    toRow.classList.toggle('is-pending', !domainField.value);
    syncPickers();
  }

  function nextAddress() {
    local = newLocal();
    localField.textContent = local;

    if (address()) {
      localField.parentNode.setAttribute('data-tip', address());
    } else {
      localField.parentNode.removeAttribute('data-tip');
    }
    showDomain();
  }
  nextAddress();

  // Email form: the address comes from the subject, so clicking it opens the subject list (it stays locked)
  const subjectPicker = pickers.find(function (picker) {
    return picker.select === domainField;
  });

  if (subjectPicker) {
    toRow.classList.add('is-linked');
    toRow.addEventListener('click', function () {
      if (subjectPicker.menu.hidden) {
        subjectPicker.open();
      } else {
        subjectPicker.close(true);
      }
    });
  }

  /* Missing or wrong fields: the browser's own bubble is rounded and does not follow the theme (and cannot point at
     a hidden list), so a square one of ours shows under the field instead, until the visitor changes something */
  form.noValidate = true;
  const problem = document.createElement('div');
  problem.className = 'compose-problem';
  problem.setAttribute('role', 'alert');
  problem.hidden = true;

  function problemText(field) {
    const v = field.validity;

    if (v.valueMissing) {
      if (field.tagName === 'SELECT') {
        const label = field.closest('.compose-field').querySelector('label');
        return label ? 'Please choose a ' + label.textContent.replace(/:\s*$/, '').toLowerCase() + '.' : 'Please choose one from the list.';
      }
      return field.type === 'email' ? 'Please enter your email address.' : field.tagName === 'TEXTAREA' ? 'Please write your message.' : 'Please fill this in.';
    }
    if (v.typeMismatch && field.type === 'email') {
      return 'Please enter a full email address, like name@example.com.';
    }
    return field.validationMessage;
  }

  function hideProblem() {
    problem.hidden = true;
    form.querySelectorAll('[aria-invalid]').forEach(function (field) {
      field.removeAttribute('aria-invalid');
    });
  }

  // Returns true when everything is filled in
  function check() {
    hideProblem();
    const field = Array.prototype.find.call(form.elements, function (element) {
      return element.willValidate && element !== form.elements.website && !element.checkValidity();
    });

    if (!field) {
      return true;
    }
    const picker = pickers.find(function (p) {
      return p.select === field;
    });
    const target = picker ? picker.button : field;

    problem.textContent = problemText(field);
    (field.closest('.compose-field') || field.closest('.compose-write') || form).appendChild(problem);
    problem.hidden = false;
    target.setAttribute('aria-invalid', 'true');
    target.focus({preventScroll: false});
    return false;
  }

  form.addEventListener('input', hideProblem);
  form.addEventListener('pointerdown', function (event) {
    if (!problem.contains(event.target)) {
      hideProblem();
    }
  });

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

    if (!check()) {
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
        syncPickers();
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
