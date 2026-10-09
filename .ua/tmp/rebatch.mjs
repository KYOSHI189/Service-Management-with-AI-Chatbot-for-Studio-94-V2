// Re-batch: split any batch over ~9,000 lines into balanced sub-batches of <=8 files.
// Part-file naming (batch-N-part-k.json) is understood by merge-batch-graphs.py.
// Output batchFiles[] entries keep all four required fields verbatim.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const MAX_LINES = 9000;
const MAX_FILES = 8;

const p = (s) => `${ROOT}/.ua/${s}`;
const batches = JSON.parse(readFileSync(p('intermediate/batches.json'), 'utf8'));

const out = [];
let nextIndex = 1;

for (const b of batches.batches) {
  const files = b.files;
  const total = files.reduce((a, f) => a + (f.sizeLines || 0), 0);

  if (total <= MAX_LINES && files.length <= MAX_FILES) {
    out.push({ ...b, batchIndex: nextIndex++ });
    continue;
  }

  // Greedy sequential packing preserves file order (keeps related files together).
  const groups = [];
  let cur = [];
  let curLines = 0;
  for (const f of files) {
    const lines = f.sizeLines || 0;
    if (cur.length && (curLines + lines > MAX_LINES || cur.length >= MAX_FILES)) {
      groups.push(cur);
      cur = [];
      curLines = 0;
    }
    cur.push(f);
    curLines += lines;
  }
  if (cur.length) groups.push(cur);

  groups.forEach((g, k) => {
    out.push({
      ...b,
      batchIndex: nextIndex,
      partIndex: k > 0 ? k + 1 : undefined,
      files: g,
      batchFiles: g,
      totalFiles: g.length,
    });
    nextIndex++;
  });
}

const batched = { ...batches, totalBatches: out.length, batches: out };
writeFileSync(p('intermediate/batches.json'), JSON.stringify(batched, null, 2));

for (const b of out) {
  const lines = b.files.reduce((a, f) => a + (f.sizeLines || 0), 0);
  const name = b.partIndex ? `batch-${b.batchIndex}-part-${b.partIndex}` : `batch-${b.batchIndex}`;
  console.log(
    `  ${name}: ${b.files.length} files, ${lines} lines -> write ${name}.json`,
  );
}
console.log(`total batches: ${out.length}`);