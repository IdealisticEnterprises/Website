(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const nav = document.getElementById('nav');

  if (nav) {
    const update = function () {
      nav.classList.toggle('scrolled', window.scrollY > 24);
    };
    window.addEventListener('scroll', update, {passive: true});
    update();
  }

  let scrollFrame = null;

  function stopScroll() {
    if (scrollFrame !== null) {
      cancelAnimationFrame(scrollFrame);
      scrollFrame = null;
    }
  }

  function smoothScrollTo(target) {
    stopScroll();
    const start = window.scrollY;
    const distance = target - start;

    if (reduceMotion || Math.abs(distance) < 2) {
      window.scrollTo(0, target);
      return;
    }
    const duration = Math.min(1100, Math.max(450, Math.abs(distance) * 0.4));
    const begin = performance.now();

    function frame(now) {
      const progress = Math.min(1, (now - begin) / duration);
      const eased = progress < 0.5 ? 4 * progress * progress * progress : 1 - Math.pow(-2 * progress + 2, 3) / 2;
      window.scrollTo(0, start + distance * eased);
      scrollFrame = progress < 1 ? requestAnimationFrame(frame) : null;
    }

    scrollFrame = requestAnimationFrame(frame);
  }

  ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function (name) {
    window.addEventListener(name, stopScroll, {passive: true});
  });

  document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href*="#"]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search || !url.hash || url.hash === '#') {
      return;
    }
    const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));

    if (!target) {
      return;
    }
    event.preventDefault();

    // The plan picker: pressing the plan that is already picked unselects it
    if (link.closest('.plan-picker') && link.getAttribute('aria-current') === 'true') {
      pickPlan('');
      return;
    }
    smoothScrollTo(Math.max(0, target.getBoundingClientRect().top + window.scrollY - 88));
    // Inside the panel, replace the address instead of adding a history step: closing the panel goes back in
    // history, and must not land on the previous plan instead
    if (window.self !== window.top) {
      history.replaceState(null, '', url.hash);
    } else {
      history.pushState(null, '', url.hash);
    }
    highlightPlan();
    // Inside the panel, the page around this one shows the new address (e.g. /club/#pro) so it can be shared
    window.dispatchEvent(new Event('urlchange'));
  });

  const plans = Array.from(document.querySelectorAll('.plan[id]'));

  function highlightPlan() {
    let wanted = '';

    try {
      wanted = decodeURIComponent(window.location.hash.slice(1)).toLowerCase();
    } catch (error) {
      wanted = '';
    }
    let found = false;
    plans.forEach(function (plan) {
      const active = plan.id === wanted;
      plan.classList.toggle('is-highlighted', active);
      found = found || active;
    });
    // Plans can be split over several groups (one-time purchases, subscriptions): dim the others in all of them
    document.querySelectorAll('.plans').forEach(function (container) {
      container.classList.toggle('has-highlight', found);
    });
    document.querySelectorAll('.plan-picker a').forEach(function (link) {
      link.setAttribute('aria-current', found && link.getAttribute('href') === '#' + wanted ? 'true' : 'false');
    });
  }

  if (plans.length) {
    window.addEventListener('hashchange', highlightPlan);
    highlightPlan();

    // Clicking a plan picks it, like the picker above (highlighted, and its address #pro, ... set); clicking it
    // again unselects it, and so does clicking anywhere else on the page
    document.addEventListener('click', function (event) {
      const selection = window.getSelection ? String(window.getSelection()) : '';

      if (event.defaultPrevented || event.button !== 0 || selection || event.target.closest('.plan-picker')) {
        return;
      }
      const plan = event.target.closest('.plans .plan[id]');

      if (!plan) {
        if (document.querySelector('.plans .plan.is-highlighted')) {
          pickPlan('');
        }
        return;
      }
      if (event.target.closest('a, button, input, select, textarea')) {
        return;
      }
      pickPlan(plan.classList.contains('is-highlighted') ? '' : plan.id);
    });
  }

  // Pick a plan (its id), or none (''): the highlight, and the address (#pro, ... or none) so it can be shared.
  // Inside the panel the address is replaced, not added, so closing the panel never steps back to a plan.
  function pickPlan(id) {
    const address = id ? '#' + id : window.location.pathname + window.location.search;

    if (window.self !== window.top) {
      history.replaceState(null, '', address);
    } else {
      history.pushState(null, '', address);
    }
    highlightPlan();
    window.dispatchEvent(new Event('urlchange'));
  }

  function animateDetails(details, opening) {
    const summary = details.querySelector(':scope > summary');
    const startHeight = details.offsetHeight;

    if (details._animation) {
      details._animation.cancel();
      details._animation = null;
    }
    details.classList.toggle('is-open', opening);

    if (reduceMotion || !details.animate) {
      details.open = opening;
      return;
    }
    const borders = details.offsetHeight - details.clientHeight;

    if (opening) {
      details.open = true;
    }
    const endHeight = opening ? details.offsetHeight : summary.offsetHeight + borders;
    details.style.overflow = 'hidden';
    details._animation = details.animate(
      {height: [startHeight + 'px', endHeight + 'px']},
      {duration: opening ? 480 : 380, easing: 'cubic-bezier(0.22, 1, 0.36, 1)'}
    );
    details._animation.onfinish = function () {
      details.open = opening;
      details._animation = null;
      details.style.overflow = '';
    };
    details._animation.oncancel = function () {
      details.style.overflow = '';
    };
  }

  // Home: the letters of the big iDEALISTIC® rise towards the mouse as it passes over them (like the Dock),
  // then settle back when it leaves. Mouse only, and not with reduced motion.
  const homeBrand = document.querySelector('.home-brand .brand-word');

  if (homeBrand && !reduceMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    Array.from(homeBrand.childNodes).forEach(function (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        const letters = document.createDocumentFragment();
        Array.from(node.textContent).forEach(function (character) {
          const letter = document.createElement('span');
          letter.className = 'home-letter';
          letter.textContent = character;
          letters.appendChild(letter);
        });
        homeBrand.replaceChild(letters, node);
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        node.classList.add('home-letter');
      }
    });
    const letters = Array.from(homeBrand.querySelectorAll('.home-letter'));
    const heading = homeBrand.closest('.home-brand');
    heading.setAttribute('aria-label', homeBrand.textContent);
    let pointer = null;
    let letterFrame = null;

    const lift = function () {
      letterFrame = null;
      const size = parseFloat(getComputedStyle(heading).fontSize) || 100;
      const range = size * 1.1;

      letters.forEach(function (letter) {
        if (pointer === null) {
          letter.style.transform = '';
          return;
        }
        const box = letter.getBoundingClientRect();
        const distance = Math.abs(pointer - (box.left + box.width / 2));
        const near = Math.cos(Math.min(distance / range, 1) * Math.PI / 2);
        letter.style.transform = near > 0.01
          ? 'translateY(' + (-0.12 * near).toFixed(3) + 'em) scale(' + (1 + 0.045 * near).toFixed(3) + ')'
          : '';
      });
    };
    const soon = function () {
      if (letterFrame === null) {
        letterFrame = requestAnimationFrame(lift);
      }
    };
    heading.addEventListener('pointermove', function (event) {
      if (event.pointerType === 'mouse') {
        pointer = event.clientX;
        soon();
      }
    });
    heading.addEventListener('pointerleave', function () {
      pointer = null;
      soon();
    });
  }

  // Home: the platforms doremi.chat is coming to, one after another, over and over
  document.querySelectorAll('[data-rotate]').forEach(function (rotator) {
    const items = Array.from(rotator.children);
    let index = 0;

    if (items.length < 2) {
      return;
    }
    setInterval(function () {
      if (document.hidden) {
        return;
      }
      const previous = items[index];
      index = (index + 1) % items.length;
      const next = items[index];

      // The next one starts below, without animating there, then slides up in as the previous one slides out
      next.style.transition = 'none';
      next.classList.remove('is-leaving');
      void next.offsetWidth;
      next.style.transition = '';
      previous.classList.remove('is-current');
      previous.classList.add('is-leaving');
      next.classList.add('is-current');
    }, 2400);
  });

  document.querySelectorAll('details[data-accordion]').forEach(function (details) {
    const summary = details.querySelector(':scope > summary');
    const group = details.getAttribute('data-accordion');

    summary.addEventListener('click', function (event) {
      event.preventDefault();
      const opening = !details.classList.contains('is-open');

      if (opening && group) {
        document.querySelectorAll('details[data-accordion="' + group + '"].is-open').forEach(function (other) {
          if (other !== details) {
            animateDetails(other, false);
          }
        });
      }
      animateDetails(details, opening);
    });
  });
})();
