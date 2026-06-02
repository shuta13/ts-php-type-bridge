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
  | { __kind: 'intersection'; members: ExtractedShape[] };

function extractTypeNode(node: ts.TypeNode): ExtractedShape {
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

mkdirSync(dirname(outPath), { recursive: true });
writeFileSync(outPath, JSON.stringify(types, null, 2) + '\n');

console.log('Extracted showcase types:');
console.log(JSON.stringify(types, null, 2));
console.log(`\nWritten to: ${outPath}`);
