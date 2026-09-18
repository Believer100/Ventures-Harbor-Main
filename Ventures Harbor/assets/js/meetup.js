/* VENTURES HARBOR — MEETUPS LOGIC (meetup.js) */

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    // Update Sidebars/Avatars
    updateHeaderAndSidebar(user);

    // Fetch and Load Meetups
    fetchAndLoadMeetups(user.id);

    // Fetch Ventures to populate schedule modal dropdown
    fetchVenturesForDropdown(user.id);

    // Tab panels switcher for meetups
    const tabBtns = document.querySelectorAll(".tab-btn[data-tab]");
    const panels = document.querySelectorAll(".tab-panel[data-panel]");
    tabBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            tabBtns.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");

            const target = btn.dataset.tab;
            panels.forEach(p => {
                if (p.dataset.panel === target) {
                    p.classList.add("active");
                } else {
                    p.classList.remove("active");
                }
            });
        });
    });

    // Schedule form submission handler
    const scheduleForm = document.getElementById("scheduleForm");
    if (scheduleForm) {
        scheduleForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const btn = scheduleForm.querySelector("button[type='submit']");
            btn.textContent = "Scheduling...";
            btn.disabled = true;

            const ventureId = document.getElementById("mFormVenture").value;
            const title = document.getElementById("mFormTitle").value.trim();
            const date = document.getElementById("mFormDate").value;
            const time = document.getElementById("mFormTime").value;
            const locationType = document.querySelector('input[name="mLocType"]:checked').value;
            const location = document.getElementById("mFormLoc").value.trim();
            const meetingLink = document.getElementById("mFormLink").value.trim();
            const notes = document.getElementById("mFormNotes").value.trim();

            // The server enforces both of these; this is only so the person is told
            // before the round trip, not after it.
            if (locationType === 'online' && !meetingLink) {
                VH.toast.error("Please paste the meeting link (Zoom, Google Meet, or similar).");
                btn.textContent = "Schedule Meetup";
                btn.disabled = false;
                return;
            }

            try {
                const response = await fetch("../api/meetups.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        action: "create",
                        user_id: user.id,
                        venture_id: parseInt(ventureId),
                        title,
                        date,
                        time,
                        location_type: locationType,
                        location: locationType === 'physical' ? location : '',
                        meeting_link: locationType === 'online' ? meetingLink : '',
                        notes
                    })
                });
                const res = await response.json();
                if (res.success) {
                    VH.toast.success("Meetup scheduled successfully!");
                    VH.modal.close("scheduleModal");
                    scheduleForm.reset();
                    // reset() re-checks Physical, which may be the locked option —
                    // re-apply the venture's mode instead of assuming offline.
                    applyMeetupMode(currentMeetupMode);
                    fetchAndLoadMeetups(user.id);
                } else {
                    VH.toast.error(res.message || "Failed to schedule meetup.");
                    // The dialog was stale — an eleventh partner joined, or the
                    // Asset filled, while it sat open. Re-read the mode so the
                    // Physical card locks instead of being refused a second time.
                    if (res.onlineOnly) loadMeetupMode(ventureId);
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error scheduling meetup.");
            } finally {
                btn.textContent = "Schedule Meetup";
                btn.disabled = false;
            }
        });
    }

    // Mobile nav sidebar hamburger
    const navHamburger = document.getElementById("navHamburger");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");
    const sidebarCollapseBtn = document.getElementById("sidebarCollapseBtn");

    if (navHamburger && sidebar) {
        navHamburger.addEventListener("click", () => {
            sidebar.classList.add("open");
            if (sidebarOverlay) sidebarOverlay.classList.add("active");
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", () => {
            sidebar.classList.remove("open");
            sidebarOverlay.classList.remove("active");
        });
    }

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener("click", () => {
            sidebar.classList.toggle("sidebar--collapsed");
            const pageWrapper = document.querySelector(".page-wrapper");
            if (pageWrapper) pageWrapper.classList.toggle("sidebar-collapsed");
        });
    }

    // Avatar Dropdown
    const avatarBtn = document.getElementById("avatarBtn");
    const navDropdown = document.getElementById("navDropdown");
    if (avatarBtn && navDropdown) {
        avatarBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            navDropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => {
            navDropdown.classList.remove("open");
        });
    }
});

