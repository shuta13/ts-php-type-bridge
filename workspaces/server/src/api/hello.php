<?php

declare(strict_types=1);

require __DIR__ . '/../shared/header.php';
require __DIR__ . '/../shared/http_variables.php';

(new Header())->set();
header('Content-Type: application/json');

$vars = new HttpVariables();

/** @var string $greeting */
$greeting = $vars->get('greeting');
/** @var string $name */
$name = $vars->get('name');

echo json_encode([
    'message'   => "{$greeting}, {$name}!",
    'timestamp' => date('c'),
]);
