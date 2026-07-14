# MealKit

MealKit is a PHP subscription-based recipe and meal planning web application built for XAMPP/PHP environments.

## Overview

- Front controller architecture using `index.php` and a custom router.
- MVC-like organization with `controllers/`, `models/`, and `views/`.
- User roles for admin and customer management.
- Recipe, meal plan, category, ingredient, subscription, payment, and notification support.
- Mobile money sandbox integration for Tanzanian providers.

## Requirements

- PHP 8.0 or newer
- MySQL / MariaDB
- XAMPP or another Apache/PHP stack
- PHP extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`, `fileinfo`, `openssl`

## Installation

1. Copy the project into your web root, for example:
   - `C:\xampp\htdocs\mealkit`

2. Ensure the following directories are writable:
   - `uploads/recipes`
   - `uploads/profiles`
   - `uploads/pdfs`

3. Configure the application:
   - Copy `config/config.example.php` to `config/config.php`
   - Update `config/config.php` with your settings:
     - `APP_URL`
     - database credentials: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
     - optional mobile money sandbox settings

4. Import the database schema:
   - Open `http://localhost/phpmyadmin`
   - Create a database named `mealkit_db` or your chosen name
   - Import `database/mealkit.sql`

5. Seed demo data (optional):
   - Visit `http://localhost/mealkit/database/seed.php`

6. Run the setup check:
   - Visit `http://localhost/mealkit/setup_check.php`
   - Confirm all checks pass

7. Delete `setup_check.php` before deploying to production.

## Folder Structure

- `assets/` - CSS, JavaScript, images, fonts
- `config/` - application settings and database bootstrap
- `controllers/` - request handlers for pages and actions
- `database/` - SQL schema, seed scripts, update scripts
- `includes/` - reusable helpers, session and security classes
- `models/` - database interaction and data access logic
- `uploads/` - user-uploaded content directories
- `views/` - HTML templates for home, auth, admin, and customer pages

## Routing

- All requests are routed through `index.php` via query string parameter `url`
- URL pattern: `?url=controller/action/param1/param2` or `/controller/action/param1/param2` (if configured with URL rewriting)
- Example routes:
  - `/` or `?url=` → `HomeController@index`
  - `/auth/login` or `?url=auth/login` → `AuthController@login`
  - `/recipes/view/42` or `?url=recipes/view/42` → `RecipeController@viewRecipe`

## Important Notes

- `APP_ENV` is set to `development` by default in `config/config.php`.
- Before production, update `APP_URL`, database credentials, `MOMO_*` settings, and remove `setup_check.php`.
- Keep user-uploaded directories protected and writable.

## Contact

For support or changes, modify the PHP files in `controllers/`, `models/`, and `views/`.
