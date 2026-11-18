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

  // Grow out of a small pill on the first page of a visit, only when starting at the top.
  // Later pages keep the island steady, since the page transition morphs it already.
  let firstPage = true;

  try {
    firstPage = !sessionStorage.getItem('island-shown');
    sessionStorage.setItem('island-shown', '1');
  } catch (error) {
    firstPage = true;
  }

  if (firstPage
    && window.scrollY <= COMPACT_AFTER
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const endIntro = function () {
      nav.classList.remove('island-intro');
    };
    nav.classList.add('island-intro');
    nav.addEventListener('animationend', function (event) {
      if (event.target === nav) {
        endIntro();
      }
    });
    // The intro clips the island's contents, so never let it linger if animationend is missed
    setTimeout(endIntro, 1400);
  }
  update();

  window.addEventListener('scroll', function () {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  }, {passive: true});
})();
