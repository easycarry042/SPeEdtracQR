# SPeEdtracQR — Device & System Requirements

Minimum and recommended specs for running SPeEdtracQR properly. Grounded in what
the system actually uses: PHP 8.3 / Laravel 13, MySQL, camera-based QR scanning
(`html5-qrcode`), Laravel Reverb WebSockets for live tracking, and the optional
self-hosted Ollama AI assistant.

---

## ⚠️ The one hard rule for phones

QR scanning uses the device **camera**, and browsers only allow camera access over
a **secure connection (HTTPS)**. On a phone, the system **must** be opened via
`https://…` (or `localhost`). Over plain `http://`, the IN/OUT scanner will not
turn the camera on. A valid SSL certificate on the server is therefore a
*functional* requirement, not just a security nicety.

---

## 1. Staff PC (desk work: create documents, dashboards, movements, reports)

| | Minimum | Recommended |
|---|---|---|
| CPU | Dual-core (~2015 or newer) | Quad-core |
| RAM | 4 GB | 8 GB |
| Display | 1366 × 768 | 1920 × 1080 |
| Browser | Chrome / Edge / Firefox — current or 1–2 versions behind | Latest Chrome or Edge |
| OS | Windows 10, macOS 11, or any modern Linux | Windows 11 / macOS 13+ |
| Network | Stable LAN/Wi-Fi to the server | Wired LAN |
| Camera | Optional (only if scanning at the desk) | Built-in/USB webcam with autofocus |

It is a web app — **no installation** on the PC. Just a modern browser. JavaScript
and cookies must be enabled.

---

## 2. Phone / Tablet (staff scanning IN/OUT + citizens tracking)

| | Minimum | Recommended |
|---|---|---|
| OS | Android 8.0+ / iOS 14+ | Android 11+ / iOS 16+ |
| RAM | 2 GB | 4 GB |
| Browser | Chrome (Android) / Safari (iOS) | Latest Chrome / Safari |
| Camera | **Rear camera with autofocus** (for QR) | 8 MP+ autofocus |
| Connection | **HTTPS** + Wi-Fi or mobile data | Wi-Fi |
| Permissions | Must allow **camera** access | — |

- **Staff** use the phone for the `/scan` page (camera QR reader). Camera + HTTPS
  are required.
- **Citizens** only open their tracking link to see status — **no camera, no app,
  no login** needed; any basic smartphone browser works.
- Brief network drops are tolerated: scans queue offline and de-duplicate on
  reconnect.

---

## 3. Server / Host (the machine that runs the system)

| | Without AI assistant | With Ollama AI assistant |
|---|---|---|
| CPU | 2 vCPU | 4+ vCPU (CPU inference is heavy) |
| RAM | 4 GB | **8–16 GB** (the `llama3.2` model needs ~4 GB resident) |
| Disk | 20 GB SSD | 30–40 GB SSD (model files ~5–10 GB) |
| OS | Ubuntu 22.04 / 24.04 LTS | same |
| Software | PHP 8.3 (+`gd` ext), MySQL 8, Nginx, Node (for asset build) | + Ollama daemon |
| Network | **HTTPS (443)** open; WebSocket/`wss` for live tracking | same |

Notes:

- A scheduled task (`php artisan schedule:run` on cron) and a queue worker must run
  for SLA emails and background jobs.
- The AI assistant is **optional** — without it, the document assistant falls back
  to a built-in rule-based responder, and the server can stay at 4 GB RAM. A GPU is
  not required but greatly speeds up AI replies.

See `DEPLOYMENT.md` for the full production setup procedure.

---

## Quick summary

- **Citizens:** any smartphone or PC with a modern browser. Nothing else.
- **Staff (scanning):** Android 8+/iOS 14+ phone with an autofocus rear camera,
  accessed over **HTTPS**.
- **Staff (desk):** any ~2015+ PC with a current browser.
- **Server:** 4 GB RAM minimum; **8–16 GB** if you keep the on-premise AI assistant
  enabled.
