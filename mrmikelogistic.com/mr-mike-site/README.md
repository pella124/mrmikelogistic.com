# MR MIKE LOGISTICS website

A responsive customer booking website and password-protected operations dashboard, built with PHP and MySQL. The booking form creates live database records; administrators can review requests, update their status, and delete records.

## Requirements

- PHP 8.1+ with PDO MySQL enabled
- MySQL 8.0+ (or compatible MariaDB)
- A PHP-capable web server (Apache, Nginx, or PHP's built-in development server)

## Set up

1. Create/import the database by running `mysql -u root -p < schema.sql`.
2. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the PHP server environment. Defaults are `127.0.0.1`, `mr_mike_logistics`, `root`, and an empty password; change these for any shared or production server.
3. Create the first administrator from this directory: `php create-admin.php "Admin Name" admin@example.com "a-long-unique-password"`. Passwords must be at least 12 characters. This script is CLI-only.
4. Serve the directory with a PHP-enabled web server. For local preview, run `php -S 127.0.0.1:8000` from this directory and open `http://127.0.0.1:8000`.
5. Use the **Admin login** link in the footer to open the dashboard.

## Included

- Nationwide ride request form for airport transfers, city rides, interstate rides, outstation trips, and errand/business trips.
- Admin sign-in with PHP password hashing, session ID rotation, CSRF tokens, prepared SQL statements, and output escaping.
- Dashboard metrics and booking table with status updates and deletion.
- Responsive desktop and mobile layout.

The booking flow records a request and returns a reference number. It does not collect payment or automatically assign drivers; the operations team confirms availability and fare with the passenger.
