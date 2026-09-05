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

    /* Zeitstempel setzen: Das Formular verrät damit dem Server, wie lange
       es offen war. Einsendungen in unter drei Sekunden sind keine
       getippten. Ohne JavaScript bleibt das Feld leer und die Prüfung
       entfällt – dann greifen Honigtopf und IP-Grenze allein. */
    const geladen = document.getElementById('geladen');
    if (geladen) geladen.value = String(Math.floor(Date.now() / 1000));

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

      /* Absenden an backend/kontakt.php. Ohne JavaScript schickt der
         Browser dasselbe Formular auf demselben Weg ab und bekommt eine
         schlichte Antwortseite – deshalb steht action im HTML. */
      const knopf = form.querySelector('button[type="submit"]');
      const beschriftung = knopf ? knopf.textContent : '';
      if (knopf) { knopf.disabled = true; knopf.textContent = 'Wird gesendet …'; }

      status.hidden = false;
      status.classList.remove('ist-fehler');
      status.textContent = 'Deine Nachricht wird verschickt …';

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
      })
        .then(function (antwort) { return antwort.json(); })
        .then(function (daten) {
          status.textContent = daten.text;
          status.classList.toggle('ist-fehler', !daten.ok);
          if (daten.ok) {
            form.reset();
            if (geladen) geladen.value = String(Math.floor(Date.now() / 1000));
          }
        })
        .catch(function () {
          status.classList.add('ist-fehler');
          status.textContent =
            'Die Nachricht konnte nicht verschickt werden. Bitte schreib uns direkt ' +
            'an taekwondo@tv-steinau.de oder ruf an: 0152 343 528 31.';
        })
        .then(function () {
          if (knopf) { knopf.disabled = false; knopf.textContent = beschriftung; }
          status.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
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
      /* Vollständige Terminliste: vergangene Termine ganz entfernen, den
         nächsten hervorheben. Monatsüberschriften ohne Termine fallen mit weg.
         Ohne JavaScript bleibt die Liste vollständig stehen.

         Zusätzlich kann über ?gruppe= auf eine Trainingsgruppe eingegrenzt
         werden – so verlinkt angebot.html die Termine der einzelnen Angebote.
         Gefiltert wird anhand des Wochentags, der in jeder Zeile steht; die
         Ausfalltermine gehören damit automatisch zur richtigen Gruppe. */
      var gruppen = window.TRAININGSGRUPPEN || {};
      var schluessel = '';
      try {
        schluessel = new URLSearchParams(window.location.search).get('gruppe') || '';
      } catch (e) { schluessel = ''; }
      var gruppe = gruppen[schluessel];
      if (!gruppe) schluessel = '';

      /* Filterleiste: die gewählte Schaltfläche markieren */
      var filter = document.getElementById('kalFilter');
      if (filter) {
        Array.prototype.slice.call(filter.querySelectorAll('a[data-gruppe]'))
          .forEach(function (a) {
            a.setAttribute('aria-pressed', String(a.dataset.gruppe === schluessel));
          });
      }

      /* Überschrift nennt die gewählte Gruppe */
      var ueberschrift = document.getElementById('termine');
      if (ueberschrift && gruppe) {
        ueberschrift.textContent = 'Die nächsten Termine – ' + gruppe.name;
      }

      function wochentag(li) {
        var el = li.querySelector('.kal-datum span');
        return el ? el.textContent.trim() : '';
      }

      function halle(li) {
        var el = li.querySelector('.halle');
        if (!el) return '';
        var treffer = /(?:^|\s)ort-([a-z]+)/.exec(el.className);
        return treffer ? treffer[1] : '';
      }

      /* Der Sammeleintrag am Samstag deckt Breitensport und Bambini ab, die
         nicht gleichzeitig anfangen. Bei gewählter Gruppe wird die Zeile auf
         deren Zeit und Namen umgeschrieben. */
      function aufGruppeUmschreiben(li) {
        var ort = halle(li);
        var zeitEl = li.querySelector('.kal-zeit');
        var gruppeEl = li.querySelector('.kal-gruppe');
        if (zeitEl) {
          zeitEl.textContent = (gruppe.zeitJeOrt && gruppe.zeitJeOrt[ort]) || gruppe.zeit;
        }
        if (gruppeEl) {
          var hinweis = (gruppe.hinweisJeOrt && gruppe.hinweisJeOrt[ort]) || gruppe.hinweis;
          gruppeEl.textContent = gruppe.name;
          if (hinweis) {
            var span = document.createElement('span');
            span.className = 'kal-hinweis';
            span.textContent = hinweis;
            gruppeEl.appendChild(span);
          }
        }
      }

      var naechsterGesetzt = false;
      Array.prototype.slice.call(kalender.querySelectorAll('.kal-eintrag[data-datum]'))
        .forEach(function (li) {
          if (alsDatum(li.dataset.datum) < heute) {
            li.remove();
            return;
          }
          if (gruppe && gruppe.tage.indexOf(wochentag(li)) === -1) {
            li.remove();
            return;
          }
          if (gruppe && !li.classList.contains('ist-frei')) aufGruppeUmschreiben(li);
          if (!naechsterGesetzt && !li.classList.contains('ist-frei')) {
            li.classList.add('ist-naechster');
            naechsterGesetzt = true;
          }
        });

      /* Ein Monatsband ohne folgenden Termin gehört zu einem Monat, aus dem
         nichts übrig geblieben ist. */
      Array.prototype.slice.call(kalender.querySelectorAll('.kal-monat'))
        .forEach(function (band) {
          var naechstes = band.nextElementSibling;
          if (!naechstes || naechstes.classList.contains('kal-monat')) band.remove();
        });

      var leerHinweis = document.getElementById('kalLeer');
      if (leerHinweis && !kalender.querySelector('.kal-eintrag')) {
        if (gruppe) {
          leerHinweis.textContent =
            'Für ' + gruppe.name + ' stehen in diesem Plan keine Termine mehr an.';
        }
        leerHinweis.hidden = false;
      }

    } else {
      /* Startseite: das nächste Training aus trainingstermine.js aufbauen.
         Gezeigt werden alle Einheiten des nächsten Trainingstages – und davor
         die Tage, an denen das Training ausfällt, damit niemand umsonst
         losfährt. */
      var liste = document.getElementById('kalListe');
      var fuss  = document.getElementById('kalFuss');
      var termine = window.TRAININGSTERMINE || [];
      var orte = window.TRAININGSORTE || {};

      var kommend = termine
        .map(function (t) { return { t: t, d: alsDatum(t.datum) }; })
        .filter(function (x) { return x.d >= heute; });

      /* Der erste Eintrag, an dem tatsächlich trainiert wird. Alles bis
         einschließlich dieses Tages wird gezeigt. */
      var zeigen = [];
      for (var i = 0; i < kommend.length; i++) {
        if (kommend[i].t.ort !== 'frei') {
          var zielTag = kommend[i].t.datum;
          zeigen = kommend.filter(function (x) { return x.t.datum <= zielTag; });
          break;
        }
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
        var naechsterOk = false;
        zeigen.forEach(function (x) {
          var li = eintragEl(x.t);
          if (!naechsterOk && x.t.ort !== 'frei') {
            li.classList.add('ist-naechster');
            naechsterOk = true;
          }
          liste.appendChild(li);
        });
        if (fuss) fuss.hidden = true;
      }
      /* Ohne Termine bleibt der Hinweis in #kalFuss samt Link stehen. */
    }
  }

  /* ---------- Jahreszahl im Footer ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = String(new Date().getFullYear());
})();
