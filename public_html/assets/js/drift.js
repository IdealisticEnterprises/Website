(function () {
  'use strict';

  /* The shapes of Home are Home itself. There are none until an app opens: then whatever is on screen (the brand,
     its lines, the news; never the footer, the Dock or the island) breaks into random pieces that burst apart and
     keep floating in every direction behind the app (apps are see-through), like stars across the night sky. Pieces
     bounce off the edges of the screen and off each other, breaking into smaller ones when they hit hard enough
     (until they are small). Everything loses energy over time until it is still; then nothing moves or runs any
     more. Closing the app plays it all back quickly in reverse, a rewind, until Home is whole again.

     Each piece is a copy of its block, cut to a polygon with clip-path (in the block's own coordinates), and moved
     with a transform around its middle, so putting it back is just going back to no transform. */

  const root = document.documentElement;
  const still = window.matchMedia('(prefers-reduced-motion: reduce)');
  // More pieces, breaking more easily (the user's wish): up to 90 at once, down to small fragments
  const MAX_PIECES = 90;
  const MIN_AREA = 900;
  let layer = null;
  let pieces = [];
  let frame = null;
  let last = 0;
  let broken = false;
  // Everything that happened, for the rewind: every piece ever made (pieces that broke are hidden, not removed) and
  // snapshots of all of them, a few times a second
  let made = [];
  let history = [];
  let lastShot = 0;
  let rewind = null;
  let shotEvery = 50;
  // At most this many snapshots are kept: when full, every other one is dropped and they are taken half as often,
  // so the rewind still covers everything with little memory
  const MAX_SHOTS = 240;
  // Energy: the pieces' cruising speed fades with time (ENERGY_TIME seconds to lose about two thirds of it). Hits
  // slower than BREAK_SPEED (px/s) only bounce. Below STILL_SPEED and STILL_SPIN everything has come to rest.
  const ENERGY_TIME = 4;
  const BREAK_SPEED = 18;
  const STILL_SPEED = 1.5;
  const STILL_SPIN = 0.02;
  let elapsed = 0;

  function random(min, max) {
    return min + Math.random() * (max - min);
  }

  function blocks() {
    return Array.prototype.slice.call(document.querySelectorAll('.home-center, .home-news > .wrap'));
  }

  // ---------- Polygons (arrays of [x, y]) ----------

  // Keep the part of a convex polygon on one side of a line: points p with nx * x + ny * y <= d
  function cut(poly, nx, ny, d) {
    const out = [];

    for (let i = 0; i < poly.length; i++) {
      const a = poly[i];
      const b = poly[(i + 1) % poly.length];
      const da = nx * a[0] + ny * a[1] - d;
      const db = nx * b[0] + ny * b[1] - d;

      if (da <= 0) {
        out.push(a);
      }
      if ((da < 0 && db > 0) || (da > 0 && db < 0)) {
        const t = da / (da - db);
        out.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
      }
    }
    return out;
  }

  function measure(poly) {
    let area = 0;
    let x = 0;
    let y = 0;

    for (let i = 0; i < poly.length; i++) {
      const a = poly[i];
      const b = poly[(i + 1) % poly.length];
      const cross = a[0] * b[1] - b[0] * a[1];
      area += cross;
      x += (a[0] + b[0]) * cross;
      y += (a[1] + b[1]) * cross;
    }
    area /= 2;

    if (Math.abs(area) < 1) {
      return {area: 0, cx: poly[0][0], cy: poly[0][1], r: 0};
    }
    const cx = x / (6 * area);
    const cy = y / (6 * area);
    let r = 0;
    poly.forEach(function (p) {
      r = Math.max(r, Math.hypot(p[0] - cx, p[1] - cy));
    });
    return {area: Math.abs(area), cx: cx, cy: cy, r: r};
  }

  // ---------- Pieces ----------

  // A copy of the block inside a shallow copy of its parent (so styles that depend on the parent still apply)
  function makePiece(source, poly, x, y, turn, vx, vy, spin) {
    const shape = measure(poly);
    const shell = source.shell.cloneNode(false);
    const copy = source.block.cloneNode(true);

    shell.removeAttribute('id');
    copy.removeAttribute('id');
    copy.querySelectorAll('[id]').forEach(function (node) {
      node.removeAttribute('id');
    });
    shell.classList.add('home-shard');
    shell.style.left = source.box.left + 'px';
    shell.style.top = source.box.top + 'px';
    shell.style.width = source.box.width + 'px';
    shell.style.height = source.box.height + 'px';
    shell.style.clipPath = 'polygon(' + poly.map(function (p) {
      return p[0].toFixed(1) + 'px ' + p[1].toFixed(1) + 'px';
    }).join(', ') + ')';
    shell.style.transformOrigin = shape.cx.toFixed(1) + 'px ' + shape.cy.toFixed(1) + 'px';
    copy.style.width = source.box.width + 'px';
    copy.style.height = source.box.height + 'px';
    copy.style.margin = '0';
    shell.appendChild(copy);
    layer.appendChild(shell);

    const piece = {
      el: shell,
      source: source,
      poly: poly,
      area: shape.area,
      cx: shape.cx,
      cy: shape.cy,
      r: shape.r,
      x: x,
      y: y,
      turn: turn,
      vx: vx,
      vy: vy,
      spin: spin,
      // After the burst, each piece settles to its own cruising speed
      cruise: random(22, 70),
      calm: 0.5,
      opacity: 1,
      alive: true
    };
    made.push(piece);
    return piece;
  }

  function place(piece) {
    piece.el.style.transform = 'translate(' + piece.x.toFixed(1) + 'px, ' + piece.y.toFixed(1) + 'px) rotate('
      + piece.turn.toFixed(4) + 'rad)';
    piece.el.style.opacity = piece.opacity.toFixed(3);
  }

  // Where the middle of a piece is on screen
  function centre(piece) {
    return [piece.source.box.left + piece.cx + piece.x, piece.source.box.top + piece.cy + piece.y];
  }

  // Break a piece in two along a random line through its middle; the halves push apart
  function split(piece) {
    if (pieces.length >= MAX_PIECES || piece.area < MIN_AREA) {
      return false;
    }
    const angle = random(0, Math.PI);
    const nx = Math.cos(angle);
    const ny = Math.sin(angle);
    const d = nx * piece.cx + ny * piece.cy;
    const halves = [cut(piece.poly, nx, ny, d), cut(piece.poly, -nx, -ny, -d)];

    if (halves[0].length < 3 || halves[1].length < 3) {
      return false;
    }
    piece.alive = false;
    piece.el.style.visibility = 'hidden';
    pieces.splice(pieces.indexOf(piece), 1);
    halves.forEach(function (poly, side) {
      const push = (side ? 1 : -1) * random(35, 80);
      const c = Math.cos(piece.turn);
      const s = Math.sin(piece.turn);
      const child = makePiece(piece.source, poly, piece.x, piece.y, piece.turn,
        piece.vx + (c * nx - s * ny) * push, piece.vy + (s * nx + c * ny) * push, piece.spin + random(-0.8, 0.8));
      // The parent turned around its own middle: move the half so it stays exactly where it was on screen
      const dx = child.cx - piece.cx;
      const dy = child.cy - piece.cy;
      child.x += (c * dx - s * dy) - dx;
      child.y += (s * dx + c * dy) - dy;
      child.opacity = piece.opacity;
      child.calm = 0.35;
      place(child);
      pieces.push(child);
    });
    return true;
  }

  function step(now) {
    const dt = last ? Math.min(0.05, (now - last) / 1000) : 0;
    last = now;
    const width = window.innerWidth;
    const height = window.innerHeight;
    elapsed += dt;
    const energy = Math.exp(-elapsed / ENERGY_TIME);

    pieces.forEach(function (piece) {
      // The burst slows down to the piece's own cruising speed, which itself fades as the energy runs out
      const speed = Math.hypot(piece.vx, piece.vy) || 1;
      const cruise = piece.cruise * energy;
      const target = cruise + (speed - cruise) * Math.pow(0.35, dt);
      piece.vx *= target / speed;
      piece.vy *= target / speed;
      piece.spin *= Math.pow(0.6, dt);
      piece.x += piece.vx * dt;
      piece.y += piece.vy * dt;
      piece.turn += piece.spin * dt;
      piece.calm = Math.max(0, piece.calm - dt);
      // They fade back a little, so they never compete with the app over them
      piece.opacity += (0.4 - piece.opacity) * Math.min(1, dt * 1.6);
    });

    // Off an edge of the screen: bounce back, and break
    pieces.slice().forEach(function (piece) {
      const at = centre(piece);
      const reach = piece.r * 0.45;
      let bounced = false;

      if ((at[0] < reach && piece.vx < 0) || (at[0] > width - reach && piece.vx > 0)) {
        piece.vx = -piece.vx;
        bounced = true;
      }
      if ((at[1] < reach && piece.vy < 0) || (at[1] > height - reach && piece.vy > 0)) {
        piece.vy = -piece.vy;
        bounced = true;
      }
      if (bounced && piece.calm <= 0 && Math.hypot(piece.vx, piece.vy) > BREAK_SPEED) {
        piece.calm = 0.3;
        split(piece);
      }
    });

    // Two pieces meeting break each other (small ones bounce off instead)
    let hit = true;
    while (hit) {
      hit = false;
      for (let i = 0; i < pieces.length && !hit; i++) {
        for (let j = i + 1; j < pieces.length && !hit; j++) {
          const a = pieces[i];
          const b = pieces[j];

          if (a.calm > 0 || b.calm > 0) {
            continue;
          }
          const pa = centre(a);
          const pb = centre(b);
          const dx = pb[0] - pa[0];
          const dy = pb[1] - pa[1];
          const distance = Math.hypot(dx, dy) || 1;

          if (distance < (a.r + b.r) * 0.55) {
            const nx = dx / distance;
            const ny = dy / distance;
            // Bounce: swap their speeds along the line between them
            const along = (b.vx - a.vx) * nx + (b.vy - a.vy) * ny;
            if (along < 0) {
              a.vx += along * nx;
              a.vy += along * ny;
              b.vx -= along * nx;
              b.vy -= along * ny;
            }
            a.calm = 0.3;
            b.calm = 0.3;
            // Only a hard enough hit breaks them
            if (-along > BREAK_SPEED) {
              split(a);
              split(b);
            }
            hit = true;
          }
        }
      }
    }

    pieces.forEach(place);

    if (now - lastShot >= shotEvery) {
      lastShot = now;
      snapshot();
    }

    // Everything at rest: keep the last picture and stop
    const still = pieces.every(function (piece) {
      return Math.hypot(piece.vx, piece.vy) < STILL_SPEED && Math.abs(piece.spin) < STILL_SPIN
        && Math.abs(piece.opacity - 0.4) < 0.01;
    });

    if (still) {
      snapshot();
      frame = null;
      return;
    }
    frame = window.requestAnimationFrame(step);
  }

  // ---------- Rewind ----------

  function snapshot() {
    const state = new Float32Array(made.length * 4);
    made.forEach(function (piece, i) {
      state[i * 4] = piece.x;
      state[i * 4 + 1] = piece.y;
      state[i * 4 + 2] = piece.turn;
      state[i * 4 + 3] = piece.alive ? piece.opacity : -1;
    });
    history.push(state);

    if (history.length > MAX_SHOTS) {
      history = history.filter(function (shot, i) {
        return i % 2 === 0 || i === history.length - 1;
      });
      shotEvery *= 2;
    }
  }

  function show(state) {
    made.forEach(function (piece, i) {
      if (i * 4 >= state.length || state[i * 4 + 3] < 0) {
        piece.el.style.visibility = 'hidden';
        return;
      }
      piece.el.style.visibility = '';
      piece.el.style.transform = 'translate(' + state[i * 4].toFixed(1) + 'px, ' + state[i * 4 + 1].toFixed(1) + 'px) rotate('
        + state[i * 4 + 2].toFixed(4) + 'rad)';
      piece.el.style.opacity = state[i * 4 + 3].toFixed(3);
    });
  }

  // Play everything back to the moment it broke, quickly (the longer it ran, the faster), easing in and out
  function playBack(done) {
    snapshot();
    const shots = history;
    const duration = Math.min(1000, 450 + shots.length * 4);
    const begin = performance.now();

    function back(now) {
      const t = Math.min(1, (now - begin) / duration);
      const eased = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
      show(shots[Math.round((1 - eased) * (shots.length - 1))]);

      if (t < 1) {
        rewind = window.requestAnimationFrame(back);
      } else {
        rewind = null;
        done();
      }
    }
    rewind = window.requestAnimationFrame(back);
  }

  // ---------- Opening and closing ----------

  function clear() {
    if (frame !== null) {
      window.cancelAnimationFrame(frame);
    }
    frame = null;
    last = 0;
    if (rewind !== null) {
      window.cancelAnimationFrame(rewind);
    }
    rewind = null;
    pieces = [];
    made = [];
    history = [];
    lastShot = 0;
    shotEvery = 50;
    elapsed = 0;
    if (layer) {
      layer.remove();
    }
    layer = null;
  }

  function breakApart() {
    if (broken) {
      return;
    }
    broken = true;
    clear();
    root.classList.add('home-broken');

    if (still.matches) {
      return;
    }
    layer = document.createElement('div');
    layer.className = 'home-shards';
    layer.setAttribute('aria-hidden', 'true');
    document.body.appendChild(layer);

    blocks().forEach(function (block) {
      const box = block.getBoundingClientRect();

      if (box.bottom <= 0 || box.top >= window.innerHeight || !box.width || !box.height) {
        return;
      }
      const source = {block: block, shell: block.parentNode, box: box};
      // Wedges around a random point near the middle of the block, cut to the block
      const cx = box.width * random(0.35, 0.65);
      const cy = box.height * random(0.35, 0.65);
      const reach = Math.hypot(box.width, box.height) * 2;
      const count = 10 + Math.floor(Math.random() * 6);
      const start = random(0, Math.PI * 2);
      const cuts = [];

      for (let i = 0; i <= count; i++) {
        cuts.push(start + (i + (i && i < count ? random(-0.3, 0.3) : 0)) / count * Math.PI * 2);
      }
      for (let i = 0; i < count; i++) {
        const from = cuts[i];
        const to = cuts[i + 1];
        const middle = (from + to) / 2;
        let poly = [[cx, cy]];
        [from, middle, to].forEach(function (angle) {
          poly.push([cx + Math.cos(angle) * reach, cy + Math.sin(angle) * reach]);
        });
        poly = cut(poly, -1, 0, 0);
        poly = cut(poly, 1, 0, box.width);
        poly = cut(poly, 0, -1, 0);
        poly = cut(poly, 0, 1, box.height);

        if (poly.length < 3) {
          continue;
        }
        // Like glass, each wedge also cracks across, into up to three rings (each crack at its own distance): the
        // outer parts fly off faster than the inner ones
        const nx = Math.cos(middle);
        const ny = Math.sin(middle);
        const base = nx * cx + ny * cy;
        const size = Math.hypot(box.width, box.height) / 2;
        const rings = [random(0.18, 0.32) * size, random(0.42, 0.62) * size];
        let rest = poly;
        const parts = [];

        rings.forEach(function (ring) {
          const inner = cut(rest, nx, ny, base + ring);
          const outer = cut(rest, -nx, -ny, -(base + ring));

          if (inner.length >= 3 && outer.length >= 3) {
            parts.push(inner);
            rest = outer;
          }
        });
        parts.push(rest);
        parts.forEach(function (part, ring) {
          const burst = random(140, 260) * (1 + ring * 0.35);
          const spread = middle + random(-0.25, 0.25);
          const piece = makePiece(source, part, 0, 0, 0, Math.cos(spread) * burst, Math.sin(spread) * burst, random(-1.6, 1.6));
          place(piece);
          pieces.push(piece);
        });
      }
    });
    // The first snapshot is the page whole, where the rewind ends
    snapshot();
    frame = window.requestAnimationFrame(step);
  }

  function putBack() {
    if (!broken) {
      return;
    }
    broken = false;

    if (frame !== null) {
      window.cancelAnimationFrame(frame);
    }
    frame = null;
    last = 0;

    if (!pieces.length) {
      clear();
      root.classList.remove('home-broken');
      return;
    }
    playBack(function () {
      if (!broken) {
        clear();
        root.classList.remove('home-broken');
      }
    });
  }

  // preview.js marks the page while an app is open
  new MutationObserver(function () {
    if (root.classList.contains('preview-locked')) {
      breakApart();
    } else {
      putBack();
    }
  }).observe(root, {attributes: true, attributeFilter: ['class']});

  if (root.classList.contains('preview-locked')) {
    breakApart();
  }
})();
