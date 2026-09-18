/* VENTURES HARBOR — SHARED UTILITIES (shared.js) */

window.VH = window.VH || {};

VH.getBasePath = function() {
    const path = window.location.pathname;
    const marker = path.match(/^(.*?)\/(pages|admin|users|api)\//);
    if (marker) {
        return marker[1] + '/';
    }
    return path.substring(0, path.lastIndexOf('/') + 1) || '/';
};

VH.renderAvatarInto = function(el, user) {
    if (!el || !user) return;
    if (user.avatarUrl) {
        const base = VH.getBasePath();
        const src = user.avatarUrl.startsWith('http') ? user.avatarUrl : base + user.avatarUrl;
        el.innerHTML = `<img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">`;
    } else {
        el.textContent = user.avatar || (user.name ? user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase() : "??");
    }
};

VH.ventureInitial = function(title) {
    const first = String(title || '').trim().split(/\s+/)[0] || '';
    const match = first.match(/[\p{L}\p{N}]/u);
    return match ? match[0].toUpperCase() : '?';
};

VH.renderVentureIconInto = function(el, venture) {
    if (!el || !venture) return;
    const logoUrl = venture.logo_url || venture.logoUrl;
    if (logoUrl) {
        const base = VH.getBasePath();
        const src = logoUrl.startsWith('http') ? logoUrl : base + logoUrl;
        el.innerHTML = `<img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">`;
    } else {
        el.textContent = VH.ventureInitial(venture.title || venture.name);
        el.style.fontWeight = '700';
    }
};

/* ── ICONS ───────────────────────────────────────────────────────────────────
 * One inline-SVG set, in the stroked 24x24 currentColor idiom the site already
 * uses everywhere it draws an icon by hand (venture detail, the gallery arrows,
 * the sidebars).
 *
 * These replace the emoji the UI used to lean on. Emoji were never really icons
 * here: they render as a different picture on every platform (and as a colour
 * photo on Windows, a flat glyph on Android, an Apple drawing on iOS), they do
 * not take the colour of the thing around them, and at the size a form label
 * needs them they turn into mush — which is exactly the complaint that started
 * this: two option cards that looked the same at a glance.
 *
 * An SVG here inherits `currentColor`, so a selected card, a hover state or a
 * disabled control colours its icon along with its text for free.
 *
 * Emoji deliberately LEFT alone: notification titles, email bodies and the legal
 * templates' ⚠ warning. Those are prose, not chrome — a person reads them in a
 * list of messages where a little colour helps, and the client has signed the
 * wording off as it stands.
 *
 *   VH.icon('wallet')            → 20px, inherits colour
 *   VH.icon('wallet', 16)        → 16px
 */
VH.icon = function (name, size) {
    var s = size || 20;
    var d = VH.icon.paths[name];
    if (!d) return '';
    return '<svg class="vh-i" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" '
         + 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
         + 'aria-hidden="true" focusable="false">' + d + '</svg>';
};

VH.icon.paths = {
    /* places & people */
    pin:        '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    globe:      '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10"/>',
    users:      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    user:       '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    building:   '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>',
    handshake:  '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',

    /* money */
    wallet:     '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
    card:       '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    receipt:    '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    trending:   '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
    briefcase:  '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',

    /* time & status */
    clock:      '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    hourglass:  '<path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/>',
    calendar:   '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    bell:       '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    check:      '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    alert:      '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/>',
    ban:        '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
    lock:       '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    flask:      '<path d="M9 3h6M10 3v6.5L4.5 19A2 2 0 0 0 6.2 22h11.6a2 2 0 0 0 1.7-3L14 9.5V3"/><path d="M7 15h10"/>',
    rocket:     '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09"/><path d="M12 15 9 12a11 11 0 0 1 2-6.5C12.5 3.5 15 2 20 2c0 5-1.5 7.5-3.5 9A11 11 0 0 1 12 15Z"/><path d="M15 9h.01"/>',

    /* documents */
    file:       '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/>',
    download:   '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    upload:     '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    paperclip:  '<path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
    clipboard:  '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
    save:       '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
    trash:      '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    edit:       '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
    image:      '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    video:      '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',

    /* misc chrome */
    door:       '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5.75 20.43A2 2 0 0 1 4 18.5V5.562a2 2 0 0 1 1.515-1.94l6-1.5A1 1 0 0 1 13 3.06Z"/>',
    sliders:    '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
    chat:       '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    question:   '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    tools:      '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/>',
    link:       '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    volume:     '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>',
    mute:       '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>',
    wave:       '<path d="M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2"/><path d="M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>',
    party:      '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="M22 2 20 4l2 2-2 2"/><path d="M11 13a9 9 0 0 1 9 9"/><path d="M2 22a9 9 0 0 1 9-9"/>',
    tag:        '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42Z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
    ticket:     '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
    chevronDown:'<polyline points="6 9 12 15 18 9"/>',
    chevronUp:  '<polyline points="18 15 12 9 6 15"/>',
    refresh:    '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>'
};

// ── TOAST NOTIFICATIONS ──
VH.toast = {
    show: function(type, message, title = '') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;

        let icon = 'ℹ';
        if (type === 'success') icon = '✓';
        if (type === 'error') icon = '✕';
        if (type === 'warning') icon = '!';

        if (!title) {
            title = type.charAt(0).toUpperCase() + type.slice(1);
        }

        toast.innerHTML = `
            <div class="toast-progress"></div>
            <div class="toast-icon">${icon}</div>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${message}</div>
            </div>
            <div class="toast-close">×</div>
        `;

        container.appendChild(toast);

        // Animate slide-in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Auto close after 4 seconds
        const autoCloseTimeout = setTimeout(() => {
            this.close(toast);
        }, 4000);

        // Click on toast to close
        toast.addEventListener('click', (e) => {
            if (e.target.classList.contains('toast-close') || e.target.closest('.toast-close')) {
                clearTimeout(autoCloseTimeout);
                this.close(toast);
            }
        });
    },
    success: function(message, title = '') {
        this.show('success', message, title);
    },
    error: function(message, title = '') {
        this.show('error', message, title);
    },
    info: function(message, title = '') {
        this.show('info', message, title);
    },
    warning: function(message, title = '') {
        this.show('warning', message, title);
    },
    close: function(toast) {
        toast.classList.remove('show');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }
};

