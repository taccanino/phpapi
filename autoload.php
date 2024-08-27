<?php
spl_autoload_register(function ($class) {
    // Define the base directory for the namespace prefix
    $baseDir = __DIR__ . '/src/';

    // Get the relative class name
    $relativeClass = $class;

    // Replace the namespace separator with the directory separator, and append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
