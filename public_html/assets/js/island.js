(function () {
  'use strict';

  const nav = document.getElementById('nav');

  if (!nav) {
    return;
  }
  const COMPACT_AFTER = 80;
  let ticking = false;

  function update() {
    ticking = false;
    // While the island holds the Dock (dock at the top) it stays full width so the icons fit
    nav.classList.toggle('is-compact',
      window.scrollY > COMPACT_AFTER && document.documentElement.getAttribute('data-dock') !== 'top');
  }

  update();

  window.addEventListener('scroll', function () {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  }, {passive: true});
})();
