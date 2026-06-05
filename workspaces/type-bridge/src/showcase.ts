/**
 * TypeScript型とPHPStan/PHPDoc型の対応を示すショーケース定義。
 * type-bridge パイプラインで json/showcase.json に変換され、
 * showcase_bridge.php が types_showcase.php のアノテーションを生成する。
 */

// 1. プリミティブ型（個別）
export type TsString = string;
export type TsNumber = number;
export type TsBoolean = boolean;
export type TsNull = null;

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

// 15. unknown型
export type TsUnknown = unknown;

// 16. any型
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type TsAny = any;

// 17. void型
export type TsVoid = void;

// 18. undefined型
export type TsUndefined = undefined;

// 19. NonNullable ユーティリティ型
export type TsNonNullable = NonNullable<string | null>;

// 20. keyof T → key-of<T>
// 参照型解決のデモ: TsSimpleShape は同ファイル内の型エイリアスを参照する
export type TsSimpleShape = { id: number; name: string };
export type TsKeyOf = keyof TsSimpleShape;

// 21. T['key'] インデックスアクセス型 → プロパティ型に解決
export type TsIndexedAccess = TsOptionalShape['required'];

// 22. Partial<T> → 全プロパティを optional に
export type TsPartial = Partial<{ name: string; age: number }>;

// 23. Required<T> → 全プロパティから optional を除去
export type TsRequired = Required<{ name?: string; age?: number }>;
