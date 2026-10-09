#!/usr/bin/env node
// Validates tour.json against the graph and the tour-builder output contract.
'use strict';
const fs = require('fs');

const tourPath = process.argv[2];
const resultsPath = process.argv[3];
const errors = [];

const raw = fs.readFileSync(tourPath);
if (raw[0] === 0xEF && raw[1] === 0xBB && raw[1] === 0xBF) errors.push('output has a UTF-8 BOM');

const buf = raw;
for (let i = 0; i < buf.length; i++) {
  if (buf[i] > 0x7F) {
    errors.push('non-ASCII byte 0x' + buf[i].toString(16) + ' at offset ' + i);
    break;
  }
}

let tour;
try { tour = JSON.parse(raw.toString('utf8')); } catch (e) {
  errors.push('JSON.parse failed: ' + e.message);
  process.exit(1);
}
const results = JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
const valid = new Set(Object.keys(results.nodeSummaryIndex));

if (!Array.isArray(tour)) errors.push('top level is not an array');
if (tour.length < 5 || tour.length > 15) errors.push('step count ' + tour.length + ' outside 5..15');

const seenOrder = new Set();
const usedNodes = new Set();
let nonCodeSteps = 0;

tour.forEach((s, i) => {
  const label = 'step ' + (s && s.order) + ' (' + (s && s.title) + ')';
  if (typeof s.order !== 'number' || s.order !== i + 1) errors.push(label + ': order must be ' + (i + 1));
  if (seenOrder.has(s.order)) errors.push(label + ': duplicate order');
  seenOrder.add(s.order);
  if (typeof s.title !== 'string' || s.title.length < 2) errors.push(label + ': bad title');
  if (typeof s.description !== 'string') errors.push(label + ': missing description');
  else {
    const sentences = s.description.split(/(?<=[.!?])\s+/).filter((x) => x.trim().length > 1);
    if (sentences.length < 2 || sentences.length > 4) {
      errors.push(label + ': description has ' + sentences.length + ' sentences (want 2-4)');
    }
  }
  if (!Array.isArray(s.nodeIds) || s.nodeIds.length === 0) errors.push(label + ': empty nodeIds');
  else {
    if (s.nodeIds.length > 5) errors.push(label + ': ' + s.nodeIds.length + ' nodeIds (max 5)');
    for (const id of s.nodeIds) {
      if (!valid.has(id)) errors.push(label + ': unknown node id ' + id);
      usedNodes.add(id);
      const t = results.nodeSummaryIndex[id] ? results.nodeSummaryIndex[id].type : '';
      if (t !== 'file') nonCodeSteps++;
    }
  }
  if ('languageLesson' in s && typeof s.languageLesson !== 'string') {
    errors.push(label + ': languageLesson must be a string');
  }
});

const nonCodeStepCount = tour.filter((s) => s.nodeIds.some((id) => {
  const t = results.nodeSummaryIndex[id] ? results.nodeSummaryIndex[id].type : 'file';
  return t !== 'file';
})).length;

if (tour[0] && !tour[0].nodeIds.some((id) => {
  const n = results.nodeSummaryIndex[id];
  return n && (n.type === 'document' || /^(?:index|main|app|server)\./.test(n.name));
})) errors.push('step 1 is not a project overview (document or entry point)');

if (nonCodeStepCount < 2) errors.push('fewer than 2 steps include non-code nodes');

console.log('steps=' + tour.length +
  ' languageLessons=' + tour.filter((s) => s.languageLesson).length +
  ' nonCodeSteps=' + nonCodeStepCount +
  ' uniqueNodesReferenced=' + usedNodes.size + '/' + valid.size);
console.log('titles: ' + tour.map((s) => s.order + '. ' + s.title).join(' | '));
if (errors.length) {
  console.log('\nFAILED (' + errors.length + '):');
  errors.forEach((e) => console.log('  - ' + e));
  process.exit(1);
}
console.log('\nOK: all constraints satisfied.');
process.exit(0);