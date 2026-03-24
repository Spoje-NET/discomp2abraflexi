<?php
/**
 * Autoloader for Debian package discomp2abraflexi
 *
 * This file is installed to /usr/share/php/SpojeNet/Discomp/autoload.php
 * It pulls in dependency autoloaders (if installed to /usr/share/php/*)
 * and registers a PSR-4 loader for the package classes.
 */

$depAutoloads = [
    '/usr/share/php/EaseCore/autoload.php',
    '/usr/share/php/AbraFlexi/autoload.php',
];

foreach ($depAutoloads as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

/* Register PSR-4 autoloader for SpojeNet\\Discomp\\ mapped to
 * /usr/share/discomp2abraflexi/Discomp/
 */
spl_autoload_register(function (string $class) : void {
    $prefix = 'SpojeNet\\\\Discomp\\\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = '/usr/share/discomp2abraflexi/Discomp/' . str_replace('\\\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

return true;
