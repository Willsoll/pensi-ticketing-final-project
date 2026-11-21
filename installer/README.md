# Windows Installer (TUI)

`Installer.cs` is the source for `PENSI_EVENTPRO_Setup.exe`, a self-contained
console installer that embeds the application (`app.zip`) and offers a menu for
XAMPP or Laragon.

## What it does

1. Extracts the embedded payload to `%TEMP%`.
2. Detects `C:\xampp` / `C:\laragon` (or asks for a custom path).
3. Backs up an existing `pensi-eventpro` folder, then copies the app into
   `htdocs\pensi-eventpro` (XAMPP) or `www\pensi-eventpro` (Laragon).
4. Imports `database/db_tiket_event_pro.sql` as MySQL root (prompts for the
   password; blank works for default XAMPP/Laragon).
5. Writes `config.local.php` with the local credentials.
6. Starts Apache/MySQL if ports 80/3306 are closed, then opens
   `http://localhost/pensi-eventpro/public/`.

## Build (Windows, .NET Framework 4.x)

```bat
:: from the repository root
7z a -tzip installer\app.zip app\*
C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe ^
  /target:exe /out:installer\PENSI_EVENTPRO_Setup.exe ^
  /resource:installer\app.zip,PensiPayload.zip ^
  /r:System.IO.Compression.dll installer\Installer.cs
```

`app.zip` must contain a top-level `app\` folder with the web application.
