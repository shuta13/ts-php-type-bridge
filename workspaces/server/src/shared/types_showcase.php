<?php

declare (strict_types=1);
/**
 * TypeScript → PHPStan/PHPDoc 型対応ショーケース。
 *
 * このファイルの @return アノテーションは showcase_bridge.php が自動生成する:
 *   1. type-bridge/src/showcase.ts に TypeScript 型を定義
 *   2. npm run bridge:showcase:extract  → json/showcase.json を生成
 *   3. composer run bridge:showcase     → このファイルのアノテーションを書き換え
 *   4. composer run phpstan             → 型検証
 *
 * ─── 再現困難 (サポート予定なし 2026/06 時点) ───────────────────────────────
 *   Mapped types:           { [K in keyof T]: ... }
 *   Template literal types: `${string}Id`
 *
 * @phpstan-type TsPrimitivesAlias array{str: string, num: int|float, bool: bool, nul: null}
 */
class TypesShowcase
{
    // =========================================================================
    // ブリッジ管理メソッド
    // showcase_bridge.php が @return アノテーションを自動書き換えする。
    // =========================================================================
    /**
     * TypeScript: { str: string; num: number; bool: boolean; nul: null }
     *
     * @return array{str: string, num: int|float, bool: bool, nul: null}
     */
    public function primitives(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: 'draft' | 'published' | 'archived'
     *
     * @return 'draft'|'published'|'archived'
     */
    public function status(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: 1 | 2 | 3
     *
     * @return 1|2|3
     */
    public function level(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: string | null
     *
     * @return string|null
     */
    public function nullable(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: string | number | boolean
     *
     * @return string|int|float|bool
     */
    public function union(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: { required: string; optional?: number; nullableOpt?: string | null }
     *
     * @return array{required: string, optional?: int|float, nullableOpt?: string|null}
     */
    public function optionalShape(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: string[]
     *
     * @return list<string>
     */
    public function stringArray(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: Array<{ id: number; label: string }>
     *
     * @return list<array{id: int|float, label: string}>
     */
    public function objectArray(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: [string, number]
     *
     * @return array{0: string, 1: int|float}
     */
    public function pair(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: Record<string, number>
     *
     * @return array<string, int|float>
     */
    public function record(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: { user: { name: string; address: { city: string; zip: number } } }
     *
     * @return array{user: array{name: string, address: array{city: string, zip: int|float}}}
     */
    public function nested(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: { required: string; [key: string]: unknown }
     * PHPStan 2.2+ Unsealed Array Shapes
     *
     * @return array{required: string, ...<string, mixed>}
     */
    public function openShape(): mixed
    {
        throw new \LogicException('showcase only');
    }
    // =========================================================================
    // 手動サンプル: ブリッジ管理外
    // =========================================================================
    /** @var array{red: string, green: string, blue: string} */
    public const COLORS = ['red' => '#f00', 'green' => '#0f0', 'blue' => '#00f'];
    /**
     * TypeScript: keyof T / T[keyof T]
     * PHPStan:    key-of<T> / value-of<T>（クラス定数・enum に対して動作）
     *
     * @param key-of<self::COLORS> $key
     * @return value-of<self::COLORS>
     */
    public function colorByKey(string $key): string
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: T['key']
     * PHPStan:    @phpstan-type エイリアスへのオフセットアクセス（クラス docblock に定義）
     *
     * @return TsPrimitivesAlias['str']
     */
    public function offsetAccess(): string
    {
        return 'example';
    }
    /**
     * TypeScript: T extends U ? X : Y（部分再現）/ infer（部分再現）
     * PHPStan:    @template + 条件型（戻り値の絞り込みに限り対応）
     *
     * @template T of int|list<int>
     * @param T $value
     * @return (T is int ? string : list<string>)
     */
    public function conditional(mixed $value): mixed
    {
        throw new \LogicException('showcase only');
    }
}
/**
 * TypeScript: Readonly<T>（部分再現）
 * PHPStan:    PHP 8.1+ readonly プロパティ（配列形状 array{...} への適用は不可）
 */
class ReadonlyExample
{
    public function __construct(public readonly string $name, public readonly int $age)
    {
    }
}