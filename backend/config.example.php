<?php
/**
 * Zugangsdaten und Pfade.
 *
 * Diese Datei nach  config.php  kopieren und ausfüllen.
 * config.php gehört NICHT ins öffentliche Git-Repository.
 */

return [

    // --- Datenbank ------------------------------------------------------
    // Die Werte stehen im Kundenmenü des Hosters (bei IONOS unter
    // „Hosting → Datenbanken"). Der Hostname ist dort selten „localhost".
    'db' => [
        'dsn'      => 'mysql:host=db1234.hosting-data.io;dbname=dbs1234567;charset=utf8mb4',
        'benutzer' => 'dbu1234567',
        'passwort' => 'HIER_EINTRAGEN',
    ],

    // --- Videoablage ----------------------------------------------------
    // WICHTIG: Dieser Ordner muss AUSSERHALB des öffentlichen Web-Ordners
    // liegen, damit niemand die Dateien direkt herunterladen kann.
    //
    //   /kunden/12345/                 ← Wurzel des Hosting-Kontos
    //     ├── videos-privat/           ← hierhin die MP4-Dateien
    //     └── www/                     ← öffentlicher Ordner (index.html …)
    //
    'video_ordner'  => __DIR__ . '/../../videos-privat',

    // Vorschaubilder dürfen öffentlich liegen – sie verraten nichts.
    // poster_ordner ist der Pfad auf der Festplatte, poster_url die Adresse,
    // unter der derselbe Ordner im Browser erreichbar ist. Beim Hochladen
    // schneidet der Browser ein Standbild aus dem Video; es landet hier.
    'poster_ordner' => __DIR__ . '/../assets/video',
    'poster_url'    => '/assets/video/',

    // Größte erlaubte Videodatei in Megabyte. Das Hochladen läuft in kleinen
    // Stücken (siehe upload.php), deshalb sind hier auch Werte weit über
    // upload_max_filesize der PHP-Einstellungen möglich.
    'max_video_mb'  => 800,

    // --- Sicherheit -----------------------------------------------------
    // Nach so vielen Fehlversuchen innerhalb des Zeitfensters wird der
    // Benutzername vorübergehend gesperrt.
    'max_versuche'  => 5,
    'sperrminuten'  => 15,

    // Sitzungsdauer in Minuten ohne Aktivität
    'sitzung_minuten' => 180,
];
