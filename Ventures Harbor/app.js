/* VENTURES HARBOR — JAVASCRIPT */

document.addEventListener("DOMContentLoaded", () => {
  /* 1. NAVBAR SCROLL EFFECT */
  const navbar = document.getElementById("navbar");
  window.addEventListener("scroll", () => {
    if (window.scrollY > 60) {
      navbar.classList.add("scrolled");
    } else {
      navbar.classList.remove("scrolled");
    }
  });

  /* 2. HAMBURGER MENU */
  const hamburger = document.getElementById("nav-hamburger");
  const navLinks = document.getElementById("nav-links");
  hamburger.addEventListener("click", () => {
    navLinks.classList.toggle("open");
    const spans = hamburger.querySelectorAll("span");
    spans[0].style.transform = navLinks.classList.contains("open")
      ? "rotate(45deg) translate(5px, 5px)"
      : "";
    spans[1].style.opacity = navLinks.classList.contains("open") ? "0" : "1";
    spans[2].style.transform = navLinks.classList.contains("open")
      ? "rotate(-45deg) translate(5px, -5px)"
      : "";
  });

  /* 3. HERO PARTICLES */
  const particlesContainer = document.getElementById("hero-particles");
  function createParticles() {
    for (let i = 0; i < 30; i++) {
      const p = document.createElement("div");
      p.classList.add("hero-particle");
      p.style.left = Math.random() * 100 + "%";
      p.style.bottom = Math.random() * 40 + "%";
      p.style.animationDuration = 8 + Math.random() * 12 + "s";
      p.style.animationDelay = Math.random() * 10 + "s";
      p.style.width = p.style.height = 1 + Math.random() * 3 + "px";
      p.style.opacity = 0.3 + Math.random() * 0.7;
      particlesContainer.appendChild(p);
    }
  }
  if (particlesContainer) createParticles();

  /* 4. STATS COUNTER ANIMATION */
  const statsBar = document.getElementById("stats-bar");
  let statsAnimated = false;

  function animateCount(el, target, duration = 1800) {
    let start = 0;
    const step = Math.ceil(target / (duration / 16));
    const timer = setInterval(() => {
      start += step;
      if (start >= target) {
        start = target;
        clearInterval(timer);
      }
      el.textContent = start.toLocaleString("en-IN");
    }, 16);
  }

  const statsObserver = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && !statsAnimated) {
        statsAnimated = true;
        document.querySelectorAll(".stat-number").forEach((el) => {
          animateCount(el, parseInt(el.dataset.target));
        });
      }
    },
    { threshold: 0.5 },
  );
  if (statsBar) statsObserver.observe(statsBar);

  /* 5. SCROLL ANIMATIONS */

  const animateEls = document.querySelectorAll(
    ".venture-card, .hiw-step, .why-card, .stat-item, .reveal, .reveal-left, .reveal-right, .testimonial-card",
  );

  const scrollObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry, idx) => {
        if (entry.isIntersecting) {
          setTimeout(() => {
            entry.target.classList.add("animate-in");
          }, idx * 80);
          scrollObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 },
  );

  animateEls.forEach((el) => {
    const startX = el.classList.contains("reveal-left")
      ? "-24px"
      : el.classList.contains("reveal-right")
        ? "24px"
        : "0";
    const startY = startX === "0" ? "30px" : "0";
    el.style.opacity = "0";
    el.style.transform = `translate(${startX}, ${startY})`;
    el.style.transition = "opacity 0.6s ease, transform 0.6s ease";
    scrollObserver.observe(el);
  });

  // Override animate-in class behaviour
  const style = document.createElement("style");
  style.textContent = `.animate-in { opacity: 1 !important; transform: translate(0, 0) !important; }`;
  document.head.appendChild(style);

  /* 6. PROGRESS BAR ANIMATION */
  const progressFills = document.querySelectorAll(".vc-progress-fill");
  progressFills.forEach((fill) => {
    const targetWidth = fill.style.width;
    fill.style.width = "0%";

    const progressObserver = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          setTimeout(() => {
            fill.style.width = targetWidth;
          }, 300);
          progressObserver.unobserve(fill);
        }
      },
      { threshold: 0.3 },
    );
    progressObserver.observe(fill);
  });

  /* 7. VENTURE FILTER TABS */
  const filterBtns = document.querySelectorAll(".filter-btn");
  const ventureCards = document.querySelectorAll(".venture-card");

  filterBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      filterBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      const filter = btn.dataset.filter;

      ventureCards.forEach((card) => {
        if (filter === "all" || card.dataset.category === filter) {
          card.classList.remove("hidden");
          card.style.animation = "fadeInUp 0.4s ease both";
        } else {
          card.classList.add("hidden");
        }
      });
    });
  });

  /* 8. JOIN MODAL (Safeguarded) */
  const modalOverlay = document.getElementById("modal-overlay");
  const modalClose = document.getElementById("modal-close");
  const joinForm = document.getElementById("join-form");
  const toast = document.getElementById("toast");
  const toastMsg = document.getElementById("toast-msg");

  function openModal() {
    if (modalOverlay) {
      modalOverlay.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  }

  function closeModal() {
    if (modalOverlay) {
      modalOverlay.classList.remove("active");
      document.body.style.overflow = "";
    }
  }

  document.querySelectorAll(".btn-join").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      const href = btn.getAttribute("href");
      if (!href || href === "#") {
        e.preventDefault();
        openModal();
      }
    });
  });

  const createVentureBtn = document.getElementById("create-venture-btn");
  if (createVentureBtn) {
    createVentureBtn.addEventListener("click", (e) => {
      const href = createVentureBtn.getAttribute("href");
      if (!href || href === "#") {
        e.preventDefault();
        openModal();
      }
    });
  }

  if (modalClose) modalClose.addEventListener("click", closeModal);
  if (modalOverlay) {
    modalOverlay.addEventListener("click", (e) => {
      if (e.target === modalOverlay) closeModal();
    });
  }
  document.addEventListener("keydown", (e) => {
    if (
      e.key === "Escape" &&
      modalOverlay &&
      modalOverlay.classList.contains("active")
    ) {
      closeModal();
    }
  });

  // Form submit (Safeguarded)
  if (joinForm) {
    joinForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById("modal-submit-btn");
      if (submitBtn) {
        submitBtn.textContent = "Submitting...";
        submitBtn.disabled = true;
      }

      setTimeout(() => {
        closeModal();
        joinForm.reset();
        if (submitBtn) {
          submitBtn.textContent = "Submit Interest";
          submitBtn.disabled = false;
        }
        showToast("Interest submitted! Our team will contact you shortly.");
      }, 1500);
    });
  }

  function showToast(message) {
    if (toast && toastMsg) {
      toastMsg.textContent = message;
      toast.classList.add("show");
      setTimeout(() => toast.classList.remove("show"), 4000);
    } else if (window.VH && window.VH.toast) {
      // Fallback to our beautiful shared toast
      window.VH.toast.info(message);
    }
  }

  /* 9. VIEW DETAILS BUTTONS (Safeguarded) */
  // Allow natural navigation to venture-detail.php
  document.querySelectorAll(".btn-details").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      const href = btn.getAttribute("href");
      if (!href || href === "#") {
        e.preventDefault();
        showToast("Detailed view coming soon! Stay tuned.");
      }
    });
  });

  /* 10. VIEW ALL VENTURES (Safeguarded) */
  const viewAllBtn = document.getElementById("view-all-btn");
  if (viewAllBtn) {
    viewAllBtn.addEventListener("click", () => {
      // Reset filter to all
      filterBtns.forEach((b) => b.classList.remove("active"));
      if (filterBtns[0]) filterBtns[0].classList.add("active");
      ventureCards.forEach((card) => card.classList.remove("hidden"));
      showToast("Showing all active Assets!");
    });
  }

  /* 11. SMOOTH SCROLL FOR ANCHORS */
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
        // Close mobile menu if open
        navLinks.classList.remove("open");
      }
    });
  });

  /* 12. ACTIVE NAV LINK ON SCROLL */
  const sections = document.querySelectorAll("section[id]");
  const navLinkEls = document.querySelectorAll(".nav-link");

  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute("id");
          navLinkEls.forEach((link) => {
            link.style.color =
              link.getAttribute("href") === `#${id}`
                ? "var(--white)"
                : "rgba(255,255,255,0.75)";
          });
        }
      });
    },
    { threshold: 0.4 },
  );

  sections.forEach((sec) => sectionObserver.observe(sec));

  /* 13. SEARCH FUNCTIONALITY */
  const searchInput = document.getElementById("nav-search-input");
  if (searchInput) {
    searchInput.addEventListener("input", (e) => {
      const query = e.target.value.toLowerCase().trim();
      if (!query) {
        ventureCards.forEach((card) => card.classList.remove("hidden"));
        return;
      }
      ventureCards.forEach((card) => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
          card.classList.remove("hidden");
        } else {
          card.classList.add("hidden");
        }
      });
      // Scroll to ventures section
      const venturesSection = document.getElementById("ventures");
      if (query.length > 1 && venturesSection) {
        venturesSection.scrollIntoView({ behavior: "smooth" });
      }
    });
  }

  /* 14. PARTNER INFO TOOLTIPS */
  document.querySelectorAll(".partner-info-icon").forEach((icon) => {
    icon.addEventListener("mouseenter", (e) => {
      const tooltip = document.createElement("div");
      tooltip.className = "tooltip-popup";
      tooltip.textContent =
        "Partners gain additional equity stake and voting rights in the Asset.";
      tooltip.style.cssText = `
        position: fixed;
        background: #3983F6;
        color: white;
        font-size: 0.72rem;
        padding: 0.5rem 0.8rem;
        border-radius: 8px;
        max-width: 220px;
        z-index: 9999;
        line-height: 1.4;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        pointer-events: none;
      `;
      const rect = e.target.getBoundingClientRect();
      tooltip.style.left = rect.left - 100 + "px";
      tooltip.style.top = rect.bottom + 8 + "px";
      tooltip.id = "active-tooltip";
      document.body.appendChild(tooltip);
    });
    icon.addEventListener("mouseleave", () => {
      const t = document.getElementById("active-tooltip");
      if (t) t.remove();
    });
  });

  /* 15. CARD TILT EFFECT */
  document.querySelectorAll(".venture-card").forEach((card) => {
    card.addEventListener("mousemove", (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const xPct = (x / rect.width - 0.5) * 6;
      const yPct = (y / rect.height - 0.5) * 6;
      card.style.transform = `translateY(-4px) rotateX(${-yPct}deg) rotateY(${xPct}deg)`;
    });
    card.addEventListener("mouseleave", () => {
      card.style.transform = "";
    });
  });

  /* 16. HOW IT WORKS STEPS ANIMATION */
  document.querySelectorAll(".hiw-step").forEach((step, i) => {
    step.style.opacity = "0";
    step.style.transform = "translateY(30px)";
    step.style.transition = "all 0.5s ease";

    const obs = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          setTimeout(() => {
            step.style.opacity = "1";
            step.style.transform = "translateY(0)";
          }, i * 150);
          obs.unobserve(step);
        }
      },
      { threshold: 0.2 },
    );
    obs.observe(step);
  });

  console.log("Ventures Harbor initialized successfully!");
});
