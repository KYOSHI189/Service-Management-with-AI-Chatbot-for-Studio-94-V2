#!/usr/bin/env node
/*
 * ua-tour-analyze.js
 * Graph topology analysis for the Understand-Anything tour-builder agent.
 *
 * usage: node ua-tour-analyze.js <input.json> <output.json>
 *
 * Computes: fan-in / fan-out rankings, entry point candidates, a BFS
 * dependency traversal from the top code entry point, a non-code file
 * inventory, tightly coupled clusters, the layer list and a node summary index.
 */
'use strict';

const fs = require('fs');
const path = require('path');

function die(msg) {
  process.stderr.write('[ua-tour-analyze] fatal: ' + msg + '\n');
  process.exit(1);
}

const inPath = process.argv[2];
const outPath = process.argv[3];
if (!inPath || !outPath) die('usage: ua-tour-analyze.js <input.json> <output.json>');

let input;
try {
  input = JSON.parse(fs.readFileSync(inPath, 'utf8'));
} catch (e) {
  die('cannot parse input ' + inPath + ': ' + e.message);
}

const nodes = Array.isArray(input.nodes) ? input.nodes : [];
const edges = Array.isArray(input.edges) ? input.edges : [];
const layers = Array.isArray(input.layers) ? input.layers : [];
if (nodes.length === 0) die('input contains no nodes');

const NUL = String.fromCharCode(0);
const pairKey = (a, b) => a + NUL + b;

// ---------------------------------------------------------------- node basics

const byId = new Map();
for (const n of nodes) {
  if (n && typeof n.id === 'string') byId.set(n.id, n);
}
function nameOf(id) {
  const n = byId.get(id);
  return n ? (n.name || id) : id;
}
function typeOf(id) {
  const n = byId.get(id);
  return n ? (n.type || 'file') : 'unknown';
}
function summaryOf(id) {
  const n = byId.get(id);
  return n ? (n.summary || '') : '';
}
function filePathOf(id) {
  const n = byId.get(id);
  if (!n) return '';
  return n.filePath || n.name || '';
}
function pathDepth(id) {
  return filePathOf(id).replace(/\\/g, '/').split('/').filter(Boolean).length;
}

// --------------------------------------------------- full-graph adjacency

const fullOut = new Map();
const fullIn = new Map();
function addTo(map, key, val) {
  if (!map.has(key)) map.set(key, new Set());
  map.get(key).add(val);
}
const edgeTypeMap = new Map();
for (const e of edges) {
  if (!e || typeof e.source !== 'string' || typeof e.target !== 'string') continue;
  addTo(fullOut, e.source, e.target);
  addTo(fullIn, e.target, e.source);
  edgeTypeMap.set(pairKey(e.source, e.target), e.type);
}
function edgeType(a, b) {
  return edgeTypeMap.get(pairKey(a, b)) || null;
}
function fanOut(id) { return (fullOut.get(id) || new Set()).size; }
function fanIn(id) { return (fullIn.get(id) || new Set()).size; }

// ---------------------------------------------------------------- A. fan-in

const fanInRanking = Array.from(byId.keys())
  .map((id) => ({ id, fanIn: fanIn(id), name: nameOf(id), type: typeOf(id) }))
  .filter((r) => r.fanIn > 0)
  .sort((a, b) => b.fanIn - a.fanIn || a.id.localeCompare(b.id))
  .slice(0, 20);

// ---------------------------------------------------------------- B. fan-out

const fanOutRanking = Array.from(byId.keys())
  .map((id) => ({ id, fanOut: fanOut(id), name: nameOf(id), type: typeOf(id) }))
  .filter((r) => r.fanOut > 0)
  .sort((a, b) => b.fanOut - a.fanOut || a.id.localeCompare(b.id))
  .slice(0, 20);

// --------------------------------------------------- file-level projection

