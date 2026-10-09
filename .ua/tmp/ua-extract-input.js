#!/usr/bin/env node
// Extracts the pre-computed graph JSON block from the dispatch markdown into a
// plain input file for the tour topology analyzer.
const fs = require('fs');
const path = require('path');

function die(msg) {
  process.stderr.write(msg + '\n');
  process.exit(1);
}

const mdPath = process.argv[2];
const outPath = process.argv[3];
if (!mdPath || !outPath) die('usage: ua-extract-input.js <dispatch.md> <out.json>');

let md;
try {
  md = fs.readFileSync(mdPath, 'utf8');
} catch (e) {
  die('cannot read ' + mdPath + ': ' + e.message);
}

const anchor = md.indexOf('### Pre-computed input');
if (anchor === -1) die('anchor "### Pre-computed input" not found');

// The dispatch file uses shortened fences (``json instead of ```json), so match
// any run of two or more backticks optionally followed by an info string.
const fenceRe = /`{2,}([a-zA-Z0-9_-]*)/g;
fenceRe.lastIndex = anchor;
const open = fenceRe.exec(md);
if (!open) die('no code fence found after anchor');
if (open[1] && open[1].toLowerCase() !== 'json') {
  die('first fence after anchor is not json (got "' + open[1] + '")');
}
const bodyStart = md.indexOf('\n', open.index) + 1;
const closeRe = /`{2,}/g;
closeRe.lastIndex = bodyStart;
const close = closeRe.exec(md);
if (!close) die('unterminated fence');

const raw = md.slice(bodyStart, close.index);
let data;
try {
  data = JSON.parse(raw);
} catch (e) {
  die('JSON.parse failed on extracted block: ' + e.message);
}

if (!Array.isArray(data.nodes) || !Array.isArray(data.edges)) {
  die('extracted block missing nodes/edges arrays');
}

fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, JSON.stringify(data, null, 2), 'utf8');
process.stdout.write(
  'wrote ' + outPath + ' nodes=' + data.nodes.length +
  ' edges=' + data.edges.length +
  ' layers=' + (Array.isArray(data.layers) ? data.layers.length : 0) + '\n'
);