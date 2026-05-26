<?php

/**
 * types_bridge.php
 *
 * Reads the JSON produced by `yarn bridge:extract` (workspaces/types-bridge/json/api.json)
 * and uses PHP-Parser to rewrite the @param phpdoc annotations in
 * src/shared/http_variables.php to match the TypeScript types.
 *
 * Run via: composer run bridge  (or: yarn bridge:apply from the repo root)
 */

declare(strict_types=1);

require dirname(__FILE__) . '/../vendor/autoload.php';

use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

$targetPhpFile = __DIR__ . '/../src/shared/http_variables.php';
$typesJsonFile = __DIR__ . '/../../types-bridge/json/api.json';

if (!file_exists($typesJsonFile)) {
    echo "Error: types JSON not found at {$typesJsonFile}\n";
    echo "Run `yarn bridge:extract` first.\n";
    exit(1);
}

$phpCode = (string) file_get_contents($targetPhpFile);
/** @var array<string, string> $types */
$types = (array) json_decode((string) file_get_contents($typesJsonFile), true);

// PHP-Parser 5.x: createForNewestSupportedVersion() targets PHP 8.x.
$parser = (new ParserFactory())->createForNewestSupportedVersion();

try {
    $ast = $parser->parse($phpCode);
} catch (Error $e) {
    echo "Parse error: {$e->getMessage()}\n";
    exit(1);
}

if ($ast === null) {
    echo "Error: parser returned null AST.\n";
    exit(1);
}

/**
 * Maps each HttpVariables method name to the JSON key holding its TS type.
 *
 * @phpstan-type MethodMap array<string, string>
 */
class TypeBridgeVisitor extends NodeVisitorAbstract
{
    /** @var array<string, string> */
    private array $types;

    /** @param array<string, string> $types */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /** @return null */
    public function enterNode(Node $node): mixed
    {
        if (!($node instanceof ClassMethod)) {
            return null;
        }

        /** Maps method name → JSON key in api.json */
        $methodToTypeKey = [
            'get'          => 'GetParam',
            'postFormData' => 'PostFormDataKey',
            'postJson'     => 'PostJsonKey',
        ];

        $methodName = $node->name->name;
        $typeKey = $methodToTypeKey[$methodName] ?? null;

        if ($typeKey === null || !isset($this->types[$typeKey])) {
            return null;
        }

        $paramType = $this->types[$typeKey];

        // Preserve every existing phpdoc line except the @param $key line,
        // then replace only that line with the newly bridged type.
        $existing = $node->getDocComment();
        $lines = $existing !== null
            ? preg_split('/\n/', $existing->getText()) ?? []
            : ['/**', ' */'];

        $newLines = array_map(function (string $line) use ($paramType): string {
            if (preg_match('/@param\s+.*\$key/', $line)) {
                return " * @param {$paramType} \$key";
            }
            return $line;
        }, $lines);

        $node->setDocComment(new Doc(implode("\n", $newLines)));

        return null;
    }
}

$traverser = new NodeTraverser();
$traverser->addVisitor(new TypeBridgeVisitor($types));
$ast = $traverser->traverse($ast);

$prettyPrinter = new PrettyPrinter\Standard();
file_put_contents($targetPhpFile, $prettyPrinter->prettyPrintFile($ast));

echo "Type bridge applied successfully.\n";
echo "Updated: {$targetPhpFile}\n";
echo "Run `composer run phpstan` to verify the types.\n";