/*
 * A file's role in the architecture is defined by the other *files* it relates
 * to, not by the individual functions it contains. So entry-point scoring,
 * BFS and clustering all run on this projection:
 *
 *   - endpoints are lifted onto declared nodes; a `function:<path>:<name>` or
 *     `class:<path>:<Name>` endpoint resolves to `file:<path>`, so a page that
 *     calls functions.php:db() still registers as depending on functions.php;
 *   - purely documentary relations ('documents', 'related') are dropped -- they
 *     describe commentary, not dependency.
 */
function resolveEndpoint(id) {
  if (byId.has(id)) return id;
  const m = /^(?:function|class):(.+):[^:]+$/.exec(id);
  if (m && byId.has('file:' + m[1])) return 'file:' + m[1];
  return null;
}

const PROJECTED_REL = new Set([
  'imports', 'depends_on', 'requires', 'calls', 'configures', 'exports'
]);
const projOut = new Map();
const projIn = new Map();
const projEdges = [];
const projSeen = new Set();
for (const e of edges) {
  if (!e || typeof e.source !== 'string' || typeof e.target !== 'string') continue;
  if (!PROJECTED_REL.has(e.type)) continue;
  const s = resolveEndpoint(e.source);
  const t = resolveEndpoint(e.target);
  if (!s || !t || s === t) continue;
  if (projSeen.has(pairKey(s, t))) continue;
  projSeen.add(pairKey(s, t));
  addTo(projOut, s, t);
  addTo(projIn, t, s);
  projEdges.push([s, t, e.type]);
}
function projFanOut(id) { return (projOut.get(id) || new Set()).size; }
function projFanIn(id) { return (projIn.get(id) || new Set()).size; }

// ------------------------------------------------- C. entry point candidates

const ENTRY_FILENAMES = new Set([
  'index.ts', 'index.js', 'main.ts', 'main.js', 'app.ts', 'app.js',
  'server.ts', 'server.js', 'mod.rs', 'main.go', 'main.py', 'main.rs',
  'manage.py', 'app.py', 'wsgi.py', 'asgi.py', 'run.py', '__main__.py',
  'Application.java', 'Main.java', 'Program.cs', 'config.ru', 'index.php',
  'App.swift', 'Application.kt', 'main.cpp', 'main.c'
]);

const isCode = (id) => {
  const t = typeOf(id);
  return t === 'file' || t === 'code' || t === 'module';
};
const codeNodes = Array.from(byId.keys()).filter(isCode);

const foVals = codeNodes.map(projFanOut).sort((a, b) => b - a);
const fiVals = codeNodes.map(projFanIn).sort((a, b) => a - b);
const foCut = foVals.length ? foVals[Math.max(0, Math.ceil(foVals.length * 0.1) - 1)] : 0;
const fiCut = fiVals.length ? fiVals[Math.max(0, Math.floor(fiVals.length * 0.25))] : 0;

const entryPointCandidates = [];
const unwiredCodeFiles = [];
for (const id of byId.keys()) {
  const t = typeOf(id);
  const nm = nameOf(id);
  const p = filePathOf(id).replace(/\\/g, '/');
  const rootName = p.split('/').pop();
  let score = 0;
  const reasons = [];

  if (t === 'document') {
    if (nm === 'README.md' && pathDepth(id) === 1) { score += 5; reasons.push('README.md at project root'); }
    else if (/\.md$/i.test(nm) && pathDepth(id) === 1) { score += 2; reasons.push('markdown at project root'); }
  } else if (isCode(id)) {
    /*
     * Reachability filter: a code file with no file-level edges at all is not
     * wired into the app -- nothing includes it and it includes nothing. Such
     * files (dead prototypes, orphan stubs, duplicate assets) can match an
     * entry-point filename by coincidence, so they are reported separately
     * instead of competing for the BFS start.
     */
    if (projFanIn(id) + projFanOut(id) === 0) {
      unwiredCodeFiles.push({ id, name: nm, filePath: p });
      continue;
    }
    if (ENTRY_FILENAMES.has(rootName)) { score += 3; reasons.push('entry filename ' + rootName); }
    if (pathDepth(id) <= 2) { score += 1; reasons.push('project root or one level deep'); }
    if (foCut > 0 && projFanOut(id) >= foCut) { score += 1; reasons.push('top 10% file-level fan-out (' + projFanOut(id) + ')'); }
    if (projFanIn(id) <= fiCut) { score += 1; reasons.push('bottom 25% file-level fan-in (' + projFanIn(id) + ')'); }
  }

  if (score > 0) {
    entryPointCandidates.push({
      id, score, name: nm, type: t, filePath: p, summary: summaryOf(id), reasons
    });
  }
}
entryPointCandidates.sort((a, b) => b.score - a.score || a.id.localeCompare(b.id));
const topEntryCandidates = entryPointCandidates.slice(0, 5);