VH.wishlist = {
    buttonHTML: function(ventureId, saved) {
        const on = !!saved;
        return '<button type="button" class="vc-wish" data-wish data-venture-id="' + ventureId + '"'
            + ' aria-pressed="' + (on ? 'true' : 'false') + '"'
            + ' aria-label="' + (on ? 'Remove from your list' : 'Save to your list') + '"'
            + ' title="' + (on ? 'Saved — click to remove' : 'Save to your list') + '">'
            + '<svg width="17" height="17" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            + '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg></button>';
    },

    paint: function(ventureId, saved, animate) {
        if (animate === undefined) animate = true;
        document.querySelectorAll('[data-wish][data-venture-id="' + ventureId + '"]').forEach(btn => {
            btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
            btn.setAttribute('aria-label', saved ? 'Remove from your list' : 'Save to your list');
            btn.title = saved ? 'Saved — click to remove' : 'Save to your list';

            const label = btn.querySelector('[data-wish-label]');
            if (label) label.textContent = saved ? 'Interested ✓' : 'Interested';
            if (saved && animate) {
                btn.classList.add('is-popping');
                setTimeout(() => btn.classList.remove('is-popping'), 340);
            }
        });
    },

    toggle: async function(btn) {
        const ventureId = parseInt(btn.getAttribute('data-venture-id'), 10);
        if (!ventureId || btn.disabled) return;

        if (!VH.auth.getUser()) {
            VH.toast.info('Sign in to save Assets to your list.');
            setTimeout(() => VH.auth.redirectToLogin(), 900);
            return;
        }

        btn.disabled = true;
        try {
            const response = await fetch(VH.getBasePath() + 'api/ventures.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'toggle_wishlist', venture_id: ventureId })
            });
            const data = await response.json();

            if (response.status === 401) {
                localStorage.removeItem('vh_user');
                VH.toast.info('Your session expired. Please sign in again.');
                setTimeout(() => VH.auth.redirectToLogin(), 900);
                return;
            }
            if (!data.success) {
                VH.toast.error(data.message || 'Could not update your list.');
                return;
            }

            VH.wishlist.paint(ventureId, data.wishlisted);
            VH.toast.success(data.message);
            document.dispatchEvent(new CustomEvent('vh:wishlist-changed', {
                detail: { ventureId: ventureId, saved: !!data.wishlisted }
            }));
        } catch (e) {
            VH.toast.error('Could not update your list. Please try again.');
        } finally {
            btn.disabled = false;
        }
    },

    bind: function() {
        if (VH.wishlist._bound) return;
        VH.wishlist._bound = true;
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-wish]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            VH.wishlist.toggle(btn);
        });
    },

    sync: async function() {
        if (!VH.auth.getUser()) return;
        try {
            const response = await fetch(VH.getBasePath() + 'api/ventures.php?action=wishlist', {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data.success || !Array.isArray(data.venture_ids)) return;
            const saved = new Set(data.venture_ids.map(Number));
            document.querySelectorAll('[data-wish][data-venture-id]').forEach(btn => {
                const id = parseInt(btn.getAttribute('data-venture-id'), 10);
                const on = saved.has(id);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.setAttribute('aria-label', on ? 'Remove from your list' : 'Save to your list');
                btn.title = on ? 'Saved — click to remove' : 'Save to your list';
            });
        } catch (e) {
        }
    }
};

document.addEventListener('DOMContentLoaded', () => VH.wishlist.bind());

