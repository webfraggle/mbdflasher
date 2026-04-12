# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MBD Flasher (Modellbahn-Displays Flasher) is a desktop GUI application for flashing firmware to ESP32/ESP8266 microcontrollers. It is a fork of [BrewFlasher](https://github.com/thorrak/brewflasher) (itself based on NodeMCU PyFlasher), adapted to fetch firmware catalogs from `modellbahn-displays.de` instead of `brewflasher.com`.

## Commands

```bash
# Activate venv
source ./venv/bin/activate          # macOS
.\venv\Scripts\Activate.ps1         # Windows (requires: set-executionpolicy remotesigned)

# Install dependencies
pip install -r requirements.txt

# Run the app
python Main.py

# Compile i18n translations (.po → .mo)
python compile_languages.py locales

# Build standalone executable
pyinstaller build-on-mac-m1.spec    # macOS (universal2, Apple Silicon)
pyinstaller build-on-win.spec       # Windows
```

There are no tests or linting configured in this project.

## Architecture

### GUI & Entry Point — `Main.py`

- Uses **wxPython** (not Qt, not Tk). The main frame is `NodeMcuFlasher` (line ~358).
- Flashing runs in a background thread to keep the UI responsive.
- `flash_firmware_using_whatever_is_appropriate()` (line ~132) builds commands for either **esptool** (ESP devices, called as Python library via `esptool.main()`) or **avrdude** (Arduino/ATmega, called as subprocess).
- The version string `__version__` lives in `Main.py` (line ~74). When bumping, also update the version in `build-on-mac-m1.spec` (the `BUNDLE` name and `CFBundleVersion`/`CFBundleShortVersionString` fields).

### Data Model & API Client — `modellbahndisplays_de_integration.py`

- Defines `Project`, `DeviceFamily`, `Firmware`, and `FirmwareList` dataclasses.
- Fetches firmware catalogs from `modellbahn-displays.de/firmware/api/` (base URL in `BREWFLASHER_COM_URL`).
- `brewflasher_com_integration.py` is the original BrewFlasher equivalent — structurally identical, only the base URL differs. **Main.py imports the modellbahndisplays variant.**
- Loading order in `FirmwareList.load_from_website()` matters: projects → device families → firmware entries → `cleanse_projects()` (prunes empty entries).

### Firmware Files

Downloaded binaries (`firmware.bin`, `partitions.bin`, `bootloader.bin`, `spiffs.bin`, `otadata.bin`) are saved next to the script/executable. Checksums verified via SHA-256 (`fhash.py`).

### Online API Data — `Online-JSON/`

Contains the server-side JSON files and PHP endpoints that power the firmware API. The `firmware_list/all/index.json` is the main catalog. Changes here are deployed to the web server.

### Other Files

- `HtmlPopupTransientWindow.py` — Custom wx popup for HTML content (firmware descriptions).
- `images.py` — Auto-generated bitmap resources (regenerate with `encode-bitmaps.py`).
- `locales/` — i18n translations (German `de`). Uses `gettext`.
- `About.py` — About dialog, imports `__version__` from Main.

### CI

- `.github/workflows/build-windows.yaml` — Builds Windows executable on push to master. Rebuilds PyInstaller bootloader from source before building. Uses Python 3.11.

## Key Conventions

- Python 3.9+ (venv uses 3.9; CI uses 3.11).
- The variable name `BREWFLASHER_COM_URL` is retained from the upstream fork even though it points to `modellbahn-displays.de` — this is intentional to minimize diff from upstream.
- Serial port detection uses `pyserial`'s `list_ports`.
- The app supports macOS locale detection via `objc` (PyObjC) as a fallback when `locale.getdefaultlocale()` returns None (common on macOS when language/region mismatch).
