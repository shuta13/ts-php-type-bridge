/**
 * Extracts TypeScript type alias declarations from src/showcase.ts using
 * the TypeScript compiler API and writes them as JSON to json/showcase.json.
 *
 * Supported patterns (encoded via __kind discriminated union):
 *   - Primitive / verbatim text         → string
 *   - Union (T | U | ...)               → { __kind: 'union', members: [...] }
 *   - Nullable (T | null)               → { __kind: 'nullable', inner: ... }
 *   - Object shape { k: T; k2?: U }     → { __kind: 'object', properties: {...}, sealed, extra? }
 *   - Index signature [key: K]: V       → sets sealed=false and extra={key,value} on parent object
 *   - Array T[]                         → { __kind: 'array', element: ... }
 *   - Generic Array<T>                  → { __kind: 'array', element: ... }
 *   - Tuple [T, U]                      → { __kind: 'tuple', elements: [...] }
 *   - Record<K, V>                      → { __kind: 'record', key: K, value: ... }
 *   - keyof T                           → { __kind: 'keyof', operand: ... }
 *   - T['key']                          → resolveTypeRefs で property type に解決（JSON には残らない）
 *   - Partial<T>                        → object shape の全プロパティを optional: true に変換
 *   - Required<T>                       → object shape の全プロパティを optional: false に変換
 *
 * Run: npm -w type-bridge run extract:showcase
 * Output: workspaces/type-bridge/json/showcase.json
 */
import * as ts from 'typescript';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const srcPath = join(__dirname, '../src/showcase.ts');
const outPath = join(__dirname, '../json/showcase.json');

const source = readFileSync(srcPath, 'utf-8');
const sourceFile = ts.createSourceFile('showcase.ts', source, ts.ScriptTarget.Latest, true);

type ExtractedProperty = { type: ExtractedShape; optional: boolean };
type ExtractedShape =
  | string
  | { __kind: 'object'; properties: Record<string, ExtractedProperty>; sealed: boolean; extra?: { key: string; value: string } }
  | { __kind: 'array'; element: ExtractedShape }
  | { __kind: 'tuple'; elements: ExtractedShape[] }
  | { __kind: 'record'; key: string; value: ExtractedShape }
  | { __kind: 'nullable'; inner: ExtractedShape }
  | { __kind: 'union'; members: ExtractedShape[] }
  | { __kind: 'intersection'; members: ExtractedShape[] }
  | { __kind: 'keyof'; operand: ExtractedShape }
  | { __kind: 'indexedAccess'; object: ExtractedShape; key: string };

function extractTypeNode(node: ts.TypeNode): ExtractedShape {
  // keyof T
  if (ts.isTypeOperatorNode(node) && node.operator === ts.SyntaxKind.KeyOfKeyword) {
    return { __kind: 'keyof', operand: extractTypeNode(node.type) };
  }

  // T['key'] — indexed access type
  if (ts.isIndexedAccessTypeNode(node)) {
    const indexNode = node.indexType;
    if (ts.isLiteralTypeNode(indexNode) && ts.isStringLiteral(indexNode.literal)) {
      return { __kind: 'indexedAccess', object: extractTypeNode(node.objectType), key: indexNode.literal.text };
    }
    return node.getText(sourceFile);
  }

  // T[]
  if (ts.isArrayTypeNode(node)) {
    return { __kind: 'array', element: extractTypeNode(node.elementType) };
  }

  // [T, U, ...] — named tuple members (e.g. [first: T]) are unwrapped
  if (ts.isTupleTypeNode(node)) {
    const elements = node.elements.map((e) =>
      ts.isNamedTupleMember(e) ? extractTypeNode(e.type) : extractTypeNode(e as ts.TypeNode),
    );
    return { __kind: 'tuple', elements };
  }

  // Array<T>, Record<K, V>, or bare type reference
  if (ts.isTypeReferenceNode(node)) {
    const name = ts.isIdentifier(node.typeName)
      ? node.typeName.text
      : node.typeName.getText(sourceFile);
    if (name === 'Array' && node.typeArguments?.length === 1) {
      return { __kind: 'array', element: extractTypeNode(node.typeArguments[0]!) };
    }
    if (name === 'Record' && node.typeArguments?.length === 2) {
      return {
        __kind: 'record',
        key: node.typeArguments[0]!.getText(sourceFile),
        value: extractTypeNode(node.typeArguments[1]!),
      };
    }
    if (name === 'Partial' && node.typeArguments?.length === 1) {
      const inner = extractTypeNode(node.typeArguments[0]!);
      if (typeof inner === 'object' && inner.__kind === 'object') {
        const props: Record<string, ExtractedProperty> = {};
        for (const [k, v] of Object.entries(inner.properties)) {
          props[k] = { type: v.type, optional: true };
        }
        return { ...inner, properties: props };
      }
      return inner;
    }
    if (name === 'Required' && node.typeArguments?.length === 1) {
      const inner = extractTypeNode(node.typeArguments[0]!);
      if (typeof inner === 'object' && inner.__kind === 'object') {
        const props: Record<string, ExtractedProperty> = {};
        for (const [k, v] of Object.entries(inner.properties)) {
          props[k] = { type: v.type, optional: false };
        }
        return { ...inner, properties: props };
      }
      return inner;
    }
    if (name === 'NonNullable' && node.typeArguments?.length === 1) {
      const inner = extractTypeNode(node.typeArguments[0]!);
      if (typeof inner === 'object' && inner.__kind === 'nullable') {
        return inner.inner;
      }
      if (typeof inner === 'object' && inner.__kind === 'union') {
        const nonNull = inner.members.filter((m) => m !== 'null');
        if (nonNull.length === 1) return nonNull[0]!;
        return { __kind: 'union', members: nonNull };
      }
      return inner;
    }
    return node.getText(sourceFile);
  }

  // T & U & ...
  if (ts.isIntersectionTypeNode(node)) {
    return { __kind: 'intersection', members: node.types.map(extractTypeNode) };
  }

  // T | U | ... — split nullable from other unions
  if (ts.isUnionTypeNode(node)) {
    const members = [...node.types];
    const nullIdx = members.findIndex((m) => m.kind === ts.SyntaxKind.NullKeyword);
    if (nullIdx !== -1 && members.length === 2) {
      const inner = members[nullIdx === 0 ? 1 : 0]!;
      return { __kind: 'nullable', inner: extractTypeNode(inner) };
    }
    return { __kind: 'union', members: members.map(extractTypeNode) };
  }

  // { prop: T; prop2?: U; [key: K]: V }
  if (ts.isTypeLiteralNode(node)) {
    return extractTypeLiteral(node);
  }

  return node.getText(sourceFile);
}

