# ts-php-type-bridge

[フロントエンド・PHPカンファレンス北海道2026](https://frontend-php-con.hokkaido.jp/) で発表する「[TypeScript Compiler APIとPHP-Parserを活用し、TypeScriptとPHPで型を共有する](https://fortee.jp/frontend-phpcon-do-2026/proposal/d7c6d509-cd29-4afa-abbd-c9fa6818960a)」のサンプルコードです。

元実装：<https://blog.did0.es/entries/0ac62654-ea0a-4d98-8a5f-a646b4a5e964>

---

## 概要

TypeScript の型定義を **TypeScript Compiler API** で解析し、JSON に変換したあと、**PHP-Parser** を使って PHP 側の PHPDoc アノテーションを自動的に書き換えます。型変更を TypeScript 側だけに書けば、PHP 側 (PHPStan) の型情報も自動で同期されます。

```
workspaces/type-bridge/src/api.ts   ← ここに TypeScript 型を書く
        │  npm run bridge:extract
        ▼
workspaces/type-bridge/json/api.json
        │  npm run bridge:apply
        ▼
workspaces/server/src/shared/http_variables.php  ← @param / @return が自動更新
        │  npm run phpstan
        ▼
PHPStan による型チェック
```

---

## プロジェクト構成

```
workspaces/
  client/          Next.js フロントエンド
  server/          PHP サーバー
    src/shared/
      http_variables.php   ブリッジ管理対象 (API 用)
      types_showcase.php   ブリッジ管理対象 (ショーケース用)
    scripts/
      types_bridge.php     ブリッジスクリプト (API 用)
      showcase_bridge.php  ブリッジスクリプト (ショーケース用)
  type-bridge/     TypeScript 型定義 & 抽出スクリプト
    src/
      api.ts        API 型定義
      showcase.ts   ショーケース型定義
    json/
      api.json      抽出結果 (自動生成)
      showcase.json 抽出結果 (自動生成)
```

---

## セットアップ

```bash
# Node.js 依存パッケージをインストール
npm install

# PHP 依存パッケージをインストール
composer install --working-dir=workspaces/server
```

---

## type-bridge の使い方

### 1. API 型ブリッジ

`workspaces/type-bridge/src/api.ts` に型を定義します。

```typescript
// 例: GET パラメータのキー
export type GetParam = 'greeting' | 'name';

// 例: JSON ボディの形状
export type PostJsonBody = {
  message: string;
  count: number;
  author: {
    name: string;
    age: number;
  };
};

// keyof を使うと自動でユニオン型に解決される
export type PostJsonKey = keyof PostJsonBody;
```

ブリッジを実行します。

```bash
# 抽出 + 適用をまとめて実行
npm run bridge

# または個別に実行
npm run bridge:extract   # TS → json/api.json
npm run bridge:apply     # json/api.json → http_variables.php の PHPDoc 更新
```

PHPStan で型を検証します。

```bash
npm run phpstan
```

#### 書き換わる対象

`HttpVariables` クラスの以下のメソッドが自動更新されます。

| メソッド | TypeScript キー | 更新される PHPDoc |
|---|---|---|
| `get(string $key)` | `GetParam` | `@param` |
| `postFormData(string $key)` | `PostFormDataKey` | `@param` |
| `postJson(string $key)` | `PostJsonKey` + `PostJsonBody` | `@param` + `@return` + メソッドボディ |

`PostJsonBody` はオブジェクト型のため、`@return` に PHPStan 条件型が生成され、メソッドボディの `match` 式も型強制付きで書き換わります。

---

### 2. ショーケース型ブリッジ

`workspaces/type-bridge/src/showcase.ts` に TypeScript 型を定義します。

```typescript
export type TsString   = string;
export type TsStatus   = 'draft' | 'published' | 'archived';
export type TsNested   = { user: { name: string; address: { city: string; zip: number } } };
export type TsNever    = never;
export type TsVoid     = void;
export type TsPartial  = Partial<{ name: string; age: number }>;

// keyof T → 参照先の型定義を展開して key-of<T> を生成
export type TsSimpleShape = { id: number; name: string };
export type TsKeyOf       = keyof TsSimpleShape;

// T['key'] → 参照先のプロパティ型に解決
export type TsIndexedAccess = TsOptionalShape['required'];
```

ブリッジを実行します。

```bash
npm run bridge:showcase
```

`workspaces/server/src/shared/types_showcase.php` の `TypesShowcase` クラスの各メソッドの `@return` アノテーションが自動更新されます。

---

## 型マッピング早見表

### bridge で自動変換できる型

| TypeScript | PHPStan / PHPDoc | 備考 |
|---|---|---|
| `string` | `string` | |
| `number` | `int\|float` | |
| `boolean` | `bool` | |
| `null` | `null` | |
| `undefined` | `null` | |
| `unknown` | `mixed` | TS の「絞り込み前は使用不可」という安全性は PHPStan の `mixed` では再現不可 |
| `any` | `mixed` | TS の型チェックスキップ意味論は PHPStan の `mixed` と一致しない |
| `never` | `never` | |
| `void` | `void` | PHP では戻り値型専用 |
| `'a' \| 'b'` | `'a'\|'b'` | |
| `1 \| 2` | `1\|2` | |
| `string \| null` | `string\|null` | |
| `string \| number \| boolean` | `string\|int\|float\|bool` | |
| `string[]` / `Array<T>` | `list<string>` / `list<T>` | |
| `[string, number]` | `array{0: string, 1: int\|float}` | |
| `Record<string, number>` | `array<string, int\|float>` | |
| `{ name: string; age?: number }` | `array{name: string, age?: int\|float}` | |
| `{ required: string; [key: string]: unknown }` | `array{required: string, ...<string, mixed>}` | PHPStan 2.2+ Unsealed Array Shapes |
| `{ name: string } & { age: number }` | `array{name: string, ...}&array{age: int\|float, ...}` | |
| `NonNullable<string \| null>` | `string` | null を除去して解決 |
| `Partial<T>` | 全プロパティが `?` の `array{...}` | 型引数が同ファイル内のインラインオブジェクトの場合 |
| `Required<T>` | 全プロパティが非 `?` の `array{...}` | 型引数が同ファイル内のインラインオブジェクトの場合 |
| `keyof T` | `key-of<array{...}>` | 同ファイル内の型エイリアス参照を展開して変換 |
| `T['key']` | プロパティ型に解決 | 同ファイル内の型エイリアス参照を展開して変換 |

### bridge では再現不可な型

| TypeScript | 理由 |
|---|---|
| `key-of<self::CONST>`（PHP クラス定数版 keyof） | PHP クラス定数を参照する構文であり、TypeScript に対応物がない |
| `T extends U ? X : Y`（条件型） | PHPStan の条件型は `@template` 手書きが必要で、型エイリアスから自動生成できない |
| Generics（`@template`） | 関数シグネチャの型変数は型エイリアス (`type T = ...`) で表現できない |
| Mapped types（`{ [K in keyof T]: ... }`） | PHP/PHPStan に対応する汎用構文がない |
| `Readonly<T>` | PHP の `readonly` はプロパティ単位の宣言でジェネリック変換不可 |
| `symbol` | PHP にシンボルの概念がなく PHPStan にも対応型なし |
| Template literal types（`` `${string}Id` ``） | 対応する PHPStan 構文なし |

---

## 開発サーバーの起動

```bash
# PHP サーバー (localhost:9999)
npm run server

# Next.js フロントエンド (localhost:3000)
npm run dev
```

---

## その他のコマンド

```bash
# 型チェック (TypeScript)
npm run typecheck

# PHP コードフォーマット
npm run format:php
```
