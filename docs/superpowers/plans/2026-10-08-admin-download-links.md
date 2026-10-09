# Admin Download Links Implementation Plan

**Goal:** Admin saves download links and visitors see the corresponding game download buttons.

**Architecture:** Existing settings table, shared PHP validation/persistence helper and public download partial. Preserve authentication, CSRF and Java download.

**Tech Stack:** PHP, mysqli, existing HTML/CSS.

Execute inline in the existing web workspace, which has no Git repository.

- [x] Add `tests/website_download_test.php`: verify HTTPS/HTTP and empty values, reject javascript and malformed links, render escaped URLs and hide empty platforms. Run `C:/xampp/php/php.exe tests/website_download_test.php` and confirm missing feature failure.
- [x] Add `includes/website_downloads.php` for validation, settings read and prepared update/insert. Add `includes/download_buttons.php` for shared public rendering.
- [x] Update `admin/website_settings.php` to validate all download URLs before saving, report failures and explain empty fields. Replace fixed download sections in `index.php`, `trang-chu.php` and `forum.php` with the partial.
- [x] Run behavior tests, PHP syntax checks and an isolated temporary-table database integration test. Confirm public HTML uses settings without changing live settings.

## Verification and deployment

The database test reproduced truncation of long URLs in VARCHAR(50). Applied `sql/migrations/20261008_website_download_urls.sql` to the local database: Android, Windows and IPhone are now TEXT. Confirmed a hash of all existing settings was unchanged by migration. Apply this same migration when deploying the source to another database.

Both tests pass. All eight changed/added PHP files pass syntax checks. Local homepage responds HTTP 200 and has no fixed APK/PC download URL. Current platform links are empty; enter them in `/admin/website_settings.php` to show the buttons.
