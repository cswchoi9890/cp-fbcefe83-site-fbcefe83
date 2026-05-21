<?php
define('DB_DIR', __DIR__ . '/../_db/');
define('DB_FILE', 'wordpress.db');
define('DB_ENGINE', 'sqlite');
define('AUTH_KEY', 'put your unique phrase here 1');
define('SECURE_AUTH_KEY', 'put your unique phrase here 2');
define('LOGGED_IN_KEY', 'put your unique phrase here 3');
define('NONCE_KEY', 'put your unique phrase here 4');
define('AUTH_SALT', 'put your unique phrase here 5');
define('SECURE_AUTH_SALT', 'put your unique phrase here 6');
define('LOGGED_IN_SALT', 'put your unique phrase here 7');
define('NONCE_SALT', 'put your unique phrase here 8');
$table_prefix = 'wp_';
define('WP_HOME',    getenv('SITE_URL') ?: '$WP_CFG_URL');
define('WP_SITEURL', getenv('SITE_URL') ?: '$WP_CFG_URL');
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_DEBUG', false);
define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
