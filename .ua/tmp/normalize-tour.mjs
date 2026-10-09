// Normalize + validate the tour per the skill contract, then report.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const graph = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/assembled-graph.json`, 'utf8'));
let tour = JSON.parse(readFileSync(`${ROOT}/.ua/intermediate/tour.json`, 'utf8'));

if (!Array.isArray(tour)) tour = tour.steps ?? [];

// Legacy field renames
for (const s of tour) {
  if (s.nodesToInspect && !s.nodeIds) s.nodeIds = s.nodesToInspect;
  if (s.whyItMatters && !s.description) s.description = s.whyItMatters;
}

const nodeIds = new Set(graph.nodes.map((n) => n.id));
const PREFIXES = ['file:', 'config:', 'document:', 'service:', 'pipeline:', 'table:', 'schema:', 'resource:', 'endpoint:'];

let dangling = 0;
for (const s of tour) {
  s.nodeIds = (s.nodeIds ?? []).map((id) => {
    if (nodeIds.has(id)) return id;
    // Convert bare paths to file:<path>, then re-check
    if (!PREFIXES.some((p) => id.startsWith(p))) {
      const asFile = `file:${id}`;
      if (nodeIds.has(asFile)) return asFile;
    }
    dangling++;
    return id;
  }).filter((id) => nodeIds.has(id));
}

tour.sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
writeFileSync(`${ROOT}/.ua/intermediate/tour.json`, JSON.stringify(tour, null, 2));

const issues = [];
tour.forEach((s, i) => {
  for (const f of ['order', 'title', 'description', 'nodeIds']) {
    if (s[f] === undefined || s[f] === null) issues.push(`step[${i}] '${s.title}' missing ${f}`);
  }
  if (typeof s.description === 'string' && s.description.trim() === '') issues.push(`step[${i}] empty description`);
  if (!Array.isArray(s.nodeIds) || s.nodeIds.length === 0) issues.push(`step[${i}] '${s.title}' has no nodeIds`);
});

console.log(`steps            : ${tour.length}`);
console.log(`dropped dangling : ${dangling}`);
console.log(`nodes referenced : ${new Set(tour.flatMap((s) => s.nodeIds)).size} / ${graph.nodes.length}`);
console.log(`languageLessons  : ${tour.filter((s) => s.languageLesson).length}`);
console.log(`contract issues  : ${issues.length}`);
for (const i of issues) console.log(`   ! ${i}`);