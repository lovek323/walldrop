<?php

$container = new \Pimple\Container();
$container[\Oconal\Walldrop\Wallhaven::class] = function (\Pimple\Container $container) {
    return new \Oconal\Walldrop\Wallhaven($container['ENV_USERNAME'], $container['ENV_PASSWORD']);
};
$container['ENV_USERNAME'] = function () {
    return getenv('USERNAME');
};
$container['ENV_PASSWORD'] = function () {
    return getenv('PASSWORD');
};
$container['ENV_BASE_PATH'] = function () {
    $path = getenv('BASE_PATH');
    if (!is_dir($path)) {
      if (!mkdir($path, 0777, true)) {
        throw new RuntimeException('Could not create path: ' . $path);
      }
    }
    return $path;
};
$container['ENV_UNCHECKED_PATH'] = function (\Pimple\Container $container) {
    $path = $container['ENV_BASE_PATH'] . DIRECTORY_SEPARATOR . 'unchecked';
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            throw new RuntimeException('Could not create path: ' . $path);
        }
    }
    return $path;
};
$container['ENV_CHECKED_PATH'] = function (\Pimple\Container $container) {
    $path = $container['ENV_BASE_PATH'] . DIRECTORY_SEPARATOR . 'checked';
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            throw new RuntimeException('Could not create path: ' . $path);
        }
    }
    return $path;
};
$container['ENV_UNWANTED_PATH'] = function (\Pimple\Container $container) {
    $path = $container['ENV_BASE_PATH'] . DIRECTORY_SEPARATOR . 'unwanted';
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            throw new RuntimeException('Could not create path: ' . $path);
        }
    }
    return $path;
};
