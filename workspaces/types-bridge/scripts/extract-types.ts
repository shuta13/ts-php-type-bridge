/**
 * Extracts TypeScript type alias declarations from src/api.ts using
 * the TypeScript compiler API and writes them as JSON to json/api.json.
 *
 * - Union literal types  → string  (verbatim source text)
 * - Object literal types → Record<string, string> (key → value type text)
 * - `keyof X` types      → resolved to a union string from the referenced shape
 *
 * Run: yarn workspace types-bridge extract
 * Output: workspaces/types-bridge/json/api.json
 */
import * as ts from 'typescript';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const srcPath = join(__dirname, '../src/api.ts');
const outPath = join(__dirname, '../json/api.json');

const source = readFileSync(srcPath, 'utf-8');

const sourceFile = ts.createSourceFile(
  'api.ts',
  source,
  ts.ScriptTarget.Latest,
  /* setParentNodes */ true,
);

type ShapeMap = Record<string, string>;
type ExtractedValue = string | ShapeMap | { __keyof: string };
const types: Record<string, ExtractedValue> = {};

function visit(node: ts.Node): void {
  if (
    ts.isTypeAliasDeclaration(node) &&
    node.modifiers?.some((m) => m.kind === ts.SyntaxKind.ExportKeyword)
  ) {
    const name = node.name.text;

    if (ts.isTypeLiteralNode(node.type)) {
      const shape: ShapeMap = {};
      for (const member of node.type.members) {
        if (ts.isPropertySignature(member) && member.type) {
          const keyName = ts.isIdentifier(member.name)
            ? member.name.text
            : member.name.getText(sourceFile);
          shape[keyName] = member.type.getText(sourceFile);
        }
      }
      types[name] = shape;
    } else if (
      ts.isTypeOperatorNode(node.type) &&
      node.type.operator === ts.SyntaxKind.KeyOfKeyword &&
      ts.isTypeReferenceNode(node.type.type)
    ) {
      // Defer resolution until all types are collected.
      const referencedName = node.type.type.typeName.getText(sourceFile);
      types[name] = { __keyof: referencedName };
    } else {
      types[node.name.text] = node.type.getText(sourceFile);
    }
  }
  ts.forEachChild(node, visit);
}

visit(sourceFile);

// Resolve deferred `keyof X` references to union literal strings.
for (const [name, value] of Object.entries(types)) {
  if (typeof value === 'object' && '__keyof' in value) {
    const ref = types[(value as { __keyof: string }).__keyof];
    if (ref && typeof ref === 'object' && !('__keyof' in ref)) {
      types[name] = Object.keys(ref as ShapeMap)
        .map((k) => `'${k}'`)
        .join(' | ');
    }
  }
}

mkdirSync(dirname(outPath), { recursive: true });
writeFileSync(outPath, JSON.stringify(types, null, 2) + '\n');

console.log('Extracted types:');
console.log(JSON.stringify(types, null, 2));
console.log(`\nWritten to: ${outPath}`);