function updateHeaderAndSidebar(user) {
    const initials = user.avatar || user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();

    const sidebarAvatar = document.getElementById("sidebarAvatar");
    const sidebarUserName = document.getElementById("sidebarUserName");
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");

    if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, user);
    if (sidebarUserName) sidebarUserName.textContent = user.name;
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);
    if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];
}

// Global location fields toggler
window.toggleLocFields = function() {
    const locType = document.querySelector('input[name="mLocType"]:checked').value;
    const physicalGroup = document.getElementById("physicalLocGroup");
    const onlineGroup = document.getElementById("onlineLocGroup");
    if (locType === 'online') {
        physicalGroup.classList.add("hidden");
        onlineGroup.classList.remove("hidden");
    } else {
        physicalGroup.classList.remove("hidden");
        onlineGroup.classList.add("hidden");
    }
};

/* ---- Offline meetup cap -------------------------------------------------
   An Asset may only meet in person while it is small enough to fit in a room.
   The rule itself lives on the server (vh_meetup_mode); this is a reader of
   that answer, the same way VH.card.buckets() reads capital_buckets rather
   than recomputing a split the browser cannot see. Fails OPEN to online-only
   is wrong here — an unreachable server should not silently forbid a venue —
   so an error simply leaves the dialog as it was and lets the create action
   refuse authoritatively. */
let currentMeetupMode = null;

function applyMeetupMode(mode) {
    const card = document.getElementById("mLocPhysicalCard");
    const note = document.getElementById("mLocModeNote");
    const physicalRadio = document.querySelector('input[name="mLocType"][value="physical"]');
    const onlineRadio = document.querySelector('input[name="mLocType"][value="online"]');
    if (!card || !note || !physicalRadio || !onlineRadio) return;

    const onlineOnly = !!(mode && mode.online_only);

    card.classList.toggle("is-locked", onlineOnly);
    physicalRadio.disabled = onlineOnly;

    if (onlineOnly) {
        onlineRadio.checked = true;
        note.textContent = mode.message;
        note.classList.remove("hidden");
    } else {
        note.classList.add("hidden");
        note.textContent = "";
    }

    window.toggleLocFields();
}

async function loadMeetupMode(ventureId) {
    if (!ventureId) {
        currentMeetupMode = null;
        applyMeetupMode(null);
        return;
    }
    try {
        const response = await fetch(`../api/meetups.php?action=meetup_mode&venture_id=${encodeURIComponent(ventureId)}`);
        const res = await response.json();
        if (res.success) {
            currentMeetupMode = res.mode;
            applyMeetupMode(res.mode);
        }
    } catch (e) {
        console.error(e);
    }
}

