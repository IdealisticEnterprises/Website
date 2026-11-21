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

  // Shown inside the preview sheet of another page of this site: drop the island, Dock and footer
  if (window.self !== window.top) {
    document.documentElement.classList.add('embedded');
  } else if (location.pathname !== '/' && location.pathname !== '/index.html'
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
})();
