/*
 * Structural architecture analyzer for Understand-Anything.
 * Usage: node ua-arch-analyze.js <input.json> <output.json>
 *
 * Computes directory grouping, node-type grouping, import adjacency,
 * cross-category edges, inter/intra-group import density, pattern matches,
 * deployment topology, data pipeline, doc coverage and dependency direction.
 */
'use strict';

const fs = require('fs');

function fail(msg) {
  process.stderr.write('ERROR: ' + msg + '\n');
  process.exit(1);
}

const inPath = process.argv[2];
const outPath = process.argv[3];
if (!inPath || !outPath) fail('usage: node ua-arch-analyze.js <input.json> <output.json>');

let input;
try {
  input = JSON.parse(fs.readFileSync(inPath, 'utf8'));
} catch (e) {
  fail('cannot parse input: ' + e.message);
}
if (!input || !Array.isArray(input.fileNodes)) fail('input.fileNodes must be an array');

const fileNodes = input.fileNodes;
const importEdges = Array.isArray(input.importEdges) ? input.importEdges : [];
const allEdges = Array.isArray(input.allEdges) ? input.importEdges.concat(input.allEdges || []) : [];

const byId = new Map();
for (const n of fileNodes) byId.set(n.id, n);

/* ------------------------------------------------------------------ */
/* A. Directory grouping                                               */
/* ------------------------------------------------------------------ */

function segments(p) { return String(p || '').split('/').filter(Boolean); }

function commonPrefix(allPaths) {
  if (!allPaths.length) return [];
  let prefix = segments(allPaths[0]);
  for (const p of allPaths.slice(1)) {
    const seg = segments(p);
    let i = 0;
    while (i < prefix.length && i < seg.length && prefix[i] === seg[i]) i++;
    prefix = prefix.slice(0, i);
    if (!prefix.length) break;
  }
  return prefix;
}

// Only consider directory (not file) parts when computing the common prefix.
const dirPaths = fileNodes.map(n => {
  const seg = segments(n.filePath);
  return seg.slice(0, Math.max(0, seg.length - 1)).join('/');
});
const prefix = commonPrefix(dirPaths);

const dirPatternMap = {
  api: ['routes', 'api', 'controllers', 'endpoints', 'handlers', 'serializers', 'controller', 'routers', 'blueprints'],
  service: ['services', 'core', 'lib', 'domain', 'logic', 'signals', 'internal', 'composables', 'mailers', 'jobs', 'channels'],
  data: ['models', 'db', 'data', 'persistence', 'repository', 'entities', 'migrations', 'entity', 'sql', 'database', 'schema'],
  ui: ['components', 'views', 'pages', 'ui', 'layouts', 'screens'],
  middleware: ['middleware', 'plugins', 'interceptors', 'guards'],
  utility: ['utils', 'helpers', 'common', 'shared', 'tools', 'templatetags', 'pkg'],
  config: ['config', 'constants', 'env', 'settings', 'management', 'commands'],
  test: ['__tests__', 'test', 'tests', 'spec', 'specs'],
  types: ['types', 'interfaces', 'schemas', 'contracts', 'dtos', 'dto', 'request', 'response'],
  hooks: ['hooks'],
  state: ['store', 'state', 'reducers', 'actions', 'slices'],
  assets: ['assets', 'static', 'public'],
  entry: ['cmd', 'bin'],
  documentation: ['docs', 'documentation', 'wiki'],
  infrastructure: ['deploy', 'deployment', 'infra', 'infrastructure', 'k8s', 'kubernetes', 'helm', 'charts', 'terraform', 'tf', 'docker'],
  'ci-cd': ['.github', '.gitlab', '.circleci']
};

function patternForDir(name) {
  const key = String(name).toLowerCase();
  if (dirPatternMap[key]) return dirPatternMap[key][0] && key in Object.fromEntries(Object.entries(dirPatternMap).map(([k, v]) => [k, 1])) ? key : dirPatternMap[key];
  for (const label of Object.keys(dirPatternMap)) {
    if (dirPatternMap[label].includes(key)) return label;
  }
  return null;
}

