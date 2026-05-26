<?php

declare(strict_types=1);

require __DIR__ . '/../shared/header.php';
require __DIR__ . '/../shared/http_variables.php';

(new Header())->set();
header('Content-Type: application/json');

$vars = new HttpVariables();

/** @var mixed $file */
$file = $vars->postFormData('file');
/** @var mixed $label */
$label = $vars->postFormData('label');

$filename = is_array($file) ? ($file['name'] ?? null) : null;

echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'label'    => $label,
]);
