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
    'idealistic.work': '/work/',
    'idealistic.download': '/apps/',
    'idealistic.app': '/apps/',
    'idealistic.community': '/community/',
    'idealistic.ai': '/ai/',
    'idealistic.enterprises': null
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

  let sheet = null;
  let lastFocus = null;
  let historyEntry = false;

  function build() {
    sheet = document.createElement('div');
    sheet.className = 'preview';
    sheet.hidden = true;
    sheet.innerHTML =
      '<div class="preview-backdrop" data-close></div>' +
      '<section class="preview-sheet" role="dialog" aria-modal="true" aria-labelledby="preview-title">' +
      '  <header class="preview-bar">' +
      '    <button class="preview-close" type="button" data-close aria-label="Close preview">' +
      '      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
      '    <div class="preview-where"><strong id="preview-title"></strong><span class="preview-host"></span></div>' +
      '    <a class="button solid small preview-open" target="_blank" rel="noopener">Open</a>' +
      '  </header>' +
      '  <div class="preview-body"></div>' +
      '</section>';
    document.body.appendChild(sheet);
    sheet.addEventListener('click', function (event) {
      if (event.target.closest('[data-close]')) {
        close();
      }
    });
    sheet.querySelector('.preview-open').addEventListener('click', function () {
      setTimeout(close, 300);
    });
  }

  function open(link, url, service) {
    if (!sheet) {
      build();
    }
    const info = service || {name: url.hostname.replace(/^www\./, ''), host: url.hostname.replace(/^www\./, ''), text: 'An external website.', tile: 'linear-gradient(180deg, #6b7280, #374151)', glyph: '↗'};
    sheet.querySelector('#preview-title').textContent = info.name;
    sheet.querySelector('.preview-host').textContent = info.host;
    sheet.querySelector('.preview-open').href = link.href;
    const body = sheet.querySelector('.preview-body');
    body.textContent = '';

    if (info.frame) {
      const frame = document.createElement('iframe');
      frame.src = info.frame;
      frame.title = info.name;
      frame.loading = 'eager';
      frame.referrerPolicy = 'strict-origin-when-cross-origin';
      // No allow-top-navigation: the framed page can never navigate this window away
      frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox');
      body.appendChild(frame);
      body.classList.add('has-frame');
    } else {
      body.classList.remove('has-frame');
      const card = document.createElement('div');
      card.className = 'preview-card';
      card.innerHTML =
        '<span class="preview-tile" aria-hidden="true"></span>' +
        '<h2></h2><p class="preview-text"></p>' +
        '<p class="preview-note">This site does not allow being shown inside other websites. Open it in a new tab: this page stays open.</p>';
      card.querySelector('.preview-tile').style.background = info.tile;
      card.querySelector('.preview-tile').textContent = info.glyph;
      card.querySelector('h2').textContent = info.name;
      card.querySelector('.preview-text').textContent = info.text;
      const button = document.createElement('a');
      button.className = 'button solid preview-open-large';
      button.href = link.href;
      button.target = '_blank';
      button.rel = 'noopener';
      button.textContent = 'Open ' + info.name;
      button.addEventListener('click', function () {
        setTimeout(close, 300);
      });
      card.appendChild(button);
      body.appendChild(card);
    }

    lastFocus = document.activeElement;
    sheet.hidden = false;
    document.documentElement.classList.add('preview-locked');
    requestAnimationFrame(function () {
      sheet.classList.add('is-open');
      sheet.querySelector('.preview-close').focus();
    });

    // The back button / back gesture closes the preview instead of leaving the page
    if (!historyEntry) {
      history.pushState({preview: true}, '');
      historyEntry = true;
    }
  }

  function close(fromHistory) {
    if (!sheet || sheet.hidden) {
      return;
    }
    sheet.classList.remove('is-open');
    document.documentElement.classList.remove('preview-locked');
    const done = function () {
      sheet.hidden = true;
      sheet.querySelector('.preview-body').textContent = '';
    };
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(done, reduce ? 0 : 420);

    if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
    if (historyEntry) {
      historyEntry = false;

      if (!fromHistory) {
        history.back();
      }
    }
  }

  window.addEventListener('popstate', function () {
    if (historyEntry) {
      close(true);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && sheet && !sheet.hidden) {
      close();
    }
  });

  document.addEventListener('click', function (event) {
    const link = event.target.closest ? event.target.closest('a[href]') : null;

    if (!link
      || event.defaultPrevented
      || event.button !== 0
      || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
      || link.closest('.preview')) {
      return;
    }
    let url;

    try {
      url = new URL(link.href, location.href);
    } catch (error) {
      return;
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      return; // mailto: and similar open the right app themselves
    }
    const host = url.hostname.replace(/^www\./, '');

    if (Object.prototype.hasOwnProperty.call(OWN_DOMAINS, host) && OWN_DOMAINS[host] !== null) {
      event.preventDefault();
      location.href = OWN_DOMAINS[host];
      return;
    }
    const service = serviceFor(url);
    const external = url.origin !== location.origin;

    if (!service && !external) {
      return; // an ordinary page of this site
    }
    event.preventDefault();
    open(link, url, service);
  });
})();