const directoryGroups = {};
const groupOf = new Map();

for (const n of fileNodes) {
  const seg = segments(n.filePath);
  let group;
  const rest = seg.slice(prefix.length);
  if (rest.length === 0) {
    group = '(root)';
  } else if (rest.length === 1) {
    // a file directly in the prefix dir (or root) -- name it by the directory it lives in
    group = prefix.length ? prefix.join('/') : '(root)';
  } else {
    group = rest[0];
  }
  if (prefix.length === 0 && seg.length === 1) group = '(root)';
  if (!directoryGroups[group]) directoryGroups[group] = [];
  directoryGroups[group].push(n.id);
  groupOf.set(n.id, group);
}

// Flat-structure fallback: if everything collapsed into one group, re-group by extension/pattern.
if (Object.keys(directoryGroups).length <= 1) {
  const reg = {};
  for (const n of fileNodes) {
    let key = 'misc';
    const f = String(n.filePath || '');
    if (/[\/\\]/.test(f)) key = 'subdir';
    else if (/\.(test|spec)\./i.test(f) || /^test_/i.test(f) || /_test\.go$/i.test(f) || /Test\.java$/.test(f)) key = 'test';
    else if (/\.config\./i.test(f) || /^(composer|package)\.json$/i.test(f) || /\.toml$|\.yml$|\.yaml$/i.test(f)) key = 'config';
    else if (/\.md$|\.rst$/i.test(f)) key = 'documentation';
    else if (/\.sql$/i.test(f)) key = 'data';
    else if (/\.css$|\.scss$|\.js$/i.test(f)) key = 'assets';
    else key = 'code';
    reg[key] = reg[key] || [];
    reg[key].push(n.id);
  }
  Object.keys(directoryGroups).forEach(k => delete directoryGroups[k]);
  for (const k of Object.keys(reg)) { directoryGroups[k] = reg[k]; }
  groupOf.clear();
  for (const k of Object.keys(reg)) for (const id of reg[k]) groupOf.set(id, k);
}

/* ------------------------------------------------------------------ */
/* B. Node type grouping                                               */
/* ------------------------------------------------------------------ */

const nodeTypeGroups = {};
for (const n of fileNodes) {
  const t = n.type || 'file';
  nodeTypeGroups[t] = nodeTypeGroups[t] || [];
  nodeTypeGroups[t].push(n.id);
}

/* ------------------------------------------------------------------ */
/* C. Import adjacency                                                 */
/* ------------------------------------------------------------------ */

const fanOut = {};
const fanIn = {};
for (const n of fileNodes) { fanOut[n.id] = 0; fanIn[n.id] = 0; }

const importAdjacency = {};
for (const e of importEdges) {
  if (!byId.has(e.source) || !byId.has(e.target)) continue;
  importAdjacency[e.source] = importAdjacency[e.source] || [];
  importAdjacency[e.source].push(e.target);
  fanOut[e.source] = (fanOut[e.source] || 0) + 1;
  fanIn[e.target] = (fanIn[e.target] || 0) + 1;
}

/* group -> set of groups it imports from / is imported by */
const groupImports = {};
const groupImportedBy = {};
const groupPair = {};   // "a->b" -> count
const intraInternal = {};
const groupTotalEdges = {};

function bumpGroupTotal(g) { groupTotalEdges[g] = (groupTotalEdges[g] || 0) + 1; }

for (const e of importEdges) {
  const sg = groupOf.get(e.source), tg = groupOf.get(e.target);
  if (!sg || !tg) continue;
  groupImports[sg] = groupImports[sg] || new Set();
  groupImports[sg].add(tg);
  groupImportedBy[tg] = groupImportedBy[tg] || new Set();
  groupImportedBy[tg].add(sg);
  const key = sg + '->' + tg;
  groupPair[key] = (groupPair[key] || 0) + 1;
  if (sg === tg) intraInternal[sg] = (intraInternal[sg] || 0) + 1;
  bumpGroupTotal(sg);
  bumpGroupTotal(tg);
}

