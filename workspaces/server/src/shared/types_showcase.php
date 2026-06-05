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
 * ─── type-bridge では再現不可 ───────────────────────────────────────────────
 *   keyof T / T[keyof T]:              PHP クラス定数への key-of<T>/value-of<T> は PHP 固有の概念
 *   T['key'] オフセットアクセス:         @phpstan-type エイリアスのオフセットアクセスは PHP 固有
 *   Generics (@template):              TypeScript の型変数は PHPDoc @template と 1:1 対応せず自動変換不可
 *   Conditional types (T extends U):   PHPStan 条件型は TS とは構文が異なり @template 手書きが必要
 *   Mapped types (Partial/Required):   { [K in keyof T]: ... } に対応する PHPStan 構文がない
 *   Readonly<T>:                       PHP 8.1 readonly はプロパティ単位の概念でジェネリック変換不可
 *   symbol:                            PHP にシンボルの概念がなく PHPStan にも対応型なし
 *   Template literal types:            `${string}Id` に相当する PHPStan 構文なし
 *
 * @phpstan-type TsOptionalShapeAlias array{required: string, optional?: int|float, nullableOpt?: string|null}
 */
class TypesShowcase
{
    // =========================================================================
    // ブリッジ管理メソッド
    // showcase_bridge.php が @return アノテーションを自動書き換えする。
    // =========================================================================
    /**
     * TypeScript: string
     *
     * @return string
     */
    public function tsString(): mixed
    {
        return '';
    }
    /**
     * TypeScript: number
     *
     * @return int|float
     */
    public function tsNumber(): mixed
    {
        return 0;
    }
    /**
     * TypeScript: boolean
     *
     * @return bool
     */
    public function tsBoolean(): mixed
    {
        return false;
    }
    /**
     * TypeScript: null
     *
     * @return null
     */
    public function tsNull(): mixed
    {
        return null;
    }
    /**
     * TypeScript: undefined
     *
     * @return null
     */
    public function undefinedType(): mixed
    {
        return null;
    }
    /**
     * TypeScript: 'draft' | 'published' | 'archived'
     *
     * @return 'draft'|'published'|'archived'
     */
    public function status(): mixed
    {
        return 'draft';
    }
    /**
     * TypeScript: 1 | 2 | 3
     *
     * @return 1|2|3
     */
    public function level(): mixed
    {
        return 1;
    }
    /**
     * TypeScript: string | null
     *
     * @return string|null
     */
    public function nullable(): mixed
    {
        return null;
    }
    /**
     * TypeScript: string | number | boolean
     *
     * @return string|int|float|bool
     */
    public function union(): mixed
    {
        return '';
    }
    /**
     * TypeScript: { required: string; optional?: number; nullableOpt?: string | null }
     *
     * @return array{required: string, optional?: int|float, nullableOpt?: string|null}
     */
    public function optionalShape(): mixed
    {
        return ['required' => ''];
    }
    /**
     * TypeScript: string[]
     *
     * @return list<string>
     */
    public function stringArray(): mixed
    {
        return [];
    }
    /**
     * TypeScript: Array<{ id: number; label: string }>
     *
     * @return list<array{id: int|float, label: string}>
     */
    public function objectArray(): mixed
    {
        return [];
    }
    /**
     * TypeScript: [string, number]
     *
     * @return array{0: string, 1: int|float}
     */
    public function pair(): mixed
    {
        return ['', 0];
    }
    /**
     * TypeScript: Record<string, number>
     *
     * @return array<string, int|float>
     */
    public function record(): mixed
    {
        return [];
    }
    /**
     * TypeScript: { user: { name: string; address: { city: string; zip: number } } }
     *
     * @return array{user: array{name: string, address: array{city: string, zip: int|float}}}
     */
    public function nested(): mixed
    {
        return ['user' => ['name' => '', 'address' => ['city' => '', 'zip' => 0]]];
    }
    /**
     * TypeScript: { required: string; [key: string]: unknown }
     * PHPStan 2.2+ Unsealed Array Shapes
     *
     * @return array{required: string, ...<string, mixed>}
     */
    public function openShape(): mixed
    {
        return ['required' => ''];
    }
    /**
     * TypeScript: { name: string } & { age: number }
     *
     * @return array{name: string, ...}&array{age: int|float, ...}
     */
    public function intersection(): mixed
    {
        return array_merge(['name' => 'example'], ['age' => 30]);
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
    /**
     * TypeScript: unknown
     * PHPStan: mixed（部分再現）
     * TypeScript の unknown は使用前に型を絞り込まないとコンパイルエラーになる安全な型。
     * PHPStan の mixed は絞り込みなしでも使えるため、unknown の型安全性は再現できない。
     *
     * @return mixed
     */
    public function unknownType(): mixed
    {
        return null;
    }
    /**
     * TypeScript: any
     * PHPStan: mixed（部分再現）
     * TypeScript の any は型チェックを完全にスキップする。
     * PHPStan の mixed は any と異なり型チェックの対象になるため、完全な再現ではない。
     *
     * @return mixed
     */
    public function anyType(): mixed
    {
        return null;
    }
    /**
     * TypeScript: function f(): void
     * PHPStan: PHP 7.1+ ネイティブ void 戻り値型（PHP では戻り値型専用、変数型としては使えない）
     *
     * @return void
     */
    public function voidReturn(): void
    {
    }
    /**
     * TypeScript: NonNullable<string | null>
     * PHPStan: null を除いた型（string）
     * NonNullable<T> は T から null/undefined を除去するユーティリティ型。
     * type-bridge では型引数を解析して null を除去した結果を生成する。
     *
     * @return string
     */
    public function nonNullable(): mixed
    {
        return '';
    }
    /**
     * TypeScript: keyof TsSimpleShape  →  keyof { id: number; name: string }
     * PHPStan: key-of<array{id: int|float, name: string}>
     * type-bridge は TypeOperatorNode (keyof) を検出し、参照先の型定義を展開して key-of<T> を生成する。
     *
     * @return key-of<array{id: int|float, name: string}>
     */
    public function keyofType(): mixed
    {
        return 'id';
    }
    /**
     * TypeScript: TsOptionalShape['required']
     * PHPStan: string（インデックスアクセス型をプロパティ型に解決）
     * type-bridge は IndexedAccessTypeNode を検出し、参照先のオブジェクト型から
     * 該当プロパティの型を取り出して具体的な PHP 型に変換する。
     *
     * @return string
     */
    public function offsetAccess(): mixed
    {
        return 'example';
    }
    /**
     * TypeScript: Partial<{ name: string; age: number }>
     * PHPStan: array{name?: string, age?: int|float}
     * type-bridge は Partial<T> の型引数を解析し、全プロパティを optional: true に変換する。
     * Mapped type 相当の変換を型引数がインラインオブジェクトの場合に自動生成できる。
     *
     * @return array{name?: string, age?: int|float}
     */
    public function partial(): mixed
    {
        return [];
    }
    /**
     * TypeScript: Required<{ name?: string; age?: number }>
     * PHPStan: array{name: string, age: int|float}
     * type-bridge は Required<T> の型引数を解析し、全プロパティを optional: false に変換する。
     *
     * @return array{name: string, age: int|float}
     */
    public function required(): mixed
    {
        return ['name' => '', 'age' => 0];
    }
    // =========================================================================
    // 手動サンプル: type-bridge では再現不可
    // =========================================================================
    /** @var array{red: string, green: string, blue: string} */
    public const COLORS = ['red' => '#f00', 'green' => '#0f0', 'blue' => '#00f'];
    /**
     * TypeScript: keyof T / T[keyof T]（クラス定数版）
     * PHPStan:    key-of<T> / value-of<T>（クラス定数・enum に対して動作）
     *
     * ─── type-bridge では再現不可 ───────────────────────────────────────────
     * key-of<self::COLORS> は PHP クラス定数を参照する PHP 固有の構文であり、
     * TypeScript には PHP クラス定数に相当する概念がないため変換対象外。
     * keyof + オブジェクト型の場合は keyofType() メソッドを参照。
     *
     * @param key-of<self::COLORS> $key
     * @return value-of<self::COLORS>
     */
    public function colorByKey(string $key): string
    {
        return self::COLORS[$key];
    }
    /**
     * TypeScript: T extends U ? X : Y（部分再現）/ infer（再現不可）
     * PHPStan:    @template + 条件型（戻り値の絞り込みに限り対応）
     *
     * ─── type-bridge では再現不可 ───────────────────────────────────────────
     * PHPStan の条件型は TypeScript とは構文が異なり、@template で T の範囲を事前に固定する必要がある。
     * type-bridge は @template を持つ関数シグネチャを JSON で表現できないため自動変換不可。
     * infer は PHPStan に相当する概念がなく、再現不可。
     *
     * @template T of int|list<int>
     * @param T $value
     * @return (T is int ? string : list<string>)
     */
    public function conditional(mixed $value): mixed
    {
        if (is_int($value)) {
            return '';
        }
        return [];
    }
    /**
     * TypeScript: function identity<T>(arg: T): T
     * PHPStan:    @template
     *
     * ─── type-bridge では再現不可 ───────────────────────────────────────────
     * TypeScript のジェネリック関数は PHPDoc @template と概念は近いが、
     * type-bridge が扱う型エイリアス（type T = ...）では関数シグネチャの型変数を表現できない。
     *
     * @template T
     * @param T $value
     * @return T
     */
    public function identity(mixed $value): mixed
    {
        return $value;
    }
    /**
     * TypeScript: function wrap<T extends object>(arg: T): { value: T }
     * PHPStan:    @template T of object（上限制約）
     *
     * ─── type-bridge では再現不可 ───────────────────────────────────────────
     * template の上限制約（T of object）は TypeScript の extends 制約に相当するが、
     * 関数シグネチャの template 宣言は type-bridge の変換対象外。
     *
     * @template T of object
     * @param T $value
     * @return array{value: T}
     */
    public function wrap(mixed $value): mixed
    {
        return ['value' => $value];
    }
    /**
     * TypeScript: function pair<T, U>(a: T, b: U): [T, U]
     * PHPStan:    複数 @template
     *
     * ─── type-bridge では再現不可 ───────────────────────────────────────────
     * 複数の型変数を持つジェネリック関数は type-bridge の変換対象外。
     *
     * @template T
     * @template U
     * @param T $a
     * @param U $b
     * @return array{0: T, 1: U}
     */
    public function genericPair(mixed $a, mixed $b): mixed
    {
        return [$a, $b];
    }
}
/**
 * TypeScript: Readonly<T>（部分再現）
 * PHPStan:    PHP 8.1+ readonly プロパティ（配列形状 array{...} への適用は不可）
 *
 * ─── type-bridge では再現不可 ───────────────────────────────────────────────
 * PHP の readonly はプロパティ単位の宣言であり、TypeScript の Readonly<T>（Mapped type）を
 * 汎用的に変換する仕組みは type-bridge では持てない。
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
 * ─── type-bridge では再現不可 ───────────────────────────────────────────────
 * クラスレベルの @template は PHP 固有の PHPDoc 構文であり、
 * TypeScript のクラス定義から自動生成する手段がない。
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