async function fetchAndLoadMeetups(userId) {
    try {
        const response = await fetch(`../api/meetups.php?action=list&user_id=${userId}`);
        const res = await response.json();

        if (res.success) {
            const meetups = res.data;
            const upcoming = meetups.filter(m => m.status === 'upcoming');
            const past = meetups.filter(m => m.status === 'past');
            const attendedCount = meetups.filter(m => m.rsvpStatus === 'accepted').length;

            // Update stats
            document.getElementById("statTotalMeetups").textContent = meetups.length;
            document.getElementById("statUpcomingMeetups").textContent = upcoming.length;
            document.getElementById("statAttendedMeetups").textContent = attendedCount;

            renderMeetupPanel(upcoming, "upcoming", userId);
            renderMeetupPanel(past, "past", userId);

            // Draw mini calendar based on upcoming meetups
            renderMiniCalendar(upcoming);
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error fetching meetup data.");
    }
}

function renderMeetupPanel(meetups, panelName, userId) {
    const panel = document.getElementById(`${panelName}Panel`);
    if (!panel) return;

    if (!meetups || meetups.length === 0) {
        panel.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">${VH.icon("calendar", 30)}</div>
                <h4>No ${panelName === 'upcoming' ? 'Upcoming' : 'Past'} Meetups</h4>
                <p>${panelName === 'upcoming' ? 'Schedule a session or wait for co-partners to invite you.' : 'No sessions recorded yet.'}</p>
            </div>
        `;
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:1rem;">';
    meetups.forEach(m => {
        const dateObj = new Date(m.date);
        const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const dateStr = dateObj.toLocaleDateString('en-IN', options);

        const isOnline = m.location_type === 'online';
        const attendeesStr = m.attendees.map(a => a.name).join(', ') || 'No attendees yet';

        html += `
            <div class="card meetup-card" style="padding:1.5rem; border-left: 4px solid ${panelName === 'upcoming' ? '#3983F6' : '#64748b'}">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                            <span class="badge badge--gold" style="font-size:0.7rem;">${m.ventureName}</span>
                            <span class="badge ${isOnline ? 'badge--info' : 'badge--success'}" style="font-size:0.7rem;">${m.location_type.toUpperCase()}</span>
                        </div>
                        <h4 style="margin:0 0 0.5rem;font-size:1.1rem;color:#0f172a;">${m.title}</h4>
                        <p style="margin:0 0 0.4rem;font-size:0.82rem;color:#475569;">${VH.icon("clock", 13)} ${dateStr} at ${m.time}</p>
                        <p style="margin:0 0 0.6rem;font-size:0.82rem;color:#475569;">${VH.icon("pin", 13)} ${isOnline ? `<a href="${m.meeting_link}" target="_blank" class="auth-link">${m.meeting_link}</a>` : m.location}</p>
                        ${m.notes ? `<p style="margin:0 0 0.6rem;font-size:0.8rem;background:#f8fafc;padding:0.5rem;border-radius:6px;color:#64748b;"><strong>Notes:</strong> ${m.notes}</p>` : ''}
                        <p style="margin:0;font-size:0.78rem;color:#64748b;">${VH.icon("users", 13)} <strong>Attendees:</strong> ${attendeesStr}</p>
                    </div>

                    ${panelName === 'upcoming' ? `
                    <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
                        ${String(m.createdBy) === String(userId) ? `
                        <span style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">You scheduled this meetup</span>
                        <button class="btn btn--sm btn--outline" onclick="cancelOwnMeetup(${m.id}, '${m.title.replace(/'/g, "\\'")}', ${userId})" style="padding:4px 10px;color:#ef4444;border-color:#fecaca;">Cancel Meeting</button>
                        ` : `
                        <span style="font-size:0.72rem;color:#94a3b8;margin-bottom:2px;">RSVP Status: <strong style="color:${m.rsvpStatus === 'accepted' ? '#16a34a' : m.rsvpStatus === 'declined' ? '#ef4444' : '#d97706'}">${m.rsvpStatus.toUpperCase()}</strong></span>
                        <div style="display:flex;gap:0.4rem;">
                            <button class="btn btn--sm ${m.rsvpStatus === 'accepted' ? 'btn--success' : 'btn--outline'}" onclick="submitRsvp(${m.id}, 'accepted', ${userId})" style="padding:4px 10px;">Accept</button>
                            <button class="btn btn--sm ${m.rsvpStatus === 'declined' ? 'btn--danger' : 'btn--outline'}" onclick="submitRsvp(${m.id}, 'declined', ${userId})" style="padding:4px 10px; color:${m.rsvpStatus === 'declined' ? 'white' : '#ef4444'}; border-color:${m.rsvpStatus === 'declined' ? '#ef4444' : '#fecaca'}">Decline</button>
                        </div>
                        `}
                        ${isOnline || String(m.founderUserId) !== String(userId) ? '' : `<button class="btn btn--sm btn--secondary" onclick="markMeetupCompleted(${m.id}, ${userId})" style="padding:4px 10px;margin-top:2px;">✓ Mark Completed</button>`}
                    </div>
                    ` : `
                    <div>
                        <span class="badge ${m.rsvpStatus === 'accepted' ? 'badge--success' : 'badge--danger'}">${m.rsvpStatus.toUpperCase()}</span>
                    </div>
                    `}
                </div>
            </div>
        `;
    });
    html += '</div>';
    panel.innerHTML = html;
}

window.submitRsvp = async function(meetupId, rsvpStatus, userId) {
    try {
        const response = await fetch("../api/meetups.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "rsvp",
                user_id: userId,
                meetup_id: meetupId,
                rsvp_status: rsvpStatus
            })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success(`RSVP updated to ${rsvpStatus.toUpperCase()}`);
            fetchAndLoadMeetups(userId);
        } else {
            VH.toast.error(res.message || "Failed to update RSVP");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error updating RSVP");
    }
};

window.cancelOwnMeetup = async function(meetupId, title, userId) {
    if (!confirm(`Cancel "${title}"? All invited members will be notified.`)) return;

    try {
        const response = await fetch("../api/meetups.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "cancel", meetup_id: meetupId })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success("Meetup cancelled and members notified.");
            fetchAndLoadMeetups(userId);
        } else {
            VH.toast.error(res.message || "Failed to cancel meetup.");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error cancelling meetup.");
    }
};

window.markMeetupCompleted = async function(meetupId, userId) {
    try {
        const response = await fetch("../api/meetups.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "complete", meetup_id: meetupId })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success("Meetup marked as completed. Members can now quit within 24 hours if they choose to.");
            fetchAndLoadMeetups(userId);
        } else {
            VH.toast.error(res.message || "Failed to mark meetup as completed.");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error marking meetup as completed.");
    }
};

async function fetchVenturesForDropdown(userId) {
    const dropdown = document.getElementById("mFormVenture");
    if (!dropdown) return;

    dropdown.addEventListener("change", () => loadMeetupMode(dropdown.value));

    try {
        const response = await fetch(`../api/dashboard_data.php?user_id=${userId}`);
        const res = await response.json();

        if (res.success) {
            /* Only Assets this person FOUNDED. It used to be
               [...res.myVentures, ...res.joinedVentures], which let an investor
               schedule a meetup on somebody else's Asset — "only select venture jiska
               vo founder ha". A partner still SEES every meetup they are invited to;
               it is calling one that is the founder's. */
            const mine = res.myVentures || [];

            let html = '<option value="">Select Venture...</option>';
            mine.forEach(v => { html += `<option value="${v.id}">${escapeMeetupHtml(v.title)}</option>`; });
            dropdown.innerHTML = html;

            /* "kisi ka nahi ha toh nothing to be select" — founded nothing, so there is
               nothing to schedule against. Revealing the button over an empty dropdown
               would be an invitation to a dead end. */
            const btn = document.getElementById("mScheduleBtn");
            if (btn) btn.classList.toggle("hidden", mine.length === 0);
        }
    } catch (e) {
        console.error(e);
    }
}

/* The dropdown builds option markup from a title the founder typed. */
function escapeMeetupHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function renderMiniCalendar(upcomingMeetups) {
    const cal = document.getElementById("miniCalendar");
    if (!cal) return;

    const date = new Date();
    const year = date.getFullYear();
    const month = date.getMonth(); // 0-indexed

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const firstDay = new Date(year, month, 1).getDay(); // 0 is Sunday
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Map scheduled dates
    const scheduleDates = new Set();
    upcomingMeetups.forEach(m => {
        const mDate = new Date(m.date);
        if (mDate.getFullYear() === year && mDate.getMonth() === month) {
            scheduleDates.add(mDate.getDate());
        }
    });

    let html = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;padding:0 4px;">
            <span style="font-weight:700;font-size:0.82rem;color:#1e293b;">${monthNames[month]} ${year}</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-size:0.68rem;font-weight:700;color:#94a3b8;margin-bottom:0.4rem;">
            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-size:0.72rem;">
    `;

    // Fill in empty slots before first day
    for (let i = 0; i < firstDay; i++) {
        html += '<div style="color:#e2e8f0;"></div>';
    }

    const todayStr = new Date().getDate();
    const isCurrentMonth = new Date().getMonth() === month && new Date().getFullYear() === year;

    for (let d = 1; d <= daysInMonth; d++) {
        const isToday = isCurrentMonth && d === todayStr;
        const hasMeetup = scheduleDates.has(d);

        let style = 'padding:4px 0; border-radius:6px; cursor:default;';
        if (isToday) {
            style += 'background:#1e293b; color:white; font-weight:700;';
        } else if (hasMeetup) {
            style += 'background:#dbeafe; color:#1f4068; font-weight:700; border: 1px solid #3983F6;';
        } else {
            style += 'color:#475569;';
        }

        html += `<div style="${style}" title="${hasMeetup ? 'Meetup scheduled!' : ''}">${d}</div>`;
    }

    html += '</div>';
    cal.innerHTML = html;
}