VH.card = {
    money: function(n) {
        n = Number(n) || 0;
        if (n >= 10000000) { const c = n / 10000000; return "₹" + (c % 1 === 0 ? c.toFixed(0) : c.toFixed(1)) + "Cr"; }
        if (n >= 100000)   { const l = n / 100000;   return "₹" + (l % 1 === 0 ? l.toFixed(0) : l.toFixed(1)) + "L"; }
        if (n >= 1000)     { const k = n / 1000;     return "₹" + (k % 1 === 0 ? k.toFixed(0) : k.toFixed(1)) + "K"; }
        return "₹" + n.toLocaleString("en-IN");
    },

    /**
     * Capital still to be raised: the target minus what has come in.
     *
     * Floored at zero so an over-subscribed venture reads "₹0" rather than a
     * negative figure — raised_capital can exceed target_capital when the last
     * partner's pledge overshoots. Both renderers and the PHP twin in index.php
     * read this one definition; don't recompute it inline.
     */
    remaining: function(v) {
        const left = (Number(v.target_capital) || 0) - (Number(v.raised_capital) || 0);
        return left > 0 ? left : 0;
    },

    esc: function(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    },

    CATEGORY_LABELS: {
        franchise: 'Franchise', food: 'Food & Beverage', tech: 'Technology',
        infra: 'Infrastructure', education: 'Education', healthcare: 'Healthcare',
        logistics: 'Logistics', agriculture: 'Agriculture', real_estate: 'Real Estate'
    },
    categoryLabel: function(slug) {
        const key = String(slug || '').trim().toLowerCase();
        if (!key) return '';
        return VH.card.CATEGORY_LABELS[key]
            || key.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },

    accent: function() {
        return '#16a34a';
    },

    coverInner: function(v) {
        const initial = VH.card.esc(VH.ventureInitial(v.title));
        const src = v.logo_url || v.cover_image;

        return src
            ? `<img src="${VH.card.esc(src.startsWith('http') ? src : VH.getBasePath() + src)}" alt="" loading="lazy"`
              + ` onerror="this.parentNode.innerHTML='<span>${initial}</span>'">`
            : `<span>${initial}</span>`;
    },

    pinSVG: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>',

    // Location only. The category chip was removed at the client's request: the
    // industry is already printed in the card subtitle, and `category` is a
    // coarse slug derived from it that falls back to 'franchise' for anything
    // unmapped (api/ventures.php) — so the chip read "Franchise" on ventures
    // that have nothing to do with franchising. `category` itself stays: it is
    // what the Browse and homepage filters match on.
    chipsHTML: function(v) {
        return v.location ? `<span class="vc-loc">${VH.card.pinSVG}${VH.card.esc(v.location)}</span>` : '';
    },

    VIEWER_STATE_LABELS: {
        founder:    'Your Venture',
        member:     '✓ Joined',
        selected:   'Selected',
        applied:    'Applied',
        waitlisted: 'On Waitlist'
    },

    /* Full, but the listing has not run out — so the Asset is out of room rather
       than closed, and Co-Own becomes Join Waitlist. This is a READER of what the
       server already sent (`waitlist_open`), computed there by vh_waitlist_is_open()
       against the same openness the buckets use; the browser cannot see the member
       rows the fullness is derived from. */
    waitlistOpen: function(v) {
        return String(v.waitlist_open ?? '') === '1' || v.waitlist_open === true;
    },

    isShowcase: function(v) {
        return String(v.is_showcase ?? '0') === '1';
    },

    showcaseRibbonHTML: function(v) {
        return VH.card.isShowcase(v)
            ? '<div class="vc-sample-ribbon" title="An example listing published by Ventures Harbor — not a real venture">Sample listing &middot; example only</div>'
            : '';
    },

    isFull: function(v) {
        const target = parseFloat(v.target_capital) || 0;
        const raised = parseFloat(v.raised_capital) || 0;
        return target > 0 && raised >= target;
    },

    takesActive: function(v) {
        const t = v.partner_types || 'both';
        return t === 'active' || t === 'both';
    },

    buckets: function(v) {
        const b = v.capital_buckets;
        if (!b || typeof b !== 'object') return null;
        const n = (x) => parseFloat(x) || 0;
        return {
            silentCap:       n(b.silent_cap),
            activeReserve:   n(b.active_reserve),

            partnerPool:     n(b.partner_pool),
            raisedSilent:    n(b.raised_silent),
            raisedActive:    n(b.raised_active),
            silentRemaining: n(b.silent_remaining),
            // The active side is bucketed too since 6 Sep 2026 — see
            // vh_capital_buckets(). This stays a READER of the server's answer.
            activeRemaining: n(b.active_remaining),
            remaining:       n(b.remaining),
            hasCap:          b.has_cap === true || b.has_cap === 1 || b.has_cap === '1',
            silentFull:      b.silent_full === true || b.silent_full === 1 || b.silent_full === '1',
            activeFull:      b.active_full === true || b.active_full === 1 || b.active_full === '1'
        };
    },

    silentCapped: function(v) {
        const b = VH.card.buckets(v);
        return !!b && b.hasCap && b.silentFull && !VH.card.isFull(v);
    },

    PARTNER_TYPE_CHIPS: {
        both:   ['Partner', 'This Asset is open to partners.'],
        active: ['Partner', 'This Asset is open to partners.'],
        silent: ['Partner', 'This Asset is open to partners.'],
        partner: ['Partner', 'This Asset is open to partners.']
    },

    /* A FULLY FUNDED ASSET GOES BACK TO STATING WHAT IT IS.
     *
     * "Phir 100 percent funded complete hone ke baad wapas Active + Silent wapas toh
     * aana chahiye" — the client, 7 Sep 2026, who approved the silent-capped flip to
     * Active Only in the same breath ("ye bhi sahi ha, filter ma show hoga agar koi
     * active dhund raha hoga") and asked only that it not survive the raise closing.
     *
     * It used to force `active` while full, so a `both` listing kept advertising
     * "Active Only" — with a tooltip reading "is ONLY looking for active partners",
     * which by then was false twice over: it was not only looking for active, and it
     * was not looking for anyone, because it was full and on the waitlist. The seat
     * that opens next is whatever role the leaver held, and on a `both` Asset that
     * may be either — so Active Only is the wrong promise at exactly the moment the
     * waitlist takes over.
     *
     * The server already agreed: vh_venture_openness() computes `active_only` as
     * `$acceptsActive && ($isFull || $silentCapped)`, and since `$acceptsActive`
     * requires `!$isFull` that `$isFull` arm is dead — the flag is already false when
     * full. These renderers just never read it; they recomputed the rule locally and
     * reached the opposite answer.
     *
     * So the chip now answers "what kind of Asset is this" once it is full, and only
     * "what can you still join as" while it is raising. The PHP twin in index.php
     * carries the same two branches — change both.
     */
    partnerTypeChipHTML: function(v) {
        const declared = v.partner_types || 'both';
        let type = declared;

        const full = VH.card.isFull(v);
        if (!full && VH.card.silentCapped(v) && VH.card.takesActive(v)) {
            type = 'active';
        }

        const chip = VH.card.PARTNER_TYPE_CHIPS[type];
        if (!chip) return '';
        // While full, the stock tooltips ("is only looking for…") would be a claim
        // about a raise that has closed, so the chip explains the waitlist instead.
        const title = full
            ? 'This Asset is fully funded, so it is not taking new partners right now. '
              + 'Join the waitlist to be told the moment a seat opens.'
            : chip[1];
        return `<span class="vc-chip vc-chip--ptype vc-chip--ptype-${VH.card.esc(type)}" title="${VH.card.esc(title)}">${VH.card.esc(chip[0])}</span>`;
    },

    capacityHTML: function(v) {
        const b = VH.card.buckets(v);
        if (!b) return '';

        const isSample = VH.card.isShowcase(v);
        const sampleFigures = isSample
            && (v.showcase_raised_silent !== null && v.showcase_raised_silent !== undefined
                || v.showcase_raised_active !== null && v.showcase_raised_active !== undefined);
        if (!b.hasCap && !sampleFigures) return '';

        const money = VH.card.money;
        const pctOf = (filled, total) => total > 0 ? Math.min(100, Math.round((filled / total) * 100)) : 0;

        const row = (label, cls, filled, total, full) => `
            <div class="vc-cap-row${full ? ' vc-cap-row--full' : ''}">
                <span class="vc-cap-label">
                    <span class="vc-cap-dot vc-cap-dot--${cls}"></span>${label}
                </span>
                <span class="vc-cap-figs">${money(filled)} / ${money(total)}</span>
                <span class="vc-cap-state">${full ? 'FULL' : 'OPEN'}</span>
                <span class="vc-cap-track"><span class="vc-cap-fill vc-cap-fill--${cls}" style="width:${pctOf(filled, total)}%"></span></span>
            </div>`;

        const totalRaised = (b.raisedSilent || 0) + (b.raisedActive || 0);
        const partnerPool = (b.partnerPool || 0) || ((b.silentCap || 0) + (b.activeReserve || 0));
        const parts = [row('Partner', 'active', totalRaised, partnerPool, (partnerPool <= 0 || totalRaised >= partnerPool))];

        const title = isSample
            ? 'Example figures — a sample listing has no real partners.'
            : 'This Asset is using the single-partner model.';
        return `<div class="vc-capacity" title="${title}">${parts.join('')}</div>`;
    },

    silentFullNoteHTML: function(v) {
        if (!VH.card.silentCapped(v)) return '';
        const b = VH.card.buckets(v);
        return `<div class="vc-cap-note">
                    <strong>Silent Partnership Full</strong>
                    Remaining investment: ${VH.card.money(b.remaining)}.
                    ${VH.card.takesActive(v) ? 'Only active partners can join this Asset.' : ''}
                </div>`;
    },

    daysLeftHTML: function(v) {
        return VH.card.isShowcase(v)
            ? 'Example listing'
            : `${parseInt(v.days_left, 10) || 0} Days Left`;
    },

    roiLabel: function(v) {
        const min = parseFloat(v.expected_roi_min);
        const max = parseFloat(v.expected_roi_max);
        if (isNaN(min) || isNaN(max)) return '';
        const n = (x) => (x % 1 === 0 ? x.toFixed(0) : x.toFixed(1));
        return min === max ? `${n(min)}%` : `${n(min)}–${n(max)}%`;
    },

    roiHTML: function(v) {
        const range = VH.card.roiLabel(v);
        if (!range) return '';
        return `<span class="vc-meta-item" title="Expected return per year, as stated by the founder"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>${range} ROI</span>`;
    },

    primaryActionHTML: function(v) {
        const base = VH.getBasePath();
        const state = v.viewer_state;

        if (VH.card.isShowcase(v)) {
            return `<a href="${base}pages/join-venture.php?id=${v.id}" class="btn-join btn-join--sample" title="Walk through the join flow on this example — nothing is charged">Preview Join Flow</a>`;
        }

        if (state === 'selected') {
            const app = v.viewer_application_id ? `&application_id=${encodeURIComponent(v.viewer_application_id)}` : '';
            return `<a href="${base}pages/join-venture.php?id=${v.id}${app}" class="btn-join btn-join--pay">Pay &amp; Confirm</a>`;
        }
        if (state === 'waitlisted') {
            return `<a href="${base}admin/waitlist.php" class="btn-join btn-join--onwaitlist" title="You are on this Asset's waitlist — open your Waitlist page">On Waitlist</a>`;
        }
        if (state) {
            const label = VH.card.VIEWER_STATE_LABELS[state] || 'Unavailable';
            return `<span class="btn-join btn-join--state btn-join--${VH.card.esc(state)}">${VH.card.esc(label)}</span>`;
        }

        if (VH.card.isFull(v)) {
            // Out of room but still inside its listing period: somebody may yet
            // leave, so offer the waitlist rather than a dead "Fully Funded" chip.
            if (VH.card.waitlistOpen(v)) {
                return `<a href="${base}admin/waitlist.php?join=${v.id}" class="btn-join btn-join--waitlist" title="This Asset is fully funded. Join the waitlist and you are notified the moment a partner exits.">Join Waitlist</a>`;
            }
            // No active-partner fallback here on purpose. A funded Asset takes no more
            // applications — see vh_venture_openness(), which now refuses them server-side
            // too. The waitlist above is the only remaining way in.
            return '<span class="btn-join btn-join--state btn-join--full" title="This Asset has raised its full target">Fully Funded</span>';
        }

        if (VH.card.silentCapped(v)) {
            return `<a href="${base}pages/join-venture.php?id=${v.id}" class="btn-join btn-join--active" title="This Asset is full at the moment, but you can still join the waitlist.">Join Waitlist</a>`;
        }

        return `<a href="${base}pages/join-venture.php?id=${v.id}" class="btn-join">Co-Own</a>`;
    },

    actionsHTML: function(v, extraClass) {
        const base = VH.getBasePath();
        return `<div class="vc-actions${extraClass ? ' ' + extraClass : ''}">
                    ${VH.card.primaryActionHTML(v)}
                    <a href="${base}pages/venture-detail.php?id=${v.id}" class="btn-details">View Details</a>
                    ${VH.wishlist.buttonHTML(v.id, v.is_wishlisted)}
                </div>`;
    },

    // Grid card.
    gridHTML: function(v) {
        const e = VH.card.esc, money = VH.card.money;
        const pct = parseInt(v.progress_percent, 10) || 0;
        return `
            <div class="venture-card vh-vcard${VH.card.isShowcase(v) ? ' vh-vcard--sample' : ''}" data-category="${e(v.category)}" style="--vc-accent:${VH.card.accent(pct)}">
                ${VH.card.showcaseRibbonHTML(v)}
                <div class="vc-header">
                    <div class="vc-cover">${VH.card.coverInner(v)}</div>
                    <div class="vc-title-block">
                        <h3 class="vc-title">${e(v.title)}</h3>
                        <div class="vc-byline">${e(v.founder_name)}<span class="vc-sep">|</span>${e(v.industry)}</div>
                        <div class="vc-chips">${VH.card.partnerTypeChipHTML(v)}${VH.card.chipsHTML(v)}</div>
                    </div>
                </div>
                <div class="vc-stats">
                    <div class="vc-stat"><span class="vc-stat-value">${money(v.target_capital)}</span><span class="vc-stat-label">Target</span></div>
                    <div class="vc-stat vc-stat--center"><span class="vc-stat-value">${money(v.min_investment)}</span><span class="vc-stat-label">Min. Ticket</span></div>
                    <div class="vc-stat vc-stat--right"><span class="vc-stat-value">${money(v.founder_contribution)}</span><span class="vc-stat-label">Founder Invested</span></div>
                    <div class="vc-stat"><span class="vc-stat-value">${money(v.raised_capital)}</span><span class="vc-stat-label">Total Raised</span></div>
                    <div class="vc-stat vc-stat--remaining"><span class="vc-stat-value">${money(VH.card.remaining(v))}</span><span class="vc-stat-label">Left to Raise</span></div>
                    <div class="vc-stat vc-stat--funded"><span class="vc-stat-value">${pct}%</span><span class="vc-stat-label">Funded</span></div>
                    <div class="vc-progress"><span class="vc-progress-fill" style="width:${pct}%"></span></div>
                </div>
                ${VH.card.capacityHTML(v)}
                ${VH.card.silentFullNoteHTML(v)}
                <div class="vc-meta">
                    <span class="vc-meta-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>${parseInt(v.members_count, 10) || 0} Members</span>
                    <span class="vc-meta-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${VH.card.daysLeftHTML(v)}</span>${VH.card.roiHTML(v)}
                </div>
                ${VH.card.actionsHTML(v)}
            </div>`;
    },

    rowHTML: function(v) {
        const e = VH.card.esc, money = VH.card.money;
        const pct = parseInt(v.progress_percent, 10) || 0;
        return `
            <div class="vc-row vh-vcard${VH.card.isShowcase(v) ? ' vh-vcard--sample' : ''}" style="--vc-accent:${VH.card.accent(pct)}">
                ${VH.card.showcaseRibbonHTML(v)}
                <div class="vc-cover vc-row-cover">${VH.card.coverInner(v)}</div>
                <div class="vc-row-main">
                    <h3 class="vc-title">${e(v.title)}</h3>
                    <div class="vc-byline">${e(v.founder_name)}<span class="vc-sep">|</span>${e(v.industry)}</div>
                    <div class="vc-chips">${VH.card.partnerTypeChipHTML(v)}${VH.card.chipsHTML(v)}
                        <span class="vc-loc">${parseInt(v.members_count, 10) || 0} Members</span>
                        <span class="vc-loc">${VH.card.daysLeftHTML(v)}</span>
                        ${VH.card.roiLabel(v) ? `<span class="vc-loc">${VH.card.roiLabel(v)} ROI</span>` : ""}
                    </div>
                </div>
                <div class="vc-row-figs">
                    <div class="vc-stat"><span class="vc-stat-value">${money(v.min_investment)}</span><span class="vc-stat-label">Min. Ticket</span></div>
                    <div class="vc-stat"><span class="vc-stat-value">${money(v.target_capital)}</span><span class="vc-stat-label">Target</span></div>
                    <div class="vc-stat"><span class="vc-stat-value">${money(VH.card.remaining(v))}</span><span class="vc-stat-label">Left to Raise</span></div>
                    <div class="vc-row-progress">
                        <div class="vc-stat vc-stat--funded"><span class="vc-stat-value">${pct}%</span><span class="vc-stat-label">Funded</span></div>
                        <div class="vc-progress"><span class="vc-progress-fill" style="width:${pct}%"></span></div>
                        ${VH.card.capacityHTML(v)}
                    </div>
                </div>
                ${VH.card.actionsHTML(v, 'vc-row-actions')}
            </div>`;
    }
};

