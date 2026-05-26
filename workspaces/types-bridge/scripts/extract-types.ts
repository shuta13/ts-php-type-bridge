/**
 * Extracts TypeScript type alias declarations from src/api.ts using
 * the TypeScript compiler API and writes them as JSON to json/api.json.
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

// Parse the TypeScript source into an AST without type-checking.
const sourceFile = ts.createSourceFile(
  'api.ts',
  source,
  ts.ScriptTarget.Latest,
  /* setParentNodes */ true,
);

const types: Record<string, string> = {};

function visit(node: ts.Node): void {
  // Only capture exported type alias declarations at the top level.
  if (
    ts.isTypeAliasDeclaration(node) &&
    node.modifiers?.some((m) => m.kind === ts.SyntaxKind.ExportKeyword)
  ) {
    // getText() returns the verbatim source text of the type expression.
    types[node.name.text] = node.type.getText(sourceFile);
  }
  ts.forEachChild(node, visit);
}

visit(sourceFile);

mkdirSync(dirname(outPath), { recursive: true });
writeFileSync(outPath, JSON.stringify(types, null, 2) + '\n');

console.log('Extracted types:');
console.log(JSON.stringify(types, null, 2));
console.log(`\nWritten to: ${outPath}`);
