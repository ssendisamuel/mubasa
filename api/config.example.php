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

    // MUBASA AI Assistant — enable web search on supported Claude models
    'anthropic_api_key' => 'YOUR_ANTHROPIC_API_KEY',
    'anthropic_model' => 'claude-3-5-haiku-latest',
    // Web search requires org admin to enable it in console.anthropic.com
];
