# Admin download links

Approved behavior: keep separate Android, Windows and iPhone links in the existing admin website settings form. Saving a valid HTTP/HTTPS link updates the public download button on the next page load. Empty values hide the corresponding button. External links open normally, including file hosts and TestFlight; no server-side file download is needed.

Use the existing settings table. Validate links before any database write; show a failure instead of a success message when saving fails. Keep admin authentication and CSRF checks. Share a download partial across index.php, trang-chu.php and forum.php so all public buttons agree. Preserve the existing Java JAR button, which has no admin setting.

Verify URL validation, escaping, hiding empty/invalid links, database read/write and public page integration. No server JAR changes and no production settings changes during tests.
