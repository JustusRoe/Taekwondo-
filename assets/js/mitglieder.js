/* =========================================================
   Mitgliederbereich – Entwurfsfassung
   -----------------------------------------------------------
   ACHTUNG: Die Anmeldung ist hier nur nachgebildet, damit der
   Ablauf gezeigt werden kann. Sie schützt nichts: Die Videos
   liegen im öffentlichen Ordner und sind über ihre Adresse
   erreichbar. Der echte Zugriffsschutz gehört auf den Server
   (siehe backend/ – Anmeldung per PHP, Videos über stream.php).
   ========================================================= */
(function () {
  'use strict';

  var DEMO_BENUTZER = 'mitglied';
  var DEMO_PASSWORT = 'taekwondo';
  var SCHLUESSEL = 'tkd-demo-anmeldung';
  var VIDEO_PFAD = 'assets/video/';

  /* ---------- Hilfsfunktionen ---------- */

  function angemeldet() {
    try { return sessionStorage.getItem(SCHLUESSEL) === 'ja'; } catch (e) { return false; }
  }

  function zeit(sekunden) {
    var s = Math.max(0, Math.floor(sekunden || 0));
    var m = Math.floor(s / 60);
    return m + ':' + String(s % 60).padStart(2, '0');
  }

  function datum(iso) {
    var t = iso.split('-');
    return t[2] + '.' + t[1] + '.' + t[0];
  }

  function findeVideo(slug) {
    var liste = window.VIDEOTHEK || [];
    for (var i = 0; i < liste.length; i++) {
      if (liste[i].slug === slug) return liste[i];
    }
    return null;
  }

  /* Karte für die Videothek und für „Weitere Videos" */
  function karte(v) {
    var a = document.createElement('a');
    a.className = 'video-card';
    a.href = 'mitglieder-video.html?v=' + encodeURIComponent(v.slug);
    a.innerHTML =
      '<span class="video-thumb">' +
        '<img src="' + VIDEO_PFAD + v.slug + '.jpg" alt="" loading="lazy" width="640" height="360">' +
        '<span class="play-badge" aria-hidden="true"></span>' +
        '<span class="video-duration">' + zeit(v.dauer) + '</span>' +
      '</span>' +
      '<span class="video-body">' +
        '<span class="video-tag">' + v.bereich + '</span>' +
        '<h3>' + v.titel + '</h3>' +
        '<span class="video-sub">' + v.grad + ' · ' + v.trainer + '</span>' +
        '<span class="video-foot">' +
          '<span>' + v.kapitel.length + ' Abschnitte</span>' +
          '<span>' + datum(v.datum) + '</span>' +
        '</span>' +
      '</span>';
    return a;
  }

  /* ---------- Anmeldeseite ---------- */

  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    if (angemeldet()) location.replace('mitglieder-videothek.html');

    loginForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var fehler = document.getElementById('loginError');
      var b = loginForm.elements.benutzer.value.trim();
      var p = loginForm.elements.passwort.value;

      if (b === DEMO_BENUTZER && p === DEMO_PASSWORT) {
        try { sessionStorage.setItem(SCHLUESSEL, 'ja'); } catch (e) {}
        location.href = 'mitglieder-videothek.html';
        return;
      }
      fehler.textContent = 'Benutzername oder Passwort stimmen nicht. ' +
        'Für den Entwurf: mitglied / taekwondo';
      loginForm.elements.passwort.focus();
      loginForm.elements.passwort.select();
    });
  }

  /* ---------- Gemeinsames für die geschützten Seiten ---------- */

  var istMitgliederseite = document.getElementById('videoGrid') || document.getElementById('player');

  if (istMitgliederseite) {
    if (!angemeldet()) {
      location.replace('mitglieder.html');
      return;
    }

    var name = document.getElementById('userName');
    if (name) name.textContent = 'Alex Muster';

    var logout = document.getElementById('logoutLink');
    if (logout) {
      logout.addEventListener('click', function (event) {
        event.preventDefault();
        try { sessionStorage.removeItem(SCHLUESSEL); } catch (e) {}
        location.href = 'mitglieder.html';
      });
    }
  }

  /* ---------- Videothek ---------- */

  var grid = document.getElementById('videoGrid');
  if (grid && window.VIDEOTHEK) {
    var leer = document.getElementById('emptyState');
    var zaehler = document.getElementById('videoCount');
    var suchfeld = document.getElementById('suche');
    var chips = Array.prototype.slice.call(document.querySelectorAll('.chip[data-filter]'));
    var bereich = 'alle';

    function zeichnen() {
      var text = (suchfeld.value || '').trim().toLowerCase();
      var treffer = window.VIDEOTHEK.filter(function (v) {
        var passtBereich = bereich === 'alle' || v.bereich === bereich;
        var passtText = !text ||
          (v.titel + ' ' + v.beschreibung + ' ' + v.trainer + ' ' + v.bereich)
            .toLowerCase().indexOf(text) !== -1;
        return passtBereich && passtText;
      });

      grid.innerHTML = '';
      treffer.forEach(function (v) { grid.appendChild(karte(v)); });

      leer.hidden = treffer.length > 0;
      zaehler.textContent = treffer.length === window.VIDEOTHEK.length
        ? window.VIDEOTHEK.length + ' Videos'
        : treffer.length + ' von ' + window.VIDEOTHEK.length + ' Videos';
    }

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        bereich = chip.dataset.filter;
        chips.forEach(function (c) {
          c.setAttribute('aria-pressed', String(c === chip));
        });
        zeichnen();
      });
    });

    suchfeld.addEventListener('input', zeichnen);
    zeichnen();
  }

  /* ---------- Player ---------- */

  var player = document.getElementById('player');
  if (player && window.VIDEOTHEK) {
    var slug = new URLSearchParams(location.search).get('v');
    var video = findeVideo(slug) || window.VIDEOTHEK[0];
    var merker = 'tkd-position-' + video.slug;

    document.title = video.titel + ' – Mitgliederbereich';
    player.poster = VIDEO_PFAD + video.slug + '.jpg';

    /* MP4 (H.264) ist das Format für den Echtbetrieb – es läuft in allen
       gängigen Browsern. Die WebM-Fassung liegt nur bei, damit die
       Platzhalter auch in Browsern ohne H.264 abspielbar sind. */
    player.innerHTML =
      '<source src="' + VIDEO_PFAD + video.slug + '.mp4" type="video/mp4">' +
      '<source src="' + VIDEO_PFAD + video.slug + '.webm" type="video/webm">' +
      'Ihr Browser kann dieses Video nicht abspielen.';
    player.load();

    document.getElementById('videoTitel').textContent = video.titel;
    document.getElementById('videoBeschreibung').textContent = video.beschreibung;
    document.getElementById('videoMeta').innerHTML =
      '<span>' + video.bereich + '</span>' +
      '<span>' + video.grad + '</span>' +
      '<span>' + video.trainer + '</span>' +
      '<span>' + datum(video.datum) + '</span>' +
      '<span>' + zeit(video.dauer) + ' Min.</span>';

    /* Springt an eine Stelle – wartet notfalls, bis die Videodaten da sind.
       Ohne diese Prüfung verwirft der Browser ein currentTime, das gesetzt
       wird, bevor er die Länge des Videos kennt. */
    function springeZu(sekunden) {
      if (player.readyState >= 1) {
        player.currentTime = sekunden;
        player.play();
      } else {
        player.addEventListener('loadedmetadata', function () {
          player.currentTime = sekunden;
          player.play();
        }, { once: true });
      }
    }

    /* Abschnittsliste */
    var liste = document.getElementById('chapterList');
    video.kapitel.forEach(function (k, i) {
      var li = document.createElement('li');
      li.dataset.start = k.t;
      li.innerHTML =
        '<button type="button">' +
          '<span class="ch-time">' + zeit(k.t) + '</span>' +
          '<span class="ch-name">' + k.name + '</span>' +
        '</button>';
      li.querySelector('button').addEventListener('click', function () {
        springeZu(k.t + 0.15);
      });
      liste.appendChild(li);
    });

    var eintraege = Array.prototype.slice.call(liste.children);

    function aktivesKapitel() {
      var t = player.currentTime;
      var index = 0;
      for (var i = 0; i < video.kapitel.length; i++) {
        if (t >= video.kapitel[i].t) index = i;
      }
      eintraege.forEach(function (li, i) {
        li.classList.toggle('is-current', i === index);
      });
    }

    player.addEventListener('timeupdate', aktivesKapitel);
    player.addEventListener('loadedmetadata', aktivesKapitel);

    /* Sprungtasten */
    document.getElementById('back10').addEventListener('click', function () {
      player.currentTime = Math.max(0, player.currentTime - 10);
    });
    document.getElementById('fwd10').addEventListener('click', function () {
      player.currentTime = Math.min(player.duration || 0, player.currentTime + 10);
    });

    /* Abspielgeschwindigkeit */
    var tempoKnoepfe = Array.prototype.slice.call(document.querySelectorAll('[data-speed]'));
    tempoKnoepfe.forEach(function (btn) {
      btn.addEventListener('click', function () {
        player.playbackRate = parseFloat(btn.dataset.speed);
        tempoKnoepfe.forEach(function (b) {
          b.setAttribute('aria-pressed', String(b === btn));
        });
      });
    });

    /* Zuletzt gesehene Stelle merken */
    var box = document.getElementById('resumeBox');
    var gespeichert = parseFloat(localStorage.getItem(merker) || '0');
    if (gespeichert > 12 && gespeichert < video.dauer - 8) {
      document.getElementById('resumeText').textContent =
        'Zuletzt gestoppt bei ' + zeit(gespeichert) + '.';
      box.hidden = false;
      document.getElementById('resumeBtn').addEventListener('click', function () {
        springeZu(gespeichert);
        box.hidden = true;
      });
    }
    player.addEventListener('timeupdate', function () {
      if (player.currentTime > 5) {
        try { localStorage.setItem(merker, String(Math.floor(player.currentTime))); } catch (e) {}
      }
    });
    player.addEventListener('ended', function () {
      try { localStorage.removeItem(merker); } catch (e) {}
    });

    /* Tastatur: Pfeil links/rechts, Leertaste */
    document.addEventListener('keydown', function (event) {
      var tag = (event.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
      if (event.key === 'ArrowLeft') { player.currentTime -= 5; event.preventDefault(); }
      if (event.key === 'ArrowRight') { player.currentTime += 5; event.preventDefault(); }
      if (event.key === ' ' && event.target === document.body) {
        player.paused ? player.play() : player.pause();
        event.preventDefault();
      }
    });

    /* Weitere Videos */
    var weitere = document.getElementById('weitereVideos');
    window.VIDEOTHEK
      .filter(function (v) { return v.slug !== video.slug; })
      .slice(0, 3)
      .forEach(function (v) { weitere.appendChild(karte(v)); });
  }
})();
