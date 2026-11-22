/* Loaded in <head> without defer: applies the visitor's saved preferences (theme, Dock position) before the first paint. */
(function () {
  'use strict';

  // Marks that JavaScript runs: buttons that only work with it (theme, Dock position) are hidden otherwise
  document.documentElement.classList.add('js');

  // Arriving from another whole page that faded out (browsers without page cross-fades, see preview.js): fade in
  try {
    if (sessionStorage.getItem('page-fade') === '1') {
      sessionStorage.removeItem('page-fade');
      document.documentElement.classList.add('page-entering');
      setTimeout(function () {
        document.documentElement.classList.remove('page-entering');
      }, 400);
    }
  } catch (error) {
    // No storage: the page simply appears
  }

  // Search engine crawlers: every page stays itself, so each one is indexed with its own address, title and
  // content. Without this they would follow the move to Home below and see every page as Home. Same content as
  // visitors get, only not moved on top of Home. (Link previews read the HTML without running this, so they need
  // nothing; the names are specific so no visitor's browser matches.)
  const CRAWLER = /googlebot|google-inspectiontool|adsbot-google|mediapartners-google|bingbot|bingpreview|duckduckbot|yandexbot|baiduspider|applebot|slurp|petalbot|seznambot|qwantbot|ecosia|chrome-lighthouse/i;
  const crawler = CRAWLER.test(navigator.userAgent || '');

  // Shown inside the preview sheet of another page of this site: drop the island, Dock and footer
  if (window.self !== window.top) {
    document.documentElement.classList.add('embedded');
  } else if (!crawler && location.pathname !== '/' && location.pathname !== '/index.html'
    && !/^\/legal(\/|$)/.test(location.pathname)) {
    // Home is always there: a page opened directly is shown on top of Home, in the sheet.
    // Legal pages (/legal/...) are the exception: they load as ordinary pages.
    location.replace('/?page=' + encodeURIComponent(location.pathname + location.search + location.hash));
    return;
  }

  // Theme: the visitor's choice (light / dark) if they made one, otherwise the system's; light when the system has none
  const root = document.documentElement;
  const darkSystem = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function savedTheme() {
    try {
      const value = localStorage.getItem('theme');
      return value === 'light' || value === 'dark' ? value : null;
    } catch (error) {
      return null;
    }
  }

  function applyTheme() {
    const choice = savedTheme();
    const theme = choice || (darkSystem && darkSystem.matches ? 'dark' : 'light');
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-theme-choice', choice || 'system');
    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
      meta.setAttribute('content', theme === 'light' ? '#e4e9f1' : '#06070a');
    }
    applyLabel();
  }

  function applyLabel() {
    const button = document.querySelector('.theme-toggle');

    if (button) {
      const label = root.getAttribute('data-theme') === 'dark' ? 'Switch to light' : 'Switch to dark';
      button.setAttribute('aria-label', label);
      button.title = label;
    }
  }

  applyTheme();
  document.addEventListener('DOMContentLoaded', applyTheme);

  // Follow the system while no choice is saved
  if (darkSystem && darkSystem.addEventListener) {
    darkSystem.addEventListener('change', applyTheme);
  }
  // A choice made in another tab, or in the page around this one (pages shown in the panel), applies here too
  window.addEventListener('storage', function (event) {
    if (event.key === 'theme' || event.key === null) {
      applyTheme();
    }
  });

  // The island's theme button: every click switches to the other theme, visibly. A choice equal to the
  // system's setting is not stored, so the site goes back to following the system.
  document.addEventListener('click', function (event) {
    const button = event.target.closest ? event.target.closest('.theme-toggle') : null;

    // While an app is open this button is its ✕ (preview.js closes it); it does not switch the theme
    if (!button || root.classList.contains('preview-locked')) {
      return;
    }
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    const system = darkSystem && darkSystem.matches ? 'dark' : 'light';

    try {
      if (next === system) {
        localStorage.removeItem('theme');
      } else {
        localStorage.setItem('theme', next);
      }
    } catch (error) {
      // Not remembered in this browser, but it still changes for this page
    }
    root.setAttribute('data-theme', next);
    root.setAttribute('data-theme-choice', next === system ? 'system' : next);
    applyLabel();
  });

  // Dock position chosen with the Dock's own button: "bottom" (default) or "top" (inside the island)
  try {
    if (localStorage.getItem('dock-position') === 'top') {
      document.documentElement.setAttribute('data-dock', 'top');
    }
  } catch (error) {
    // Storage unavailable (private mode or blocked): the Dock stays at the bottom
  }

  // The reply address hint: a tap shows it (phones do not focus plain text), a tap anywhere else hides it
  document.addEventListener('click', function (event) {
    const address = event.target.closest ? event.target.closest('.reply-address') : null;
    document.querySelectorAll('.reply-address.is-hinting').forEach(function (other) {
      if (other !== address) {
        other.classList.remove('is-hinting');
      }
    });
    if (address) {
      address.classList.toggle('is-hinting');
    }
  });
  /* Tooltips: the browser's own (from title="…") are rounded and do not follow the site's theme, so every title
     becomes data-tip (kept for screen readers as aria-description) and a square one of ours shows on mouse hover */
  function takeTitle(element) {
    const text = element.getAttribute('title');

    if (text === null || element.tagName === 'IFRAME' || element instanceof SVGElement) {
      return;
    }
    element.removeAttribute('title');

    if (text) {
      element.setAttribute('data-tip', text);

      if (!element.hasAttribute('aria-label')) {
        element.setAttribute('aria-description', text);
      }
    }
  }

  function takeTitles(scope) {
    if (scope.getAttribute && scope.hasAttribute('title')) {
      takeTitle(scope);
    }
    if (scope.querySelectorAll) {
      scope.querySelectorAll('[title]').forEach(takeTitle);
    }
  }

  new MutationObserver(function (changes) {
    changes.forEach(function (change) {
      if (change.type === 'attributes') {
        takeTitle(change.target);
      } else {
        change.addedNodes.forEach(takeTitles);
      }
    });
  }).observe(root, {subtree: true, childList: true, attributes: true, attributeFilter: ['title']});
  takeTitles(root);

  let tip = null;
  let tipFor = null;
  let tipTimer = 0;

  function hideTip() {
    clearTimeout(tipTimer);
    tipFor = null;

    if (tip) {
      tip.classList.remove('is-shown');
    }
  }

  function showTip(element) {
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'site-tip';
      tip.setAttribute('aria-hidden', 'true');
      document.body.appendChild(tip);
    }
    tip.textContent = element.getAttribute('data-tip');
    tip.style.left = '0px';
    tip.style.top = '0px';
    const box = element.getBoundingClientRect();
    const size = tip.getBoundingClientRect();
    const gap = 8;
    const left = Math.max(gap, Math.min(window.innerWidth - size.width - gap, box.left + box.width / 2 - size.width / 2));
    // Under the element, or over it when there is no room below
    const top = box.bottom + gap + size.height <= window.innerHeight - gap ? box.bottom + gap : Math.max(gap, box.top - gap - size.height);
    tip.style.left = Math.round(left) + 'px';
    tip.style.top = Math.round(top) + 'px';
    tip.classList.add('is-shown');
  }

  document.addEventListener('pointerover', function (event) {
    const element = event.pointerType === 'mouse' && event.target.closest ? event.target.closest('[data-tip]') : null;

    if (element === tipFor) {
      return;
    }
    hideTip();

    if (element) {
      tipFor = element;
      tipTimer = setTimeout(function () {
        if (tipFor === element && element.isConnected) {
          showTip(element);
        }
      }, 450);
    }
  });
  ['pointerdown', 'keydown', 'blur'].forEach(function (name) {
    window.addEventListener(name, hideTip, true);
  });
  window.addEventListener('scroll', hideTip, {capture: true, passive: true});
  document.addEventListener('pointerleave', hideTip);
})();
