
(function () {
  'use strict';

  var SELECTOR = '[data-border-glow]';

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  function edgeProximity(w, h, x, y) {
    var cx = w / 2;
    var cy = h / 2;
    var dx = x - cx;
    var dy = y - cy;
    var kx = dx === 0 ? Infinity : cx / Math.abs(dx);
    var ky = dy === 0 ? Infinity : cy / Math.abs(dy);
    return Math.min(Math.max(1 / Math.min(kx, ky), 0), 1);
  }

  function cursorAngle(w, h, x, y) {
    var dx = x - w / 2;
    var dy = y - h / 2;
    if (dx === 0 && dy === 0) return 0;
    var degrees = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
    if (degrees < 0) degrees += 360;
    return degrees;
  }

  function ensureEdgeLight(card) {
    if (card.querySelector(':scope > .edge-light')) return;
    var span = document.createElement('span');
    span.className = 'edge-light';
    span.setAttribute('aria-hidden', 'true');
    card.appendChild(span); // absolutely positioned, so it stays out of flow
  }

  function boot() {
    var cards = document.querySelectorAll(SELECTOR);
    if (!cards.length) return;
    for (var i = 0; i < cards.length; i++) ensureEdgeLight(cards[i]);

    document.addEventListener('pointermove', function (e) {
      var card = e.target && e.target.closest ? e.target.closest(SELECTOR) : null;
      if (!card) return;

      var rect = card.getBoundingClientRect();
      if (!rect.width || !rect.height) return;

      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;

      card.style.setProperty('--edge-proximity',
        (edgeProximity(rect.width, rect.height, x, y) * 100).toFixed(3));
      card.style.setProperty('--cursor-angle',
        cursorAngle(rect.width, rect.height, x, y).toFixed(3) + 'deg');
    }, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