/* ── EQUITY CALCULATOR ──
 *
 * What a pledge of a given size actually buys, worked out from the founder's
 * "Equity & Salary per Role" table. Three rules do all of it, and each one
 * comes straight from how that table is worded on the listing:
 *
 *  - Every percentage in it is quoted PER MEMBER AT THE MINIMUM INVESTMENT —
 *    the note printed under the table everywhere it appears. That ticket is the
 *    unit the whole calculation is measured in.
 *
 *  - An active partner's stated equity buys ONE ticket of the active role, and
 *    the minimum investment is therefore that partner's active limit. Capital
 *    above it is not buying more of the active slot: it is extra capital, so it
 *    is recognised as silent (capital-only) partnership and earns the SILENT
 *    rate on top. On a venture whose minimum is ₹3L, quoting 15% active and
 *    6.7% silent, ₹4L as an active partner is 15% + 2.23% = 17.23% — not 20%.
 *    (A venture that lists no silent role has nothing for the excess to be
 *    recognised as, so there the investment half simply scales.)
 *
 *  - The operations half never scales at all. It is paid for the work an active
 *    partner does, not for capital, so it is earned once whatever they bring.
 *
 * Silent partners have no such limit — their whole commitment is capital, so it
 * earns the silent rate pro rata. Everything that shows a partner their equity
 * calls this — the join flow and the venture page — so the two can never quote
 * different numbers.
 */
