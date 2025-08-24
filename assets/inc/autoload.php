<?php
declare(strict_types=1);

// Project root (…/cargo)
define('CARGO_ROOT', dirname(__DIR__, 1));

// Load Composer autoloader (prefer assets/vendor/, fallback to /vendor/)
$paths = [
    CARGO_ROOT . '/assets/vendor/autoload.php',
    CARGO_ROOT . '/vendor/autoload.php',
];
foreach ($paths as $p) {
    if (is_file($p)) { require_once $p; return; }
}
http_response_code(500);
exit('Composer autoload not found. Run "composer install" (vendor in assets/).');
