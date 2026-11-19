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
    ios: {name: 'App Store', host: 'apps.apple.com', text: 'iDEALISTIC® for iPhone and iPad.', logo: 'appstore', tile: 'linear-gradient(180deg, #6cc6ff, #1677ff)', glyph: 'A'},
    ipados: 'ios',
    android: {name: 'Google Play', host: 'play.google.com', text: 'iDEALISTIC® for Android phones and tablets.', logo: 'googleplay', tile: 'linear-gradient(180deg, #6ef08a, #1fb84b)', glyph: '▶'},
    whatsapp: {name: 'WhatsApp', host: 'wa.me', text: 'Use iDEALISTIC® in WhatsApp.', logo: 'whatsapp', tile: 'linear-gradient(180deg, #5ef08c, #19b34a)', glyph: 'W'},
    telegram: {name: 'Telegram', host: 't.me', text: 'Use iDEALISTIC® in Telegram.', logo: 'telegram', tile: 'linear-gradient(180deg, #6fd0ff, #229ed9)', glyph: 'T'},
    messenger: {name: 'Messenger', host: 'facebook.com', text: 'Use iDEALISTIC® in Messenger.', logo: 'messenger', tile: 'linear-gradient(180deg, #8f8cff, #0a7cff)', glyph: 'M'},
    facebook: 'messenger',
    instagram: {name: 'Instagram', host: 'instagram.com', text: 'Use iDEALISTIC® in Instagram messages.', logo: 'instagram', tile: 'linear-gradient(180deg, #ffb36b, #e1306c 55%, #8a3ab9)', glyph: 'I'},
    discord: {name: 'Discord', host: 'discord.com', text: 'Use iDEALISTIC® in Discord.', logo: 'discord', tile: 'linear-gradient(180deg, #8b93ff, #5865f2)', glyph: 'D'},
    x: {name: 'X', host: 'x.com', text: 'Use iDEALISTIC® on X.', logo: 'x', tile: 'linear-gradient(180deg, #4b5563, #111318)', glyph: 'X'},
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
      '  <div class="preview-grab" aria-hidden="true"><span></span></div>' +
      '  <div class="preview-island-space"></div>' +
      '  <div class="preview-hint" aria-hidden="true">Slide in any direction to close</div>' +
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
  let widenTimer = null;

  // Only transform and opacity animate (graphics chip only), so it stays smooth on slow machines
  // The shell starts where the island is, with its size, and grows to host the app (and back again)
  function measure() {
    const nav = document.getElementById('nav');
    const panel = sheet.querySelector('.preview-sheet');
    const body = sheet.querySelector('.preview-body');
    const bar = sheet.querySelector('.preview-bar');
    const box = nav.getBoundingClientRect();
    const islandH = nav.offsetHeight;
    const dock = document.querySelector('body > .dock');
    // Wide screens with the Dock at the bottom: stop above it (on phones the Dock element is the hidden menu list)
    // Stop above the Dock when it is at the bottom (always on phones, unless moved to the top on wide screens)
    const narrowScreen = window.matchMedia('(max-width: 820px)').matches;
    // Wide screens with the Dock at the bottom: stop above it. Phones: the Dock leaves, so use the full height.
    const dockBox = dock && !narrowScreen && !document.documentElement.hasAttribute('data-dock')
      ? dock.getBoundingClientRect() : null;
    const bottomEdge = dockBox ? dockBox.top - 14 : window.innerHeight - 16;
    let openH;

    if (sheet.classList.contains('is-page')) {
      openH = bottomEdge - box.top;
    } else {
      const card = body.firstElementChild;
      openH = islandH + (card ? card.offsetHeight + 20 : 0);
    }

    // The bar (✕, title, address) sits in the island's own row: between the brand and the theme button
    // (or the Dock, when it is in the island). Measured from layout sizes, so the island's width animation
    // does not matter.
    const style = getComputedStyle(nav);
    const brand = nav.querySelector('.brand');
    const toggle = nav.querySelector('.theme-toggle');
    const innerDock = nav.querySelector('.dock');
    const shellW = panel.getBoundingClientRect().width;
    const barLeft = parseFloat(style.paddingLeft) + (brand ? brand.offsetWidth : 0) + 16;
    let barRight = parseFloat(style.paddingRight) + (toggle ? toggle.offsetWidth : 0) + 12;

    if (innerDock) {
      barRight = Math.max(barRight, shellW / 2 + innerDock.offsetWidth / 2 + 12);
    }
    panel.style.setProperty('--island-top', box.top + 'px');
    panel.style.setProperty('--island-h', islandH + 'px');
    panel.style.setProperty('--bar-left', barLeft + 'px');
    panel.style.setProperty('--bar-right', barRight + 'px');
    panel.style.setProperty('--open-h', Math.max(openH, islandH + 120) + 'px');
    panel.style.setProperty('--body-h', Math.max(openH - islandH, 60) + 'px');
  }

  function showHint() {
    if (!touchScreen.matches) {
      return;
    }
    let seen = 0;

    try {
      seen = parseInt(localStorage.getItem('slide-hint') || '0', 10);
    } catch (error) {
      seen = 0;
    }
    if (seen >= 3) {
      return;
    }
    try {
      localStorage.setItem('slide-hint', String(seen + 1));
    } catch (error) {
      // fine
    }
    sheet.classList.add('show-hint');
    setTimeout(function () {
      sheet.classList.remove('show-hint');
    }, 2800);
  }

  window.addEventListener('resize', function () {
    if (isOpen) {
      measure();
    }
  });

  function setOpen(open, instant) {
    clearTimeout(hideTimer);
    const panel = sheet.querySelector('.preview-sheet');
    panel.style.transition = '';
    panel.style.transform = '';
    sheet.querySelector('.preview-backdrop').style.opacity = '';

    if (open) {
      const root = document.documentElement;
      const nav = document.getElementById('nav');
      const grow = function () {
        if (!isOpen) {
          return;
        }
        // The island takes the shell's width and lends it its row; the shell appears at the island's size
        root.classList.add('island-hosting');
        root.classList.remove('island-widening');
        sheet.hidden = false;
        measure();
        void panel.offsetWidth;
        requestAnimationFrame(function () {
          sheet.classList.add('is-open');
        });
        showHint();
      };

      // A shortened island first widens to full length (keeping its glass), then grows down into the app
      if (nav && nav.classList.contains('is-compact') && !reduceMotion.matches
        && !root.classList.contains('island-hosting')) {
        root.classList.add('island-widening');
        clearTimeout(widenTimer);
        widenTimer = setTimeout(grow, 380);
      } else {
        grow();
      }
    } else {
      clearTimeout(widenTimer);
      sheet.classList.remove('is-open');
      hideTimer = setTimeout(function () {
        sheet.hidden = true;
        sheet.querySelector('.preview-body').textContent = '';
        // Back to just the island: it shows its own glass again, then (when shortened) narrows back
        document.documentElement.classList.remove('island-hosting', 'island-widening');
      }, reduceMotion.matches || instant ? 0 : 500);
    }
  }


  function show(barUrl, replace) {
    lastFocus = lastFocus || document.activeElement;

    if (!isOpen) {
      isOpen = true;
      setOpen(true);
    } else {
      measure();
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
      const tile = card.querySelector('.preview-tile');
      tile.style.background = info.tile;

      if (info.logo) {
        const glyph = document.createElement('span');
        glyph.className = 'preview-logo';
        glyph.style.setProperty('--logo', 'url(/assets/img/brands/' + info.logo + '.svg)');
        tile.appendChild(glyph);
      } else {
        tile.textContent = info.glyph;
      }
      card.querySelector('strong').textContent = info.name;
      card.querySelector('.preview-text').textContent = info.text;
      body.appendChild(card);
    }
    show(null, false);
  }

  function close(fromHistory, instant) {
    if (!sheet || !isOpen) {
      return;
    }
    isOpen = false;
    setOpen(false, instant);
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

  // Phones and portrait tablets: grab the top of the island and slide it in any direction to close it.
  // The whole island (its row and the app) follows the finger; let go far enough, or flick, and it flies off
  // that way. Otherwise it springs back.
  const touchScreen = window.matchMedia('(max-width: 820px)');

  function enableDrag() {
    const panel = sheet.querySelector('.preview-sheet');
    const backdrop = sheet.querySelector('.preview-backdrop');
    const nav = document.getElementById('nav');
    const zones = [sheet.querySelector('.preview-grab'), sheet.querySelector('.preview-bar')];
    let start = null;
    let delta = {x: 0, y: 0};
    let dragging = false;
    let samples = [];
    let zone = null;

    function place(x, y) {
      const move = ' translate(' + x + 'px, ' + y + 'px)';
      panel.style.transform = 'translateX(-50%)' + move;
      nav.style.transform = 'translateX(-50%)' + move;
      backdrop.style.opacity = String(Math.max(0, 1 - Math.hypot(x, y) / 500));
    }

    function reset(animated) {
      const ease = animated ? 'transform 0.35s cubic-bezier(0.32, 0.72, 0, 1)' : 'none';
      panel.style.transition = ease;
      nav.style.transition = ease;
      backdrop.style.transition = animated ? 'opacity 0.3s ease' : 'none';
      panel.style.transform = '';
      nav.style.transform = '';
      backdrop.style.opacity = '';
      setTimeout(function () {
        panel.style.transition = '';
        nav.style.transition = '';
        backdrop.style.transition = '';
      }, animated ? 360 : 0);
    }

    function down(event) {
      if (!touchScreen.matches || !isOpen || (event.pointerType === 'mouse' && event.button !== 0)) {
        return;
      }
      zone = event.currentTarget;
      start = {x: event.clientX, y: event.clientY};
      delta = {x: 0, y: 0};
      dragging = false;
      samples = [{x: event.clientX, y: event.clientY, t: event.timeStamp}];
    }

    function move(event) {
      if (!start || event.currentTarget !== zone) {
        return;
      }
      const x = event.clientX - start.x;
      const y = event.clientY - start.y;

      if (!dragging) {
        if (Math.hypot(x, y) < 8) {
          return;
        }
        dragging = true;
        zone.setPointerCapture(event.pointerId);
        panel.style.transition = 'none';
        nav.style.transition = 'none';
        backdrop.style.transition = 'none';
        sheet.classList.add('was-dragged');
      }
      delta = {x: x, y: y};
      place(x, y);
      samples.push({x: event.clientX, y: event.clientY, t: event.timeStamp});

      if (samples.length > 5) {
        samples.shift();
      }
    }

    function up(event) {
      if (!start) {
        return;
      }
      start = null;

      if (!dragging) {
        return;
      }
      dragging = false;
      const first = samples[0];
      const last = samples[samples.length - 1];
      const time = last.t - first.t || 1;
      const speed = Math.hypot(last.x - first.x, last.y - first.y) / time;
      const distance = Math.hypot(delta.x, delta.y);

      // A drag that started on a button is not a tap on it
      if (event.type === 'pointerup') {
        const swallow = function (clickEvent) {
          clickEvent.stopPropagation();
          clickEvent.preventDefault();
        };
        zone.addEventListener('click', swallow, true);
        setTimeout(function () {
          zone.removeEventListener('click', swallow, true);
        }, 0);
      }

      if (distance > 110 || speed > 0.5) {
        // Fly off in the direction it was slid
        const angle = Math.atan2(delta.y || 0.001, delta.x);
        const far = Math.max(window.innerWidth, window.innerHeight) * 1.2;
        const ease = 'transform 0.32s cubic-bezier(0.2, 0.7, 0.3, 1), opacity 0.32s ease';
        panel.style.transition = ease;
        nav.style.transition = ease;
        backdrop.style.transition = 'opacity 0.3s ease';
        place(Math.cos(angle) * far, Math.sin(angle) * far);
        panel.style.opacity = '0';
        nav.style.opacity = '0';
        setTimeout(function () {
          close(false, true);
          // The island comes back in its place
          panel.style.transition = 'none';
          nav.style.transition = 'none';
          panel.style.transform = '';
          nav.style.transform = '';
          panel.style.opacity = '';
          void nav.offsetWidth;
          nav.style.transition = 'opacity 0.3s ease';
          nav.style.opacity = '';
          setTimeout(function () {
            panel.style.transition = '';
            nav.style.transition = '';
          }, 320);
        }, 320);
      } else {
        reset(true);
      }
    }

    zones.forEach(function (target) {
      if (!target) {
        return;
      }
      target.addEventListener('pointerdown', down);
      target.addEventListener('pointermove', move);
      target.addEventListener('pointerup', up);
      target.addEventListener('pointercancel', up);
    });
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
