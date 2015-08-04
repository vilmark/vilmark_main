<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, and ABSPATH. You can find more information by visiting
 * {@link https://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 * Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'villagem_main');

/** MySQL database username */
define('DB_USER', 'villagem_db');

/** MySQL database password */
define('DB_PASSWORD', 'V2Mprbgc1');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         ')qN75<ZlK~D-kBetYEQph0iPn?1v<<9q/JDFqxD?BvwE*d-_@[5%k6AT6>|ES0N8');
define('SECURE_AUTH_KEY',  '{dI[Jdk[y8|eyap+-=O(adnaAsc& +NoVA|Wqk&4YE!Y_e`TVFt&V1vp6hQA-GD,');
define('LOGGED_IN_KEY',    'DNw((Hr}eu.+yWZy Ik/9ukKMJiKwtcq-+-ii*}M](5^0Q|Mnosd9-=@fUw]T;2w');
define('NONCE_KEY',        'HfI;Rjk/|ZguTIETKqB?!J  )~cF7-ztQD=><Uo8vSu6lUKzr_OR4m,3Lnw[!Y9 ');
define('AUTH_SALT',        '$cOgRWth@A#{VN*<UV~]%.JMVufmd!O&nxoUIYloLZP(=!(kZM%NM=N7M+g=xK00');
define('SECURE_AUTH_SALT', 'Rc SiPvSo`J9X-5K^]-9VBsD;p2*K02~8p2m]!<.BTa$!-PVe(AqG`OR3y=U eOm');
define('LOGGED_IN_SALT',   '@V}b(#j7MuLZ{3l0+1ka~elb_8f+f{BC.GgJpc8GOm>LU6}>]7jF?Xn)ux.pzKR(');
define('NONCE_SALT',       'p,a[bFT&?^YRz^A$d~)cl==x2|(5-)@3A]Og=>qUiO|$n}<NHAW+K6UG_*XN3,dL');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'vm_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

define( 'WP_ALLOW_MULTISITE', true );

define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', 'vilmark.com');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
