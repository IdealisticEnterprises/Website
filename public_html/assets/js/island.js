(function () {
  'use strict';

  const nav = document.getElementById('nav');

  if (!nav) {
    return;
  }
  const COMPACT_AFTER = 80;
  let ticking = false;

  // The narrowest the island can be while it holds the Dock (dock at the top): the apps in the middle, with room
  // for the name on the left and the theme button on the right (the Dock stays centred, so both sides get the
  // larger of the two)
  function fit() {
    const dock = nav.querySelector('.dock');
    const brand = nav.querySelector('.brand');
    const toggle = nav.querySelector('.theme-toggle');

    if (!dock || !brand || !toggle) {
      return 0;
    }
    const style = getComputedStyle(nav);
    const left = parseFloat(style.paddingLeft) + brand.offsetWidth + 24;
    const right = parseFloat(style.paddingRight) + toggle.offsetWidth + 24;
    return Math.ceil(dock.offsetWidth + 2 * Math.max(left, right) + 2);
  }

  function update() {
    ticking = false;
    // Once the page is scrolled the island shrinks: to a small pill, or, while it holds the Dock, as far as
    // its apps allow
    if (document.documentElement.getAttribute('data-dock') === 'top') {
      const width = fit();

      if (width) {
        nav.style.setProperty('--island-fit', width + 'px');
      }
    }
    nav.classList.toggle('is-compact', window.scrollY > COMPACT_AFTER);
  }

  update();

  // The Dock moving into the island or back, and a resized window, change how far it can shrink
  new MutationObserver(function () {
    requestAnimationFrame(update);
  }).observe(document.documentElement, {attributes: true, attributeFilter: ['data-dock']});
  window.addEventListener('resize', function () {
    requestAnimationFrame(update);
  });

  window.addEventListener('scroll', function () {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  }, {passive: true});
})();
