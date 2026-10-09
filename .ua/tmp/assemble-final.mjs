// Assemble the final KnowledgeGraph per the Phase 6 contract.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const COMMIT = process.argv[3];

const graph = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, 'utf8'));
const scan = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/scan-result.json`, 'utf8'));
const layers = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/layers.json`, 'utf8'));
const tour = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/tour.json`, 'utf8'));

const nodeIds = new Set(graph.nodes.map((n) => n.id));
const FILE_LEVEL = new Set([
  'file', 'config', 'document', 'service', 'pipeline',
  'table', 'schema', 'resource', 'endpoint',
]);

// Normalize layers: unwrap envelope, rename legacy fields, drop dangling refs.
let layerList = Array.isArray(layers) ? layers : layers.layers ?? [];
layerList = layerList.map((L, k) => {
  let ids = L.nodeIds ?? L.nodes ?? [];
  ids = ids.map((x) => (typeof x === 'object' ? x.id : x));
  ids = ids.map((id) => (!/^(file|config|document|service|pipeline|table|schema|resource|endpoint):/.test(id) ? `file:${id}` : id));
  return {
    id: L.id ?? `layer:${String(L.name ?? `layer-${k}`).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
    name: L.name,
    description: L.description ?? '',
    nodeIds: ids.filter((id) => nodeIds.has(id)),
  };
});

const final = {
  version: '1.0.0',
  project: {
    name: scan.name,
    languages: scan.languages,
    frameworks: scan.frameworks ?? [],
    description: scan.description,
    analyzedAt: new Date().toISOString(),
    gitCommitHash: COMMIT,
  },
  nodes: graph.nodes,
  edges: graph.edges,
  layers: layerList,
  tour,
};

// --- Contract validation before write ---
const issues = [];
for (const L of layerList) {
  for (const f of ['id', 'name', 'description', 'nodeIds']) {
    if (L[f] === undefined || L[f] === null || L[f] === '') issues.push(`layer '${L.id}' missing/empty ${f}`);
  }
  if (!Array.isArray(L.nodeIds)) issues.push(`layer '${L.id}' nodeIds not an array`);
}
for (const [i, s] of tour.entries()) {
  for (const f of ['order', 'title', 'description', 'nodeIds']) {
    if (s[f] === undefined || s[f] === null) issues.push(`tour[${i}] missing ${f}`);
  }
  for (const id of s.nodeIds ?? []) if (!nodeIds.has(id)) issues.push(`tour[${i}] refs missing node '${id}'`);
}

// Every file-level node must sit in exactly one layer.
const assigned = new Map();
for (const L of layerList) for (const id of L.nodeIds) {
  if (assigned.has(id)) issues.push(`node '${id}' in multiple layers (${assigned.get(id)}, ${L.id})`);
  assigned.set(id, L.id);
}
for (const n of graph.nodes) {
  if (FILE_LEVEL.has(n.type) && !assigned.has(n.id)) issues.push(`file node '${n.id}' not in any layer`);
}

console.log(`nodes=${final.nodes.length} edges=${final.edges.length} layers=${layerList.length} tour=${tour.length}`);
console.log(`file-level nodes assigned: ${assigned.size}`);
console.log(`contract issues: ${issues.length}`);
for (const i of issues.slice(0, 30)) console.log(`   ! ${i}`);

writeFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, JSON.stringify(final, null, 2));
process.exit(issues.length ? 1 : 0);