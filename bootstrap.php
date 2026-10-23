<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('APP_NAME', 'Música');
define('APP_VERSION', '0.5.0');
define('USER_AGENT', 'MusicaPlatform/0.1 (+http://localhost/musica)');

date_default_timezone_set('Europe/Lisbon');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_ROOT . '/src/helpers.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('musica_sid');
    session_start();
}