// ------------------------------------------------------- D. BFS dependency chain

const FOLLOW_TYPES = new Set(['imports', 'calls', 'requires', 'depends_on']);
const topCodeEntry = entryPointCandidates.find((c) => isCode(c.id)) || null;

let bfsTraversal = {
  startNode: null, order: [], depthMap: {}, byDepth: {}, edgeTypes: Array.from(FOLLOW_TYPES)
};
if (topCodeEntry) {
  const start = topCodeEntry.id;
  const order = [start];
  const depthMap = {};
  const byDepth = {};
  depthMap[start] = 0;
  byDepth[0] = [start];
  const seen = new Set([start]);
  const queue = [start];
  while (queue.length) {
    const cur = queue.shift();
    const d = depthMap[cur];
    const nexts = Array.from(projOut.get(cur) || new Set())
      .filter((n) => byId.has(n) && FOLLOW_TYPES.has(edgeType(cur, n)))
      .sort();
    for (const nx of nexts) {
      if (seen.has(nx)) continue;
      seen.add(nx);
      depthMap[nx] = d + 1;
      (byDepth[d + 1] = byDepth[d + 1] || []).push(nx);
      order.push(nx);
      queue.push(nx);
    }
  }
  bfsTraversal = {
    startNode: start, order, depthMap, byDepth, edgeTypes: Array.from(FOLLOW_TYPES)
  };
}

// ------------------------------------------------------- E. non-code inventory

function collect(types) {
  const out = [];
  for (const id of byId.keys()) {
    if (types.indexOf(typeOf(id)) === -1) continue;
    out.push({ id, name: nameOf(id), type: typeOf(id), summary: summaryOf(id) });
  }
  out.sort((a, b) => a.id.localeCompare(b.id));
  return out;
}

const nonCodeFiles = {
  documentation: collect(['document']),
  infrastructure: collect(['service', 'pipeline', 'resource']),
  data: collect(['table', 'schema', 'endpoint']),
  config: collect(['config'])
};

// ------------------------------------------------------------- F. clusters

/*
 * Tightly coupled groups, in the spirit of a community / cluster analysis.
 *
 * The top hubs (config.php, functions.php, index.php, ...) are excluded as
 * cluster *members*: nearly every file depends on them, so admitting them
 * collapses every group into the same meaningless star. They remain valid
 * cluster *centers*, since "these five files all route through the kernel" is
 * the kernel's story, not a feature's.
 */
const HUBS = new Set(
  Array.from(byId.keys())
    .map((id) => ({ id, deg: projFanIn(id) }))
    .sort((a, b) => b.deg - a.deg || a.id.localeCompare(b.id))
    .slice(0, 4)
    .map((x) => x.id)
);

const projUndirected = new Map();
function plink(a, b) {
  if (!projUndirected.has(a)) projUndirected.set(a, new Set());
  projUndirected.get(a).add(b);
}
for (const pair of projEdges) {
  plink(pair[0], pair[1]);
  plink(pair[1], pair[0]);
}
function projNeighbors(id) { return projUndirected.get(id) || new Set(); }

function internalEdgeCount(members) {
  const set = new Set(members);
  let count = 0;
  for (const pair of projEdges) {
    if (set.has(pair[0]) && set.has(pair[1]) && pair[0] !== pair[1]) count++;
  }
  return count;
}