VH.equity = {
    pct: function(n) {
        const r = Math.round((Number(n) || 0) * 100) / 100;
        return (r % 1 === 0 ? r.toFixed(0) : String(r)) + '%';
    },

    inr: function(n) {
        return '₹' + Math.round(Number(n) || 0).toLocaleString('en-IN');
    },

    // A blank percentage is "not agreed yet", which is not the same as 0%.
    rate: function(x) {
        if (x === null || x === undefined || x === '') return null;
        const n = parseFloat(x);
        return isNaN(n) ? null : n;
    },

    rates: function(v) {
        return {
            ticket:       Math.max(0, parseFloat(v.min_investment) || 0),
            activeInvest: VH.equity.rate(v.active_equity_percent),
            activeOps:    VH.equity.rate(v.active_ops_equity_percent),
            silentInvest: VH.equity.rate(v.silent_equity_percent)
        };
    },

    /** Does this venture state enough for any of it to be worked out at all? */
    isStated: function(v) {
        const r = VH.equity.rates(v);
        return r.ticket > 0
            && (r.activeInvest !== null || r.activeOps !== null || r.silentInvest !== null);
    },

    /**
     * How much of an active partner's commitment counts as active capital. One
     * ticket — the minimum investment the founder quoted the active percentage
     * against. Infinite on a venture with no silent role, where there is no
     * silent side for anything above it to be recognised as.
     */
    activeLimit: function(v) {
        const r = VH.equity.rates(v);
        if (String(v.partner_types || 'both') === 'active') return Infinity;
        return r.ticket;
    },

    forPledge: function(v, role, amount) {
        const r = VH.equity.rates(v);
        const amt = Math.max(0, Math.round(Number(amount) || 0));
        const isActive = String(role) === 'active';

        const limit = isActive ? VH.equity.activeLimit(v) : 0;
        const activeCapital = isActive ? Math.min(amt, limit) : 0;

        const out = {
            role:          isActive ? 'active' : 'silent',
            amount:        amt,
            ticket:        r.ticket,
            activeLimit:   limit,
            activeCapital: activeCapital,
            silentCapital: amt - activeCapital,
            activeEquity:  0,
            silentEquity:  0,
            opsEquity:     0,
            total:         0,
            capped:        false,
            missingRate:   false,
            known:         false
        };

        // Without a minimum ticket the percentages have nothing to be measured
        // against, so there is no honest number to show.
        if (amt <= 0 || r.ticket <= 0) return out;

        const share = (capital, rate) => {
            if (capital <= 0) return 0;
            if (rate === null) { out.missingRate = true; return 0; }
            out.known = true;
            return (capital / r.ticket) * rate;
        };

        out.activeEquity = share(out.activeCapital, r.activeInvest);
        out.silentEquity = share(out.silentCapital, r.silentInvest);

        if (isActive && r.activeOps !== null) {
            out.opsEquity = r.activeOps;
            out.known = true;
        }

        let total = out.activeEquity + out.silentEquity + out.opsEquity;
        if (total > 100) { total = 100; out.capped = true; }
        out.total = total;
        return out;
    },

    /**
     * What a member who has ALREADY JOINED holds.
     *
     * `equity_terms` is the snapshot the server stamped on their venture_members row at
     * the moment they paid (migration step 53). Everything else on the listing — the
     * minimum ticket especially — stays editable by the founder, so without the snapshot
     * this figure moved under people: dropping the minimum from 50,000 to 25,000 took a
     * partner who had paid 50,000 from 6% to 12%.
     *
     * A row with no snapshot falls back to the venture's live terms, which is precisely
     * the old behaviour, so nothing that predates the column breaks. This is a READER of
     * the server's snapshot, like VH.card.buckets() — it re-runs forPledge() rather than
     * carrying a second copy of the rule.
     */
    held: function(member, venture) {
        if (!member) return null;
        const role   = member.role || member.partner_type || 'silent';
        const amount = member.invested_amount != null ? member.invested_amount : member.my_investment;
        const terms  = member.equity_terms || venture;
        if (!terms) return null;
        return VH.equity.forPledge(terms, role, amount);
    },

    /** The frozen figure as a string, '' when it cannot be worked out. */
    heldPct: function(member, venture) {
        const e = VH.equity.held(member, venture);
        return e && e.known ? VH.equity.pct(e.total) : '';
    },

    /** The headline figure on its own, for summary rows. '' when unknown. */
    totalLabel: function(v, role, amount) {
        const e = VH.equity.forPledge(v, role, amount);
        return e.known ? VH.equity.pct(e.total) : '';
    },

    summaryHTML: function(v, role, amount) {
        const e = VH.equity.forPledge(v, role, amount);
        if (!e.known) return '';

        const pct = VH.equity.pct;
        const inr = VH.equity.inr;
        const esc = VH.card.esc;
        const rates = VH.equity.rates(v);

        const line = (label, value) =>
            `<li><span>${label}</span><strong>${esc(value)}</strong></li>`;

        const lines = [];
        if (e.activeEquity > 0) {
            // Named for the column it comes from on the listing's equity table,
            // so the two read as the same figure rather than two figures.
            lines.push(line(`Investment equity on ${esc(inr(e.activeCapital))}`, pct(e.activeEquity)));
        }
        if (e.silentEquity > 0) {
            lines.push(line(
                e.role === 'active'
                    ? `Silent equity on the extra ${esc(inr(e.silentCapital))} <small>(above the active limit)</small>`
                    : `Investment equity on ${esc(inr(e.silentCapital))}`,
                pct(e.silentEquity)
            ));
        }
        if (e.opsEquity > 0) {
            lines.push(line('Operations equity <small>(for the work you do)</small>', pct(e.opsEquity)));
        }

        const notes = [];
        if (e.role === 'active' && e.silentCapital > 0) {
            notes.push(`<p class="eq-calc-flag">The active partner equity is quoted against ${esc(inr(e.ticket))},
                this Asset's minimum investment, so that is the active limit for one partner. The extra
                ${esc(inr(e.silentCapital))} is recognised as silent (capital-only) partnership and earns the
                silent rate on top.</p>`);
        }
        if (e.missingRate) {
            notes.push(`<p class="eq-calc-flag">The founder hasn't stated an equity percentage for part of this
                commitment, so it isn't counted above — agree that share with them directly.</p>`);
        }
        if (e.capped) {
            notes.push(`<p class="eq-calc-flag">This works out above a 100% stake, so it is shown capped.
                Confirm the real split with the founder before you commit.</p>`);
        }

        const per = [];
        if (e.role === 'active' && (rates.activeInvest !== null || rates.activeOps !== null)) {
            // The founder's own headline figure for the role: what one ticket
            // buys, investment and operations together, exactly as the listing's
            // equity table prints it.
            const stated = (rates.activeInvest || 0) + (rates.activeOps || 0);
            per.push(isFinite(e.activeLimit)
                ? `${pct(stated)} for the first ${inr(rates.ticket)} as an active partner`
                : `${pct(stated)} per ${inr(rates.ticket)} as an active partner`);
        }
        if (rates.silentInvest !== null && (e.role === 'silent' || e.silentCapital > 0)) {
            per.push(`${pct(rates.silentInvest)} per ${inr(rates.ticket)} as a silent partner`);
        }

        return `<div class="eq-calc">
            <div class="eq-calc-top">
                <span class="eq-calc-title">Equity you'd receive</span>
                <strong class="eq-calc-total">${esc(pct(e.total))}</strong>
            </div>
            <ul class="eq-calc-lines">${lines.join('')}</ul>
            ${notes.join('')}
            <p class="eq-calc-note">Worked out from the founder's equity table${per.length ? ' — ' + esc(per.join(', ')) : ''}.
                Percentages are indicative; the final split is signed offline with the founder.</p>
        </div>`;
    },

    /**
     * The rule in words, for a listing whose founder never filled in the equity
     * table. No figure can honestly be quoted there, but the rule that decides
     * it can still be explained — and has to be, because otherwise the whole
     * feature is simply invisible on such a listing and a partner committing
     * above the minimum has no idea the excess is treated differently.
     *
     * The minimum investment is real and always known, so it is named; every
     * percentage is left out rather than guessed at.
     */
    explainerHTML: function(v, role) {
        const esc = VH.card.esc;
        const r = VH.equity.rates(v);
        const isActive = String(role) === 'active';
        const perTicket = r.ticket > 0 ? ' (' + VH.equity.inr(r.ticket) + ')' : '';
        // Only where a silent role exists for the excess to be recognised as.
        const hasSilentRole = String(v.partner_types || 'both') !== 'active';

        const rule = (isActive && hasSilentRole)
            ? `The active partner percentage is quoted per minimum investment${esc(perTicket)}, so that minimum is
               the active limit for one partner. Anything you commit above it is extra capital — it is recognised as
               <strong>silent (capital-only) partnership</strong> and earns the silent partner rate on top.`
            : isActive
                ? `The active partner percentage is quoted per minimum investment${esc(perTicket)}. This Asset has no
                   silent role, so a larger commitment scales that investment share; the operations share is earned
                   once, for the work you do, whatever you bring.`
                : `The silent partner percentage is quoted per minimum investment${esc(perTicket)}, and your whole
                   commitment is capital, so it earns that rate pro rata.`;

        return `<div class="eq-calc eq-calc--pending">
            <div class="eq-calc-top"><span class="eq-calc-title">How your equity would be worked out</span></div>
            <p class="eq-calc-rule">${rule}</p>
            <p class="eq-calc-flag">This founder hasn't published their equity percentages yet, so the exact share
                can't be calculated here. Agree it with them directly before you commit — the split is signed
                offline either way.</p>
        </div>`;
    },

    /**
     * The floor a commitment has to clear. The founder's minimum investment is
     * what every quoted percentage is measured against, so an amount below it
     * buys nothing the table describes — and the join flow would refuse it.
     * Both roles share the one minimum; only the ceiling differs by role.
     */
    minimumFor: function(v) {
        return VH.equity.rates(v).ticket;
    },

    /**
     * Below the founder's minimum there is no honest percentage to quote, so say
     * what the minimum is instead of quoting one. This used to print a number:
     * a listing with a 50K minimum answered "4%" for 40,000, which is a share
     * the visitor could never actually buy — the join flow rejects the amount a
     * step later. Stating the floor is the same information, minus the promise.
     */
    belowMinimumHTML: function(v, role, amount) {
        const esc = VH.card.esc;
        const min = VH.equity.minimumFor(v);
        const isActive = String(role) === 'active';
        const roleWord = isActive ? 'an active partner' : 'a silent partner';
        return `<div class="eq-calc eq-calc--pending">
            <div class="eq-calc-top"><span class="eq-calc-title">Below the minimum commitment</span></div>
            <p class="eq-calc-rule">This Asset's minimum investment is
                <strong>${esc(VH.equity.inr(min))}</strong>, and every percentage in the table above is quoted
                against it. ${esc(VH.equity.inr(amount))} is under that, so there is no share to work out yet.</p>
            <p class="eq-calc-flag">Enter ${esc(VH.equity.inr(min))} or more to see what your commitment would buy
                as ${roleWord}.</p>
        </div>`;
    },

    /**
     * Paint into a container. Shows the calculation where the founder stated
     * enough for one, the minimum where the amount is under it, the rule in
     * words where the percentages are missing, and nothing at all when there is
     * simply no amount to work on yet.
     */
    paintInto: function(el, v, role, amount) {
        if (typeof el === 'string') el = document.getElementById(el);
        if (!el) return;
        let html = '';
        if (v) {
            const amt = Math.max(0, Math.round(Number(amount) || 0));
            const min = VH.equity.minimumFor(v);
            // An empty box is not a rejected amount — it stays silent, as before.
            html = (amt > 0 && min > 0 && amt < min)
                ? VH.equity.belowMinimumHTML(v, role, amt)
                : VH.equity.isStated(v)
                    ? VH.equity.summaryHTML(v, role, amount)
                    : VH.equity.explainerHTML(v, role);
        }
        el.innerHTML = html;
        el.classList.toggle('hidden', html === '');
    }
};

