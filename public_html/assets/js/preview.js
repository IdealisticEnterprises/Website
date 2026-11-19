(function () {
  'use strict';

  /* External links open in a preview sheet instead of leaving the site.
     Sites that allow being framed load inside the sheet (sandboxed, so they cannot take over the window).
     (set `frame: <url>` on a service once its site allows framing; as of 2026-11-19 none of ours do, the App Store
     included, so every service uses the card).
     Sites that forbid it (X-Frame-Options / frame-ancestors) get a preview card with an
     "Open" button that opens a new tab (or the app on phones), so this site stays open. */

  // Keyed by the last path part of a short link (/whatsapp, idealistic.chat/whatsapp) or by host
  const SERVICES = {
    ios: {name: 'App Store', host: 'apps.apple.com', text: 'iDEALISTIC® for iPhone and iPad.', tile: 'linear-gradient(180deg, #6cc6ff, #1677ff)', glyph: 'A'},
    ipados: 'ios',
    android: {name: 'Google Play', host: 'play.google.com', text: 'iDEALISTIC® for Android phones and tablets.', tile: 'linear-gradient(180deg, #6ef08a, #1fb84b)', glyph: '▶'},
    whatsapp: {name: 'WhatsApp', host: 'wa.me', text: 'Use iDEALISTIC® in WhatsApp.', tile: 'linear-gradient(180deg, #5ef08c, #19b34a)', glyph: 'W'},
    telegram: {name: 'Telegram', host: 't.me', text: 'Use iDEALISTIC® in Telegram.', tile: 'linear-gradient(180deg, #6fd0ff, #229ed9)', glyph: 'T'},
    messenger: {name: 'Messenger', host: 'facebook.com', text: 'Use iDEALISTIC® in Messenger.', tile: 'linear-gradient(180deg, #8f8cff, #0a7cff)', glyph: 'M'},
    facebook: 'messenger',
    instagram: {name: 'Instagram', host: 'instagram.com', text: 'Use iDEALISTIC® in Instagram messages.', tile: 'linear-gradient(180deg, #ffb36b, #e1306c 55%, #8a3ab9)', glyph: 'I'},
    discord: {name: 'Discord', host: 'discord.com', text: 'Use iDEALISTIC® in Discord.', tile: 'linear-gradient(180deg, #8b93ff, #5865f2)', glyph: 'D'},
    x: {name: 'X', host: 'x.com', text: 'Use iDEALISTIC® on X.', tile: 'linear-gradient(180deg, #4b5563, #111318)', glyph: 'X'},
    twitter: 'x',
    github: {name: 'GitHub', host: 'github.com', text: 'IdealisticEnterprises on GitHub.', tile: 'linear-gradient(180deg, #4b5563, #111318)', glyph: 'G'},
    'spartan.top': {name: 'Spartan AntiCheat', host: 'spartan.top', text: 'Another product by Idealistic OÜ.', tile: 'linear-gradient(180deg, #ff8a6b, #d9381e)', glyph: 'S'},
    'ariregister.rik.ee': {name: 'Estonian Business Register', host: 'ariregister.rik.ee', text: 'The official company record of Idealistic OÜ.', tile: 'linear-gradient(180deg, #6cc6ff, #0b5cad)', glyph: 'E'},
    'euipo.europa.eu': {name: 'EU trademark', host: 'euipo.europa.eu', text: 'EU trademark no. 019353308 for iDEALISTIC®.', tile: 'linear-gradient(180deg, #6c8cff, #1a3a9e)', glyph: '®'}
  };

  // Our own domains: they only redirect back here, so go to the page directly
  const OWN_DOMAINS = {
    'idealistic.club': '/club/',
    'doremi.chat': '/doremi/',
    'idealistic.work': '/work/',
    'idealistic.download': '/apps/',
    'idealistic.app': '/apps/',
    'idealistic.community': '/community/',
    'idealistic.ai': '/ai/',
    'idealistic.enterprises': null
  };

  const PAGE_DOMAIN = {
    '/club/': 'idealistic.club',
    '/doremi/': 'doremi.chat',
    '/work/': 'idealistic.work',
    '/apps/': 'idealistic.download',
    '/community/': 'idealistic.community',
    '/ai/': 'idealistic.ai'
  };

  const SHORT_LINK_HOSTS = ['idealistic.chat', 'idealistic.enterprises', location.hostname];

  function lookup(key) {
    let entry = SERVICES[key];
    return typeof entry === 'string' ? SERVICES[entry] : entry;
  }

  function serviceFor(url) {
    const host = url.hostname.replace(/^www\./, '');
    const slug = url.pathname.replace(/\/+$/, '').split('/').pop().toLowerCase();

    if (SHORT_LINK_HOSTS.indexOf(host) !== -1 && lookup(slug)) {
      return lookup(slug);
    }
    return lookup(host) || null;
  }

  const EMBEDDED = window.self !== window.top;
  let sheet = null;
  let lastFocus = null;
  let historyEntry = false;

  // The address as it was clicked, before any redirect: host and path, without the protocol
  function shown(url) {
    if (url.origin === location.origin && PAGE_DOMAIN[url.pathname]) {
      return PAGE_DOMAIN[url.pathname] + url.search + url.hash;
    }
    const path = url.pathname === '/' ? '' : url.pathname;
    return url.hostname.replace(/^www\./, '') + path + url.search + url.hash;
  }

  // A page of this site (not an external short link, not a file)
  function isPage(url) {
    return url.origin === location.origin
      && !serviceFor(url)
      && !/\.[a-z0-9]{2,5}$/i.test(url.pathname);
  }

  // Inside the sheet, a page hands every link it cannot handle itself up to the page around it
  if (EMBEDDED) {
    document.addEventListener('click', function (event) {
      const link = event.target.closest ? event.target.closest('a[href]') : null;

      if (!link || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      let url;

      try {
        url = new URL(link.href, location.href);
      } catch (error) {
        return;
      }
      if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return;
      }
      // Home is always behind the panel: a link to Home closes the panel instead of loading Home inside it
      if (url.origin === location.origin && (url.pathname === '/' || url.pathname === '/index.html') && !url.hash) {
        event.preventDefault();
        window.parent.postMessage({type: 'preview-close'}, location.origin);
        return;
      }
      // Same-page anchors and other pages of this site simply load inside the sheet
      if (isPage(url) && !OWN_DOMAINS[url.hostname.replace(/^www\./, '')]) {
        return;
      }
      event.preventDefault();
      window.parent.postMessage({type: 'preview-open', href: link.href}, location.origin);
    });
    return;
  }

  window.addEventListener('message', function (event) {
    if (event.origin === location.origin && event.data && event.data.type === 'preview-open') {
      route(event.data.href, null);
    } else if (event.origin === location.origin && event.data && event.data.type === 'preview-close') {
      close();
    }
  });

  const CROSS = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  const ARROW = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7"/><path d="M9 7h8v8"/></svg>';

  function build() {
    sheet = document.createElement('div');
    sheet.className = 'preview';
    sheet.hidden = true;
    sheet.innerHTML =
      '<div class="preview-backdrop" data-close></div>' +
      '<section class="preview-sheet" role="dialog" aria-modal="true" aria-labelledby="preview-title">' +
      '  <header class="preview-bar">' +
      '    <button class="preview-close preview-close-left" type="button" data-close aria-label="Close">' + CROSS + '</button>' +
      '    <div class="preview-where"><strong id="preview-title"></strong><span class="preview-host"></span></div>' +
      '    <div class="preview-corner">' +
      '      <button class="preview-close preview-close-right" type="button" data-close tabindex="-1" aria-hidden="true">' + CROSS + '</button>' +
      '    </div>' +
      '  </header>' +
      '  <div class="preview-body"></div>' +
      '</section>';
    document.body.appendChild(sheet);
    sheet.addEventListener('click', function (event) {
      if (event.target.closest('[data-close]')) {
        close();
      }
    });
    enableDrag();
  }

  // Shows the sheet. barUrl: the address to show in the browser bar while it is open (pages of this site)
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const phone = window.matchMedia('(max-width: 640px)');
  let isOpen = false;
  let hideTimer = null;

  // Only transform and opacity animate (graphics chip only), so it stays smooth on slow machines
  function setOpen(open) {
    clearTimeout(hideTimer);
    const panel = sheet.querySelector('.preview-sheet');
    panel.style.transition = '';
    panel.style.transform = '';
    sheet.querySelector('.preview-backdrop').style.opacity = '';

    if (open) {
      sheet.hidden = false;
      // Start from the closed position, then move to open on the next frame
      void panel.offsetWidth;
      requestAnimationFrame(function () {
        sheet.classList.add('is-open');
      });
    } else {
      sheet.classList.remove('is-open');
      hideTimer = setTimeout(function () {
        sheet.hidden = true;
        sheet.querySelector('.preview-body').textContent = '';
      }, reduceMotion.matches ? 0 : 420);
    }
  }

  function show(barUrl, replace) {
    lastFocus = lastFocus || document.activeElement;

    if (!isOpen) {
      isOpen = true;
      setOpen(true);
    }
    document.documentElement.classList.add('preview-locked');
    const panel = sheet.querySelector('.preview-sheet');
    panel.setAttribute('tabindex', '-1');
    panel.focus({preventScroll: true});

    // The back button / back gesture closes the sheet instead of leaving Home
    if (!historyEntry) {
      if (replace) {
        history.replaceState({preview: true}, '', barUrl || location.href);
      } else {
        history.pushState({preview: true}, '', barUrl || location.href);
      }
      historyEntry = true;
    } else if (barUrl) {
      history.replaceState({preview: true}, '', barUrl);
    }
  }


  // The Dock's dot follows the page that is open in the panel (none while Home is showing)
  function markDock(pathname) {
    document.querySelectorAll('.dock .dock-item[href]').forEach(function (item) {
      const path = item.getAttribute('data-path') || new URL(item.href, location.href).pathname;

      if (pathname && path === pathname) {
        item.setAttribute('aria-current', 'page');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  }



  // A page of this site (or one of our own domains), loaded inside the sheet as an iframe of its address
  function openPage(url, display, replace, pagePath) {
    if (!sheet) {
      build();
    }
    const sameSite = url.origin === location.origin;
    const body = sheet.querySelector('.preview-body');
    sheet.classList.add('is-page');
    body.textContent = '';
    sheet.querySelector('#preview-title').textContent = '';
    sheet.querySelector('.preview-host').textContent = display;
    // Our own domains lead to their own address; pages of this site are already where they belong
    const path = sameSite ? url.pathname : (pagePath || null);
    markDock(path);
    let first = true;

    const frame = document.createElement('iframe');
    frame.title = 'Page';
    frame.src = url.href;
    frame.addEventListener('load', function () {
      try {
        const inner = frame.contentWindow.location;
        const title = frame.contentDocument.title.replace(/\s*\|\s*iDEALISTIC®\s*$/, '');
        sheet.querySelector('#preview-title').textContent = title;
        frame.title = title;

        // Keep the clicked address for the first page; later pages opened inside the sheet show their own
        if (!first) {
          sheet.querySelector('.preview-host').textContent = shown(inner);
        }
        if (inner.origin === location.origin) {
          markDock(inner.pathname);
        }
        if (inner.origin === location.origin && historyEntry) {
          history.replaceState({preview: true}, '', inner.pathname + inner.search + inner.hash);
        }
        first = false;
      } catch (error) {
        // Not readable (should not happen for pages of this site)
      }
    });
    body.appendChild(frame);
    show(sameSite ? url.pathname + url.search + url.hash : (pagePath ? pagePath + url.search + url.hash : null), replace);
  }

  // An external site: shown inside the sheet when it allows it, otherwise a message-like card
  function openExternal(href, url, service, display) {
    if (!sheet) {
      build();
    }
    sheet.classList.remove('is-page');
    const host = url.hostname.replace(/^www\./, '');
    const info = service || {name: host, host: host, text: 'An external website.', tile: 'linear-gradient(180deg, #6b7280, #374151)', glyph: '↗'};
    sheet.querySelector('#preview-title').textContent = info.name;
    sheet.querySelector('.preview-host').textContent = display;
    markDock(null);
    const body = sheet.querySelector('.preview-body');
    body.textContent = '';

    if (info.frame) {
      const frame = document.createElement('iframe');
      frame.src = info.frame;
      frame.title = info.name;
      frame.referrerPolicy = 'strict-origin-when-cross-origin';
      // No allow-top-navigation: the framed page can never navigate this window away
      frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox');
      body.appendChild(frame);
    } else {
      const card = document.createElement('a');
      card.className = 'preview-card';
      card.href = href;
      card.target = '_blank';
      card.rel = 'noopener';
      card.innerHTML =
        '<span class="preview-tile" aria-hidden="true"></span>' +
        '<span class="preview-card-text"><strong></strong><span class="preview-text"></span></span>' +
        '<span class="preview-card-go" aria-hidden="true">' + ARROW + '</span>';
      card.querySelector('.preview-tile').style.background = info.tile;
      card.querySelector('.preview-tile').textContent = info.glyph;
      card.querySelector('strong').textContent = info.name;
      card.querySelector('.preview-text').textContent = info.text;
      body.appendChild(card);
    }
    show(null, false);
  }

  function close(fromHistory) {
    if (!sheet || !isOpen) {
      return;
    }
    isOpen = false;
    setOpen(false);
    markDock(null);
    document.documentElement.classList.remove('preview-locked');

    if (lastFocus && lastFocus.focus) {
      lastFocus.focus({preventScroll: true});
    }
    lastFocus = null;

    if (historyEntry) {
      historyEntry = false;

      if (!fromHistory) {
        history.back();
      }
    }
  }

  // Phones: drag the panel down by its bar to dismiss it, like an iPhone sheet
  function enableDrag() {
    const panel = sheet.querySelector('.preview-sheet');
    const bar = sheet.querySelector('.preview-bar');
    const backdrop = sheet.querySelector('.preview-backdrop');
    let startY = 0;
    let dy = 0;
    let dragging = false;
    let tracking = false;
    let samples = [];

    bar.addEventListener('pointerdown', function (event) {
      if (!phone.matches || !isOpen || (event.pointerType === 'mouse' && event.button !== 0)) {
        return;
      }
      tracking = true;
      dragging = false;
      startY = event.clientY;
      dy = 0;
      samples = [{y: event.clientY, t: event.timeStamp}];
    });

    bar.addEventListener('pointermove', function (event) {
      if (!tracking) {
        return;
      }
      const raw = event.clientY - startY;

      if (!dragging) {
        if (Math.abs(raw) < 6) {
          return;
        }
        dragging = true;
        bar.setPointerCapture(event.pointerId);
        panel.style.transition = 'none';
        backdrop.style.transition = 'none';
      }
      // Downwards follows the finger; upwards resists, like iOS
      dy = raw > 0 ? raw : -Math.sqrt(-raw) * 2;
      panel.style.transform = 'translateX(-50%) translateY(' + dy + 'px)';
      backdrop.style.opacity = String(Math.max(0, 1 - dy / (panel.offsetHeight || 600)));
      samples.push({y: event.clientY, t: event.timeStamp});

      if (samples.length > 5) {
        samples.shift();
      }
    });

    function release(event) {
      if (!tracking) {
        return;
      }
      tracking = false;

      if (!dragging) {
        return;
      }
      dragging = false;
      const first = samples[0];
      const last = samples[samples.length - 1];
      const velocity = last && first && last.t > first.t ? (last.y - first.y) / (last.t - first.t) : 0;
      panel.style.transition = '';
      backdrop.style.transition = '';

      if (dy > 120 || velocity > 0.5) {
        // Continue the slide from where the finger let go
        panel.style.transform = '';
        backdrop.style.opacity = '';
        close();
      } else {
        // Not far enough: spring back up
        panel.style.transform = '';
        backdrop.style.opacity = '';
      }
      // A drag that started on a button is not a tap on it
      if (event && event.type === 'pointerup') {
        const swallow = function (clickEvent) {
          clickEvent.stopPropagation();
          clickEvent.preventDefault();
        };
        bar.addEventListener('click', swallow, true);
        // Only the click that ends this drag is swallowed, never a later tap
        setTimeout(function () {
          bar.removeEventListener('click', swallow, true);
        }, 0);
      }
    }

    bar.addEventListener('pointerup', release);
    bar.addEventListener('pointercancel', release);
  }


  // Decide what a link does. Returns true when it was handled here.
  function route(href, event) {
    let url;

    try {
      url = new URL(href, location.href);
    } catch (error) {
      return false;
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      return false; // mailto: and similar open the right app themselves
    }
    const host = url.hostname.replace(/^www\./, '');
    const display = shown(url);

    // Our own domains: load the address itself in the iframe (it redirects to the matching page inside the frame)
    if (Object.prototype.hasOwnProperty.call(OWN_DOMAINS, host) && OWN_DOMAINS[host] !== null) {
      const pagePath = OWN_DOMAINS[host];

      // Clicking the app that is already open closes it, like tapping its icon again
      if (isOpen && sheet.classList.contains('is-page') && location.pathname === pagePath && !url.hash) {
        close();
        return true;
      }
      openPage(url, display, false, pagePath);
      return true;
    }
    if (isPage(url)) {
      // Home is always behind every page: a link to Home just closes the sheet; anchors on Home behave as usual
      if (url.pathname === '/' || url.pathname === '/index.html') {
        if (!url.hash) {
          close();
          return Boolean(event);
        }
        return false;
      }
      // Clicking the app that is already open closes it, like tapping its icon again
      if (isOpen && sheet.classList.contains('is-page') && url.pathname === location.pathname && !url.hash) {
        close();
        return true;
      }
      openPage(url, display, false);
      return true;
    }
    openExternal(href, url, serviceFor(url), display);
    return true;
  }

  window.addEventListener('popstate', function () {
    if (historyEntry) {
      close(true);
    } else if (location.pathname !== '/' && location.pathname !== '/index.html') {
      // Forward to a page that was open: show it again on top of Home
      openPage(new URL(location.href), shown(new URL(location.href)), true);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && sheet && isOpen) {
      close();
    }
  });

  document.addEventListener('click', function (event) {
    const link = event.target.closest ? event.target.closest('a[href]') : null;

    if (!link
      || event.defaultPrevented
      || event.button !== 0
      || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
      || link.target === '_blank'
      || link.closest('.preview')) {
      return;
    }
    if (route(link.href, event)) {
      event.preventDefault();
    }
  });

  // A page opened directly (prefs.js sent it here as /?page=...): Home with that page open on top
  const params = new URLSearchParams(location.search);
  const requested = params.get('page');

  if (requested && requested.charAt(0) === '/' && requested.charAt(1) !== '/') {
    const target = new URL(requested, location.origin);

    if (target.origin === location.origin && target.pathname !== '/') {
      history.replaceState(null, '', '/');
      openPage(target, shown(target), false);
    }
  }
})();
