/* Loaded in <head> without defer: the opening animation has to be ready before the first paint. */
(function () {
  'use strict';

  const root = document.documentElement;
  const KEY = 'app-origin';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let origin = null;

  // Where the link that opened this page was tapped, as a percentage of the screen
  try {
    origin = JSON.parse(sessionStorage.getItem(KEY) || 'null');
    sessionStorage.removeItem(KEY);
  } catch (error) {
    origin = null;
  }
  if (origin && Date.now() - origin.time < 5000) {
    root.style.setProperty('--app-origin', origin.x.toFixed(2) + '% ' + origin.y.toFixed(2) + '%');
    // Genie: the page pours out of the clicked spot, narrow at that end (Dock at the bottom or at the top)
    root.setAttribute('data-genie', origin.y > 50 ? 'bottom' : 'top');
  } else {
    origin = null;
  }

  function fallbackOpen() {
    if (reduceMotion || !origin) {
      return;
    }
    root.classList.add('app-opening');
    window.addEventListener('animationend', function done(event) {
      if (event.target === document.body) {
        root.classList.remove('app-opening');
        window.removeEventListener('animationend', done);
      }
    });
  }

  if ('onpagereveal' in window) {
    // Browsers with page reveal events: only animate by hand when no view transition runs
    window.addEventListener('pagereveal', function (event) {
      if (!event.viewTransition) {
        fallbackOpen();
      }
    });
  } else {
    document.addEventListener('DOMContentLoaded', fallbackOpen);
  }

  // Remember where same-site links are tapped, so the next page can open from there
  document.addEventListener('click', function (event) {
    const link = event.target.closest ? event.target.closest('a[href]') : null;

    if (!link
      || event.defaultPrevented
      || event.button !== 0
      || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
      || (link.target && link.target !== '_self')
      || link.hasAttribute('download')) {
      return;
    }
    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin
      || (url.pathname === window.location.pathname && url.hash)) {
      return;
    }
    const box = link.getBoundingClientRect();

    try {
      sessionStorage.setItem(KEY, JSON.stringify({
        x: (box.left + box.width / 2) / window.innerWidth * 100,
        y: (box.top + box.height / 2) / window.innerHeight * 100,
        time: Date.now()
      }));
    } catch (error) {
      // Storage can be unavailable (private mode); the page then opens from the centre
    }
  }, true);
})();