const clusters = [];
const clusterKeys = new Set();
function pushCluster(list, extra) {
  const sorted = list.filter((id) => !HUBS.has(id)).sort();
  if (sorted.length < 2 || sorted.length > 5) return;
  const ck = sorted.join(NUL);
  if (clusterKeys.has(ck)) return;
  clusterKeys.add(ck);
  clusters.push(Object.assign({ nodes: sorted, edgeCount: internalEdgeCount(sorted) }, extra || {}));
}

// 1. mutual pairs -- A depends on B AND B depends on A -- grown by any node
//    adjacent to 2+ current members, capped at 5.
const seenPair = new Set();
for (const pair of projEdges) {
  const s = pair[0];
  const t = pair[1];
  if (!projSeen.has(pairKey(t, s))) continue;
  const rkey = s < t ? pairKey(s, t) : pairKey(t, s);
  if (seenPair.has(rkey)) continue;
  seenPair.add(rkey);
  const members = [s, t];
  const inside = new Set(members);
  let grew = true;
  while (grew && members.length < 5) {
    grew = false;
    const cand = new Map();
    for (const m of members) {
      for (const n of projNeighbors(m)) {
        if (inside.has(n)) continue;
        cand.set(n, (cand.get(n) || 0) + 1);
      }
    }
    let best = null;
    let bestScore = 1;
    for (const entry of cand) {
      const id = entry[0];
      const shared = entry[1];
      const score = shared * 10 + projFanOut(id);
      if (shared >= 2 && score > bestScore) { bestScore = score; best = id; }
    }
    if (best) {
      members.push(best);
      inside.add(best);
      grew = true;
    }
  }
  pushCluster(members, { shape: 'mutual' });
}

// 2. shared-dependency groups: a center plus its non-hub dependents (cap 5).
function countShared(sa, sb, pool) {
  let c = 0;
  for (const x of pool) if (sa.has(x) && sb.has(x)) c++;
  return c;
}
for (const center of byId.keys()) {
  const deps = Array.from(projIn.get(center) || new Set())
    .filter((d) => !HUBS.has(d) && d !== center)
    .sort();
  if (deps.length < 2) continue;
  const ranked = deps.slice().sort((a, b) => {
    const shared = countShared(projNeighbors(a), projNeighbors(b), deps);
    const rev = countShared(projNeighbors(b), projNeighbors(a), deps);
    return (shared - rev) || (projFanOut(b) - projFanOut(a)) || a.localeCompare(b);
  });
  pushCluster([center].concat(ranked.slice(0, 4)), {
    shape: 'shared-dependency', center
  });
}

// 3. Greedy modularity communities over the undirected projection, reduced to
//    the densest 2-5 node representative subset of each.
function modularityCommunities() {
  const verts = Array.from(byId.keys())
    .filter((id) => !HUBS.has(id) && (projFanIn(id) + projFanOut(id)) > 0)
    .sort();
  if (verts.length < 2) return [];

  const idx = new Map();
  verts.forEach((v, i) => idx.set(v, i));
  const n = verts.length;
  const adj = [];
  const strength = new Array(n).fill(0);
  let m2 = 0;
  for (let i = 0; i < n; i++) adj.push([]);
  for (const pair of projEdges) {
    const si = idx.get(pair[0]);
    const ti = idx.get(pair[1]);
    if (si === undefined || ti === undefined) continue;
    adj[si].push(ti);
    adj[ti].push(si);
    strength[si] += 1;
    strength[ti] += 1;
    m2 += 2;
  }
  if (m2 === 0) return [];
  const m = m2 / 2;

  const comm = new Int32Array(n);
  for (let i = 0; i < n; i++) comm[i] = i;
  const commStrength = new Float64Array(n);
  for (let i = 0; i < n; i++) commStrength[i] = strength[i];

  function gainOfMove(i, to) {
    let kIn = 0;
    for (const j of adj[i]) if (j !== i && comm[j] === to) kIn += 1;
    return kIn - (commStrength[to] * strength[i]) / (2 * m);
  }

  for (let pass = 0; pass < 30; pass++) {
    let moved = 0;
    for (let i = 0; i < n; i++) {
      const from = comm[i];
      commStrength[from] -= strength[i];
      const options = new Set([from]);
      for (const j of adj[i]) if (j !== i) options.add(comm[j]);
      let bestC = from;
      let bestG = gainOfMove(i, from);
      for (const c of options) {
        if (c === from) continue;
        const g = gainOfMove(i, c);
        if (g > bestG + 1e-12) { bestG = g; bestC = c; }
      }
      comm[i] = bestC;
      commStrength[bestC] += strength[i];
      if (bestC !== from) moved++;
    }
    if (moved === 0) break;
  }

  const groups = new Map();
  for (let i = 0; i < n; i++) {
    if (!groups.has(comm[i])) groups.set(comm[i], []);
    groups.get(comm[i]).push(verts[i]);
  }
  return Array.from(groups.values()).sort((a, b) => b.length - a.length);
}

