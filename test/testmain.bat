@echo off
REM ============================================================
REM  testmain.bat - Testumgebung unter Windows starten
REM
REM    test\testmain.bat            einrichten und starten
REM    test\testmain.bat --neu      Testdatenbank zuruecksetzen
REM
REM  Beenden mit Strg+C.
REM  Unter WSL oder Git Bash laesst sich stattdessen
REM  ./test/testmain.sh verwenden - das prueft zusaetzlich
REM  automatisch alle Dienste.
REM ============================================================
setlocal
cd /d "%~dp0.."

set PORT=8080

where php >nul 2>nul
if errorlevel 1 (
  echo.
  echo PHP wurde nicht gefunden.
  echo Bitte von https://windows.php.net/download/ laden
  echo und den Ordner zur Umgebungsvariablen PATH hinzufuegen.
  echo.
  pause
  exit /b 1
)

echo.
echo Taekwondo Club Musterstadt - Testumgebung
echo ========================================================
echo.

php test\setup.php %1
if errorlevel 1 (
  echo.
  echo Die Einrichtung ist fehlgeschlagen.
  pause
  exit /b 1
)

echo.
echo Server startet auf http://localhost:%PORT%
echo.
echo   Website               http://localhost:%PORT%/index.html
echo   Mitglieder (Entwurf)  http://localhost:%PORT%/mitglieder.html
echo   Mitglieder (Server)   http://localhost:%PORT%/backend/login.php
echo.
echo   Testzugaenge (Passwort jeweils: test1234)
echo     testuser      Mitglied
echo     testtrainer   Trainer
echo.
echo   Pruefung in einem zweiten Fenster:
echo     php test\check.php http://localhost:%PORT%
echo.
echo   Beenden mit Strg+C
echo ========================================================
echo.

php -S localhost:%PORT% -t . test\router.php

endlocal
