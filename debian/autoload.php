<?php
/**
 * Autoloader for Debian package discomp2abraflexi
 *
 * This file is installed to /usr/share/php/SpojeNet/Discomp/autoload.php
 * It pulls in dependency autoloaders (if installed to /usr/share/php/*)
 * and registers a PSR-4 loader for the package classes.
 */

require_once '/usr/share/php/Ease/autoload.php';
require_once '/usr/share/php/AbraFlexi/autoload.php';
spl_autoload_register(function ($class) {
    $prefix = 'SpojeNet\\Discomp\\';
    $base_dir = '/usr/share/php/SpojeNet/Discomp/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . '/' . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
