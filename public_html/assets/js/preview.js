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
    whatsapp: {name: 'WhatsApp', host: 'wa.me', text: 'Use doremi.chat in WhatsApp.', logo: 'whatsapp', tile: 'linear-gradient(180deg, #5ef08c, #19b34a)', glyph: 'W'},
    telegram: {name: 'Telegram', host: 't.me', text: 'Use doremi.chat in Telegram.', logo: 'telegram', tile: 'linear-gradient(180deg, #6fd0ff, #229ed9)', glyph: 'T'},
    messenger: {name: 'Messenger', host: 'facebook.com', text: 'Use doremi.chat in Messenger.', logo: 'messenger', tile: 'linear-gradient(180deg, #8f8cff, #0a7cff)', glyph: 'M'},
    facebook: 'messenger',
    instagram: {name: 'Instagram', host: 'instagram.com', text: 'Use doremi.chat in Instagram messages.', logo: 'instagram', tile: 'linear-gradient(180deg, #ffb36b, #e1306c 55%, #8a3ab9)', glyph: 'I'},
    discord: {name: 'Discord', host: 'discord.com', text: 'Use doremi.chat in Discord.', logo: 'discord', tile: 'linear-gradient(180deg, #8b93ff, #5865f2)', glyph: 'D'},
    x: {name: 'X', host: 'x.com', text: 'Use doremi.chat on X.', logo: 'x', tile: 'linear-gradient(180deg, #4b5563, #111318)', glyph: 'X'},
    twitter: 'x',
    // The community is the creator's Discord server at vagdedes.com (idealistic.community leads there too)
    'vagdedes.com': {name: 'Community', host: 'vagdedes.com', text: 'The creator\'s Discord server, where the iDEALISTIC® community meets.', logo: 'discord', tile: 'linear-gradient(180deg, #8b93ff, #5865f2)', glyph: 'D'},
    'idealistic.community': 'vagdedes.com',
    github: {name: 'GitHub', host: 'github.com', text: 'IdealisticEnterprises on GitHub.', logo: 'github', tile: 'linear-gradient(180deg, #4b5563, #111318)', glyph: 'G'},
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
    'idealistic.ai': '/ai/',
    'idealistic.click': '/ads/',
    'idealistic.gives': '/giveaways/',
    'idealistic.discount': '/discounts/',
    'idealistic.sale': '/sales/',
    'idealistic.email': '/email/',
    'idealistic.help': '/faq/',
    'idealistic.contact': '/email/',
    'idealistic.shop': '/club/',
    'idealistic.enterprises': null
  };

  const PAGE_DOMAIN = {
    '/club/': 'idealistic.club',
    '/doremi/': 'doremi.chat',
    '/work/': 'idealistic.work',
    '/apps/': 'idealistic.app',
    '/ai/': 'idealistic.ai',
    '/ads/': 'idealistic.click',
    '/giveaways/': 'idealistic.gives',
    '/discounts/': 'idealistic.discount',
    '/sales/': 'idealistic.sale',
    '/email/': 'idealistic.email',
    '/faq/': 'idealistic.help'
  };

  // Our support address: on the site it opens the Email app (a support ticket), not the mail program.
  // The links stay ordinary email links, so they still work without JavaScript.
  const EMAIL_APP = {'human@idealistic.ai': 'https://idealistic.email'};

  function emailApp(url) {
    return url.protocol === 'mailto:' ? EMAIL_APP[decodeURIComponent(url.pathname).toLowerCase()] || null : null;
  }

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

  // Legal pages (terms, policies, instructions) load as ordinary pages, never inside the panel
  function isStandalone(url) {
    return url.origin === location.origin && /^\/(terms|policies|instructions)(\/|$)/.test(url.pathname);
  }

  // Home itself, or a legal page that loaded on its own
  const ON_HOME = location.pathname === '/' || location.pathname === '/index.html';

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
      // The support address opens the Email app, in the panel
      if (emailApp(url)) {
        event.preventDefault();
        window.parent.postMessage({type: 'preview-open', href: emailApp(url)}, location.origin);
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
      // A legal page replaces the whole window, outside the panel
      if (isStandalone(url)) {
        event.preventDefault();
        window.top.location.href = url.href;
        return;
      }
      // Same-page anchors and other pages of this site simply load inside the sheet
      if (isPage(url) && !OWN_DOMAINS[url.hostname.replace(/^www\./, '')]) {
        return;
      }
      event.preventDefault();
      window.parent.postMessage({type: 'preview-open', href: link.href}, location.origin);
    });

    // The address of this page changed inside the panel (a plan picked on Club: #pro, ...): tell the page around
    // it, which shows it in the browser bar, so the link can be shared
    const tellAddress = function () {
      window.parent.postMessage({type: 'preview-url', href: location.href}, location.origin);
    };
    window.addEventListener('urlchange', tellAddress);
    window.addEventListener('hashchange', tellAddress);
    window.addEventListener('popstate', tellAddress);
    return;
  }

  window.addEventListener('message', function (event) {
    if (event.origin === location.origin && event.data && event.data.type === 'preview-open') {
      route(event.data.href, null);
    } else if (event.origin === location.origin && event.data && event.data.type === 'preview-close') {
      close();
    } else if (event.origin === location.origin && event.data && event.data.type === 'preview-url') {
      followAddress(event.data.href);
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
      '  <header class="preview-bar">' +
      '    <div class="preview-where"><strong id="preview-title"></strong><span class="preview-host"></span></div>' +
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
  // Put the island and the shell back exactly as they were (no drag offsets, fully visible)
  let openToken = 0;

  function restoreIsland() {
    const nav = document.getElementById('nav');

    if (nav) {
      nav.style.transform = '';
      nav.style.opacity = '';
      nav.style.transition = '';
    }
    if (sheet) {
      const panel = sheet.querySelector('.preview-sheet');
      const backdrop = sheet.querySelector('.preview-backdrop');
      panel.style.transform = '';
      panel.style.opacity = '';
      panel.style.transition = '';
      backdrop.style.opacity = '';
      backdrop.style.transition = '';
    }
  }

  // The shell starts where the island is, with its size, and grows to host the app (and back again)
  function measure(deferShrink) {
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
    // Phones: the visible part of the screen, so the panel ends above the keyboard while it is open and grows
    // back when it closes
    const view = window.visualViewport;
    // (a pinch zoom also shrinks the visible part, but the panel keeps its size then: only the keyboard counts)
    const keyboardOnly = narrowScreen && view && view.scale < 1.01;
    const visibleBottom = keyboardOnly ? view.offsetTop + view.height : window.innerHeight;
    const bottomEdge = dockBox ? dockBox.top - 14 : visibleBottom - 16;
    let openH;

    if (sheet.classList.contains('is-page')) {
      openH = bottomEdge - box.top;
    } else {
      const card = body.querySelector(':scope > :not(.preview-leaving)');
      const cardStyle = card ? getComputedStyle(card) : null;
      openH = islandH + (card ? card.offsetHeight + parseFloat(cardStyle.marginTop) + parseFloat(cardStyle.marginBottom) : 0);
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
    // Search results fit exactly (one result is a short panel); other bubbles keep a minimum size
    const minBody = sheet.classList.contains('is-search') ? 40 : 120;
    panel.style.setProperty('--open-h', Math.max(openH, islandH + minBody) + 'px');
    const bodyH = Math.max(openH - islandH, Math.min(minBody, 60));
    const oldBodyH = parseFloat(panel.style.getPropertyValue('--body-h')) || 0;
    clearTimeout(shrinkTimer);

    // Shrinking while open: the content keeps its size until the shell has finished getting shorter, so the
    // bottom never shows a gap (growing needs no wait: the shell simply reveals more of it)
    if (deferShrink && bodyH < oldBodyH && !reduceMotion.matches) {
      shrinkTimer = setTimeout(function () {
        panel.style.setProperty('--body-h', bodyH + 'px');
      }, 500);
    } else {
      panel.style.setProperty('--body-h', bodyH + 'px');
    }
  }

  // The Dock moved (into the island or back to the bottom) while an app is open: the shell follows it,
  // stopping above the Dock at the bottom and using the full height when the Dock is in the island
  let shrinkTimer = null;
  new MutationObserver(function () {
    if (isOpen && sheet && !sheet.hidden) {
      measure(true);
    }
  }).observe(document.documentElement, {attributes: true, attributeFilter: ['data-dock']});

  window.addEventListener('resize', function () {
    if (isOpen) {
      measure();
    }
  });

  // The phone keyboard opening and closing changes only the visible part of the screen (no window resize on
  // iPhone): the open panel follows it
  if (window.visualViewport) {
    let viewFrame = null;
    const followView = function () {
      if (!isOpen || !sheet || viewFrame !== null) {
        return;
      }
      viewFrame = requestAnimationFrame(function () {
        viewFrame = null;
        measure();
      });
    };
    window.visualViewport.addEventListener('resize', followView);
    window.visualViewport.addEventListener('scroll', followView);
  }

  function setOpen(open, instant) {
    clearTimeout(hideTimer);
    const panel = sheet.querySelector('.preview-sheet');
    panel.style.transition = '';
    panel.style.transform = '';
    sheet.querySelector('.preview-backdrop').style.opacity = '';

    if (open) {
      restoreIsland();
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
      };

      // The island first widens to the open width (wider than usual, or from its shortened pill), keeping its
      // glass, then grows down into the app
      let narrower = false;

      if (nav && !root.classList.contains('island-hosting')) {
        const before = nav.offsetWidth;
        nav.style.transition = 'none';
        root.classList.add('island-widening');
        const wide = nav.offsetWidth;
        root.classList.remove('island-widening');
        void nav.offsetWidth;
        nav.style.transition = '';
        narrower = before < wide - 2;
      }
      if (narrower && !reduceMotion.matches) {
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
        restoreIsland();
      }, reduceMotion.matches || instant ? 0 : 500);
    }
  }


  function show(barUrl, replace) {
    lastFocus = lastFocus || document.activeElement;

    openToken++;
    document.documentElement.classList.toggle('preview-page', sheet.classList.contains('is-page'));

    if (!isOpen) {
      isOpen = true;
      labelToggle();
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


  // The Dock's dot follows the page that is open in the panel (none while Home is showing). pathname is a page
  // of this site (/club/), or the full address of an app that lives elsewhere (Community: https://vagdedes.com/)
  function markDock(pathname) {
    document.querySelectorAll('.dock .dock-item[href]').forEach(function (item) {
      const path = item.getAttribute('data-path') || new URL(item.href, location.href).pathname;
      const full = new URL(path, location.href).href;

      if (pathname && (path === pathname || full === pathname)) {
        item.setAttribute('aria-current', 'page');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  }



  // Switching apps while the panel is open: the island stays and simply changes. The app that was open fades out
  // at once; while the new page loads the panel beats softly in blue (a heartbeat), and the page fades in as soon
  // as it has loaded. Opening and closing an app on its own keep their usual animation.
  function beginSwitch(body) {
    if (!isOpen || !body.children.length) {
      body.textContent = '';
      return null;
    }
    const leaving = Array.from(body.children);
    leaving.forEach(function (element) {
      element.classList.remove('preview-entering', 'is-in');
      element.classList.add('preview-leaving');
    });
    requestAnimationFrame(function () {
      leaving.forEach(function (element) {
        element.classList.add('is-gone');
      });
    });
    setTimeout(function () {
      leaving.forEach(function (element) {
        // Unless it came back in the meantime (the search list is reused)
        if (element.classList.contains('preview-leaving')) {
          element.classList.remove('preview-leaving', 'is-gone');
          element.remove();
        }
      });
    }, reduceMotion.matches ? 0 : 380);
    // The title and address change with it
    const where = sheet.querySelector('.preview-where');
    where.classList.remove('is-switching');
    void where.offsetWidth;
    where.classList.add('is-switching');
    return leaving;
  }

  function finishSwitch(leaving, entering) {
    if (!leaving) {
      return function () {};
    }
    entering.classList.add('preview-entering');
    let done = false;
    const reveal = function () {
      if (done) {
        return;
      }
      done = true;
      requestAnimationFrame(function () {
        entering.classList.add('is-in');
      });
    };
    // Never wait long for a slow page
    setTimeout(reveal, 2500);
    return reveal;
  }

  // The page has loaded: the heartbeat does not jump off mid-beat, it fades out smoothly from where it is
  function stopHeartbeat() {
    if (!sheet || !sheet.classList.contains('is-loading')) {
      return;
    }
    const body = sheet.querySelector('.preview-body');
    let glow = 0;

    try {
      glow = parseFloat(getComputedStyle(body, '::after').opacity) || 0;
    } catch (error) {
      glow = 0;
    }
    sheet.classList.remove('is-loading');

    if (glow > 0.01 && body.animate && !reduceMotion.matches) {
      try {
        body.animate([{opacity: glow}, {opacity: 0}], {duration: 450, easing: 'ease-out', pseudoElement: '::after'});
      } catch (error) {
        // Older browsers: it simply stops
      }
    }
  }

  // A page of this site (or one of our own domains), loaded inside the sheet as an iframe of its address
  function openPage(url, display, replace, pagePath) {
    if (!sheet) {
      build();
    }
    leaveSearch();
    const sameSite = url.origin === location.origin;
    const body = sheet.querySelector('.preview-body');
    const leaving = beginSwitch(body);
    sheet.classList.add('is-page');
    sheet.querySelector('#preview-title').textContent = '';
    sheet.querySelector('.preview-host').textContent = display;
    // Our own domains lead to their own address; pages of this site are already where they belong
    const path = sameSite ? url.pathname : (pagePath || null);
    markDock(path);
    let first = true;

    const frame = document.createElement('iframe');
    frame.title = 'Page';
    frame.src = url.href;
    const reveal = finishSwitch(leaving, frame);
    // A soft blue heartbeat in the panel until the page has loaded
    sheet.classList.add('is-loading');
    frame.addEventListener('load', function () {
      reveal();

      if (frame.isConnected) {
        stopHeartbeat();
      }

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
    leaveSearch();
    sheet.classList.remove('is-page');
    const host = url.hostname.replace(/^www\./, '');
    const info = service || {name: host, host: host, text: 'An external website.', tile: 'linear-gradient(180deg, #6b7280, #374151)', glyph: '↗'};
    sheet.querySelector('#preview-title').textContent = info.name;
    sheet.querySelector('.preview-host').textContent = display;
    // An app that lives elsewhere (Community) is still the open app: its Dock icon shows it, and tapping it closes it
    sheet.setAttribute('data-href', url.href);
    markDock(url.href);
    stopHeartbeat();
    const body = sheet.querySelector('.preview-body');
    const leaving = beginSwitch(body);

    if (info.frame) {
      const frame = document.createElement('iframe');
      frame.src = info.frame;
      frame.title = info.name;
      frame.referrerPolicy = 'strict-origin-when-cross-origin';
      // No allow-top-navigation: the framed page can never navigate this window away
      frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox');
      body.appendChild(frame);
      finishSwitch(leaving, frame)();
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
      // Leaving for the app: close straight away, so this tab is clean when the visitor comes back
      // (a tab in the background pauses animations and timers, which left a half-closed panel behind)
      card.addEventListener('click', function () {
        close(false, true);
      });
      body.appendChild(card);
      finishSwitch(leaving, card)();
    }
    show(null, false);
  }

  function close(fromHistory, instant) {
    if (!sheet || !isOpen) {
      return;
    }
    leaveSearch();
    sheet.classList.remove('is-loading');
    isOpen = false;
    document.documentElement.classList.remove('preview-page');
    setOpen(false, instant);
    labelToggle();
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

  // Phones and portrait tablets: grab the top of the app and slide it in any direction to close it.
  // The app follows the finger while the island (brand and ✕) stays where it is, with its own glass back;
  // let go far enough, or flick, and the app flies off that way. Otherwise it springs back into the island.
  const touchScreen = window.matchMedia('(max-width: 820px)');

  // Set by enableDrag: makes another area a handle to slide the panel away (the search row in the island)
  let addDragZone = null;

  function enableDrag() {
    const panel = sheet.querySelector('.preview-sheet');
    const backdrop = sheet.querySelector('.preview-backdrop');
    const zones = [sheet.querySelector('.preview-grab'), sheet.querySelector('.preview-bar')];
    let start = null;
    let delta = {x: 0, y: 0};
    let dragging = false;
    let samples = [];
    let zone = null;
    let flying = false;

    function place(x, y) {
      const move = ' translate(' + x + 'px, ' + y + 'px)';
      panel.style.transform = 'translateX(-50%)' + move;
      backdrop.style.opacity = String(Math.max(0, 1 - Math.hypot(x, y) / 500));
    }

    function reset(animated) {
      const ease = animated ? 'transform 0.35s cubic-bezier(0.32, 0.72, 0, 1)' : 'none';
      panel.style.transition = ease;
      backdrop.style.transition = animated ? 'opacity 0.3s ease' : 'none';
      panel.style.transform = '';
      backdrop.style.opacity = '';
      // Back in place: the island hosts the app again
      if (isOpen) {
        document.documentElement.classList.add('island-hosting');
      }
      setTimeout(function () {
        panel.style.transition = '';
        backdrop.style.transition = '';
      }, animated ? 360 : 0);
    }

    function down(event) {
      if (!touchScreen.matches || !isOpen || flying || start || (event.pointerType === 'mouse' && event.button !== 0)) {
        return;
      }
      zone = event.currentTarget;
      start = {x: event.clientX, y: event.clientY};
      delta = {x: 0, y: 0};
      dragging = false;
      samples = [{x: event.clientX, y: event.clientY, t: event.timeStamp}];
    }

    function move(event) {
      if (!start || (event.currentTarget !== zone && event.currentTarget !== window) || flying) {
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
        backdrop.style.transition = 'none';
        sheet.classList.add('was-dragged');
        // The island lets go of the app and keeps its own glass, so it never leaves with it
        document.documentElement.classList.remove('island-hosting');
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
      if (event.type === 'pointerup' && zone) {
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
        backdrop.style.transition = 'opacity 0.3s ease';
        place(Math.cos(angle) * far, Math.sin(angle) * far);
        panel.style.opacity = '0';
        flying = true;
        const token = openToken;
        setTimeout(function () {
          flying = false;

          // Only close the app this slide started on (another one may have been opened meanwhile)
          if (token === openToken) {
            close(false, true);
          }
          // The app is gone; put the (hidden) shell back in place for next time
          panel.style.transition = 'none';
          panel.style.transform = '';
          panel.style.opacity = '';
          setTimeout(function () {
            panel.style.transition = '';
          }, 0);
        }, 320);
      } else {
        reset(true);
      }
    }

    function addZone(target) {
      if (!target) {
        return;
      }
      target.addEventListener('pointerdown', down);
      target.addEventListener('pointermove', move);
      target.addEventListener('pointerup', up);
      target.addEventListener('pointercancel', up);
      // Only the drag area losing the finger counts. (On touch screens the browser first holds the finger on the
      // element under it and lets go of it when the drag takes over; that is not the finger lifting.)
      target.addEventListener('lostpointercapture', function (event) {
        if (event.target === zone && !dragging) {
          up(event);
        }
      });
    }

    zones.forEach(addZone);
    addDragZone = addZone;

    // However the finger leaves (anywhere on screen, a cancelled touch, a lost event), the drag always ends
    ['pointerup', 'pointercancel', 'touchend', 'touchcancel', 'blur'].forEach(function (type) {
      window.addEventListener(type, function (event) {
        if (start) {
          up(event);
        }
      }, true);
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden && start) {
        start = null;
        dragging = false;
        reset(false);
      }
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
    // The support address opens the Email app instead of the mail program
    if (emailApp(url)) {
      return route(emailApp(url), event);
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      return false; // other mailto: and similar open the right app themselves
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
    if (isStandalone(url)) {
      return false; // legal pages: an ordinary link
    }
    if (isPage(url)) {
      // A legal page that loaded on its own: Home is a real link from here (it closes an open app first)
      if (!ON_HOME && (url.pathname === '/' || url.pathname === '/index.html')) {
        return false;
      }
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
    // Clicking the outside app that is already open (Community in the Dock) closes it, like any other app
    if (isOpen && !sheet.classList.contains('is-page') && sheet.getAttribute('data-href') === url.href) {
      close();
      return true;
    }
    openExternal(href, url, serviceFor(url), display);
    return true;
  }

  // A page in the panel changed its own address (#pro on Club, ...): show it in the browser bar and the panel's bar
  function followAddress(href) {
    let url;

    try {
      url = new URL(href);
    } catch (error) {
      return;
    }
    if (!isOpen || !sheet || !sheet.classList.contains('is-page') || url.origin !== location.origin) {
      return;
    }
    const frame = sheet.querySelector('.preview-body iframe');

    // Only the page that is open right now may change the address
    try {
      if (!frame || frame.contentWindow.location.href !== url.href) {
        return;
      }
    } catch (error) {
      return;
    }
    if (historyEntry) {
      history.replaceState({preview: true}, '', url.pathname + url.search + url.hash);
    }
    sheet.querySelector('.preview-host').textContent = shown(url);
  }

  // While an app is open, the island's theme button is its ✕
  function labelToggle() {
    const toggle = document.querySelector('#nav .theme-toggle');

    if (!toggle) {
      return;
    }
    const label = isOpen ? 'Close'
      : (document.documentElement.getAttribute('data-theme') === 'dark' ? 'Switch to light' : 'Switch to dark');
    toggle.setAttribute('aria-label', label);
    toggle.title = label;
  }

  document.addEventListener('click', function (event) {
    const toggle = event.target.closest ? event.target.closest('#nav .theme-toggle') : null;

    if (toggle && isOpen) {
      event.preventDefault();
      close();
    }
  });

  // If no app is open, nothing of the panel may remain: no blur, no transparent island, no locked page
  function settle() {
    if (isOpen || !sheet) {
      return;
    }
    clearTimeout(hideTimer);
    clearTimeout(widenTimer);
    sheet.classList.remove('is-open');
    sheet.hidden = true;
    sheet.querySelector('.preview-body').textContent = '';
    document.documentElement.classList.remove('island-hosting', 'island-widening', 'preview-locked', 'preview-page');
    restoreIsland();
    markDock(null);
    labelToggle();
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      settle();
    }
  });
  window.addEventListener('pageshow', settle);
  window.addEventListener('focus', settle);

  window.addEventListener('popstate', function () {
    if (historyEntry) {
      close(true);
    } else if (location.pathname !== '/' && location.pathname !== '/index.html'
      && !isStandalone(new URL(location.href))) {
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

  // ---------- Search in the island ----------
  // Clicking the island (not its buttons or links) turns it into a search for everything: the Dock's apps first,
  // then every link in the footer (other pages and products, the community, legal pages, the support email, the
  // company records). The results open in the panel, exactly like an app: the island grows down to hold them, the
  // page behind blurs, and the theme button is the ✕. Choosing one opens it like a click on it (an app turns the
  // panel into that app). Not while an app is open, and not while the Dock sits in the island.
  const SEARCH_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>';
  const EXTRA_ICONS = {
    '/faq/': {tile: 'linear-gradient(180deg, #5eead4 0%, #0f9488 100%)', svg: '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .8-1 1.5v.4"/><path d="M12 16.6v.1"/>'},
    '/ads/': {tile: 'linear-gradient(180deg, #6ee7d8 0%, #0d8f84 100%)', svg: '<path d="M9 9l11 4-4.8 1.6L13.6 19.5z"/><path d="M5.5 3.5l1.2 2.4M3 8l2.6.6M11.5 3.2l-.9 2.6"/>'},
    '/work/': {tile: 'linear-gradient(180deg, #a3aab5 0%, #4b5260 100%)', svg: '<rect x="3.5" y="7.5" width="17" height="12.5" rx="2.5"/><path d="M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5"/><path d="M3.5 12.5h17"/>'},
    '/ai/': {tile: 'linear-gradient(180deg, #c4b5fd 0%, #6d28d9 100%)', svg: '<path d="M12 3.5l1.9 5.1 5.1 1.9-5.1 1.9L12 17.5l-1.9-5.1L5 10.5l5.1-1.9z"/><path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>'}
  };
  const searchNav = document.getElementById('nav');
  let searchForm = null;
  let searchInput = null;
  let searchList = null;
  let searchItems = [];
  let searchShown = [];
  let searchActive = 0;

  function appKey(url) {
    const host = url.hostname.replace(/^www\./, '');

    if (Object.prototype.hasOwnProperty.call(OWN_DOMAINS, host) && OWN_DOMAINS[host]) {
      return OWN_DOMAINS[host];
    }
    return url.origin === location.origin ? url.pathname : url.href;
  }

  // Everything that can be searched: the Dock's apps first, then the pages in the footer that open as apps
  // Everyday words people may type for each place, so the search finds it even without its exact name.
  // Keyed by the page's path, the email address, or the website's host.
  const SEARCH_WORDS = {
    '/doremi/': 'doremi chat chatbot bot assistant ai message messages messaging talk text whatsapp telegram messenger instagram discord coming soon product new',
    '/club/': 'plans plan pricing price prices cost membership member premium subscription subscribe buy purchase pay payment upgrade plus pro vip red shop store tiers',
    '/sales/': 'sale deals deal offer offers promo promotion promotions cheap bargain black friday cyber monday reduced',
    '/discounts/': 'discount coupon coupons code codes voucher student students teacher school university nonprofit non profit charity open source creator youtuber streamer hardship cheaper reduced price request',
    '/giveaways/': 'giveaway free win winner prize prizes contest competition raffle gift gifts lottery',
    '/apps/': 'app application download install iphone ipad ios apple app store android google play store phone mobile tablet',
    '/faq/': 'help faq questions question answers support how what why info information guide problem problems issue trouble',
    '/email/': 'email mail contact message write support ticket help complaint feedback reach us customer service talk human report bug human@idealistic.ai address',
    '/ads/': 'ads ad advertising advertisement advertise promote promotion sponsor sponsored marketing click',
    '/work/': 'work job jobs career careers hiring hire apply application employment position positions vacancy vacancies team join recruit',
    '/ai/': 'ai artificial intelligence robot machine learning gpt model how we use ai',
    '/github': 'github code source source code open source repository repositories repo developer developers git',
    '/terms/terms_of_use/': 'terms terms of use terms of service tos rules conditions agreement legal',
    '/policies/privacy_policy/': 'privacy policy data personal data gdpr cookies tracking legal',
    '/instructions/user_data_deletion/': 'delete deletion remove erase data account gdpr forget me right to be forgotten privacy legal',
    'spartan.top': 'spartan anticheat anti cheat cheat cheating hack hacks hacker minecraft plugin server protection',
    'vagdedes.com': 'community discord server people members forum join social chat creator friends',
    'ariregister.rik.ee': 'company business register registry estonia estonian legal entity registration idealistic ou owner owns owned behind who official',
    'euipo.europa.eu': 'trademark brand registered euipo eu europe legal'
  };

  // Lower case, no accents, punctuation as spaces: "Idealistic OÜ" matches "idealistic ou"
  function plain(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9@]+/g, ' ').trim();
  }

  // Up to one slip: a letter wrong, missing, extra, or two letters swapped (for words of 5 letters or more)
  function nearlySame(a, b) {
    if (Math.abs(a.length - b.length) > 1) {
      return false;
    }
    let i = 0;
    let j = 0;
    let edits = 0;

    while (i < a.length && j < b.length) {
      if (a[i] === b[j]) {
        i++;
        j++;
        continue;
      }
      if (++edits > 1) {
        return false;
      }
      if (a.length === b.length && a[i] === b[j + 1] && a[i + 1] === b[j]) {
        i += 2;
        j += 2;
      } else if (a.length > b.length) {
        i++;
      } else if (a.length < b.length) {
        j++;
      } else {
        i++;
        j++;
      }
    }
    return edits + (a.length - i) + (b.length - j) <= 1;
  }

  // Little words that say nothing about where to go ("delete my data", "how do i get a discount")
  const FILLER = ['a', 'an', 'the', 'my', 'me', 'i', 'to', 'of', 'for', 'do', 'can', 'want', 'need', 'where', 'is',
    'are', 'please', 'your', 'you', 'our', 'with', 'on', 'in', 'it', 'and', 'or', 'at', 'about', 'get', 'find', 'see',
    'how', 'what', 'why', 'when', 'which', 'there', 'any', 'some', 'have', 'has', 'does', 'will', 'would', 'this',
    'that', 'website', 'site', 'page'];

  // How well an item matches what was typed: 0 is no match, higher is better. Every typed word must match.
  function score(item, query) {
    const all = plain(query).split(' ').filter(Boolean);
    const meaningful = all.filter(function (word) {
      return FILLER.indexOf(word) === -1;
    });
    const typed = meaningful.length ? meaningful : all;
    const name = plain(item.label);

    if (!typed.length) {
      return 1;
    }
    if (name.indexOf(all.join(' ')) === 0) {
      return 100;
    }
    let total = 0;

    for (let k = 0; k < typed.length; k++) {
      const word = typed[k];
      let best = 0;

      if (name.split(' ').some(function (part) { return part.indexOf(word) === 0; })) {
        best = 60;
      } else if (name.indexOf(word) !== -1) {
        best = 45;
      } else if (item.terms.some(function (term) { return term.indexOf(word) === 0; })) {
        best = 30;
      } else if (word.length >= 3 && item.text.indexOf(word) !== -1) {
        best = 20;
      } else if (word.length >= 5 && item.terms.some(function (term) { return nearlySame(word, term); })) {
        best = 10;
      }
      if (!best) {
        return 0;
      }
      total += best;
    }
    return total / typed.length;
  }

  const DOC_ICON = '<path d="M7 3.5h7l4 4v13H7z"/><path d="M14 3.5v4h4"/><path d="M9.5 12h6M9.5 15.5h6"/>';
  const MAIL_ICON = '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="m4 7.5 8 5.5 8-5.5"/>';

  function footerIcon(url) {
    if (url.protocol === 'mailto:') {
      return {tile: 'linear-gradient(180deg, #7cc8ff 0%, #2f7cf6 100%)', svg: MAIL_ICON};
    }
    const key = appKey(url);

    if (EXTRA_ICONS[key]) {
      return EXTRA_ICONS[key];
    }
    const service = serviceFor(url);

    if (service) {
      return {tile: service.tile, logo: service.logo, glyph: service.glyph};
    }
    if (isStandalone(url)) {
      return {tile: 'linear-gradient(180deg, #a3aab5 0%, #4b5260 100%)', svg: DOC_ICON};
    }
    return {tile: '', svg: ''};
  }

  function extraWords(key, url) {
    const host = url.hostname ? url.hostname.replace(/^www\./, '') : '';
    return SEARCH_WORDS[key] || SEARCH_WORDS[url.href] || SEARCH_WORDS[host] || '';
  }

  function collectApps() {
    const items = [];
    const seen = {};

    function add(href, label, icon) {
      let url;

      try {
        url = new URL(href, location.href);
      } catch (error) {
        return;
      }
      // The support address is the Email app: listed once
      const key = emailApp(url) ? appKey(new URL(emailApp(url))) : url.protocol === 'mailto:' ? url.href : appKey(url);

      if (seen[key] || !label) {
        return;
      }
      seen[key] = true;
      const host = url.hostname.replace(/^www\./, '');
      const service = url.protocol === 'mailto:' ? null : serviceFor(url);
      const aliases = Object.keys(OWN_DOMAINS).filter(function (domain) {
        return OWN_DOMAINS[domain] === key;
      });
      let place = host;

      if (url.protocol === 'mailto:') {
        place = 'Email';
      } else if (service) {
        place = service.host;
      } else if (url.origin === location.origin) {
        place = PAGE_DOMAIN[url.pathname] || host + url.pathname.replace(/\/$/, '');
      }
      items.push({
        href: url.href,
        label: label,
        place: place,
        text: plain([label, place].concat(aliases, [extraWords(key, url)]).join(' ')),
        terms: plain([label, place].concat(aliases, [extraWords(key, url)]).join(' ')).split(' '),
        icon: icon
      });
    }

    document.querySelectorAll('.dock .dock-item[data-app][href]').forEach(function (item) {
      const svg = item.querySelector('.dock-icon svg');
      add(item.getAttribute('href'), item.querySelector('.dock-label').textContent.trim(), {
        tile: getComputedStyle(item).getPropertyValue('--tile').trim(),
        svg: svg ? svg.innerHTML : ''
      });
    });
    // Then every other link in the footer (other products, the community, legal pages, support email, company
    // records), each with a fitting icon
    document.querySelectorAll('.footer a[href]').forEach(function (link) {
      let url;

      try {
        url = new URL(link.href, location.href);
      } catch (error) {
        return;
      }
      if (link.classList.contains('brand-i') || link.classList.contains('brand-r')
        || ['http:', 'https:', 'mailto:'].indexOf(url.protocol) === -1) {
        return;
      }
      // The link's name, without the icon in front of it
      const name = Array.from(link.childNodes).filter(function (node) {
        return !(node.classList && node.classList.contains('footer-icon'));
      }).map(function (node) {
        return node.textContent;
      }).join('').trim();
      add(link.href, name, footerIcon(url));
    });
    return items;
  }

  function searchAllowed() {
    const root = document.documentElement;
    const dockInIsland = root.getAttribute('data-dock') === 'top' && !window.matchMedia('(max-width: 820px)').matches;
    return searchNav && !EMBEDDED && !isOpen && !dockInIsland && !root.classList.contains('preview-locked');
  }

  function searching() {
    return Boolean(sheet && sheet.classList.contains('is-search'));
  }

  function buildSearch() {
    searchForm = document.createElement('form');
    searchForm.className = 'island-search';
    searchForm.setAttribute('role', 'search');
    searchForm.innerHTML = SEARCH_ICON +
      '<input type="search" placeholder="Search apps in iDEALISTIC®" aria-label="Search apps in iDEALISTIC®" autocomplete="off" spellcheck="false"' +
      ' role="combobox" aria-expanded="true" aria-controls="island-results" aria-autocomplete="list">';
    searchInput = searchForm.querySelector('input');
    searchNav.insertBefore(searchForm, searchNav.querySelector('.theme-toggle'));
    // Phones: the search row is the handle to slide the search away in any direction, like an app's title bar
    if (addDragZone) {
      addDragZone(searchForm);
    }

    searchList = document.createElement('div');
    searchList.className = 'island-results';
    searchList.id = 'island-results';
    searchList.setAttribute('role', 'listbox');
    searchList.setAttribute('aria-label', 'Apps');

    searchInput.addEventListener('input', function () {
      searchActive = 0;
      renderResults();
      measure(true);
    });
    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        searchActive = (searchActive + step + searchShown.length) % Math.max(searchShown.length, 1);
        markActive(true);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        close();
      }
    });
    searchForm.addEventListener('submit', function (event) {
      event.preventDefault();

      if (searchShown[searchActive]) {
        openResult(searchShown[searchActive]);
      }
    });
    searchList.addEventListener('scroll', blurEdgesSoon, {passive: true});
    window.addEventListener('resize', blurEdgesSoon);

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', blurEdgesSoon);
    }
    searchList.addEventListener('mousedown', function (event) {
      event.preventDefault(); // keep the field focused while choosing
    });
    // The highlight follows the pointer: one app highlighted at a time, the same one Enter opens
    searchList.addEventListener('mousemove', function (event) {
      const row = event.target.closest('[data-index]');

      if (row && Number(row.getAttribute('data-index')) !== searchActive) {
        searchActive = Number(row.getAttribute('data-index'));
        markActive(false);
      }
    });
    searchList.addEventListener('click', function (event) {
      const row = event.target.closest('[data-index]');

      if (row) {
        openResult(searchShown[Number(row.getAttribute('data-index'))]);
      }
    });
  }

  function renderResults() {
    const query = searchInput.value;
    // Best matches first; equal ones keep their usual order (the Dock's apps first)
    searchShown = searchItems.map(function (item, index) {
      return {item: item, index: index, score: score(item, query)};
    }).filter(function (entry) {
      return entry.score > 0;
    }).sort(function (a, b) {
      return b.score - a.score || a.index - b.index;
    }).map(function (entry) {
      return entry.item;
    });
    searchList.textContent = '';

    if (!searchShown.length) {
      const empty = document.createElement('p');
      empty.className = 'island-results-empty';
      empty.textContent = 'No app found.';
      searchList.appendChild(empty);
      return;
    }
    searchShown.forEach(function (item, index) {
      const row = document.createElement('div');
      row.className = 'island-result';
      row.id = 'island-result-' + index;
      row.setAttribute('role', 'option');
      row.setAttribute('data-index', String(index));
      const tile = document.createElement('span');
      tile.className = 'island-result-icon';
      tile.setAttribute('aria-hidden', 'true');

      if (item.icon.tile) {
        tile.style.background = item.icon.tile;
      }
      if (item.icon.logo) {
        const logo = document.createElement('span');
        logo.className = 'island-result-logo';
        logo.style.setProperty('--logo', 'url(/assets/img/brands/' + item.icon.logo + '.svg)');
        tile.appendChild(logo);
      } else if (item.icon.svg) {
        tile.innerHTML = '<svg viewBox="0 0 24 24">' + item.icon.svg + '</svg>';
      } else {
        tile.textContent = item.icon.glyph || item.label.charAt(0);
      }
      const text = document.createElement('span');
      text.className = 'island-result-text';
      const name = document.createElement('strong');
      name.textContent = item.label;
      const place = document.createElement('span');
      place.textContent = item.place;
      text.appendChild(name);
      text.appendChild(place);
      row.appendChild(tile);
      row.appendChild(text);
      searchList.appendChild(row);
    });
    markActive(true);
    blurEdgesSoon();
  }

  // Apps only partly in view at the top or bottom of the list are blurred, more the further out they are (like the
  // apps peeking in at the edges of the phone Dock), so it is clear there is more to scroll to
  let edgeFrame = null;

  function blurEdges() {
    if (!searchList || !searchList.isConnected) {
      return;
    }
    const box = searchList.getBoundingClientRect();
    searchList.querySelectorAll('.island-result').forEach(function (row) {
      const r = row.getBoundingClientRect();
      const outside = Math.max(0, box.top - r.top) + Math.max(0, r.bottom - box.bottom);
      const amount = r.height ? Math.min(1, outside / (r.height * 0.75)) : 0;

      if (amount > 0.01) {
        row.classList.add('is-peek');
        row.style.setProperty('--peek-amount', amount.toFixed(3));
      } else {
        row.classList.remove('is-peek');
        row.style.removeProperty('--peek-amount');
      }
    });
  }

  function blurEdgesSoon() {
    if (edgeFrame === null) {
      edgeFrame = requestAnimationFrame(function () {
        edgeFrame = null;
        blurEdges();
      });
    }
  }

  // scroll: bring the highlighted app into view (keyboard), not when the pointer chose it
  function markActive(scroll) {
    searchList.querySelectorAll('.island-result').forEach(function (row, index) {
      const active = index === searchActive;
      row.classList.toggle('is-active', active);
      row.setAttribute('aria-selected', active ? 'true' : 'false');

      if (active) {
        searchInput.setAttribute('aria-activedescendant', row.id);

        if (scroll) {
          row.scrollIntoView({block: 'nearest'});
        }
      }
    });
  }

  function openSearch() {
    if (!sheet) {
      build();
    }
    if (!searchForm) {
      buildSearch();
    }
    searchItems = collectApps();
    searchActive = 0;
    searchInput.value = '';
    renderResults();

    // The panel holds the results, like an app: no title bar, the field is in the island's own row
    stopHeartbeat();
    sheet.classList.remove('is-page');
    sheet.classList.add('is-search');
    sheet.querySelector('#preview-title').textContent = 'Search';
    sheet.querySelector('.preview-host').textContent = '';
    const body = sheet.querySelector('.preview-body');
    body.textContent = '';
    searchList.classList.remove('preview-leaving', 'is-gone', 'preview-entering', 'is-in');
    body.appendChild(searchList);
    markDock(null);
    searchNav.classList.add('is-searching');
    // Phones: the Dock leaves while searching, like it does for an app (preview.css)
    document.documentElement.classList.add('preview-search');
    show(null, false);
    blurEdgesSoon();

    // With a mouse, type straight away. On touch screens the keyboard waits until the field itself is tapped,
    // so the results can be looked through and scrolled first.
    if (!window.matchMedia('(pointer: coarse)').matches) {
      searchInput.focus({preventScroll: true});
    }
  }

  // Back to a plain island (the panel closed, or it now shows the chosen app)
  function leaveSearch() {
    document.documentElement.classList.remove('preview-search');

    if (sheet) {
      sheet.classList.remove('is-search');
    }
    if (searchNav && searchNav.classList.contains('is-searching')) {
      searchNav.classList.remove('is-searching');

      if (searchInput) {
        searchInput.blur();
      }
    }
  }

  function openResult(item) {
    if (!item) {
      return;
    }
    // Close the keyboard first; the panel measures again as it goes (and once more after, to be sure)
    searchInput.blur();
    [350, 800].forEach(function (wait) {
      setTimeout(function () {
        if (isOpen && sheet.classList.contains('is-page')) {
          measure();
        }
      }, wait);
    });
    // Open it like a click on the app: the panel turns into it (or its card, for apps that live elsewhere)
    if (!route(item.href, null)) {
      close(false, true);
      location.href = item.href;
    }
  }

  if (searchNav && !EMBEDDED) {
    searchNav.addEventListener('click', function (event) {
      if (searching() || event.target.closest('a, button, input, form, .dock') || !searchAllowed()) {
        return;
      }
      openSearch();
    });
  }

  // Going to another whole page (a legal page and Home, both ways): browsers that cross-fade pages on their own
  // (@view-transition in layout.css) need nothing; others fade this page out first, and the next one fades in
  if (!EMBEDDED && !('CSSViewTransitionRule' in window)
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.addEventListener('click', function (event) {
      const link = event.target.closest ? event.target.closest('a[href]') : null;

      if (!link || event.defaultPrevented || event.button !== 0 || link.target === '_blank'
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      let url;

      try {
        url = new URL(link.href, location.href);
      } catch (error) {
        return;
      }
      if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search)) {
        return;
      }
      event.preventDefault();

      try {
        sessionStorage.setItem('page-fade', '1');
      } catch (error) {
        // Without storage the next page simply appears
      }
      document.documentElement.classList.add('page-leaving');
      setTimeout(function () {
        location.href = url.href;
      }, 220);
    });
    // Coming back with the back button to a page that had faded out: show it again
    window.addEventListener('pageshow', function () {
      document.documentElement.classList.remove('page-leaving');
    });
  }

  // A page opened directly (prefs.js sent it here as /?page=...): Home with that page open on top.
  // Last in this file: opening a page uses everything above (the search included), which must exist first.
  const params = new URLSearchParams(location.search);
  const requested = params.get('page');

  if (requested && requested.charAt(0) === '/' && requested.charAt(1) !== '/') {
    const target = new URL(requested, location.origin);

    if (isStandalone(target)) {
      // An old link to a legal page: show it on its own
      location.replace(target.pathname + target.search + target.hash);
    } else if (target.origin === location.origin && target.pathname !== '/') {
      history.replaceState(null, '', '/');
      openPage(target, shown(target), false);
    }
  }
})();
