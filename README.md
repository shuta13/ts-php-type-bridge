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
export type TsString  = string;
export type TsStatus  = 'draft' | 'published' | 'archived';
export type TsNested  = { user: { name: string; address: { city: string; zip: number } } };
export type TsNever   = never;
// ... その他多数
```

ブリッジを実行します。

```bash
npm run bridge:showcase
```

`workspaces/server/src/shared/types_showcase.php` の `TypesShowcase` クラスの各メソッドの `@return` アノテーションが自動更新されます。

---

## 型マッピング早見表

| TypeScript | PHPStan / PHPDoc |
|---|---|
| `string` | `string` |
| `number` | `int\|float` |
| `boolean` | `bool` |
| `null` | `null` |
| `undefined` | `null` |
| `unknown` / `any` | `mixed` |
| `never` | `never` |
| `'a' \| 'b'` | `'a'\|'b'` |
| `1 \| 2` | `1\|2` |
| `string \| null` | `string\|null` |
| `string[]` / `Array<T>` | `list<string>` / `list<T>` |
| `[string, number]` | `array{0: string, 1: int\|float}` |
| `Record<string, number>` | `array<string, int\|float>` |
| `{ name: string; age?: number }` | `array{name: string, age?: int\|float}` |
| `{ required: string; [key: string]: unknown }` | `array{required: string, ...<string, mixed>}` |
| `{ name: string } & { age: number }` | `array{name: string, ...}&array{age: int\|float, ...}` |

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
