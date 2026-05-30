<?php
// Prevent this file from being served directly
if (basename($_SERVER['PHP_SELF']) == 'config.php') die();

// ─────────────────────────────────────────────
// DATABASE — fill in your SiteGround DB details
// ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');       // e.g. 'acuervoz_organizer'
define('DB_USER', 'your_db_user');       // e.g. 'acuervoz_admin'
define('DB_PASS', 'your_db_password');

// ─────────────────────────────────────────────
// APP PASSWORD
// Replace REPLACE_WITH_YOUR_PASSWORD with your chosen password,
// then upload this file. The hash is computed at runtime once.
// After setup is confirmed working you can hard-code the hash if preferred.
// ─────────────────────────────────────────────
define('APP_PASSWORD', password_hash('REPLACE_WITH_YOUR_PASSWORD', PASSWORD_BCRYPT));

// ─────────────────────────────────────────────
// COOKIE SETTINGS
// ─────────────────────────────────────────────
define('COOKIE_NAME',   'organizer_device');
define('COOKIE_DOMAIN', 'acuervoz.com');    // no leading dot needed for SameSite=Strict
