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

  /* ---------- Aktiver Navigationspunkt ----------
     Seit der Aufteilung in Einzelseiten führt jeder Menüpunkt auf eine eigene
     Datei. Der aktive Punkt ergibt sich damit aus dem Dateinamen und nicht mehr
     aus dem gerade sichtbaren Abschnitt. */
  const seite = (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();

  Array.prototype.slice.call(document.querySelectorAll('.site-nav a[href]'))
    .forEach(function (link) {
      const ziel = link.getAttribute('href').split('#')[0].split('/').pop().toLowerCase();
      if (ziel && ziel === seite) {
        link.classList.add('is-active');
        link.setAttribute('aria-current', 'page');
      }
    });

  /* ---------- Einblenden beim Scrollen ---------- */
  const revealTargets = document.querySelectorAll(
    '.section-head, .card, .member, .panel, .hub-card, .split-media, .split-copy, .downloads, .contact-form, .stats'
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
        return value.trim().length >= 2 ? '' : 'Bitte gib deinen Namen an.';
      },
      email: function (value) {
        if (!value.trim()) return 'Bitte gib eine E-Mail-Adresse an.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim())
          ? ''
          : 'Diese E-Mail-Adresse sieht nicht vollständig aus.';
      },
      message: function (value) {
        return value.trim().length >= 10
          ? ''
          : 'Bitte beschreibe dein Anliegen in mindestens 10 Zeichen.';
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
        showError(privacy, 'Bitte bestätige die Datenschutzhinweise.');
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
        'sobald die Seite freigeschaltet ist, geht deine Anfrage an ' +
        'taekwondo@tv-steinau.de. Bis dahin erreichst du uns direkt unter dieser Adresse.';
      status.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      form.reset();
    });
  }

  /* ---------- Terminkalender ---------- */
  var kalender = document.getElementById('kalender');
  if (kalender) {
    var heute = new Date();
    heute.setHours(0, 0, 0, 0);
    var modus = kalender.dataset.modus || 'voll';

    function alsDatum(iso) {
      var t = iso.split('-');
      return new Date(+t[0], t[1] - 1, +t[2]);
    }

    if (modus === 'voll') {
      /* Vollständige Terminseite: vergangene Termine ganz entfernen, den
         nächsten hervorheben. Monatsüberschriften ohne Termine fallen mit weg.
         Ohne JavaScript bleibt die Liste vollständig stehen. */
      var naechsterGesetzt = false;
      Array.prototype.slice.call(kalender.querySelectorAll('.kal-eintrag[data-datum]'))
        .forEach(function (li) {
          if (alsDatum(li.dataset.datum) < heute) {
            li.remove();
          } else if (!naechsterGesetzt && !li.classList.contains('ist-frei')) {
            li.classList.add('ist-naechster');
            naechsterGesetzt = true;
          }
        });

      /* Ein Monatsband ohne folgenden Termin gehört zu einem Monat, der
         komplett vorbei ist. */
      Array.prototype.slice.call(kalender.querySelectorAll('.kal-monat'))
        .forEach(function (band) {
          var naechstes = band.nextElementSibling;
          if (!naechstes || naechstes.classList.contains('kal-monat')) band.remove();
        });

      var leerHinweis = document.getElementById('kalLeer');
      if (leerHinweis && !kalender.querySelector('.kal-eintrag')) leerHinweis.hidden = false;

    } else {
      /* Startseite: nur die laufende Woche aus trainingstermine.js aufbauen.
         Ist die Woche schon vorbei, wird das nächste Training gezeigt. */
      var liste = document.getElementById('kalListe');
      var titel = document.getElementById('kalTitel');
      var fuss  = document.getElementById('kalFuss');
      var termine = window.TRAININGSTERMINE || [];
      var orte = window.TRAININGSORTE || {};

      /* Montag der aktuellen Woche (Woche beginnt Montag) */
      var montag = new Date(heute);
      montag.setDate(montag.getDate() - ((montag.getDay() + 6) % 7));
      var naechsterMontag = new Date(montag);
      naechsterMontag.setDate(naechsterMontag.getDate() + 7);

      var mitDatum = termine.map(function (t) {
        return { t: t, d: alsDatum(t.datum) };
      });
      var dieseWoche = mitDatum.filter(function (x) {
        return x.d >= montag && x.d < naechsterMontag;
      });
      var offenInWoche = dieseWoche.filter(function (x) { return x.d >= heute; });

      var zeigen, alsWoche;
      if (offenInWoche.length) {
        zeigen = dieseWoche;              // ganze Woche, Vergangenes ausgegraut
        alsWoche = true;
      } else {
        /* Nächsten Trainingstag suchen und dessen Einheiten zeigen */
        var kommend = mitDatum.filter(function (x) { return x.d >= heute; });
        if (kommend.length) {
          var erstesDatum = kommend[0].t.datum;
          zeigen = kommend.filter(function (x) { return x.t.datum === erstesDatum; });
        } else {
          zeigen = [];
        }
        alsWoche = false;
      }

      function eintragEl(t) {
        var teil = t.datum.split('-');
        var frei = t.ort === 'frei';
        var li = document.createElement('li');
        li.className = 'kal-eintrag' + (frei ? ' ist-frei' : '');
        li.dataset.datum = t.datum;
        var info = orte[t.ort] || {};
        var name = info.name || '';
        var hinweis = t.hinweis ? ' <span class="kal-hinweis">' + t.hinweis + '</span>' : '';
        var ort;
        if (frei) {
          ort = '<span class="kal-frei">kein Training</span>';
        } else if (info.karte) {
          ort = '<a class="halle ort-' + t.ort + '" href="' + info.karte +
                '" target="_blank" rel="noopener">' + name + '</a>';
        } else {
          ort = '<span class="halle ort-' + t.ort + '">' + name + '</span>';
        }
        li.innerHTML =
          '<span class="kal-datum"><strong>' + teil[2] + '.' + teil[1] + '.</strong>' +
            '<span>' + t.tag + '</span></span>' +
          '<span class="kal-zeit">' + t.zeit + '</span>' +
          '<span class="kal-gruppe">' + t.gruppe + hinweis + '</span>' +
          ort;
        return li;
      }

      if (liste && zeigen.length) {
        kalender.classList.add('zeigt-alle');   // Vergangenes ausgegraut statt versteckt
        var naechsterOk = false;
        zeigen.forEach(function (x) {
          var li = eintragEl(x.t);
          if (x.d < heute) {
            li.classList.add('ist-vergangen');
          } else if (!naechsterOk && x.t.ort !== 'frei') {
            li.classList.add('ist-naechster');
            naechsterOk = true;
          }
          liste.appendChild(li);
        });
        if (titel && !alsWoche) titel.textContent = 'Nächstes Training';
        if (fuss) fuss.hidden = true;
      }
      /* Ohne Termine bleibt der Hinweis in #kalFuss samt Link stehen. */
    }
  }

  /* ---------- Jahreszahl im Footer ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = String(new Date().getFullYear());
})();
