<?php

declare(strict_types=1);

require __DIR__ . '/../shared/header.php';
require __DIR__ . '/../shared/http_variables.php';

(new Header())->set();
header('Content-Type: application/json');

$vars = new HttpVariables();

/** @var mixed $message */
$message = $vars->postJson('message');
/** @var mixed $count */
$count = $vars->postJson('count');

echo json_encode([
    'echo'    => $message,
    'doubled' => is_numeric($count) ? (int) $count * 2 : null,
]);
