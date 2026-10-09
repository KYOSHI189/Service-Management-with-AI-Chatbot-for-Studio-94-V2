// Build tour-builder input: file-level nodes, layer defs (no nodeIds), all edges.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const graph = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, 'utf8'));
const layers = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/layers.json`, 'utf8'));

const FILE_LEVEL = new Set([
  'file', 'config', 'document', 'service', 'pipeline',
  'table', 'schema', 'resource', 'endpoint',
]);

// No function/class nodes, per the tour-builder contract.
const nodes = graph.nodes
  .filter((n) => FILE_LEVEL.has(n.type))
  .map(({ id, name, filePath, summary, type }) => ({ id, name, filePath, summary, type }));

const nodeIds = new Set(graph.nodes.map((n) => n.id));
const edges = graph.edges
  .filter((e) => nodeIds.has(e.source) && nodeIds.has(e.target))
  .map(({ source, target, type }) => ({ source, target, type }));

const layerDefs = layers.map(({ id, name, description }) => ({ id, name, description }));

const out = { nodes, layers: layerDefs, edges };
writeFileSync(`${ROOT}/.ua/tmp/tour-input.json`, JSON.stringify(out, null, 2));
console.log(`nodes=${nodes.length} layers=${layerDefs.length} edges=${edges.length}`);