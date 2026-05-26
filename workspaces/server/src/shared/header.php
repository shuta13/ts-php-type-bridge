<?php

declare(strict_types=1);

class Header
{
    private string $httpOrigin;

    public function __construct()
    {
        $this->httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    }

    public function set(): void
    {
        $allowed = ['http://localhost:3000'];

        if (in_array($this->httpOrigin, $allowed, true)) {
            header("Access-Control-Allow-Origin: {$this->httpOrigin}");
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
