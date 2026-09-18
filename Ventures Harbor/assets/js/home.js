
(function () {
  'use strict';

  var GOLD = '#F5C518';

  /* 1. The nav itself — its scroll repaint, account dropdown, burger/drawer and
   * search — now lives in assets/js/vh-nav.js, alongside partials/header.php.
   * It was moved out so pages that need the header but not this file (venture
   * detail, join, create, payment return) can have it. Do not re-add it here:
   * both files load together on the marketing pages, so a copy would bind every
   * handler twice. */

  /* ---------- 2. Rising gold dots in the hero ---------- */
  var dotLayer = document.getElementById('vh-hero-dots');
  if (dotLayer) {
    var frag = document.createDocumentFragment();
    for (var i = 0; i < 28; i++) {
      var size = 4 + Math.pow(Math.random(), 2) * 6;
      var d = document.createElement('div');
      d.style.cssText =
        'position:absolute;bottom:0;border-radius:999px;background:' + GOLD +
        ';opacity:0;z-index:0' +
        ';left:' + (2 + Math.random() * 96).toFixed(1) + '%' +
        ';width:' + size.toFixed(0) + 'px' +
        ';height:' + size.toFixed(0) + 'px' +
        ';box-shadow:0 0 ' + Math.round(size * 1.4) + 'px rgba(245,197,24,.8)' +
        ';animation:vh-rise ' + (11 + Math.random() * 7).toFixed(1) + 's linear ' +
        (Math.random() * 11).toFixed(1) + 's infinite';
      frag.appendChild(d);
    }
    dotLayer.appendChild(frag);
  }

  /* 3. Category filter over the venture grid */
  var filterBtns = document.querySelectorAll('[data-filter]');
  var cards = document.querySelectorAll('[data-venture-cat]');

  function paintFilter(btn, active) {
    btn.style.background = active ? '#2563EB' : '#fff';
    btn.style.color = active ? '#fff' : '#33415C';
    btn.style.borderColor = active ? '#2563EB' : '#DCE3EC';
  }

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var want = btn.getAttribute('data-filter');
      filterBtns.forEach(function (b) { paintFilter(b, b === btn); });
      cards.forEach(function (card) {
        // A card carries every class it claims, so match membership not equality.
        var have = (card.getAttribute('data-venture-cat') || '').split(',');
        var show = want === 'all' || have.indexOf(want) !== -1;
        card.style.display = show ? 'flex' : 'none';
      });
      if (window.ScrollTrigger) setTimeout(function () { ScrollTrigger.refresh(); }, 60);
    });
  });

  /* 3b. Hero CTA alternates its label every 2s.
   *
   * The width is pinned to the wider of the two labels before the first swap,
   * because the button sits in a centred flex row: letting it resize would nudge
   * the "Get Started Free" button sideways twice every two seconds for as long
   * as anyone looks at the hero. Measured after fonts settle, or the reserved
   * width is computed from a fallback face and is wrong once the real one loads.
   *
   * Reduced motion holds the first label. A CTA that rewrites itself on a timer
   * is exactly the kind of unrequested movement that setting asks us to drop,
   * and the second label is a rephrasing rather than information of its own. */
  var ctaLabel = document.getElementById('heroCtaLabel');
  var ctaBtn = ctaLabel && ctaLabel.closest('a');
  if (ctaLabel && ctaBtn) {
    var CTA_TEXTS  = ['Explore Marketplace', 'Start Co-Investing'];
    // The whole button changes colour, not just the words. Gold text on the blue
    // read as a glitch rather than a second state, and gold is a background
    // colour everywhere else on this page. White on #F5C518 is unreadable, so the
    // gold state takes the dark ink the site already pairs with gold elsewhere.
    var CTA_BG     = ['#2563EB', GOLD];
    var CTA_INK    = ['#fff', '#081421'];
    var CTA_GLOW   = ['0 10px 30px rgba(37,99,235,.35)', '0 10px 30px rgba(245,197,24,.35)'];
    // Half the swap: the label fades out over CTA_MS, then the new label fades
    // in over CTA_MS. The button's colour does not glide -- see below.
    var CTA_MS   = 500;
    var CTA_EASE = 'cubic-bezier(.4,0,.2,1)';
    var reduceMotion = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    ctaLabel.style.display = 'inline-block';
    ctaLabel.style.textAlign = 'center';
    // One duration, one curve, for the words and the button alike. An earlier
    // version ran the colour slower than the text on purpose; the client read
    // the two speeds as the swap being out of step with itself, so they now
    // move together. CTA_EASE/CTA_MS are the single source for both — change
    // the pace here and the label and the button stay locked to each other.
    ctaLabel.style.transition = 'opacity ' + CTA_MS + 'ms ' + CTA_EASE;
    // Colours are set inline on purpose: .vh-btn-blue:hover forces color:#fff and
    // a blue glow, which would blank the label and halo the gold state the moment
    // the pointer landed on it. An inline value outranks that rule, and the hover
    // lift (a transform, unset inline) still works.
    //
    // The colour is deliberately NOT transitioned. CSS interpolates #2563EB to
    // #F5C518 straight through sRGB, and the midpoint of that path is an olive
    // grey -- for a quarter of a second the button looked washed out and disabled
    // rather than mid-swap, which is exactly what the client reported. Snapping it
    // instead means the button is only ever one of the two solid brand colours,
    // and the snap lands on the frame where the label is at opacity 0, so nothing
    // is seen to jump. The smoothness comes from the label cross-fade; the colour
    // just has to arrive at the same instant the new words do.
    ctaBtn.style.transition = 'transform .25s';

    var reserveWidth = function () {
      var original = ctaLabel.textContent;
      var widest = 0;
      ctaLabel.style.minWidth = '';
      for (var i = 0; i < CTA_TEXTS.length; i++) {
        ctaLabel.textContent = CTA_TEXTS[i];
        widest = Math.max(widest, ctaLabel.getBoundingClientRect().width);
      }
      ctaLabel.textContent = original;
      ctaLabel.style.minWidth = Math.ceil(widest) + 'px';
    };

    if (document.fonts && document.fonts.ready) document.fonts.ready.then(reserveWidth);
    else reserveWidth();

    if (!reduceMotion) {
      var ctaIndex = 0;
      setInterval(function () {
        var next = (ctaIndex + 1) % CTA_TEXTS.length;
        // Phase 1: the old label fades out. The button holds its colour.
        ctaLabel.style.opacity = '0';
        setTimeout(function () {
          // Phase 2: the new state arrives whole, on one frame, while the label
          // is invisible -- new words and new colour at the same instant. Any
          // other arrangement was read as two changes: gliding the colour from
          // phase 1 finished it a full CTA_MS early, and gliding it from here
          // dragged it through the olive midpoint described above.
          ctaIndex = next;
          ctaLabel.textContent = CTA_TEXTS[ctaIndex];
          ctaLabel.style.opacity = '1';
          ctaBtn.style.backgroundColor = CTA_BG[next];
          ctaBtn.style.color = CTA_INK[next];
          ctaBtn.style.boxShadow = CTA_GLOW[next];
        }, CTA_MS);
        // 2s exactly, as asked. The label cross-fade is 2 x CTA_MS, well inside it.
      }, 2000);
    }
  }

  /* 4. Nav search: live-filters the grid, Enter jumps to it */
  var searchInput = document.getElementById('vh-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim().toLowerCase();
      cards.forEach(function (card) {
        var show = !q || card.textContent.toLowerCase().indexOf(q) !== -1;
        card.style.display = show ? 'flex' : 'none';
      });
      if (window.ScrollTrigger) setTimeout(function () { ScrollTrigger.refresh(); }, 60);
    });
    // Enter is handled by vh-nav.js, which scrolls to this grid when it exists
    // and hands the term to Browse when it doesn't.
  }

  /* ---------- 5 & 6. Account menu, auth-aware nav, mobile drawer ---------- */
  // All three moved to assets/js/vh-nav.js with the rest of the header.

  /* ---------- 7. GSAP entrance + scroll animations ---------- */
  var gsapDone = false;
  function initGsap() {
    if (gsapDone || !window.gsap || !window.ScrollTrigger) return;
    gsapDone = true;
    gsap.registerPlugin(ScrollTrigger);

    gsap.from('[data-hero-line]', {
      yPercent: 115, duration: 1.1, stagger: 0.14, ease: 'power4.out', delay: 0.1
    });

    var img = document.getElementById('vh-hero-img');
    if (img) {
      gsap.from(img, { scale: 1.12, opacity: 0, duration: 1.6, ease: 'power3.out', delay: 0.25 });
      gsap.to(img, {
        yPercent: 7, ease: 'none',
        scrollTrigger: { trigger: '#top', start: 'top top', end: 'bottom top', scrub: true }
      });
    }

    gsap.from('[data-badge]', {
      y: 18, opacity: 0, duration: 0.6, stagger: 0.08, ease: 'power2.out', delay: 0.7
    });

    document.querySelectorAll('#top [data-anim="fade"]').forEach(function (el, i) {
      gsap.from(el, { y: 26, opacity: 0, duration: 0.9, ease: 'power3.out', delay: 0.3 + i * 0.12 });
    });

    document.querySelectorAll('section [data-anim="fade"], footer [data-anim="fade"]').forEach(function (el) {
      gsap.from(el, {
        y: 30, opacity: 0, duration: 0.85, ease: 'power3.out',
        scrollTrigger: { trigger: el, start: 'top 88%' }
      });
    });

    document.querySelectorAll('[data-anim="card"]').forEach(function (el, i) {
      gsap.from(el, {
        y: 44, opacity: 0, scale: 0.97, duration: 0.8, ease: 'power3.out', delay: (i % 4) * 0.09,
        scrollTrigger: { trigger: el, start: 'top 90%' },
        /* Hand the transform back to CSS when the reveal finishes.
           A `from` tween leaves its end state on the style attribute — every card
           kept `transform: translate(0px, 0px) scale(1, 1)` inline forever — and an
           inline transform beats a stylesheet one, so `:hover { transform: ... }`
           was silently dead on both the step cards and the venture cards: they
           changed colour on hover but never lifted. Clearing only `transform`
           leaves the faded-in opacity alone. */
        clearProps: 'transform'
      });
    });

    document.querySelectorAll('[data-bar]').forEach(function (el) {
      var w = el.style.width;
      gsap.from(el, {
        width: 0, duration: 1.2, ease: 'power3.out',
        scrollTrigger: { trigger: el, start: 'top 92%' },
        onComplete: function () { el.style.width = w; }
      });
    });

    document.querySelectorAll('[data-count]').forEach(function (el) {
      var raw = el.getAttribute('data-count') || '0';
      var target = parseFloat(raw) || 0;
      // Count at the precision the server actually sent: "42.1" animates through
      // tenths and lands on 42.1, "3" stays a whole number. Rounding every frame
      // instead threw the decimal away, so a "42.1L+" figure still finished as "42L+".
      var dot = raw.indexOf('.');
      var decimals = dot === -1 ? 0 : raw.length - dot - 1;
      var obj = { v: 0 };
      gsap.to(obj, {
        v: target, duration: 1.8, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 92%' },
        onUpdate: function () { el.textContent = obj.v.toFixed(decimals); }
      });
    });
  }

  var t0 = Date.now();
  (function tick() {
    if (window.gsap && window.ScrollTrigger) initGsap();
    else if (Date.now() - t0 < 8000) setTimeout(tick, 120);
  })();
})();
