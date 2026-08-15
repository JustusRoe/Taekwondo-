/* =========================================================
   Taekwondo im TV 1897 Steinau e.V. – Interaktion
   Navigation, Terminkalender, Lightbox, Formularprüfung
   ========================================================= */
(function () {
  'use strict';

  /* ---------- Mobile-Navigation ---------- */
  const toggle = document.getElementById('navToggle');
  const nav = document.getElementById('siteNav');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.querySelector('.visually-hidden').textContent = open ? 'Menü schließen' : 'Menü öffnen';
    });

    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Schatten am Header beim Scrollen ---------- */
  const header = document.querySelector('.site-header');
  if (header) {
    const setStuck = function () {
      header.classList.toggle('is-stuck', window.scrollY > 8);
    };
    setStuck();
    window.addEventListener('scroll', setStuck, { passive: true });
  }

  /* ---------- Aktiver Navigationspunkt ---------- */
  const navLinks = Array.prototype.slice.call(
    document.querySelectorAll('.site-nav a[href^="#"]')
  );
  const sections = navLinks
    .map(function (link) { return document.querySelector(link.getAttribute('href')); })
    .filter(Boolean);

  if (sections.length && 'IntersectionObserver' in window) {
    const spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        navLinks.forEach(function (link) {
          link.classList.toggle(
            'is-active',
            link.getAttribute('href') === '#' + entry.target.id
          );
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });

    sections.forEach(function (section) { spy.observe(section); });
  }

  /* ---------- Einblenden beim Scrollen ---------- */
  const revealTargets = document.querySelectorAll(
    '.section-head, .card, .member, .panel, .split-media, .split-copy, .downloads, .contact-form, .stats'
  );

  if ('IntersectionObserver' in window) {
    revealTargets.forEach(function (el) { el.classList.add('reveal'); });
    const revealer = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    revealTargets.forEach(function (el) { revealer.observe(el); });
  }

  /* ---------- Galerie-Lightbox ---------- */
  const gallery = document.getElementById('gallery');
  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightboxImg');
  const lightboxCaption = document.getElementById('lightboxCaption');
  const lightboxClose = document.getElementById('lightboxClose');
  let lastFocused = null;

  function openLightbox(button) {
    const img = button.querySelector('img');
    if (!img) return;
    lastFocused = button;
    lightboxImg.src = img.currentSrc || img.src;
    lightboxImg.alt = img.alt;
    lightboxCaption.textContent = button.dataset.caption || '';
    lightbox.hidden = false;
    document.body.style.overflow = 'hidden';
    lightboxClose.focus();
  }

  function closeLightbox() {
    lightbox.hidden = true;
    lightboxImg.src = '';
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  if (gallery && lightbox) {
    gallery.addEventListener('click', function (event) {
      const button = event.target.closest('.shot');
      if (button) openLightbox(button);
    });

    lightboxClose.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', function (event) {
      if (event.target === lightbox || event.target.classList.contains('lightbox-inner')) {
        closeLightbox();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !lightbox.hidden) closeLightbox();
    });
  }

  /* ---------- Kontaktformular ---------- */
  const form = document.getElementById('contactForm');
  const status = document.getElementById('formStatus');

  function showError(field, message) {
    const target = document.querySelector('[data-error-for="' + field.name + '"]');
    if (target) target.textContent = message;
    field.classList.add('has-error');
    field.setAttribute('aria-invalid', 'true');
  }

  function clearError(field) {
    const target = document.querySelector('[data-error-for="' + field.name + '"]');
    if (target) target.textContent = '';
    field.classList.remove('has-error');
    field.removeAttribute('aria-invalid');
  }

  if (form) {
    const validators = {
      name: function (value) {
        return value.trim().length >= 2 ? '' : 'Bitte geben Sie Ihren Namen an.';
      },
      email: function (value) {
        if (!value.trim()) return 'Bitte geben Sie eine E-Mail-Adresse an.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim())
          ? ''
          : 'Diese E-Mail-Adresse sieht nicht vollständig aus.';
      },
      message: function (value) {
        return value.trim().length >= 10
          ? ''
          : 'Bitte beschreiben Sie Ihr Anliegen in mindestens 10 Zeichen.';
      }
    };

    Object.keys(validators).forEach(function (name) {
      const field = form.elements[name];
      if (!field) return;
      field.addEventListener('blur', function () {
        const message = validators[name](field.value);
        if (message) { showError(field, message); } else { clearError(field); }
      });
      field.addEventListener('input', function () {
        if (field.classList.contains('has-error') && !validators[name](field.value)) {
          clearError(field);
        }
      });
    });

    const privacy = form.elements.privacy;
    if (privacy) {
      privacy.addEventListener('change', function () {
        if (privacy.checked) clearError(privacy);
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      let firstInvalid = null;

      Object.keys(validators).forEach(function (name) {
        const field = form.elements[name];
        if (!field) return;
        const message = validators[name](field.value);
        if (message) {
          showError(field, message);
          if (!firstInvalid) firstInvalid = field;
        } else {
          clearError(field);
        }
      });

      if (privacy && !privacy.checked) {
        showError(privacy, 'Bitte bestätigen Sie die Datenschutzhinweise.');
        if (!firstInvalid) firstInvalid = privacy;
      }

      if (firstInvalid) {
        status.hidden = true;
        firstInvalid.focus();
        return;
      }

      const name = form.elements.name.value.trim().split(' ')[0];
      status.hidden = false;
      status.textContent =
        'Vielen Dank, ' + name + '. Dieser Entwurf versendet noch keine Nachrichten – ' +
        'sobald die Seite freigeschaltet ist, geht Ihre Anfrage an ' +
        'taekwondo@tv-steinau.de. Bis dahin erreichen Sie uns direkt unter dieser Adresse.';
      status.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      form.reset();
    });
  }

  /* ---------- Terminkalender ---------- */
  /* Vergangene Termine werden ausgeblendet, der nächste hervorgehoben.
     Ohne JavaScript bleibt schlicht die vollständige Liste stehen. */
  var kalender = document.getElementById('kalender');
  if (kalender) {
    var eintraege = Array.prototype.slice.call(
      kalender.querySelectorAll('.kal-eintrag[data-datum]')
    );
    var heute = new Date();
    heute.setHours(0, 0, 0, 0);

    var vergangene = 0;
    var naechsterGesetzt = false;

    eintraege.forEach(function (li) {
      var teile = li.dataset.datum.split('-');
      var tag = new Date(+teile[0], teile[1] - 1, +teile[2]);

      if (tag < heute) {
        li.classList.add('ist-vergangen');
        vergangene++;
      } else if (!naechsterGesetzt && !li.classList.contains('ist-frei')) {
        li.classList.add('ist-naechster');
        naechsterGesetzt = true;
      }
    });

    /* Zunächst nur die nächsten acht Termine zeigen – der Rest auf Klick */
    var kommende = eintraege.filter(function (li) {
      return !li.classList.contains('ist-vergangen');
    });
    kommende.slice(8).forEach(function (li) { li.classList.add('ist-spaeter'); });

    /* Monatsüberschriften ohne sichtbare Termine ausblenden */
    Array.prototype.slice.call(kalender.querySelectorAll('.kal-monat'))
      .forEach(function (monat) {
        var sichtbar = false;
        var el = monat.nextElementSibling;
        while (el && !el.classList.contains('kal-monat')) {
          if (!el.classList.contains('ist-vergangen') &&
              !el.classList.contains('ist-spaeter')) { sichtbar = true; break; }
          el = el.nextElementSibling;
        }
        if (!sichtbar) monat.classList.add('ist-vergangen');
      });

    var versteckt = vergangene + Math.max(0, kommende.length - 8);
    var schalter = document.getElementById('kalAlle');
    if (schalter && versteckt > 0) {
      schalter.hidden = false;
      schalter.textContent = 'Alle ' + eintraege.length + ' Termine anzeigen';
      schalter.addEventListener('click', function () {
        var offen = kalender.classList.toggle('zeigt-alle');
        schalter.textContent = offen
          ? 'Nur die nächsten Termine anzeigen'
          : 'Alle ' + eintraege.length + ' Termine anzeigen';
      });
    }
  }

  /* ---------- Jahreszahl im Footer ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = String(new Date().getFullYear());
})();
