<?php

/**
 * showcase_bridge.php
 *
 * Reads json/showcase.json (produced by `npm run bridge:showcase:extract`) and uses
 * PHP-Parser to rewrite @return annotations in TypesShowcase's bridge-managed methods.
 *
 * JSON format uses __kind discriminated union:
 *   string                              → primitive / verbatim TS text
 *   { __kind: 'object', ... }          → array{k: T, k2?: U} / unsealed array{..., ...<K,V>}
 *   { __kind: 'array', element }       → list<T>
 *   { __kind: 'tuple', elements }      → array{0: T, 1: U}
 *   { __kind: 'record', key, value }   → array<K, V>
 *   { __kind: 'nullable', inner }      → T|null
 *   { __kind: 'union', members }       → T1|T2|...
 *
 * Run via: composer run bridge:showcase  (or: npm run bridge:showcase from repo root)
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

$targetPhpFile   = __DIR__ . '/../src/shared/types_showcase.php';
$showcaseJsonFile = __DIR__ . '/../../type-bridge/json/showcase.json';

if (!file_exists($showcaseJsonFile)) {
    echo "Error: showcase.json not found at {$showcaseJsonFile}\n";
    echo "Run `npm run bridge:showcase:extract` first.\n";
    exit(1);
}

$phpCode = (string) file_get_contents($targetPhpFile);
/** @var array<string, mixed> $types */
$types = (array) json_decode((string) file_get_contents($showcaseJsonFile), true);

$parserFactory = new ParserFactory();
$parser        = $parserFactory->createForNewestSupportedVersion();

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

class ShowcaseBridgeVisitor extends NodeVisitorAbstract
{
    /** @var array<string, mixed> */
    private array $types;

    /** @var array<string, string> method name → JSON key */
    private array $methodToTypeKey = [
        'tsString'      => 'TsString',
        'tsNumber'      => 'TsNumber',
        'tsBoolean'     => 'TsBoolean',
        'tsNull'        => 'TsNull',
        'status'        => 'TsStatus',
        'level'         => 'TsLevel',
        'nullable'      => 'TsNullable',
        'union'         => 'TsUnion',
        'optionalShape' => 'TsOptionalShape',
        'stringArray'   => 'TsStringArray',
        'objectArray'   => 'TsObjectArray',
        'pair'          => 'TsPair',
        'record'        => 'TsRecord',
        'nested'        => 'TsNested',
        'openShape'     => 'TsOpenShape',
        'intersection'  => 'TsIntersection',
        'neverType'     => 'TsNever',
        'unknownType'   => 'TsUnknown',
        'anyType'       => 'TsAny',
        'voidReturn'    => 'TsVoid',
        'undefinedType' => 'TsUndefined',
        'nonNullable'   => 'TsNonNullable',
        'keyofType'     => 'TsKeyOf',
        'offsetAccess'  => 'TsIndexedAccess',
        'partial'       => 'TsPartial',
        'required'      => 'TsRequired',
    ];

    /** @param array<string, mixed> $types */
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

        $methodName = $node->name->name;
        $typeKey    = $this->methodToTypeKey[$methodName] ?? null;
        if ($typeKey === null || !isset($this->types[$typeKey])) {
            return null;
        }

        $phpstanType = $this->buildPhpstanType($this->types[$typeKey]);

        $existing = $node->getDocComment();
        $lines    = $existing !== null
            ? preg_split('/\n/', $existing->getText()) ?? []
            : ['/**', ' */'];

        $newLines = array_map(static function (string $line) use ($phpstanType): string {
            if (preg_match('/@return\s+/', $line)) {
                return " * @return {$phpstanType}";
            }
            return $line;
        }, $lines);

        $node->setDocComment(new Doc(implode("\n", $newLines)));