// ── MODAL HANDLING ──
VH.modal = {
    open: function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },
    close: function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
};

// ── AUTHENTICATION HELPERS ──
VH.auth = {
    getUser: function() {
        const u = localStorage.getItem('vh_user');
        if (!u) return null;
        try {
            return JSON.parse(u);
        } catch (e) {
            return null;
        }
    },
    setUser: function(user) {
        localStorage.setItem('vh_user', JSON.stringify(user));
    },
    clearUser: function() {
        localStorage.removeItem('vh_user');
    },
    getRedirectUrl: function(user) {
        if (user && user.role === 'admin') {
            return VH.getBasePath() + 'admin/admin.php';
        }
        return VH.getBasePath() + 'admin/dashboard.php';
    },
    redirectToDashboard: function(user) {
        if (VH.auth.bounced()) return;
        window.location.href = VH.auth.getRedirectUrl(user || VH.auth.getUser());
    },

    syncSession: async function() {
        let response;
        try {
            response = await fetch(VH.getBasePath() + 'api/auth.php?action=me', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
        } catch (e) {
            console.warn('Session sync failed', e);
            return undefined;
        }

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            return undefined;
        }

        if (data && data.success && data.user) {
            VH.auth.setUser(data.user);
            return data.user;
        }
        if (response.status === 401 || (data && data.success === false)) {
            VH.auth.clearUser();
            return null;
        }
        return undefined;
    },

    bounced: function() {
        const KEY = 'vh_auth_bounce';
        const now = Date.now();
        let log = [];
        try {
            log = JSON.parse(sessionStorage.getItem(KEY) || '[]');
        } catch (e) {
            log = [];
        }
        log = log.filter(t => now - t < 5000);
        log.push(now);
        try {
            sessionStorage.setItem(KEY, JSON.stringify(log));
        } catch (e) { /* private mode — just skip the guard */ }

        if (log.length <= 3) return false;
        try { sessionStorage.removeItem(KEY); } catch (e) {}
        VH.auth.clearUser();
        console.warn('VH: auth redirect loop detected — stopping and clearing the cached user.');
        return true;
    },

    bindLogout: function() {
        if (VH.auth._logoutBound) return;
        VH.auth._logoutBound = true;
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#sidebarLogout, #navLogoutBtn, [data-logout]');
            if (!btn) return;

            e.preventDefault();
            VH.auth.logout();
        });
    },
    logout: async function() {
        if (VH.auth._loggingOut) return;
        VH.auth._loggingOut = true;

        try {
            await fetch(VH.getBasePath() + 'api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'logout' })
            });
        } catch (e) {
            console.warn('Logout API failed', e);
        }
        VH.auth.clearUser();
        VH.toast.success('Logged out successfully.');
        setTimeout(() => {
            window.location.href = VH.getBasePath() + 'users/auth.php';
        }, 800);
    },
    redirectToLogin: function() {
        if (VH.auth.bounced()) return;
        window.location.href = VH.getBasePath() + 'users/auth.php';
    },
    applyRoleUI: function(user) {
        if (!user) return;
        const adminLinks = document.querySelectorAll('[data-admin-only]');
        adminLinks.forEach(el => {
            el.style.display = user.role === 'admin' ? '' : 'none';
        });
        const roleEls = document.querySelectorAll('[data-user-role]');
        roleEls.forEach(el => {
            el.textContent = user.role === 'admin' ? 'Administrator' : 'Active Member';
        });
    }
};

