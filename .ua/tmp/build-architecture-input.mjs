// Build the architecture-analyzer input: file-level nodes + edges between them.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const graph = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, 'utf8'));

const FILE_LEVEL = new Set([
  'file', 'config', 'document', 'service', 'pipeline',
  'table', 'schema', 'resource', 'endpoint',
]);

const fileNodes = graph.nodes
  .filter((n) => FILE_LEVEL.has(n.type))
  .map(({ id, type, name, filePath, summary, tags }) => ({ id, type, name, filePath, summary, tags }));

const fileIds = new Set(fileNodes.map((n) => n.id));

// Only file-level -> file-level edges; drop file->function `contains` noise.
const allEdges = graph.edges
  .filter((e) => fileIds.has(e.source) && fileIds.has(e.target))
  .map(({ source, target, type }) => ({ source, target, type }));

const importEdges = allEdges.filter((e) => e.type === 'imports');

const out = { fileNodes, importEdges, allEdges };
writeFileSync(`${ROOT}/.ua/tmp/architecture-input.json`, JSON.stringify(out, null, 2));
console.log(
  `fileNodes=${fileNodes.length} importEdges=${importEdges.length} allFileEdges=${allEdges.length}`,
);