        return null;
    }

    // -------------------------------------------------------------------------
    // PHPStan 型文字列の生成（再帰）
    // -------------------------------------------------------------------------

    private function buildPhpstanType(mixed $shape): string
    {
        if (is_string($shape)) {
            return $this->mapPrimitive($shape);
        }
        if (!is_array($shape)) {
            return 'mixed';
        }

        return match ((string) ($shape['__kind'] ?? '')) {
            'object'   => $this->buildObjectType($shape),
            'array'    => 'list<' . $this->buildPhpstanType($shape['element']) . '>',
            'tuple'    => $this->buildTupleType((array) $shape['elements']),
            'record'   => 'array<'
                . $this->mapPrimitive((string) ($shape['key'] ?? 'mixed'))
                . ', '
                . $this->buildPhpstanType($shape['value'])
                . '>',
            'nullable' => $this->buildPhpstanType($shape['inner']) . '|null',
            'union'        => implode('|', array_map([$this, 'buildPhpstanType'], (array) $shape['members'])),
            'intersection' => $this->buildIntersectionType((array) $shape['members']),
            'keyof'        => 'key-of<' . $this->buildPhpstanType($shape['operand']) . '>',
            default        => 'mixed',
        };
    }

    /** @param array<string, mixed> $shape */
    private function buildObjectType(array $shape): string
    {
        $parts = [];
        foreach ((array) ($shape['properties'] ?? []) as $key => $prop) {
            $prop    = (array) $prop;
            $typeStr = $this->buildPhpstanType($prop['type'] ?? 'mixed');
            $opt     = (bool) ($prop['optional'] ?? false) ? '?' : '';
            $parts[] = "{$key}{$opt}: {$typeStr}";
        }
        $inner = implode(', ', $parts);

        $sealed = (bool) ($shape['sealed'] ?? true);
        /** @var array{key: string, value: string}|null $extra */
        $extra = isset($shape['extra']) ? (array) $shape['extra'] : null;

        if (!$sealed && $extra !== null) {
            $k        = $this->mapPrimitive((string) ($extra['key'] ?? 'mixed'));
            $v        = $this->mapPrimitive((string) ($extra['value'] ?? 'mixed'));
            $ellipsis = "...<{$k}, {$v}>";
            return $inner !== '' ? "array{{$inner}, {$ellipsis}}" : "array{{$ellipsis}}";
        }

        return "array{{$inner}}";
    }

    /**
     * 交差型を生成する。
     * 配列形状メンバーは PHPStan 2.2 の Unsealed 形式（bare `...`）に変換して `&` で連結する。
     * array{a: int, ...}&array{b: string, ...} → PHPStan が array{a: int, b: string, ...} と解釈する。
     *
     * @param array<int, mixed> $members
     */
    private function buildIntersectionType(array $members): string
    {
        $parts = array_map(function (mixed $member): string {
            if (is_array($member) && ($member['__kind'] ?? '') === 'object') {
                return $this->buildUnsealedObjectType($member);
            }
            return $this->buildPhpstanType($member);
        }, $members);
        return implode('&', $parts);
    }

    /** @param array<string, mixed> $shape */
    private function buildUnsealedObjectType(array $shape): string
    {
        $parts = [];
        foreach ((array) ($shape['properties'] ?? []) as $key => $prop) {
            $prop    = (array) $prop;
            $typeStr = $this->buildPhpstanType($prop['type'] ?? 'mixed');
            $opt     = (bool) ($prop['optional'] ?? false) ? '?' : '';
            $parts[] = "{$key}{$opt}: {$typeStr}";
        }
        $inner = implode(', ', $parts);
        return $inner !== '' ? "array{{$inner}, ...}" : 'array{...}';
    }

    /** @param array<int, mixed> $elements */
    private function buildTupleType(array $elements): string
    {
        $parts = [];
        foreach ($elements as $i => $elem) {
            $parts[] = "{$i}: " . $this->buildPhpstanType($elem);
        }
        return 'array{' . implode(', ', $parts) . '}';
    }

    private function mapPrimitive(string $ts): string
    {
        return match (trim($ts)) {
            'string'    => 'string',
            'number'    => 'int|float',
            'boolean'   => 'bool',
            'null'      => 'null',
            'undefined' => 'null',
            'unknown'   => 'mixed',
            'any'       => 'mixed',
            'never'     => 'never',
            'void'      => 'void',
            default     => $ts,
        };
    }
}

$traverser = new NodeTraverser();
$traverser->addVisitor(new ShowcaseBridgeVisitor($types));
$ast = $traverser->traverse($ast);

$prettyPrinter = new PrettyPrinter\Standard();
file_put_contents($targetPhpFile, $prettyPrinter->prettyPrintFile($ast));

echo "Showcase bridge applied successfully.\n";
echo "Updated: {$targetPhpFile}\n";
echo "Run `composer run phpstan` to verify the types.\n";
