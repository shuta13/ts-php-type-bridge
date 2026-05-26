<?php

declare (strict_types=1);
/**
 * Typed wrappers around PHP superglobals.
 *
 * The @param type annotations on get(), postFormData(), and postJson() are
 * managed by the type bridge pipeline:
 *
 *   1. Types are defined in workspaces/types-bridge/src/api.ts
 *   2. `npm run bridge:extract` parses the TS AST and writes json/api.json
 *   3. `composer run bridge` uses PHP-Parser to rewrite these phpdoc annotations
 *   4. `composer run phpstan` validates the result
 */
class HttpVariables
{
    /**
     * @param 'greeting' | 'name' $key
     * @return string
     */
    public function get(string $key): string
    {
        $raw = $_GET[$key] ?? '';
        return htmlspecialchars(is_string($raw) ? $raw : '');
    }
    /**
     * @param 'file' | 'label' $key
     * @return mixed
     */
    public function postFormData(string $key): mixed
    {
        if (isset($_FILES[$key])) {
            return $_FILES[$key];
        }
        $raw = $_POST[$key] ?? '';
        return htmlspecialchars(is_string($raw) ? $raw : '');
    }
    /**
     * @param 'message' | 'count' $key
     * @return ($key is 'message' ? string : ($key is 'count' ? int|float : never))
     */
    public function postJson(string $key): mixed
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($raw) ? $raw : [];
        return match ($key) {
            'message' => is_string($message = $body['message'] ?? '') ? $message : '',
            'count' => is_int($count = $body['count'] ?? 0) || is_float($count) ? $count : 0.0,
        };
    }
}