<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'payment-plug' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'GH8k2P#mN&vX9@zQrS4tU7wY0eR5i8oL1nM3pA6sD9fG2hJ5kE8xC0vB7nM4qW6z' );
define( 'SECURE_AUTH_KEY',  'V2nB5mK8xL1wY4rE7uI0oP3aS6dF9gH2jK5lZ8xC1vB4nM7qW0eR3tY6uI9oP2aS' );
define( 'LOGGED_IN_KEY',    'T7yU0iO3pA6sD9fG2hJ5kL8xC1vB4nM7qW0eR3tY6uI9oP2aS5dF8gH1jK4lZ7xC' );
define( 'NONCE_KEY',        'Q5wE8rT1yU4iO7pA0sD3fG6hJ9kL2xC5vB8nM1qW4eR7tY0uI3oP6aS9dF2gH5jK' );
define( 'AUTH_SALT',        'R9tY2uI5oP8aS1dF4gH7jK0lZ3xC6vB9nM2qW5eR8tY1uI4oP7aS0dF3gH6jK9lZ' );
define( 'SECURE_AUTH_SALT', 'E4rT7yU0iO3pA6sD9fG2hJ5kL8xC1vB4nM7qW0eR3tY6uI9oP2aS5dF8gH1jK4lZ' );
define( 'LOGGED_IN_SALT',   'W1qE4rT7yU0iO3pA6sD9fG2hJ5kL8xC1vB4nM7qW0eR3tY6uI9oP2aS5dF8gH1jK' );
define( 'NONCE_SALT',       'Y8uI1oP4aS7dF0gH3jK6lZ9xC2vB5nM8qW1eR4tY7uI0oP3aS6dF9gH2jK5lZ8xC' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';