<?php
/**
 * CloudPress WordPress 설정 (자동 생성)
 * DB: GitHub 레포 내 _db/wordpress.db (SQLite)
 */

// ── SQLite 연동 (sqlite-database-integration 플러그인) ──
define( 'DB_NAME',     'wordpress' );
define( 'DB_USER',     'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST',     'localhost' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );
define( 'table_prefix', 'wp_' );

// SQLite 플러그인 설정 (DB_DIR/DB_FILE이 실제 사용되는 상수)
define( 'DB_DIR',  __DIR__ . '/../_db/' );
define( 'DB_FILE', 'wordpress.db' );

// ── 인증 키/솔트 ──
define( 'AUTH_KEY',         'o90dvg9kb88k8p3a0f6pouzb2biisy5eam27zktviuq4oqpnufqmnc0bh7hr4j42' );
define( 'SECURE_AUTH_KEY',  'j5395nm3onw2tivzfaq7prcxu2wuyt9lkce1z7jfjtj0v35a7fv0gird6wl0lgtz' );
define( 'LOGGED_IN_KEY',    '1if0vz8m00acac6d4hd7e7q7b70zvw10y5dz8a528y1u0hywzbi7gcjrb1kf2n3z' );
define( 'NONCE_KEY',        't4faxu60em3ixm77knnsjkbdn2uxliozwmgceht5p9obb19g2vbi5su63547bqwu' );
define( 'AUTH_SALT',        'xq1nqvxtsrc59p240y0dzljicuosx2x7khkcrbczqtn11bwb2wjnjnz50ejxapkk' );
define( 'SECURE_AUTH_SALT', '6bxedlapk6m4mucz64egne8nbipoyawxem1kpz3kfau9vrx8768mk572wawx9uii' );
define( 'LOGGED_IN_SALT',   '3f76scrdtzw08e7mjy4dxp4fux2d8p504b7pbunzoeutmxg0w7joibw662umuypm' );
define( 'NONCE_SALT',       'wy3qp2vdcnnepf8tuk5u7k3ksupkntmez5i2serju76l25pat124q6o2dby99pns' );

// ── URL 설정 ──
define( 'WP_HOME',    'https://cp-fbcefe83-wp.choichoi3227.workers.dev' );
define( 'WP_SITEURL', 'https://cp-fbcefe83-wp.choichoi3227.workers.dev' );

// ── 기타 ──
define( 'WP_DEBUG',        false );
define( 'WP_CACHE',        true  );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'DISALLOW_FILE_EDIT',  false );

if ( ! defined( 'ABSPATH' ) ) {
  define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
}
require_once ABSPATH . 'wp-settings.php';
