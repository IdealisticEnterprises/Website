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

  const navTry = document.getElementById('nav-try');
  const tryButtons = document.querySelectorAll('[data-try]');

  if (navTry && tryButtons.length && 'IntersectionObserver' in window) {
    const visible = new Set();
    const tryObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          visible.add(entry.target);
        } else {
          visible.delete(entry.target);
        }
      });
      navTry.classList.toggle('is-hidden', visible.size > 0);
    }, {rootMargin: '-72px 0px 0px 0px'});
    tryButtons.forEach(function (button) {
      tryObserver.observe(button);
    });
  }

  document.querySelectorAll('[data-paddle-price]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!window.Paddle || !window.Paddle.Checkout) {
        window.location.href = button.getAttribute('data-fallback');
        return;
      }
      window.Paddle.Checkout.open({items: [{priceId: button.getAttribute('data-paddle-price'), quantity: 1}]});
    });
  });

  const revealItems = document.querySelectorAll('.reveal');

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          observer.unobserve(entry.target);
        }
      });
    }, {threshold: 0.08});
    revealItems.forEach(function (item) {
      observer.observe(item);
    });
  } else {
    revealItems.forEach(function (item) {
      item.classList.add('in');
    });
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
    smoothScrollTo(Math.max(0, target.getBoundingClientRect().top + window.scrollY - 88));
    history.pushState(null, '', url.hash);
    highlightPlan();
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
  }

  document.querySelectorAll('[data-tabs]').forEach(function (root) {
    const tabs = Array.from(root.querySelectorAll('[role="tab"]'));
    const panels = Array.from(root.querySelectorAll('[role="tabpanel"]'));

    function select(tab, focus) {
      tabs.forEach(function (other, index) {
        const active = other === tab;
        other.setAttribute('aria-selected', active ? 'true' : 'false');
        other.tabIndex = active ? 0 : -1;

        if (active) {
          panels[index].removeAttribute('data-inactive');
        } else {
          panels[index].setAttribute('data-inactive', '');
        }
      });

      if (focus) {
        tab.focus();
      }
    }

    tabs.forEach(function (tab, index) {
      tab.tabIndex = index === 0 ? 0 : -1;
      tab.addEventListener('click', function () {
        select(tab, false);
      });
      tab.addEventListener('keydown', function (event) {
        let next = null;

        if (event.key === 'ArrowRight') {
          next = tabs[(index + 1) % tabs.length];
        } else if (event.key === 'ArrowLeft') {
          next = tabs[(index - 1 + tabs.length) % tabs.length];
        } else if (event.key === 'Home') {
          next = tabs[0];
        } else if (event.key === 'End') {
          next = tabs[tabs.length - 1];
        }

        if (next) {
          event.preventDefault();
          select(next, true);
        }
      });
    });

    const hash = window.location.hash.replace('#', '');
    const wanted = hash ? root.querySelector('#tab-' + hash) : null;

    if (wanted) {
      select(wanted, false);
    }
  });

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
