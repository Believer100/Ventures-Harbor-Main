
(function () {
  'use strict';

  function init() {
    var card = document.querySelector('.auth-card');
    if (!card || !window.MutationObserver) return;

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;

    var DURATION = 460;
    var EASING = 'cubic-bezier(.16, 1, .3, 1)';
    var animating = false;
    var timer = null;

    var lastHeight = card.getBoundingClientRect().height;

    function settle() {
      card.style.transition = '';
      card.style.height = '';
      animating = false;
      lastHeight = card.getBoundingClientRect().height;
    }

    function animateHeight() {
      var from = lastHeight;

      card.style.transition = 'none';
      card.style.height = 'auto';
      var to = card.getBoundingClientRect().height;
      lastHeight = to;

      if (Math.abs(to - from) < 1) {
        settle();
        return;
      }

      card.style.height = from + 'px';
      void card.offsetHeight;

      card.style.transition = 'height ' + DURATION + 'ms ' + EASING;
      card.style.height = to + 'px';
      animating = true;

      clearTimeout(timer);
      timer = setTimeout(settle, DURATION + 80);
    }

    if (window.ResizeObserver) {
      new ResizeObserver(function () {
        if (!animating) lastHeight = card.getBoundingClientRect().height;
      }).observe(card);
    }

    var observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var t = mutations[i].target;
        if (t.classList && t.classList.contains('auth-form-panel')) {
          animateHeight();
          return;
        }
      }
    });

    observer.observe(card, {
      subtree: true,
      attributes: true,
      attributeFilter: ['class']
    });

    card.addEventListener('transitionend', function (e) {
      if (e.target === card && e.propertyName === 'height' && animating) {
        clearTimeout(timer);
        settle();
      }
    });
  }

  function initBgVideo() {
    var video = document.getElementById('authBgVideo');
    if (!video) return;

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var RATE = 1;

    function applyRate() { try { video.playbackRate = RATE; } catch (e) { /* not ready yet */ } }

    function markReady() {
      video.classList.add('is-ready');
      // One held frame instead of motion.
      if (reduce) video.pause();
    }

    video.addEventListener('loadedmetadata', applyRate);
    video.addEventListener('play', applyRate);
    applyRate();

    if (video.readyState >= 2) markReady(); // HAVE_CURRENT_DATA: a frame exists
    video.addEventListener('loadeddata', markReady);
    video.addEventListener('canplay', markReady);

    function fail() { if (video.parentNode) video.parentNode.removeChild(video); }
    if (video.error) fail();
    video.addEventListener('error', fail, true);

    var attempt = video.play();
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(markReady);
    }
  }

  function boot() {
    init();
    initBgVideo();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
