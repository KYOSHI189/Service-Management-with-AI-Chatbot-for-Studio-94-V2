// Assemble final scan-result.json for SnapTrack per project-scanner contract.
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.argv[2];
const p = (s) => `${ROOT}/.ua/${s}`;

const scan = JSON.parse(readFileSync(p('intermediate/scan-result.json'), 'utf8'));
const imports = JSON.parse(readFileSync(p('tmp/ua-import-map-output.json'), 'utf8'));

const final = {
  name: 'SnapTrack',
  description:
    'Studio 94 SnapTrack — a procedural PHP/MySQL photography studio management and ' +
    'booking system. Single index.php router dispatches role-based pages (admin, staff, ' +
    'client) rendered as server-side PHP with vanilla JS/CSS. Handles bookings, payments, ' +
    'inventory, loyalty tiers, feedback with Gemini AI analysis, MFA/TOTP and Google OAuth ' +
    'auth, PHPMailer/Resend email, and web push notifications. Deployed via Nixpacks to Railway.',
  languages: ['php', 'javascript', 'css', 'html', 'sql', 'markdown', 'json', 'toml'],
  frameworks: [],
  totalFiles: scan.totalFiles,
  filteredByIgnore: scan.filteredByIgnore,
  estimatedComplexity: scan.estimatedComplexity,
  // files must be verbatim from scan-project.mjs
  files: scan.files,
  // importMap must be verbatim from extract-import-map.mjs
  importMap: imports.importMap,
};

// Strip transient fields that must not appear in the final contract output.
delete final.scriptCompleted;
delete final.stats;
delete final.failures;

writeFileSync(p('intermediate/scan-result.json'), JSON.stringify(final, null, 2));
console.log(
  `assembled: files=${final.files.length} importMapEntries=${Object.keys(final.importMap).length}`,
);