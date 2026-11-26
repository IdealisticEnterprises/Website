// Hints (the small square "i"): hover or focus shows the explanation with CSS alone. This adds taps (touch screens
// have no hover: a tap opens it, a tap elsewhere or Escape closes it) and keeps the bubble inside the screen.
(function () {
  'use strict';

  function place(hint) {
    const tip = hint.querySelector('.hint-tip');
    if (!tip) {
      return;
    }
    hint.style.setProperty('--shift', '0px');
    hint.classList.remove('is-below');
    const box = hint.getBoundingClientRect();
    // Not enough room above: show it below
    if (box.top - tip.offsetHeight - 16 < 0) {
      hint.classList.add('is-below');
    }
    const half = tip.offsetWidth / 2;
    const centre = box.left + box.width / 2;
    const gutter = 12;
    let shift = 0;
    if (centre - half < gutter) {
      shift = gutter - (centre - half);
    } else if (centre + half > window.innerWidth - gutter) {
      shift = window.innerWidth - gutter - (centre + half);
    }
    hint.style.setProperty('--shift', Math.round(shift) + 'px');
  }

  function closeAll(except) {
    document.querySelectorAll('.hint.is-open').forEach(function (hint) {
      if (hint !== except) {
        hint.classList.remove('is-open');
        hint.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.addEventListener('pointerover', function (event) {
    const hint = event.target.closest ? event.target.closest('.hint') : null;
    if (hint) {
      place(hint);
    }
  });

  document.addEventListener('focusin', function (event) {
    const hint = event.target.closest ? event.target.closest('.hint') : null;
    if (hint) {
      place(hint);
    }
  });

  document.addEventListener('click', function (event) {
    const hint = event.target.closest ? event.target.closest('.hint') : null;
    if (!hint) {
      closeAll(null);
      return;
    }
    // A hint inside a link or a card that opens something: explain, don't open
    event.preventDefault();
    event.stopPropagation();
    const open = !hint.classList.contains('is-open');
    closeAll(hint);
    if (open) {
      place(hint);
    }
    hint.classList.toggle('is-open', open);
    hint.setAttribute('aria-expanded', open ? 'true' : 'false');
  }, true);

  // Escape closes an open hint first (and only that: not the app around it too)
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && document.querySelector('.hint.is-open')) {
      event.stopPropagation();
      closeAll(null);
    }
  }, true);

  window.addEventListener('scroll', function () {
    closeAll(null);
  }, {passive: true});
})();
