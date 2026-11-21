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
  const narrow = window.matchMedia('(max-width: 820px)');
  let pointerX = null;
  let frame = null;

  // Remember where the Dock lives at the bottom, so it can return there
  dock.parentNode.insertBefore(home, dock);

  function place(position) {
    // Phones and portrait tablets always keep the links in the menu list, never inside the island
    if (position === 'top' && island && !narrow.matches) {
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
  // While the Dock moves (and until the mouse moves again afterwards), no app shows its name: the icons fly past
  // the pointer, which would otherwise count as hovering them
  let quietTimer = null;

  function quietLabels(duration) {
    dock.classList.add('is-moving');
    clearTimeout(quietTimer);
    quietTimer = setTimeout(function () {
      const wake = function () {
        dock.classList.remove('is-moving');
        document.removeEventListener('pointermove', wake);
      };
      document.addEventListener('pointermove', wake);
    }, duration);
  }

  function move(next) {
    if (reduceMotion.matches || typeof Element.prototype.animate !== 'function') {
      quietLabels(0);
      place(next);
      return;
    }
    quietLabels(620 + items.length * 32 + 80);
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

  // Menu list (phones and portrait tablets): the button in the island opens the links in the middle of the screen
  const menuButton = document.querySelector('.island-menu');
  const backdrop = document.createElement('div');
  backdrop.className = 'menu-backdrop';
  document.body.appendChild(backdrop);
  items.forEach(function (item, index) {
    item.style.setProperty('--i', index);
  });

  function setMenu(open) {
    root.classList.toggle('menu-open', open);

    if (menuButton) {
      menuButton.setAttribute('aria-expanded', String(open));
      menuButton.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    if (open) {
      const current = dock.querySelector('[aria-current="page"]') || items[0];
      setTimeout(function () {
        current.focus({preventScroll: true});
      }, 50);
    }
  }

  if (menuButton) {
    menuButton.addEventListener('click', function () {
      setMenu(!root.classList.contains('menu-open'));
    });
  }
  backdrop.addEventListener('click', function () {
    setMenu(false);
  });
  items.forEach(function (item) {
    item.addEventListener('click', function () {
      if (root.classList.contains('menu-open')) {
        setTimeout(function () {
          setMenu(false);
        }, 150);
      }
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && root.classList.contains('menu-open')) {
      setMenu(false);
      if (menuButton) {
        menuButton.focus();
      }
    }
  });

  // Rotating a tablet or resizing a window across the breakpoint: put the Dock back where it belongs
  narrow.addEventListener('change', function () {
    setMenu(false);
    let saved = 'bottom';

    try {
      saved = localStorage.getItem(KEY) === 'top' ? 'top' : 'bottom';
    } catch (error) {
      saved = 'bottom';
    }
    place(saved);
  });

  // Phones: 4 apps at a time, a swipe moves 4 further (the last swipe shows the last 4). The apps beyond the
  // edges peek in, blurred, only on the side where there are more: at first only on the right.
  const track = dock.querySelector('.dock-track');
  const PER_PAGE = 4;

  function maxScroll() {
    return track.scrollWidth - track.clientWidth;
  }

  function pageLeft(page) {
    const first = track.querySelector('.dock-item[data-app]');
    const width = first ? first.getBoundingClientRect().width * PER_PAGE : track.clientWidth;
    return Math.min(page * width, maxScroll());
  }

  function updateEdges() {
    const phone = narrow.matches;
    dock.classList.toggle('more-left', phone && track.scrollLeft > 2);
    dock.classList.toggle('more-right', phone && track.scrollLeft < maxScroll() - 2);

    // The apps outside the 4 in full view are the blurred ones peeking in. The blur follows the finger: it grows
    // gradually as an app slides out past the edge of the 4 in full view, and fades as it slides back in.
    const box = track.getBoundingClientRect();
    const peek = parseFloat(getComputedStyle(track).paddingLeft) || 0;
    const inLeft = box.left + peek;
    const inRight = box.right - peek;
    items.forEach(function (item) {
      if (!item.hasAttribute('data-app')) {
        return;
      }
      const r = item.getBoundingClientRect();
      const outside = phone && r.width > 0
        ? Math.max(0, inLeft - r.left) + Math.max(0, r.right - inRight) : 0;
      const amount = Math.min(1, outside / (r.width * 0.75));
      if (amount > 0.01) {
        item.classList.add('is-peek');
        item.style.setProperty('--peek-amount', amount.toFixed(3));
      } else {
        item.classList.remove('is-peek');
        item.style.removeProperty('--peek-amount');
      }
    });
  }

  function buildPages() {
    if (!track) {
      return;
    }
    // Start on the 4 that hold the app that is open
    if (narrow.matches) {
      const apps = items.filter(function (item) {
        return item.hasAttribute('data-app');
      });
      const current = dock.querySelector('.dock-item[aria-current="page"]');
      const index = current ? apps.indexOf(current) : -1;
      track.scrollLeft = index >= 0 ? pageLeft(Math.floor(index / PER_PAGE)) : 0;
    }
    updateEdges();
  }

  if (track) {
    let edgeFrame = null;
    track.addEventListener('scroll', function () {
      if (edgeFrame === null) {
        edgeFrame = requestAnimationFrame(function () {
          edgeFrame = null;
          updateEdges();
        });
      }
    }, {passive: true});
    narrow.addEventListener('change', buildPages);
    window.addEventListener('resize', updateEdges);
    buildPages();
  }

  // macOS Dock magnification: with a mouse, icons grow towards the pointer. Only at the bottom: in the island
  // (Dock moved to the top) the apps keep their size and simply show their name on hover.
  function apply() {
    frame = null;
    const top = root.getAttribute('data-dock') === 'top';
    const maxScale = 1.6;
    const range = 150;

    items.forEach(function (item) {
      let scale = 1;

      // The Move to top / bottom button never grows: on hover it only shows its name
      if (pointerX !== null && !top && !item.classList.contains('dock-toggle')) {
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
    if (event.pointerType !== 'mouse' || !finePointer.matches || reduceMotion.matches || narrow.matches) {
      return;
    }
    // On the Move to top / bottom button (or the line before it), the apps stay their normal size
    pointerX = event.target.closest('.dock-toggle, .dock-separator') ? null : event.clientX;
    schedule();
  });

  dock.addEventListener('pointerleave', function () {
    pointerX = null;
    schedule();
  });
})();
