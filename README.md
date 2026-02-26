# Baby Shower Invitation Website (Core PHP)

A small, mobile-first Gujarati baby shower invitation project built using only:
- Core PHP
- HTML
- CSS
- Vanilla JavaScript

## Requirements

- PHP 8.0+
- Writable `storage/` directory
- Optional: `qrencode` binary for fully scannable QR generation
- MySQL 8+ (for admin CMS + DB content storage)

## Run locally

```bash
php -S localhost:8000
```

Then open:
- `http://localhost:8000/index.php`
- `http://localhost:8000/admin/login.php`

## Generate share image (one command)

```bash
./generate-share-image.sh
```

Optional custom output path:

```bash
./generate-share-image.sh assets/images/invitation-preview.png
```

## Project structure

```text
babe-shower/
├── assets/
│   ├── css/
│   │   └── styles.css
│   └── js/
│       └── main.js
├── libs/
│   └── phpqrcode/
│       └── qrlib.php
├── storage/
│   ├── .gitkeep
│   ├── qr.png                # generated automatically when qr_enabled=true
│   └── rsvp.json             # RSVP submissions stored as JSON array
├── data.php
├── index.php
├── share-image.php           # dynamic OpenGraph/WhatsApp preview image (PNG)
└── README.md
```

## Notes

- Without DB, the site falls back to `data.php`.
- With DB enabled, content is loaded from MySQL and editable in `/admin`.
- RSVP is shown only when `rsvp_enabled` is `true`.
- QR section is shown only when `qr_enabled` is `true`.
- WhatsApp/share preview uses OpenGraph tags and `share-image.php`.
- RSVP rate limit: max **3 submissions per IP per hour**.
- All dynamic output in `index.php` is HTML-escaped.

## MySQL setup

1. Create DB + tables:

```bash
mysql -u<user> -p < database/setup.sql
```

2. Copy env file and set DB credentials:

```bash
cp .env.example .env
```

3. Default admin login (first run):

- Username: `admin`
- Password: `Admin@12345`