const communities = modularityCommunities();
for (const group of communities) {
  if (group.length < 2) continue;
  if (group.length <= 5) {
    pushCluster(group, { shape: 'community' });
    continue;
  }
  const members = new Set([group[0]]);
  while (members.size < 5) {
    let best = null;
    let bestScore = -1;
    for (const cand of group) {
      if (members.has(cand)) continue;
      let shared = 0;
      for (const m of members) {
        if (projNeighbors(cand).has(m) || projNeighbors(m).has(cand)) shared += 1;
      }
      const score = shared * 100 + projFanOut(cand) + projFanIn(cand);
      if (score > bestScore) { bestScore = score; best = cand; }
    }
    if (!best) break;
    members.add(best);
  }
  pushCluster(Array.from(members), { shape: 'community-subset', communitySize: group.length });
}

clusters.sort((a, b) =>
  b.edgeCount - a.edgeCount ||
  b.nodes.length - a.nodes.length ||
  a.nodes.join().length - b.nodes.join().length);
const topClusters = clusters.slice(0, 10);

// ---------------------------------------------------------------- G. layers

const layerInfo = {
  count: layers.length,
  list: layers.map((l) => ({ id: l.id, name: l.name, description: l.description }))
};

// ------------------------------------------------------- H. node summary index

const nodeSummaryIndex = {};
for (const id of Array.from(byId.keys()).sort()) {
  nodeSummaryIndex[id] = { name: nameOf(id), type: typeOf(id), summary: summaryOf(id) };
}

// -------------------------------------------------------------------- output

function countBy(ids, fn) {
  const acc = {};
  for (const id of ids) {
    const k = fn(id);
    acc[k] = (acc[k] || 0) + 1;
  }
  return acc;
}

const results = {
  scriptCompleted: true,
  entryPointCandidates: topEntryCandidates,
  fanInRanking,
  fanOutRanking,
  bfsTraversal,
  nonCodeFiles,
  clusters: topClusters,
  layers: layerInfo,
  nodeSummaryIndex,
  totalNodes: nodes.length,
  totalEdges: edges.length,
  stats: {
    method: {
      projection: 'function:/class: endpoints lifted onto file:<path>; relations ' +
        Array.from(PROJECTED_REL).join(',') + '; documentary relations excluded',
      bfsEdgeTypes: Array.from(FOLLOW_TYPES),
      hubsExcludedFromClusters: Array.from(HUBS).sort()
    },
    fanOutTop10Cutoff: foCut,
    fanInBottom25Cutoff: fiCut,
    bfsReached: bfsTraversal.order.length,
    projectedEdges: projEdges.length,
    clusterCount: clusters.length,
    communityCount: communities.length,
    nodeTypes: countBy(Array.from(byId.keys()), typeOf),
    unwiredCodeFiles: unwiredCodeFiles.sort((a, b) => a.id.localeCompare(b.id))
  }
};

try {
  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(results, null, 2), 'utf8');
} catch (e) {
  die('cannot write ' + outPath + ': ' + e.message);
}

process.stdout.write(
  '[ua-tour-analyze] ok nodes=' + nodes.length + ' edges=' + edges.length +
  ' bfsStart=' + bfsTraversal.startNode + ' bfsReached=' + bfsTraversal.order.length +
  ' clusters=' + topClusters.length + ' communities=' + communities.length +
  ' -> ' + outPath + '\n'
);
process.exit(0);