/**
 * TypeScript型とPHPStan/PHPDoc型の対応を示すショーケース定義。
 * type-bridge パイプラインで json/showcase.json に変換され、
 * showcase_bridge.php が types_showcase.php のアノテーションを生成する。
 */

// 1. プリミティブ型をまとめたオブジェクト形状
export type TsPrimitives = {
  str: string;
  num: number;
  bool: boolean;
  nul: null;
};

// 2. 文字列リテラルユニオン
export type TsStatus = 'draft' | 'published' | 'archived';

// 3. 数値リテラルユニオン
export type TsLevel = 1 | 2 | 3;

// 4. Nullable (string | null)
export type TsNullable = string | null;

// 5. プリミティブのユニオン
export type TsUnion = string | number | boolean;

// 6. オプショナルプロパティを持つオブジェクト
export type TsOptionalShape = {
  required: string;
  optional?: number;
  nullableOpt?: string | null;
};

// 7. 配列型 (T[])
export type TsStringArray = string[];

// 8. ジェネリック配列 Array<T>（オブジェクト要素）
export type TsObjectArray = Array<{ id: number; label: string }>;

// 9. タプル型
export type TsPair = [string, number];

// 10. Record型
export type TsRecord = Record<string, number>;

// 11. ネストオブジェクト
export type TsNested = {
  user: {
    name: string;
    address: { city: string; zip: number };
  };
};

// 12. インデックスシグネチャ付きオブジェクト（開いた形状）→ PHPStan 2.2 Unsealed Array Shapes
export type TsOpenShape = {
  required: string;
  [key: string]: unknown;
};

// 13. 交差型
export type TsIntersection = { name: string } & { age: number };

// 14. never型
export type TsNever = never;
