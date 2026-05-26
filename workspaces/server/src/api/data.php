<?php

declare(strict_types=1);

require __DIR__ . '/../shared/header.php';
require __DIR__ . '/../shared/http_variables.php';

(new Header())->set();
header('Content-Type: application/json');

$vars = new HttpVariables();

$message = $vars->postJson('message');
$count   = $vars->postJson('count');
$author  = $vars->postJson('author');

echo json_encode([
    'echo'    => $message,
    'doubled' => $count * 2,
    'author'  => $author,
]);
