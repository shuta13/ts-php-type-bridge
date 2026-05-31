<?php

/**
 * types_bridge.php
 *
 * Reads the JSON produced by `yarn bridge:extract` (workspaces/type-bridge/json/api.json)
 * and uses PHP-Parser to rewrite HttpVariables in src/shared/http_variables.php:
 *
 * - Union literal types (string values in JSON) → rewrites @param $key annotation
 * - Object shape types (object values in JSON)  → rewrites @return with PHPStan
 *   conditional type AND replaces the method body with a type-coercing match expression.
 *   Nested objects produce array{key: type} PHPStan shapes and inline array literals.
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
$typesJsonFile = __DIR__ . '/../../type-bridge/json/api.json';

if (!file_exists($typesJsonFile)) {
    echo "Error: types JSON not found at {$typesJsonFile}\n";
    echo "Run `yarn bridge:extract` first.\n";
    exit(1);
}

$phpCode = (string) file_get_contents($targetPhpFile);
/** @var array<string, mixed> $types */
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

class TypeBridgeVisitor extends NodeVisitorAbstract
{
    /** @var array<string, mixed> */
    private array $types;

    private ParserFactory $parserFactory;

    /** @param array<string, mixed> $types */
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

        /** @var array<string, array{paramKey: string, bodyKey?: string}> $methodToConfig */
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

        /** @var array<string, mixed>|null $bodyShape */
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