function extractTypeLiteral(node: ts.TypeLiteralNode): ExtractedShape {
  const properties: Record<string, ExtractedProperty> = {};
  let sealed = true;
  let extra: { key: string; value: string } | undefined;

  for (const member of node.members) {
    if (ts.isPropertySignature(member) && member.type) {
      const key = ts.isIdentifier(member.name)
        ? member.name.text
        : member.name.getText(sourceFile);
      properties[key] = {
        type: extractTypeNode(member.type),
        optional: member.questionToken !== undefined,
      };
    } else if (ts.isIndexSignatureDeclaration(member) && member.type) {
      sealed = false;
      extra = {
        key: member.parameters[0]?.type?.getText(sourceFile) ?? 'string',
        value: member.type.getText(sourceFile),
      };
    }
  }

  return {
    __kind: 'object',
    properties,
    sealed,
    ...(extra !== undefined ? { extra } : {}),
  };
}

/**
 * 型エイリアス参照を解決するポストプロセス。
 *   keyof SomeType      → { __kind: 'keyof', operand: <SomeType の定義> }
 *   SomeType['key']     → <SomeType.key の型> （JSON には indexedAccess は残らない）
 */
function resolveTypeRefs(
  shape: ExtractedShape,
  allTypes: Record<string, ExtractedShape>,
): ExtractedShape {
  if (typeof shape !== 'object') return shape;

  if (shape.__kind === 'keyof') {
    const operand = shape.operand;
    if (typeof operand === 'string' && operand in allTypes) {
      return { __kind: 'keyof', operand: allTypes[operand]! };
    }
    return shape;
  }

  if (shape.__kind === 'indexedAccess') {
    let obj: ExtractedShape = shape.object;
    if (typeof obj === 'string' && obj in allTypes) {
      obj = allTypes[obj]!;
    }
    if (typeof obj === 'object' && obj.__kind === 'object' && shape.key in obj.properties) {
      return obj.properties[shape.key]!.type;
    }
    return 'mixed';
  }

  return shape;
}

const types: Record<string, ExtractedShape> = {};

function visit(node: ts.Node): void {
  if (
    ts.isTypeAliasDeclaration(node) &&
    node.modifiers?.some((m) => m.kind === ts.SyntaxKind.ExportKeyword)
  ) {
    types[node.name.text] = extractTypeNode(node.type);
  }
  ts.forEachChild(node, visit);
}

visit(sourceFile);

// 型エイリアス参照（keyof / T['key']）を解決する
for (const key of Object.keys(types)) {
  types[key] = resolveTypeRefs(types[key]!, types);
}

mkdirSync(dirname(outPath), { recursive: true });
writeFileSync(outPath, JSON.stringify(types, null, 2) + '\n');

console.log('Extracted showcase types:');
console.log(JSON.stringify(types, null, 2));
console.log(`\nWritten to: ${outPath}`);