VH.auth.bindLogout();

// ── CLIENT-SIDE GATEKEEPER ROUTING ──
document.addEventListener("DOMContentLoaded", async () => {
    // The site serves clean URLs — /pages/browse, not /pages/browse.php (see the
    // .htaccess at the project root). Strip the extension before matching so this
    // list works whichever form the visitor arrived on: an old .php link that has
    // not yet been redirected, or the clean URL. Matching the extension alone was
    // enough to make every public page look private and bounce signed-out
    // visitors to the login screen.
    const path = window.location.pathname.replace(/\.php$/, '');
    const isAuthPage = path.endsWith('/users/auth') || path.endsWith('/users/reset-password');
    const isIndex = path.endsWith('/index') || path.endsWith('/') || path.endsWith('/setup');
    const isBrowse = path.endsWith('/pages/browse') || path.endsWith('/pages/venture-detail');
    const isPublicInfoPage = path.endsWith('/pages/about') || path.endsWith('/pages/contact');

    const cachedUser = VH.auth.getUser();
    const verified = await VH.auth.syncSession();

    let user = verified === undefined ? cachedUser : verified;

    if (!!cachedUser !== !!user) {
        document.dispatchEvent(new CustomEvent('vh:auth-changed', { detail: { user: user } }));
    }

    if (!user && !isAuthPage && !isIndex && !isBrowse && !isPublicInfoPage) {
        VH.auth.redirectToLogin();
        return;
    }

    if (user) {
        VH.auth.applyRoleUI(user);
    }

    if (user && isAuthPage && !path.endsWith('/users/reset-password')) {
        VH.auth.redirectToDashboard(user);
    }

    const sidebarUser = document.querySelector('.sidebar-user');
    if (sidebarUser && !sidebarUser.dataset.profileLinked) {
        sidebarUser.dataset.profileLinked = '1';
        sidebarUser.style.cursor = 'pointer';
        sidebarUser.setAttribute('title', 'View your profile');
        sidebarUser.addEventListener('click', () => {
            window.location.href = VH.getBasePath() + 'users/profile.php';
        });
    }
});

