<?php

// Router for `php -S`: serve real static files, fall back to the Grocy front controller.
// Keeps the UI in Spanish regardless of the operating system.
putenv('GROCY_DEFAULT_LOCALE=es');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../public' . $path;

if ($path !== '/' && is_file($file))
{
	return false;
}

require __DIR__ . '/../public/index.php';
