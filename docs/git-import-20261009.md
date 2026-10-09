# Git import — 2026-10-09

Repository: https://github.com/huysadboizdev/webnrohuydev.git

Source PHP, tests, migrations, images and static assets are included. Runtime configuration (`connect.php`, `*.local.php`), payment secrets, database dumps, download builds, backups and local analysis are excluded.

The existing SePay secret was moved from `api/bank.php` to ignored `api/sepay-secret.local.php`, preserving its original value. `connect.example.php` and `api/sepay-secret.example.php` are templates for a fresh checkout. PHP syntax checks for the modified file and both configuration templates pass.

Full PHP syntax scan checked 103 files; three existing errors remain in this import:

- `assets/fontawesome-free/css/bootstrap-min-css.php`: unmatched `}` at line 290.
- `images/avatar/zoom.php`: unmatched `}` at line 290.
- `tests/admin_catalog_test.php`: mysqli method signature incompatibility in the current PHP runtime.

No application tests or payment transactions were executed during Git import. This commit imports the current source and does not certify every existing feature as working.
