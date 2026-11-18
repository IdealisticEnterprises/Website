(function () {
  'use strict';

  const root = document.documentElement;
  const dock = document.getElementById('dock');
  const island = document.getElementById('nav');

  if (!dock) {
    return;
  }
  const KEY = 'dock-position';
  const toggle = dock.querySelector('.dock-toggle');
  const toggleLabel = toggle ? toggle.querySelector('.dock-label') : null;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  const home = document.createComment('dock');
  const items = Array.from(dock.querySelectorAll('.dock-item'));
  let pointerX = null;
  let frame = null;

  // Remember where the Dock lives at the bottom, so it can return there
  dock.parentNode.insertBefore(home, dock);

  function place(position) {
    if (position === 'top' && island) {
      root.setAttribute('data-dock', 'top');
      island.insertBefore(dock, island.querySelector('.button'));
    } else {
      root.removeAttribute('data-dock');
      home.parentNode.insertBefore(dock, home.nextSibling);
    }
    if (toggle) {
      const top = position === 'top';
      toggle.setAttribute('aria-label', top ? 'Move the Dock to the bottom' : 'Move the Dock to the top');
      toggle.setAttribute('aria-pressed', String(top));

      if (toggleLabel) {
        toggleLabel.textContent = top ? 'Move to bottom' : 'Move to top';
      }
    }
    pointerX = null;
    apply();
    // Let the island re-check its compact state (it stays full width while it holds the Dock)
    window.dispatchEvent(new Event('scroll'));
  }

  let saved = 'bottom';

  try {
    saved = localStorage.getItem(KEY) === 'top' ? 'top' : 'bottom';
  } catch (error) {
    saved = 'bottom';
  }
  place(saved);

  if (toggle) {
    toggle.addEventListener('click', function () {
      const next = root.getAttribute('data-dock') === 'top' ? 'bottom' : 'top';

      try {
        localStorage.setItem(KEY, next);
      } catch (error) {
        // Not remembered in this browser, but the move still happens
      }
      move(next);
    });
  }

  // Animated move: every icon flies from its old place to its new one, one after another (FLIP),
  // while the shelf fades in (moving to the bottom) or the icons settle into the island (moving to the top)
  function move(next) {
    if (reduceMotion.matches || typeof Element.prototype.animate !== 'function') {
      place(next);
      return;
    }
    const before = items.map(function (item) {
      return item.getBoundingClientRect();
    });
    place(next);
    const after = items.map(function (item) {
      return item.getBoundingClientRect();
    });
    const lift = next === 'top' ? -28 : 28;

    items.forEach(function (item, index) {
      const from = before[index];
      const to = after[index];

      if (!to.width) {
        return;
      }
      const dx = from.left - to.left;
      const dy = from.top - to.top;
      const scale = from.width / to.width;
      item.animate([
        {transform: 'translate(' + dx + 'px, ' + dy + 'px) scale(' + scale + ')', transformOrigin: '0 0'},
        {transform: 'translate(' + dx * 0.35 + 'px, ' + (dy * 0.35 + lift) + 'px) scale(' + (1 + scale) / 2 + ')', transformOrigin: '0 0', offset: 0.6},
        {transform: 'none', transformOrigin: '0 0'}
      ], {
        duration: 620,
        delay: index * 32,
        easing: 'cubic-bezier(0.32, 0.72, 0, 1)',
        fill: 'backwards'
      });
    });

    if (next === 'bottom') {
      dock.animate([
        {backgroundColor: 'rgba(28, 30, 38, 0)', borderColor: 'rgba(255, 255, 255, 0)', boxShadow: '0 0 0 rgba(0, 0, 0, 0)'},
        {}
      ], {duration: 520, delay: 120, easing: 'ease', fill: 'backwards'});
    }
  }

  // macOS Dock magnification: with a mouse, icons grow towards the pointer
  function apply() {
    frame = null;
    const top = root.getAttribute('data-dock') === 'top';
    const maxScale = top ? 1.35 : 1.6;
    const range = top ? 110 : 150;

    items.forEach(function (item) {
      let scale = 1;

      if (pointerX !== null) {
        const box = item.getBoundingClientRect();
        const distance = Math.abs(pointerX - (box.left + box.width / 2));
        scale = 1 + (maxScale - 1) * Math.cos(Math.min(distance / range, 1) * Math.PI / 2);
      }
      item.style.setProperty('--dock-scale', scale.toFixed(3));
    });
  }

  function schedule() {
    if (frame === null) {
      frame = requestAnimationFrame(apply);
    }
  }

  dock.addEventListener('pointermove', function (event) {
    if (event.pointerType !== 'mouse' || !finePointer.matches || reduceMotion.matches) {
      return;
    }
    pointerX = event.clientX;
    schedule();
  });

  dock.addEventListener('pointerleave', function () {
    pointerX = null;
    schedule();
  });
})();
