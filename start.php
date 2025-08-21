<?php

require __DIR__.'/vendor/autoload.php';

if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