/* ------------------------------------------------------------------ */
/* D. Cross-category edges                                             */
/* ------------------------------------------------------------------ */

const crossMap = {};
for (const e of allEdges) {
  const s = byId.get(e.source), t = byId.get(e.target);
  if (!s || !t) continue;
  const key = (s.type || 'file') + ' -> ' + (t.type || 'file') + ' (' + (e.type || 'unknown') + ')';
  crossMap[key] = (crossMap[key] || 0) + 1;
}
const crossCategoryEdges = Object.keys(crossMap)
  .map(k => {
    const m = k.match(/^(.*?) -> (.*?) \((.*?)\)$/);
    return { fromType: m[1], toType: m[2], edgeType: m[3], count: crossMap[k] };
  })
  .sort((a, b) => b.count - a.count);

/* non-code -> code connectivity */
const nonCodeConnectivity = {};
for (const e of allEdges) {
  const s = byId.get(e.source), t = byId.get(e.target);
  if (!s || !t) continue;
  if ((s.type || 'file') === 'file' && (t.type || 'file') === 'file') continue;
  const dir = (s.type || 'file') === 'file' ? t.id + ' <- ' + s.id : s.id + ' -> ' + t.id;
  nonCodeConnectivity[dir] = (nonCodeConnectivity[dir] || 0) + 1;
}

/* ------------------------------------------------------------------ */
/* E/F. Inter-group matrix + intra-group density                      */
/* ------------------------------------------------------------------ */

const interGroupImports = Object.keys(groupPair)
  .map(k => {
    const [from, to] = k.split('->');
    return { from, to, count: groupPair[k] };
  })
  .sort((a, b) => b.count - a.count);

const intraGroupDensity = {};
for (const g of Object.keys(directoryGroups)) {
  const internalEdges = intraInternal[g] || 0;
  const totalEdges = groupTotalEdges[g] || 0;
  intraGroupDensity[g] = {
    internalEdges,
    totalEdges,
    density: totalEdges ? Math.round((internalEdges / totalEdges) * 1000) / 1000 : 0
  };
}

/* ------------------------------------------------------------------ */
/* G. Pattern matching                                                */
/* ------------------------------------------------------------------ */

const patternMatches = {};
for (const g of Object.keys(directoryGroups)) patternMatches[g] = patternForDir(g);

const filePattern = [];
const dirPattern = {};
for (const g of Object.keys(directoryGroups)) dirPattern[g] = patternMatches[g];