        // Replace the method body with a type-coercing match expression.
        if ($bodyShape !== null) {
            $node->stmts = $this->buildMatchBody($bodyShape);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // PHPStan 型生成（再帰）
    // -------------------------------------------------------------------------

    /**
     * TS型（文字列 or ネストオブジェクト）→ PHPStan型文字列
     *
     * 'string'                              → 'string'
     * 'number'                              → 'int|float'
     * ['name' => 'string', 'age' => 'number'] → 'array{name: string, age: int|float}'
     *
     * @param string|array<string, mixed> $tsType
     */
    private function buildPhpType(mixed $tsType): string
    {
        if (is_array($tsType)) {
            $entries = [];
            foreach ($tsType as $key => $value) {
                $entries[] = "{$key}: {$this->buildPhpType($value)}";
            }
            return 'array{' . implode(', ', $entries) . '}';
        }
        return $this->mapTsTypeToPhp($tsType);
    }

    /**
     * shape 全体から PHPStan conditional return type を生成する。
     *
     * e.g. ['message' => 'string', 'author' => ['name' => 'string', 'age' => 'number']]
     *   → "($key is 'message' ? string : ($key is 'author' ? array{name: string, age: int|float} : never))"
     *
     * @param array<string, mixed> $shape
     */
    private function buildConditionalReturn(array $shape): string
    {
        $entries = [];
        foreach ($shape as $key => $tsType) {
            $entries[] = [$key, $this->buildPhpType($tsType)];
        }

        $result = 'never';
        foreach (array_reverse($entries) as [$key, $phpType]) {
            $result = "(\$key is '{$key}' ? {$phpType} : {$result})";
        }

        return $result;
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

    // -------------------------------------------------------------------------
    // メソッドボディ生成
    // -------------------------------------------------------------------------

    /**
     * postJson のメソッドボディ全体を生成してパースする。
     * ネストオブジェクトの事前変数宣言 + match 式を含む。
     *
     * @param array<string, mixed> $shape
     * @return \PhpParser\Node\Stmt[]
     */
    private function buildMatchBody(array $shape): array
    {
        $preDecls = $this->collectPreDecls($shape, '$body', '');
        $preCode = $preDecls !== [] ? "\n" . implode("\n", $preDecls) : '';

        $arms = [];
        foreach ($shape as $key => $tsType) {
            $arms[] = $this->buildMatchArmLine($key, $tsType);
        }
        $armsCode = implode("\n", $arms);

        $src = <<<PHP
<?php
class _Bridge
{
    public function _method(string \$key): mixed
    {
        \$raw = json_decode((string) file_get_contents('php://input'), true);
        \$body = is_array(\$raw) ? \$raw : [];{$preCode}
        return match (\$key) {
{$armsCode}
        };
    }
}
PHP;
        $parser = $this->parserFactory->createForNewestSupportedVersion();
        $tmpAst = $parser->parse($src);
        return $tmpAst[0]->stmts[0]->stmts;
    }

    /**
     * ネストオブジェクトに対応する事前変数宣言を再帰的に収集する。
     *
     * PostJsonBody = { author: { name, address: { city } } } の場合:
     *   $_author = is_array($body['author'] ?? null) ? $body['author'] : [];
     *   $_author_address = is_array($_author['address'] ?? null) ? $_author['address'] : [];
     *
     * @param array<string, mixed> $shape
     * @param string $sourceVar  親変数名（例: '$body', '$_author'）
     * @param string $prefix     変数名プレフィックス（例: '', 'author', 'author_address'）
     * @return string[]
     */
    private function collectPreDecls(array $shape, string $sourceVar, string $prefix): array
    {
        $lines = [];
        foreach ($shape as $key => $tsType) {
            if (is_array($tsType)) {
                $varName = '$_' . ($prefix !== '' ? "{$prefix}_{$key}" : $key);
                $lines[] = "        {$varName} = is_array({$sourceVar}['{$key}'] ?? null) ? {$sourceVar}['{$key}'] : [];";
                $lines = array_merge(
                    $lines,
                    $this->collectPreDecls($tsType, $varName, $prefix !== '' ? "{$prefix}_{$key}" : $key)
                );
            }
        }
        return $lines;
    }

    /**
     * トップレベルの match アーム1行を生成する。
     * プリミティブ → inline type guard
     * オブジェクト → array literal（事前宣言変数を使用）
     *
     * @param string|array<string, mixed> $tsType
     */
    private function buildMatchArmLine(string $key, mixed $tsType): string
    {
        if (is_array($tsType)) {
            $objVar = "\$_{$key}";
            $literal = $this->buildArrayLiteral($tsType, $objVar, $key, '            ');
            return "            '{$key}' => {$literal},";
        }

        $var = "\${$key}";
        return match (trim($tsType)) {
            'string'  => "            '{$key}' => is_string({$var} = \$body['{$key}'] ?? '') ? {$var} : '',",
            'number'  => "            '{$key}' => is_int({$var} = \$body['{$key}'] ?? 0) || is_float({$var}) ? {$var} : 0.0,",
            'boolean' => "            '{$key}' => is_bool({$var} = \$body['{$key}'] ?? false) ? {$var} : false,",
            default   => "            '{$key}' => \$body['{$key}'] ?? null,",
        };
    }

    /**
     * ネストオブジェクトの array literal を再帰的に生成する。
     * PHPStan はこの形式から array{key: type} を直接推論できる。
     *
     * @param array<string, mixed> $shape
     * @param string $sourceVar  このオブジェクトの事前宣言変数（例: '$_author'）
     * @param string $prefix     変数名プレフィックス（例: 'author', 'author_address'）
     * @param string $indent     インデント文字列
     */
    private function buildArrayLiteral(array $shape, string $sourceVar, string $prefix, string $indent): string
    {
        $lines = [];
        foreach ($shape as $key => $tsType) {
            $path = "{$prefix}_{$key}";
            if (is_array($tsType)) {
                $childVar = "\$_{$path}";
                $childLiteral = $this->buildArrayLiteral($tsType, $childVar, $path, "{$indent}    ");
                $lines[] = "{$indent}    '{$key}' => {$childLiteral},";
            } else {
                $var = "\$_{$path}";
                $expr = match (trim($tsType)) {
                    'string'  => "is_string({$var} = {$sourceVar}['{$key}'] ?? '') ? {$var} : ''",
                    'number'  => "is_int({$var} = {$sourceVar}['{$key}'] ?? 0) || is_float({$var}) ? {$var} : 0.0",
                    'boolean' => "is_bool({$var} = {$sourceVar}['{$key}'] ?? false) ? {$var} : false",
                    default   => "{$sourceVar}['{$key}'] ?? null",
                };
                $lines[] = "{$indent}    '{$key}' => {$expr},";
            }
        }
        return "[\n" . implode("\n", $lines) . "\n{$indent}]";
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
