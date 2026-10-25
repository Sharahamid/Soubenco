# Soubenco Android app

The Soubenco shop (soubenco.com) as an Android app.

- Shows **app-only prices** (the website recognises the app).
- **Phone notifications** for price drops, items back in stock and order updates (checked about every 15 minutes, even when the app is closed).
- Cart, wishlist and account stay in sync with the website.
- Profile-photo upload, pull-to-refresh, back button, offline screen.

## Getting the app file
Every change pushed to `main` is built automatically by GitHub:
**Actions → latest "Build Soubenco app" run → Artifacts**
- `Soubenco-app-install-on-phone` — APK to install on an Android phone for testing.
- `Soubenco-PlayStore-bundle` — AAB to upload to Google Play (needs the signing key secrets).

Store listing images: `store-icon-512.png`, `store-feature-1024x500.png`.

## Website source (backup)
`website/` holds the soubenco.com design (`theme-soubenco`) and shop engine (`plugin-soubenco-core`) installed on the WordPress site. These are a backup copy only — the app build ignores them.