for (const n of fileNodes) {
  const f = String(n.filePath || '');
  const base = f.split('/').pop();
  let label = null;
  if (/\.(test|spec)\./i.test(f) || /^test_/i.test(base) || /_test\.go$/i.test(f) || /Test\.java$/.test(f) || /_spec\.rb$/i.test(f) || /Tests\.cs$/i.test(f)) label = 'test';
  else if (/\.d\.ts$/.test(f)) label = 'types';
  else if (base === 'index.ts' || base === 'index.js' || base === '__init__.py') label = 'entry';
  else if (base === 'manage.py') label = 'entry';
  else if (base === 'wsgi.py' || base === 'asgi.py') label = 'config';
  else if (/^(Cargo\.toml|go\.mod|Gemfile|pom\.xml|build\.gradle|composer\.json|package\.json)$/.test(base)) label = 'config';
  else if (base === 'Dockerfile' || /^docker-compose\./i.test(base)) label = 'infrastructure';
  else if (/\.(tf|tfvars)$/.test(f)) label = 'infrastructure';
  else if (/\.github\/workflows\//.test(f) || base === '.gitlab-ci.yml' || base === 'Jenkinsfile') label = 'ci-cd';
  else if (/\.sql$/i.test(f)) label = 'data';
  else if (/\.(graphql|gql|proto)$/i.test(f)) label = 'types';
  else if (/\.(md|rst)$/i.test(f)) label = 'documentation';
  else if (base === 'Makefile') label = 'infrastructure';
  if (label) filePattern.push({ id: n.id, filePath: f, label, dirPattern: dirPattern[groupOf.get(n.id)] || null });
}

/* ------------------------------------------------------------------ */
/* H. Deployment topology                                             */
/* ------------------------------------------------------------------ */

const infraFiles = [];
let hasDockerfile = false, hasCompose = false, hasK8s = false, hasTerraform = false, hasCI = false;
const environments = new Set();
for (const n of fileNodes) {
  const f = String(n.filePath || '');
  const base = f.split('/').pop();
  if (base === 'Dockerfile' || /^Dockerfile\./.test(base)) { hasDockerfile = true; infraFiles.push(f); const m = base.match(/^Dockerfile\.(.+)$/); if (m) environments.add('docker:' + m[1]); }
  else if (/^docker-compose\./.test(base)) { hasCompose = true; infraFiles.push(f); environments.add('compose:' + base.split('.').slice(2).join('.') || 'default'); }
  else if (/(^|\/)(k8s|kubernetes|helm|charts)(\/|$)/.test(f) || /\.ya?ml$/.test(f) && /k8s|kube|helm/i.test(f)) { hasK8s = true; infraFiles.push(f); }
  else if (/\.(tf|tfvars)$/.test(f) || /(^|\/)(terraform|tf)(\/|$)/.test(f)) { hasTerraform = true; infraFiles.push(f); }
  else if (/\.github\/workflows\//.test(f) || base === '.gitlab-ci.yml' || base === 'Jenkinsfile' || /\.circleci\//.test(f)) { hasCI = true; infraFiles.push(f); }
  else if (base === 'Makefile') { infraFiles.push(f); }
}

const deploymentTopology = {
  hasDockerfile, hasCompose, hasK8s, hasTerraform, hasCI,
  environments: Array.from(environments),
  infraFiles
};

/* ------------------------------------------------------------------ */
/* I. Data pipeline detection                                          */
/* ------------------------------------------------------------------ */

const schemaFiles = [], migrationFiles = [], dataModelFiles = [], apiHandlerFiles = [];
for (const n of fileNodes) {
  const f = String(n.filePath || '');
  const seg = segments(f);
  const inApi = seg.includes('api') || /\bapi\b/.test(seg.join('/'));
  if (/\.sql$/i.test(f)) {
    if (/migrat/i.test(f)) migrationFiles.push(f);
    else schemaFiles.push(f);
    dataModelFiles.push(f);
  }
  if (/\.(graphql|gql|proto|prisma)$/i.test(f)) schemaFiles.push(f);
  if (/(^|\/)(models?|entities|repository|schemas?)(\/|$)/i.test(f) || (n.type === 'schema') || (n.type === 'table')) dataModelFiles.push(f);
  if (inApi || /\.(sql)$/i.test(f) === false && /api-handler|json-api|json-endpoint|endpoint/.test((n.tags || []).join(' '))) apiHandlerFiles.push(f);
}
const deploymentToApi = [];
for (const e of allEdges) {
  const s = byId.get(e.source), t = byId.get(e.target);
  if (!s || !t) continue;
  const sf = segments(String(s.filePath || '')), tf = segments(String(t.filePath || ''));
  if (sf.includes('api') && tf.includes('api')) deploymentToApi.push({ source: s.id, target: t.id, type: e.type });
}

const dataPipeline = {
  schemaFiles, migrationFiles,
  dataModelFiles: Array.from(new Set(dataModelFiles)),
  apiHandlerFiles: Array.from(new Set(apiHandlerFiles)),
  schemaToHandlerFlow: deploymentToApi.slice(0, 20)
};

/* ------------------------------------------------------------------ */
/* J. Documentation coverage                                           */
/* ------------------------------------------------------------------ */

const docGroupRefs = {};
for (const e of allEdges) {
  const s = byId.get(e.source), t = byId.get(e.target);
  if (!s || !t) continue;
  if (s.type !== 'document') continue;
  const g = groupOf.get(t.id);
  if (!g) continue;
  docGroupRefs[g] = (docGroupRefs[g] || 0) + 1;
}
const totalGroups = Object.keys(directoryGroups).length;
const groupsWithDocs = Object.keys(docGroupRefs);
const undocumentedGroups = Object.keys(directoryGroups).filter(g => !docGroupRefs[g]);
const docCoverage = {
  groupsWithDocs: groupsWithDocs.length,
  totalGroups,
  coverageRatio: totalGroups ? Math.round((groupsWithDocs.length / totalGroups) * 1000) / 1000 : 0,
  docRefsByGroup: docGroupRefs,
  undocumentedGroups
};

/* ------------------------------------------------------------------ */
/* K. Dependency direction                                             */
/* ------------------------------------------------------------------ */

const pairMap = {};
for (const it of interGroupImports) pairMap[it.from + '|' + it.to] = it.count;
const seenPairs = new Set();
const dependencyDirection = [];
for (const it of interGroupImports) {
  const pk = [it.from, it.to].sort().join('|');
  if (seenPairs.has(pk)) continue;
  seenPairs.add(pk);
  const fwd = pairMap[it.from + '|' + it.to] || 0;
  const back = pairMap[it.to + '|' + it.from] || 0;
  if (fwd === back) continue;
  if (fwd > back) dependencyDirection.push({ dependent: it.from, dependsOn: it.to, weight: fwd - back, fwd, back });
  else dependencyDirection.push({ dependent: it.to, dependsOn: it.from, weight: back - fwd, fwd: back, back: fwd });
}
dependencyDirection.sort((a, b) => b.weight - a.weight);

/* ------------------------------------------------------------------ */
/* File stats                                                          */
/* ------------------------------------------------------------------ */

const filesPerGroup = {};
for (const g of Object.keys(directoryGroups)) filesPerGroup[g] = directoryGroups[g].length;
const nodeTypeCounts = {};
for (const t of Object.keys(nodeTypeGroups)) nodeTypeCounts[t] = nodeTypeGroups[t].length;

const uniqFanIn = {}, uniqFanOut = {};
for (const id of Object.keys(fanIn)) uniqFanIn[id] = fanIn[id];
for (const id of Object.keys(fanOut)) uniqFanOut[id] = fanOut[id];

/* ------------------------------------------------------------------ */
/* Output                                                              */
/* ------------------------------------------------------------------ */

const results = {
  scriptCompleted: true,
  commonPrefix: prefix.join('/') || '(none)',
  directoryGroups,
  nodeTypeGroups,
  crossCategoryEdges,
  nonCodeConnectivity,
  interGroupImports,
  intraGroupDensity,
  patternMatches,
  dirPattern,
  filePatternMatches: filePattern,
  groupImports: Object.fromEntries(Object.entries(groupImports).map(([k, v]) => [k, Array.from(v)])),
  groupImportedBy: Object.fromEntries(Object.entries(groupImportedBy).map(([k, v]) => [k, Array.from(v)])),
  importAdjacency,
  deploymentTopology,
  dataPipeline,
  docCoverage,
  dependencyDirection,
  fileStats: {
    totalFileNodes: fileNodes.length,
    filesPerGroup,
    nodeTypeCounts
  },
  fileFanIn: uniqFanIn,
  fileFanOut: uniqFanOut
};

try {
  fs.writeFileSync(outPath, JSON.stringify(results, null, 2), 'utf8');
} catch (e) {
  fail('cannot write output: ' + e.message);
}
process.exit(0);