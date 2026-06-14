<?php
/**
 * Copy this file to config.php and fill in your CWP MySQL credentials.
 * config.php is gitignored — never commit real passwords.
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'ssendi_mubasa',
    'db_user' => 'ssendi_mubasa',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'notify_email' => 'sssendi@mubs.ac.ug',

    // Policy Assistant — add on server only (never commit config.php)
    'anthropic_api_key' => 'YOUR_ANTHROPIC_API_KEY',
    'anthropic_model' => 'claude-haiku-4-5-20251001',
];
