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

  // Phones: an iPhone-style paged Dock, 4 apps per page, with page dots
  const track = dock.querySelector('.dock-track');
  const dots = dock.querySelector('.dock-pages');
  const PER_PAGE = 4;

  function maxScroll() {
    return track.scrollWidth - track.clientWidth;
  }

  // The last page always shows 4 apps (the last 4), so it counts as the last page once scrolled to the end
  function currentPage() {
    if (!track) {
      return 0;
    }
    const pages = dots.children.length;

    if (pages > 1 && track.scrollLeft >= maxScroll() - 2) {
      return pages - 1;
    }
    return Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));
  }

  function pageLeft(page) {
    return Math.min(page * track.clientWidth, maxScroll());
  }

  function updateDots() {
    const page = currentPage();
    Array.prototype.forEach.call(dots.children, function (dot, index) {
      dot.setAttribute('aria-current', String(index === page));
    });
  }

  function buildPages() {
    if (!track || !dots) {
      return;
    }
    track.querySelectorAll('.dock-spacer').forEach(function (spacer) {
      spacer.remove();
    });
    dots.textContent = '';

    if (!narrow.matches) {
      dots.hidden = true;
      return;
    }
    const apps = items.filter(function (item) {
      return item.hasAttribute('data-app');
    });
    const pages = Math.ceil(apps.length / PER_PAGE);
    for (let page = 0; page < pages; page++) {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.setAttribute('aria-label', 'Apps, page ' + (page + 1) + ' of ' + pages);
      dot.addEventListener('click', function () {
        track.scrollTo({left: pageLeft(page), behavior: reduceMotion.matches ? 'auto' : 'smooth'});
      });
      dots.appendChild(dot);
    }
    dots.hidden = pages < 2;

    // Start on the page that holds the app that is open
    const current = dock.querySelector('.dock-item[aria-current="page"]');
    const index = current ? apps.indexOf(current) : -1;
    track.scrollLeft = index >= 0 ? pageLeft(Math.floor(index / PER_PAGE)) : 0;
    updateDots();
  }

  if (track && dots) {
    let pageFrame = null;
    track.addEventListener('scroll', function () {
      if (pageFrame === null) {
        pageFrame = requestAnimationFrame(function () {
          pageFrame = null;
          updateDots();
        });
      }
    }, {passive: true});
    narrow.addEventListener('change', buildPages);
    window.addEventListener('resize', function () {
      if (narrow.matches) {
        updateDots();
      }
    });
    buildPages();
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
    if (event.pointerType !== 'mouse' || !finePointer.matches || reduceMotion.matches || narrow.matches) {
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
