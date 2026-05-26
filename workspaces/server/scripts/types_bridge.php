<?php

/**
 * types_bridge.php
 *
 * Reads the JSON produced by `yarn bridge:extract` (workspaces/types-bridge/json/api.json)
 * and uses PHP-Parser to rewrite HttpVariables in src/shared/http_variables.php:
 *
 * - Union literal types (string values in JSON) → rewrites @param $key annotation
 * - Object shape types (object values in JSON)  → rewrites @return with PHPStan
 *   conditional type AND replaces the method body with a type-coercing match expression
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
/** @var array<string, string|array<string, string>> $types */
$types = (array) json_decode((string) file_get_contents($typesJsonFile), true);

$parserFactory = new ParserFactory();
$parser = $parserFactory->createForNewestSupportedVersion();

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
 * Maps each HttpVariables method name to the JSON keys holding its TS types.
 * paramKey: union literal type → rewrites @param $key
 * bodyKey:  object shape type  → rewrites @return with PHPStan conditional type
 *                                AND replaces method body with a type-coercing match
 */
class TypeBridgeVisitor extends NodeVisitorAbstract
{
    /** @var array<string, string|array<string, string>> */
    private array $types;

    private ParserFactory $parserFactory;

    /** @param array<string, string|array<string, string>> $types */
    public function __construct(array $types, ParserFactory $parserFactory)
    {
        $this->types = $types;
        $this->parserFactory = $parserFactory;
    }

    /** @return null */
    public function enterNode(Node $node): mixed
    {
        if (!($node instanceof ClassMethod)) {
            return null;
        }

        /**
         * Maps method name → { paramKey, bodyKey? }
         *
         * @var array<string, array{paramKey: string, bodyKey?: string}> $methodToConfig
         */
        $methodToConfig = [
            'get'          => ['paramKey' => 'GetParam'],
            'postFormData' => ['paramKey' => 'PostFormDataKey'],
            'postJson'     => ['paramKey' => 'PostJsonKey', 'bodyKey' => 'PostJsonBody'],
        ];

        $methodName = $node->name->name;
        $config = $methodToConfig[$methodName] ?? null;

        if ($config === null) {
            return null;
        }

        $paramType = is_string($this->types[$config['paramKey']] ?? null)
            ? $this->types[$config['paramKey']]
            : null;

        /** @var array<string, string>|null $bodyShape */
        $bodyShape = isset($config['bodyKey']) && is_array($this->types[$config['bodyKey']] ?? null)
            ? $this->types[$config['bodyKey']]
            : null;

        // Update docblock annotations.
        $existing = $node->getDocComment();
        $lines = $existing !== null
            ? preg_split('/\n/', $existing->getText()) ?? []
            : ['/**', ' */'];

        $newLines = array_map(function (string $line) use ($paramType, $bodyShape): string {
            if ($paramType !== null && preg_match('/@param\s+.*\$key/', $line)) {
                return " * @param {$paramType} \$key";
            }
            if ($bodyShape !== null && preg_match('/@return/', $line)) {
                return " * @return {$this->buildConditionalReturn($bodyShape)}";
            }
            return $line;
        }, $lines);

        $node->setDocComment(new Doc(implode("\n", $newLines)));

        // Replace the method body with a type-coercing match expression so
        // PHPStan can verify the implementation matches the declared return type.
        if ($bodyShape !== null) {
            $node->stmts = $this->buildMatchBody($bodyShape);
        }

        return null;
    }

    /**
     * Builds a PHPStan conditional return type expression from a TS shape map.
     *
     * e.g. ['message' => 'string', 'count' => 'number']
     *   → "($key is 'message' ? string : ($key is 'count' ? int|float : never))"
     *
     * @param array<string, string> $shape
     */
    private function buildConditionalReturn(array $shape): string
    {
        $entries = [];
        foreach ($shape as $key => $tsType) {
            $entries[] = [$key, $this->mapTsTypeToPhp($tsType)];
        }

        $result = 'never';
        foreach (array_reverse($entries) as [$key, $phpType]) {
            $result = "(\$key is '{$key}' ? {$phpType} : {$result})";
        }

        return $result;
    }

    /**
     * Parses and returns replacement stmts for the method body.
     * Generates a match expression that coerces each value to its declared PHP type,
     * allowing PHPStan to verify the implementation against the @return annotation.
     *
     * e.g. ['message' => 'string', 'count' => 'number'] produces:
     *   $raw = json_decode((string) file_get_contents('php://input'), true);
     *   $body = is_array($raw) ? $raw : [];
     *   return match ($key) {
     *       'message' => (string) ($body['message'] ?? ''),
     *       'count'   => (float)  ($body['count']   ?? 0),
     *   };
     *
     * @param array<string, string> $shape
     * @return \PhpParser\Node\Stmt[]
     */
    private function buildMatchBody(array $shape): array
    {
        $arms = [];
        foreach ($shape as $key => $tsType) {
            $arms[] = $this->buildMatchArm($key, $tsType);
        }

        $armsCode = implode("\n", $arms);
        $src = <<<PHP
<?php
class _Bridge
{
    public function _method(string \$key): mixed
    {
        \$raw = json_decode((string) file_get_contents('php://input'), true);
        \$body = is_array(\$raw) ? \$raw : [];
        return match (\$key) {
{$armsCode}
        };
    }
}
PHP;

        $parser = $this->parserFactory->createForNewestSupportedVersion();
        $tmpAst = $parser->parse($src);
        // $tmpAst[0] = class _Bridge, ->stmts[0] = method _method
        return $tmpAst[0]->stmts[0]->stmts;
    }

    /**
     * Generates a single match arm that narrows the raw JSON value to its PHP type
     * using is_* type guards (PHPStan max level does not allow casting mixed directly).
     *
     * string  → is_string($key = $body['key'] ?? '') ? $key : ''
     * number  → is_int($key = $body['key'] ?? 0) || is_float($key) ? $key : 0.0
     * boolean → is_bool($key = $body['key'] ?? false) ? $key : false
     * other   → $body['key'] ?? null
     */
    private function buildMatchArm(string $key, string $tsType): string
    {
        $var = "\${$key}";
        return match (trim($tsType)) {
            'string'  => "            '{$key}' => is_string({$var} = \$body['{$key}'] ?? '') ? {$var} : '',",
            'number'  => "            '{$key}' => is_int({$var} = \$body['{$key}'] ?? 0) || is_float({$var}) ? {$var} : 0.0,",
            'boolean' => "            '{$key}' => is_bool({$var} = \$body['{$key}'] ?? false) ? {$var} : false,",
            default   => "            '{$key}' => \$body['{$key}'] ?? null,",
        };
    }

    private function mapTsTypeToPhp(string $tsType): string
    {
        return match (trim($tsType)) {
            'string'    => 'string',
            'number'    => 'int|float',
            'boolean'   => 'bool',
            'null'      => 'null',
            'undefined' => 'null',
            default     => 'mixed',
        };
    }
}

$traverser = new NodeTraverser();
$traverser->addVisitor(new TypeBridgeVisitor($types, $parserFactory));
$ast = $traverser->traverse($ast);

$prettyPrinter = new PrettyPrinter\Standard();
file_put_contents($targetPhpFile, $prettyPrinter->prettyPrintFile($ast));

echo "Type bridge applied successfully.\n";
echo "Updated: {$targetPhpFile}\n";
echo "Run `composer run phpstan` to verify the types.\n";
