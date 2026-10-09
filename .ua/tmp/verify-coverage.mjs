// Verify assembled graph covers every scanned file, and classify dropped edges.
import { readFileSync } from 'node:fs';

const ROOT = process.argv[2];
const scan = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/scan-result.json`, 'utf8'));
const graph = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, 'utf8'));

const nodes = graph.nodes ?? [];
const edges = graph.edges ?? [];
const byId = new Map(nodes.map((n) => [n.id, n]));

const covered = new Set(
  nodes
    .filter((n) => n.filePath)
    .map((n) => n.filePath),
);

const missing = scan.files.map((f) => f.path).filter((p) => !covered.has(p));

console.log(`scanned files      : ${scan.files.length}`);
console.log(`graph nodes        : ${nodes.length}`);
console.log(`graph edges        : ${edges.length}`);
console.log(`files with a node  : ${covered.size}`);
console.log(`files MISSING      : ${missing.length}`);
for (const m of missing) console.log(`   ! ${m}`);

const nodeTypes = {};
for (const n of nodes) nodeTypes[n.type] = (nodeTypes[n.type] ?? 0) + 1;
console.log(`\nnode types         : ${JSON.stringify(nodeTypes)}`);

const edgeTypes = {};
for (const e of edges) edgeTypes[e.type] = (edgeTypes[e.type] ?? 0) + 1;
console.log(`edge types         : ${JSON.stringify(edgeTypes)}`);

const dangling = edges.filter((e) => !byId.has(e.source) || !byId.has(e.target));
console.log(`dangling edges     : ${dangling.length}`);

const orphans = nodes.filter(
  (n) => !edges.some((e) => e.source === n.id || e.target === n.id),
);
console.log(`orphan nodes       : ${orphans.length}`);