VH.deleteAccountRefund = {
    /**
     * A founder cannot delete their account while a venture of theirs is still
     * running, or while a refund one of their ventures owes is still unpaid.
     * The server refuses either way (api/auth.php?action=deactivate); this asks
     * first so the dialog says why up front instead of letting someone type
     * DELETE, fill in their bank details and only then be turned away.
     *
     * Lives here rather than in profile.js/dashboard.js because the dialog
     * exists on both pages — the delete warning itself was already duplicated
     * once and drifted, which is how the dashboard kept the old wording.
     */
    gate() {
        const modal = document.getElementById("deleteAccountModal");
        const confirmBtn = document.getElementById("confirmDeleteAccountBtn");
        if (!modal || !confirmBtn) return;

        const typeBox = document.getElementById("deleteAccountConfirm");
        const typeGroup = typeBox ? typeBox.closest(".form-group") : null;
        const refundBlock = document.getElementById("deleteRefundBlock");
        const consequences = modal.querySelector(".delete-consequences");
        const warning = modal.querySelector(".delete-final-warning");
        const host = warning ? warning.parentNode : modal.querySelector(".modal-header").nextElementSibling;

        let notice = document.getElementById("deleteBlockedNotice");
        if (!notice && host) {
            notice = document.createElement("div");
            notice.id = "deleteBlockedNotice";
            notice.className = "delete-blocked-notice hidden";
            host.insertBefore(notice, host.firstChild);
        }

        const setBlocked = (blocked, html) => {
            if (notice) {
                notice.innerHTML = html || "";
                notice.classList.toggle("hidden", !blocked);
            }
            confirmBtn.disabled = blocked;
            confirmBtn.classList.toggle("btn--disabled", blocked);
            if (warning) warning.classList.toggle("hidden", blocked);
            if (consequences) consequences.classList.toggle("hidden", blocked);
            if (typeGroup) typeGroup.classList.toggle("hidden", blocked);
            if (refundBlock) refundBlock.classList.toggle("hidden", blocked);
        };

        // Closed until the answer arrives: opening the dialog must never show a
        // usable Delete button to someone the server is going to refuse.
        setBlocked(true, '<p class="delete-blocked-checking">Checking your Ventures…</p>');

        fetch(VH.getBasePath() + "api/auth.php?action=deletion_eligibility", { credentials: "same-origin" })
            .then(r => r.json())
            .then(res => {
                if (!res.success) { setBlocked(false); return; }
                const b = res.blockers || {};
                if (b.can_delete) { setBlocked(false); return; }

                const esc = VH.card.esc;
                const rows = (b.ventures || []).map(v =>
                    `<li><strong>${esc(v.title)}</strong> <span>${esc(String(v.status).replace(/_/g, " "))}`
                    + `${v.members_count > 0 ? ` · ${v.members_count} member${v.members_count === 1 ? "" : "s"}` : ""}</span></li>`
                ).join("");

                let html = '<p class="delete-blocked-title">You can\'t delete your account yet.</p>';
                if (b.venture_count > 0) {
                    html += `<p>These Assets are still running. Close each one from your dashboard first —
                             partners are committed to them, and once an Asset is closed any refunds it owes
                             are opened automatically.</p><ul class="delete-blocked-list">${rows}</ul>`;
                }
                if (b.pending_refunds > 0) {
                    html += `<p>${b.pending_refunds === 1 ? "One refund" : b.pending_refunds + " refunds"}
                             totalling ₹${Number(b.refund_total || 0).toLocaleString("en-IN")} from your Ventures
                             ${b.pending_refunds === 1 ? "is" : "are"} still being paid out by our team. Your account
                             can be deleted once ${b.pending_refunds === 1 ? "it has" : "they have"} been settled.</p>`;
                }
                setBlocked(true, html);
            })
            .catch(() => {
                // Never fail open — the server would refuse anyway, and a Delete
                // button that looks live but errors is worse than a closed one.
                setBlocked(true, '<p class="delete-blocked-title">Couldn\'t check your Ventures just now.</p>'
                    + '<p>Please close this and try again in a moment.</p>');
            });
    },

    init() {
        const toggle = document.getElementById("deleteRequestRefund");
        const fields = document.getElementById("deleteBankFields");
        if (!toggle || !fields) return;

        toggle.addEventListener("change", () => {
            fields.classList.toggle("hidden", !toggle.checked);
        });

        fetch(VH.getBasePath() + "api/auth.php?action=refundable_total", { credentials: "same-origin" })
            .then(r => r.json())
            .then(res => {
                const label = document.getElementById("deleteRefundAmount");
                const block = document.getElementById("deleteRefundBlock");
                if (!res.success) return;

                if (res.refundable > 0) {
                    if (label) label.textContent =
                        "You've paid ₹" + Number(res.refundable).toLocaleString("en-IN") +
                        " in commitment fees that you can claim back.";
                } else {
                    if (block) block.classList.add("hidden");
                }
            })
            .catch(() => {});
    },

    collect() {
        const toggle = document.getElementById("deleteRequestRefund");
        if (!toggle || !toggle.checked) return { requested: false, payload: {} };

        const holder = (document.getElementById("deleteBankHolder").value || "").trim();
        const number = (document.getElementById("deleteBankNumber").value || "").trim();
        const ifsc = (document.getElementById("deleteBankIfsc").value || "").trim().toUpperCase();
        const bankName = (document.getElementById("deleteBankName").value || "").trim();

        if (!holder || !number || !ifsc) {
            return { error: "Please fill in the account holder name, account number and IFSC code." };
        }
        if (!/^\d{6,20}$/.test(number)) {
            return { error: "Account number must be 6-20 digits." };
        }
        if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) {
            return { error: "Please enter a valid IFSC code (e.g. HDFC0001234)." };
        }

        return {
            requested: true,
            payload: {
                request_refund: true,
                bank_account_name: holder,
                bank_account_number: number,
                bank_ifsc: ifsc,
                bank_name: bankName
            }
        };
    }
};

/**
 * Eased window scroll, shared by the create-venture and join-venture wizards.
 *
 * Both used to swap step panels without scrolling at all, so a visitor who had
 * scrolled down to the buttons landed mid-way through the next step and had to
 * scroll back up to the first field.
 */
VH.scroll = {
    reducedMotion: function () {
        return window.matchMedia
            && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    },

    toY: function (targetY, duration) {
        const self = this;
        return new Promise(function (resolve) {
            const startY = window.scrollY;
            const distance = Math.max(0, targetY) - startY;
            if (self.reducedMotion() || Math.abs(distance) < 2) {
                window.scrollTo({ top: Math.max(0, targetY), behavior: "auto" });
                resolve();
                return;
            }
            const started = performance.now();
            (function frame(now) {
                const t = Math.min(1, (now - started) / (duration || 420));
                const eased = 1 - Math.pow(1 - t, 3);   // easeOutCubic
                window.scrollTo({ top: startY + distance * eased, behavior: "auto" });
                if (t < 1) requestAnimationFrame(frame);
                else resolve();
            })(performance.now());
        });
    },

    /**
     * An element's resting position in the document — where it would sit if the
     * page were scrolled to the top.
     *
     * A pinned position:sticky element is *painted* at its sticky offset, and
     * reports that offset through getBoundingClientRect() AND through offsetTop
     * (offsetTop is not a way around this — Chrome tracks the shift in both). So
     * `rect.top + scrollY` on a stuck element resolves to roughly the current
     * scroll position, and toY() then computes a distance of a few pixels:
     * clicking Next appeared not to scroll at all. Dropping the element out of
     * sticky for one synchronous measurement gives its place in the flow.
     */
    documentTop: function (el) {
        if (getComputedStyle(el).position !== 'sticky') {
            return el.getBoundingClientRect().top + window.scrollY;
        }
        // !important, because a stylesheet rule that declares the sticky with
        // !important would otherwise outrank a plain inline style and the
        // measurement would silently come back stuck.
        const prevValue = el.style.getPropertyValue('position');
        const prevPriority = el.style.getPropertyPriority('position');
        el.style.setProperty('position', 'static', 'important');
        const y = el.getBoundingClientRect().top + window.scrollY;
        if (prevValue) el.style.setProperty('position', prevValue, prevPriority);
        else el.style.removeProperty('position');
        return y;
    },

    // Scrolls an element to just under the fixed navbar, so a wizard step always
    // starts at its first field rather than wherever the last one ended.
    toElementTop: function (el, extraGap) {
        if (!el) return Promise.resolve();
        const nav = document.querySelector(".navbar");
        const navH = nav ? nav.getBoundingClientRect().height : 0;
        const top = this.documentTop(el);
        return this.toY(top - navH - (extraGap === undefined ? 18 : extraGap));
    }
};
