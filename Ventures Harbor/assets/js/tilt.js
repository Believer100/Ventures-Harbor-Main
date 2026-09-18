
(function () {
  'use strict';

  var SELECTOR = '.vh-card, .venture-card';
  var MAX_TILT = 4.5;      // max rotation in degrees at the card's edge
  var PERSPECTIVE = 1000;  // px — lower is a stronger 3D effect
  var LIFT = 6;            // px the card rises when fully engaged
  var SCALE = 1.015;
  var EASE = 0.11;         // per-frame approach factor at 60fps (lower = smoother)
  var SETTLED = 0.002;     // below this the animation is considered finished

  if (!window.matchMedia) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

  var states = new Map();  // card element -> { cur, tgt }
  var hovered = null;      // card the pointer is over right now, if any
  var frame = 0;
  var last = 0;

  function ensureFx(el) {
    var fx = el.querySelector(':scope > .vh-tilt-fx');
    if (!fx) {
      fx = document.createElement('div');
      fx.className = 'vh-tilt-fx';
      fx.setAttribute('aria-hidden', 'true');
      el.appendChild(fx); // absolutely positioned, so it stays out of flow
    }
  }

  function clear(el) {
    el.classList.remove('is-tilting', 'is-tilt-anim');
    el.style.transform = '';
    el.style.removeProperty('--tilt-mx');
    el.style.removeProperty('--tilt-my');
    el.style.removeProperty('--tilt-k');
  }

  function stateFor(el) {
    var s = states.get(el);
    if (!s) {
      s = {
        cur: { rx: 0, ry: 0, k: 0, mx: 50, my: 50 },
        tgt: { rx: 0, ry: 0, k: 0, mx: 50, my: 50 }
      };
      states.set(el, s);
      ensureFx(el);
      el.classList.add('is-tilt-anim');
    }
    return s;
  }

  function release(el) {
    if (!el) return;
    el.classList.remove('is-tilting');
    var s = states.get(el);
    if (s) {
      s.tgt.rx = 0;
      s.tgt.ry = 0;
      s.tgt.k = 0;   // mx/my are left alone so the light fades where it was
    }
  }

  function tick(now) {
    var dt = last ? Math.min(now - last, 100) : 16.67;
    last = now;

    var t = 1 - Math.pow(1 - EASE, dt / 16.67);

    states.forEach(function (s, el) {
      if (!el.isConnected) {
        states.delete(el);
        return;
      }

      var cur = s.cur, tgt = s.tgt;
      cur.rx += (tgt.rx - cur.rx) * t;
      cur.ry += (tgt.ry - cur.ry) * t;
      cur.k  += (tgt.k  - cur.k)  * t;
      cur.mx += (tgt.mx - cur.mx) * t;
      cur.my += (tgt.my - cur.my) * t;

      el.style.transform =
        'perspective(' + PERSPECTIVE + 'px) ' +
        'translateY(' + (-LIFT * cur.k).toFixed(3) + 'px) ' +
        'rotateX(' + cur.rx.toFixed(3) + 'deg) ' +
        'rotateY(' + cur.ry.toFixed(3) + 'deg) ' +
        'scale(' + (1 + (SCALE - 1) * cur.k).toFixed(4) + ')';

      el.style.setProperty('--tilt-mx', cur.mx.toFixed(2) + '%');
      el.style.setProperty('--tilt-my', cur.my.toFixed(2) + '%');
      el.style.setProperty('--tilt-k', cur.k.toFixed(4));

      var moving =
        Math.abs(tgt.rx - cur.rx) > SETTLED ||
        Math.abs(tgt.ry - cur.ry) > SETTLED ||
        Math.abs(tgt.k - cur.k) > SETTLED;

      if (el !== hovered && !moving) {
        clear(el);
        states.delete(el);
      }
    });

    if (states.size) {
      frame = requestAnimationFrame(tick);
    } else {
      frame = 0;
      last = 0;
    }
  }

  function start() {
    if (!frame) {
      last = 0;
      frame = requestAnimationFrame(tick);
    }
  }

  document.addEventListener('pointermove', function (e) {
    if (e.pointerType && e.pointerType !== 'mouse') return;

    var el = e.target && e.target.closest ? e.target.closest(SELECTOR) : null;

    if (el !== hovered) {
      release(hovered);   // old card eases home instead of being wiped
      hovered = el;
    }
    if (!el) {
      if (states.size) start();
      return;
    }

    var r = el.getBoundingClientRect();
    if (!r.width || !r.height) return;

    var s = stateFor(el);
    var fx = (e.clientX - r.left) / r.width;   // 0 (left edge) .. 1 (right edge)
    var fy = (e.clientY - r.top) / r.height;   // 0 (top edge)  .. 1 (bottom edge)

    el.classList.add('is-tilting');
    s.tgt.ry = (fx - 0.5) * 2 * MAX_TILT;    // horizontal position drives rotateY
    s.tgt.rx = -(fy - 0.5) * 2 * MAX_TILT;   // vertical position drives rotateX
    s.tgt.k = 1;
    s.tgt.mx = fx * 100;
    s.tgt.my = fy * 100;
    start();
  }, { passive: true });

  function releaseAll() {
    release(hovered);
    hovered = null;
    if (states.size) start();
  }
  document.addEventListener('pointerleave', releaseAll);
  window.addEventListener('blur', releaseAll);
})();
