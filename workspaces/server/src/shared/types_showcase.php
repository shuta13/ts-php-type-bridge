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
 *   symbol:                 PHP にシンボルの概念がなく、PHPStan にも対応型なし (mixed すら意味論を再現できない)
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
    /**
     * TypeScript: { name: string } & { age: number }
     *
     * @return array{name: string, ...}&array{age: int|float, ...}
     */
    public function intersection(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: never
     *
     * @return never
     */
    public function neverType(): mixed
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
     * TypeScript: unknown  →  PHPStan: mixed（部分再現）
     * TypeScript の unknown は「使用前に型を絞り込まないとコンパイルエラー」という安全性がある。
     * PHPStan の mixed は絞り込みなしでも使えるため、unknown の型安全性は再現できない。
     *
     * @return mixed
     */
    public function unknownType(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: any  →  PHPStan: mixed（部分再現）
     * TypeScript の any は型チェックを完全にスキップする。
     * PHPStan の mixed は any と異なり型チェックの対象になるため、完全な再現ではない。
     *
     * @return mixed
     */
    public function anyType(): mixed
    {
        throw new \LogicException('showcase only');
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
    /**
     * TypeScript: Partial<{ name: string; age: number }>（部分再現）
     * PHPStan:    全プロパティに ? を付けた array{...} を手動で記述（ジェネリック変換不可）
     * Mapped types が必要なため、特定の型に対してのみ手動で再現できる。
     *
     * @return array{name?: string, age?: int|float}
     */
    public function partial(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: Required<{ name?: string; age?: number }>（部分再現）
     * PHPStan:    全プロパティから ? を除いた array{...} を手動で記述（ジェネリック変換不可）
     * Mapped types が必要なため、特定の型に対してのみ手動で再現できる。
     *
     * @return array{name: string, age: int|float}
     */
    public function required(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: NonNullable<string | null>（部分再現）
     * PHPStan:    |null を除いた型を手動で記述（ジェネリック変換不可）
     * 特定の型に対しては null を含まない型として表現できるが、
     * ジェネリックな NonNullable<T> に相当する PHPStan 構文は存在しない。
     *
     * @return string
     */
    public function nonNullable(): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: function identity<T>(arg: T): T
     * PHPStan:    @template
     *
     * @template T
     * @param T $value
     * @return T
     */
    public function identity(mixed $value): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: function wrap<T extends object>(arg: T): { value: T }
     * PHPStan:    @template T of object（上限制約）
     *
     * @template T of object
     * @param T $value
     * @return array{value: T}
     */
    public function wrap(mixed $value): mixed
    {
        throw new \LogicException('showcase only');
    }
    /**
     * TypeScript: function pair<T, U>(a: T, b: U): [T, U]
     * PHPStan:    複数 @template
     *
     * @template T
     * @template U
     * @param T $a
     * @param U $b
     * @return array{0: T, 1: U}
     */
    public function genericPair(mixed $a, mixed $b): mixed
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
/**
 * TypeScript: class Box<T> { constructor(private value: T) {} get(): T }
 * PHPStan:    クラス docblock に @template を付与してジェネリッククラスを表現
 *
 * @template T
 */
class Box
{
    /** @param T $value */
    public function __construct(private mixed $value)
    {
    }
    /** @return T */
    public function get(): mixed
    {
        return $this->value;
    }
}