
(function () {
    'use strict';

    var VG = {
        ventureId: null,
        isFounder: false,
        canEdit: false,
        canModerate: false,
        items: [],
        maxItems: 25,
        // Past a dozen items the dot row is wider than the hero on a phone, so
        // above this the dots are dropped entirely — the "n / m" counter in the
        // expand button already states the position and the arrows still navigate.
        maxDots: 12,

        muted: true
    };

    function autoplayAllowed() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
        return !window.matchMedia('(max-width: 640px), (pointer: coarse)').matches;
    }

    function api() { return VH.getBasePath() + 'api/venture_media.php'; }

    function mediaUrl(path) {
        if (!path) return '';
        return /^https?:\/\//i.test(path) ? path : VH.getBasePath() + path;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------------- hero carousel ---------------- */

    function slideHTML(item, index) {
        var url = mediaUrl(item.file_path);

        if (item.media_type === 'image') {
            return '<div class="vd-hslide vd-hslide--image">' +
                   '<img src="' + esc(url) + '" alt=""' + (index === 0 ? '' : ' loading="lazy"') +
                   ' data-vg-full="' + esc(url) + '">' +
                   '</div>';
        }

        if (item.media_type === 'video') {
            return '<div class="vd-hslide vd-hslide--video">' +
                   '<span class="vg-badge">Video</span>' +
                   '<video src="' + esc(url) + '" muted loop playsinline preload="metadata"></video>' +
                   '<button type="button" class="vd-hplay hidden" data-vg-play aria-label="Play video">' +
                   '<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>' +
                   '</button></div>';
        }

        // A channel or profile has no player, so it opens in a new tab. Framing
        // it would render an empty box — both YouTube and Instagram refuse it.
        if (item.media_type === 'link') {
            var kind = linkKind(item.embed_url);
            return '<div class="vd-hslide vd-hslide--link">' +
                   '<span class="vg-badge">' + esc(kind.label) + '</span>' +
                   '<a class="vg-linkcard" href="' + esc(item.embed_url) + '" target="_blank" rel="noopener noreferrer">' +
                   '<span class="vg-linkcard-icon">' + kind.icon + '</span>' +
                   '<span class="vg-linkcard-name">' + esc(kind.handle || kind.label) + '</span>' +
                   '<span class="vg-linkcard-go">Open ' + esc(kind.label) + ' →</span>' +
                   '</a></div>';
        }

        var thumb = mediaUrl(item.thumbnail_path);
        var style = thumb ? ' style="background-image:url(\'' + esc(thumb) + '\')"' : '';
        var isInsta = /instagram\.com/i.test(item.embed_url || '');
        return '<div class="vd-hslide vd-hslide--embed">' +
               '<span class="vg-badge">' + (isInsta ? 'Instagram' : 'Video') + '</span>' +
               '<button type="button" class="vg-poster" data-vg-embed="' + esc(item.embed_url) + '"' + style + ' aria-label="Play video">' +
               '<span class="vg-play"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>' +
               '</button></div>';
    }

    /* Which site a stored link points at, for the card's label and icon. */
    function linkKind(url) {
        var u = String(url || '');
        var m;
        if (/youtube\.com|youtu\.be/i.test(u)) {
            m = u.match(/youtube\.com\/(?:channel\/|c\/|user\/|@)([A-Za-z0-9_.-]+)/i);
            return { label: 'YouTube', handle: m ? '@' + m[1].replace(/^@/, '') : '',
                     icon: '<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M23 12s0-3.8-.5-5.6a2.9 2.9 0 0 0-2-2C18.7 4 12 4 12 4s-6.7 0-8.5.4a2.9 2.9 0 0 0-2 2C1 8.2 1 12 1 12s0 3.8.5 5.6a2.9 2.9 0 0 0 2 2C5.3 20 12 20 12 20s6.7 0 8.5-.4a2.9 2.9 0 0 0 2-2C23 15.8 23 12 23 12zM9.8 15.4V8.6l5.8 3.4z"/></svg>' };
        }
        m = u.match(/instagram\.com\/([A-Za-z0-9_.]+)/i);
        return { label: 'Instagram', handle: m ? '@' + m[1] : '',
                 icon: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>' };
    }

    function renderCarousel() {
        var media = document.getElementById('vdHeroMedia');
        var hero = document.getElementById('vdHero');
        var controls = document.getElementById('vdHeroControls');
        if (!media || !hero) return;

        if (!VG.items.length) {
            media.classList.add('hidden');
            if (controls) controls.classList.add('hidden');
            hero.classList.remove('vd-hero--gallery');
            return;
        }

        media.classList.remove('hidden');

        hero.classList.add('vd-hero--gallery', 'vd-hero--has-cover');

        var track = document.getElementById('vgTrack');
        var dots = document.getElementById('vgDots');
        var count = document.getElementById('vgCount');

        track.innerHTML = VG.items.map(slideHTML).join('');

        var multi = VG.items.length > 1;
        var showDots = multi && VG.items.length <= VG.maxDots;
        dots.innerHTML = showDots
            ? VG.items.map(function (_, i) {
                return '<button type="button" class="vg-dot' + (i === 0 ? ' is-active' : '') + '" data-vg-go="' + i + '" aria-label="Go to item ' + (i + 1) + '"></button>';
            }).join('')
            : '';
        document.getElementById('vgPrev').classList.toggle('hidden', !multi);
        document.getElementById('vgNext').classList.toggle('hidden', !multi);
        if (controls) controls.classList.remove('hidden');
        if (count) count.textContent = '1 / ' + VG.items.length;

        syncArrows();
        syncPlayback();
    }

    function slideStep(track) {
        var first = track && track.firstElementChild;
        if (!first) return 1;
        var cs = getComputedStyle(track);
        var gap = parseFloat(cs.columnGap || cs.gap || '0') || 0;
        return first.getBoundingClientRect().width + gap;
    }

    function currentIndex() {
        var track = document.getElementById('vgTrack');
        if (!track || !track.firstElementChild) return 0;
        return Math.round(track.scrollLeft / slideStep(track));
    }

    function goTo(index) {
        var track = document.getElementById('vgTrack');
        if (!track || !track.firstElementChild) return;
        index = Math.max(0, Math.min(VG.items.length - 1, index));
        track.scrollTo({ left: index * slideStep(track), behavior: 'smooth' });
    }

    function syncArrows() {
        var track = document.getElementById('vgTrack');
        var prev = document.getElementById('vgPrev');
        var next = document.getElementById('vgNext');
        if (!track || !prev || !next) return;

        var i = currentIndex();
        prev.disabled = i <= 0;
        next.disabled = i >= VG.items.length - 1;

        var dots = document.querySelectorAll('#vgDots .vg-dot');
        for (var d = 0; d < dots.length; d++) {
            dots[d].classList.toggle('is-active', d === i);
        }

        var count = document.getElementById('vgCount');
        if (count && VG.items.length) count.textContent = (i + 1) + ' / ' + VG.items.length;
    }

    var heroVisible = true;

    function syncPlayback() {
        var track = document.getElementById('vgTrack');
        if (!track) return;

        var active = currentIndex();
        var allowed = autoplayAllowed();
        var slides = track.children;

        for (var i = 0; i < slides.length; i++) {
            var video = slides[i].querySelector('video');
            if (!video) continue;

            var playBtn = slides[i].querySelector('[data-vg-play]');
            var isActive = i === active;

            if (isActive && allowed && heroVisible) {
                video.muted = VG.muted;

                var p = video.play();
                if (p && p.catch) p.catch(function () {});
                if (playBtn) playBtn.classList.add('hidden');
            } else {
                video.pause();

                if (playBtn) playBtn.classList.toggle('hidden', !isActive);
            }
        }

        syncSoundBtn(active);
    }

    function syncSoundBtn(active) {
        var btn = document.getElementById('vgSoundBtn');
        if (!btn) return;

        var item = VG.items[active];
        var isVideo = item && item.media_type === 'video';
        btn.classList.toggle('hidden', !isVideo);
        if (!isVideo) return;

        var icon = document.getElementById('vgSoundIcon');
        if (icon) icon.innerHTML = VG.muted ? VH.icon('mute', 16) : VH.icon('volume', 16);
        btn.setAttribute('aria-label', VG.muted ? 'Unmute video' : 'Mute video');
    }

    function activeSlide() {
        var track = document.getElementById('vgTrack');
        return track ? track.children[currentIndex()] : null;
    }

    function openLightbox(src) {
        var box = document.getElementById('vgLightbox');
        if (!box) return;
        box.querySelector('img').src = src;
        box.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        var box = document.getElementById('vgLightbox');
        if (!box) return;
        box.classList.remove('open');
        box.querySelector('img').src = '';
        document.body.style.overflow = '';
    }

    function expandCurrent() {
        var slide = activeSlide();
        if (!slide) return;

        var img = slide.querySelector('[data-vg-full]');
        if (img) { openLightbox(img.getAttribute('data-vg-full')); return; }

        var video = slide.querySelector('video');
        if (video) {
            video.controls = true;
            if (video.requestFullscreen) video.requestFullscreen();
            else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen(); // iOS Safari
            return;
        }

        var poster = slide.querySelector('[data-vg-embed]');
        if (poster) poster.click();
    }

    function loadEmbed(poster) {
        var url = poster.getAttribute('data-vg-embed');
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        var frame = document.createElement('iframe');
        frame.src = url + sep + 'autoplay=1';
        frame.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
        frame.setAttribute('allowfullscreen', '');
        frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        poster.parentNode.replaceChild(frame, poster);
    }

    function bindCarousel() {
        var track = document.getElementById('vgTrack');
        if (!track) return;

        document.getElementById('vgPrev').addEventListener('click', function () { goTo(currentIndex() - 1); });
        document.getElementById('vgNext').addEventListener('click', function () { goTo(currentIndex() + 1); });

        var raf = 0;
        track.addEventListener('scroll', function () {
            if (raf) return;
            raf = requestAnimationFrame(function () {
                raf = 0;
                syncArrows();
                syncPlayback();
            });
        }, { passive: true });

        track.addEventListener('click', function (e) {
            var poster = e.target.closest('[data-vg-embed]');
            if (poster) { loadEmbed(poster); return; }

            var play = e.target.closest('[data-vg-play]');
            if (play) {
                var v = play.parentNode.querySelector('video');
                if (v) {
                    VG.muted = false;
                    v.muted = false;
                    v.controls = true;
                    var pr = v.play();
                    if (pr && pr.catch) pr.catch(function () {});
                    play.classList.add('hidden');
                    syncSoundBtn(currentIndex());
                }
                return;
            }

            var img = e.target.closest('[data-vg-full]');
            if (img) openLightbox(img.getAttribute('data-vg-full'));
        });

        document.getElementById('vgDots').addEventListener('click', function (e) {
            var dot = e.target.closest('[data-vg-go]');
            if (dot) goTo(parseInt(dot.getAttribute('data-vg-go'), 10));
        });

        var expandBtn = document.getElementById('vgExpandBtn');
        if (expandBtn) expandBtn.addEventListener('click', expandCurrent);

        var soundBtn = document.getElementById('vgSoundBtn');
        if (soundBtn) {
            soundBtn.addEventListener('click', function () {
                VG.muted = !VG.muted;
                var slide = activeSlide();
                var v = slide && slide.querySelector('video');
                if (v) {
                    v.muted = VG.muted;

                    if (!VG.muted && v.paused) {
                        var pr = v.play();
                        if (pr && pr.catch) pr.catch(function () {});
                    }
                }
                syncSoundBtn(currentIndex());
            });
        }

        var media = document.getElementById('vdHeroMedia');
        if (media && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                heroVisible = entries[0].isIntersecting;
                syncPlayback();
            }, { threshold: 0.15 }).observe(media);
        }

        var box = document.getElementById('vgLightbox');
        if (box) {
            box.addEventListener('click', function (e) {
                if (e.target === box || e.target.closest('.vg-lightbox-close')) closeLightbox();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (box && box.classList.contains('open')) {
                if (e.key === 'Escape') closeLightbox();
                return;
            }

            if (!track.contains(document.activeElement) && document.activeElement !== track) return;
            if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(currentIndex() - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(currentIndex() + 1); }
        });
    }

    /* ---------------- founder / admin manager ---------------- */

    function tileHTML(item, index) {
        var isCover = index === 0;
        var inner;

        if (item.media_type === 'image') {
            inner = '<img src="' + esc(mediaUrl(item.file_path)) + '" alt="">';
        } else if (item.media_type === 'link') {
            var lk = linkKind(item.embed_url);
            inner = '<div class="vg-tile-fallback">' + lk.icon +
                    '<span style="display:block;margin-top:0.2rem">' + esc(lk.handle || lk.label) + '</span></div>';
        } else if (item.thumbnail_path) {
            inner = '<img src="' + esc(mediaUrl(item.thumbnail_path)) + '" alt="">';
        } else {
            inner = '<div class="vg-tile-fallback">' +
                    (item.media_type === 'video' ? 'Video file'
                     : (/instagram\.com/i.test(item.embed_url || '') ? 'Instagram post' : 'Video')) + '</div>';
        }

        var canOrder = VG.canEdit;
        var bar = '<div class="vg-tile-bar">' +
            (canOrder
                ? '<button type="button" class="vg-tile-btn" data-vg-move="up" data-vg-id="' + item.id + '"' + (index === 0 ? ' disabled' : '') + ' title="Move earlier">←</button>' +
                  '<button type="button" class="vg-tile-btn" data-vg-move="down" data-vg-id="' + item.id + '"' + (index === VG.items.length - 1 ? ' disabled' : '') + ' title="Move later">→</button>'
                : '') +
            '<button type="button" class="vg-tile-btn vg-tile-btn--danger" data-vg-del="' + item.id + '" title="Remove">✕</button>' +
            '</div>';

        return '<div class="vg-tile' + (isCover && item.media_type === 'image' ? ' vg-tile--cover' : '') + '">' + inner + bar + '</div>';
    }

    function renderManager() {
        var grid = document.getElementById('vgManageGrid');
        if (!grid) return;

        grid.innerHTML = VG.items.length
            ? VG.items.map(tileHTML).join('')
            : '<div class="vg-empty" style="grid-column:1/-1">No media yet. Add photos or a video to show partners what this Asset looks like.</div>';

        var remaining = VG.maxItems - VG.items.length;
        var note = document.getElementById('vgRemaining');
        if (note) note.textContent = remaining > 0 ? remaining + ' of ' + VG.maxItems + ' slots left' : 'Gallery full (' + VG.maxItems + ' items)';

        // Adding is for whoever may edit this venture's content — the founder, or
        // any admin on a sample listing — and only while there is room.
        ['vgAddFilesBtn', 'vgEmbedUrl', 'vgAddEmbedBtn'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = !VG.canEdit || remaining <= 0;
        });
    }

    function applyResponse(res) {
        if (!res || !res.success) {
            VH.toast.error((res && res.message) || 'Something went wrong.');
            return false;
        }
        if (res.media) VG.items = res.media;

        if (typeof window.applyVentureCover === 'function') {
            var lead = VG.items.filter(function (m) { return m.media_type === 'image' && m.file_path; })[0];
            window.applyVentureCover(lead ? lead.file_path : null);
        }
        renderCarousel();
        renderManager();
        if (res.message) VH.toast.success(res.message);
        return true;
    }

    async function uploadFiles(files) {
        var btn = document.getElementById('vgAddFilesBtn');
        var original = btn ? btn.textContent : '';
        if (btn) { btn.textContent = 'Uploading...'; btn.disabled = true; }

        try {
            for (var i = 0; i < files.length; i++) {
                if (VG.items.length >= VG.maxItems) {
                    VH.toast.error('Gallery is full at ' + VG.maxItems + ' items.');
                    break;
                }
                var fd = new FormData();
                fd.append('action', 'upload');
                fd.append('venture_id', VG.ventureId);
                fd.append('media', files[i]);

                var r = await fetch(api() + '?action=upload', { method: 'POST', body: fd });
                var res = await r.json();
                if (!res.success) {
                    VH.toast.error(res.message || 'Upload failed for ' + files[i].name);
                    continue;
                }
                if (res.media) VG.items = res.media;
            }
            applyResponse({ success: true, media: VG.items });
        } catch (err) {
            console.error(err);
            VH.toast.error('Network error while uploading.');
        } finally {
            if (btn) { btn.textContent = original; btn.disabled = false; }
            renderManager();
        }
    }

    async function post(action, body) {
        var r = await fetch(api() + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return r.json();
    }

    function bindManager() {
        var openBtn = document.getElementById('manageGalleryBtn');
        var modal = document.getElementById('vgManageModal');
        if (!openBtn || !modal) return;

        openBtn.addEventListener('click', function () { modal.classList.add('open'); renderManager(); });
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-vg-close]')) modal.classList.remove('open');
        });

        var fileInput = document.getElementById('vgFileInput');
        var addBtn = document.getElementById('vgAddFilesBtn');
        if (addBtn && fileInput) {
            addBtn.addEventListener('click', function () { fileInput.click(); });
            fileInput.addEventListener('change', function () {
                var files = Array.prototype.slice.call(fileInput.files || []);
                fileInput.value = '';
                if (files.length) uploadFiles(files);
            });
        }

        var embedBtn = document.getElementById('vgAddEmbedBtn');
        var embedInput = document.getElementById('vgEmbedUrl');
        if (embedBtn && embedInput) {
            embedBtn.addEventListener('click', async function () {
                var url = embedInput.value.trim();
                if (!url) { VH.toast.error('Paste a YouTube, Instagram or Vimeo link first.'); return; }
                embedBtn.disabled = true;
                try {
                    var res = await post('add_embed', { venture_id: VG.ventureId, url: url });
                    if (applyResponse(res)) embedInput.value = '';
                } catch (err) {
                    console.error(err);
                    VH.toast.error('Network error adding the video link.');
                } finally {
                    embedBtn.disabled = false;
                }
            });
        }

        var grid = document.getElementById('vgManageGrid');
        if (grid) {
            grid.addEventListener('click', async function (e) {
                var del = e.target.closest('[data-vg-del]');
                if (del) {
                    if (!confirm('Remove this item from the gallery?\n\nThis cannot be undone.')) return;
                    applyResponse(await post('delete', { id: parseInt(del.getAttribute('data-vg-del'), 10) }));
                    return;
                }

                var move = e.target.closest('[data-vg-move]');
                if (move) {
                    var id = parseInt(move.getAttribute('data-vg-id'), 10);
                    var dir = move.getAttribute('data-vg-move') === 'up' ? -1 : 1;
                    var from = VG.items.findIndex(function (m) { return m.id === id; });
                    var to = from + dir;
                    if (from < 0 || to < 0 || to >= VG.items.length) return;

                    var next = VG.items.slice();
                    next.splice(to, 0, next.splice(from, 1)[0]);
                    VG.items = next;
                    renderManager();

                    applyResponse(await post('reorder', {
                        venture_id: VG.ventureId,
                        order: VG.items.map(function (m) { return m.id; })
                    }));
                }
            });
        }
    }

    /* ---------------- entry point ---------------- */

    // isShowcase matters because a sample listing is platform content: any admin
    // may add to and reorder it, not only the admin account that published it.
    // canModerate (removal) stays broader — an admin may take media down anywhere.
    window.initVentureGallery = async function (ventureId, isFounder, isAdmin, isShowcase) {
        VG.ventureId = ventureId;
        VG.isFounder = !!isFounder;
        VG.canEdit = !!isFounder || (!!isAdmin && !!isShowcase);
        VG.canModerate = !!isFounder || !!isAdmin;

        var manageBtn = document.getElementById('manageGalleryBtn');
        if (manageBtn) manageBtn.classList.toggle('hidden', !VG.canModerate);

        try {
            var r = await fetch(api() + '?action=list&venture_id=' + encodeURIComponent(ventureId));
            var res = await r.json();
            if (!res.success) return;
            VG.items = res.media || [];
            VG.maxItems = res.maxItems || 25;
            // The hint under the uploader used to hardcode the number in the page
            // markup, so raising the cap left it telling founders the old limit.
            // It is written from the server's answer instead.
            var maxNote = document.getElementById('vgMaxNote');
            if (maxNote) maxNote.textContent = VG.maxItems;
        } catch (err) {
            console.error('Gallery load failed', err);
            return;
        }

        renderCarousel();
        renderManager();
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindCarousel();
        bindManager();
    });
})();
