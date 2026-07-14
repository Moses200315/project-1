<?php

/**
 * MealKit – Front Controller (Single Entry Point)
 * =================================================
 * All HTTP requests are routed through this file via .htaccess.
 * The Router parses the URL and dispatches to the correct
 * Controller → action method.
 *
 * Boot order:
 *   1. Define root constants
 *   2. Load config/config.php  (which auto-loads DB, Security, Session, helpers)
 *   3. Instantiate Router and dispatch
 */

declare(strict_types=1);

// ── Root constants (only what config.php needs BEFORE it loads) ─────────────
// ROOT_PATH and DS must exist before config.php runs because config.php
// uses them to build every other path constant. All other constants
// (APP_VERSION, APP_NAME, etc.) are defined inside config.php.
define("ROOT_PATH", __DIR__);
define("DS", DIRECTORY_SEPARATOR);

// ── Bootstrap: config loads database, security, session, helpers, starts session
require_once ROOT_PATH . DS . "config" . DS . "config.php";

// ── Security headers on every response ───────────────────────────────────────
Security::sendSecurityHeaders();

// ── Load Router and dispatch request ─────────────────────────────────────────
require_once ROOT_PATH . DS . "router.php";

$router = new Router();
$router->dispatch();
