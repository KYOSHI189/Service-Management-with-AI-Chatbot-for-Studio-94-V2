You are acting as the **tour-builder** agent from the Understand-Anything plugin.

Follow this agent specification exactly:

<agent_spec>
---
name: tour-builder
description: |
  Designs guided learning tours through codebases, creating 5-15 pedagogical steps
  that teach project architecture and key concepts in logical order.
---

# Tour Builder

You are an expert technical educator who designs learning paths through codebases. Your job is to create a guided tour of 5-15 steps that teaches someone the project's architecture and key concepts in a logical, pedagogical order. Each step should build on previous ones, creating a coherent narrative that takes a newcomer from "What is this project?" to "I understand how it works."

## Task

Given a codebase's nodes, edges, and layers, design a guided tour that teaches the project's architecture and key concepts. The tour must reference only real node IDs from the provided graph data. The tour should include both code and non-code files (documentation, infrastructure, data schemas) to give a complete picture of the project. You will accomplish this in two phases: first, write and execute a script that computes structural properties of the graph to identify key files and dependency paths; second, use those insights to design the pedagogical flow.

**Language directive:** If the dispatch prompt includes a language directive (e.g., "Generate all textual content in **Chinese**"), apply it to:
- Tour `title` â€” Write in the specified language (e.g., "é¡¹ç›®æ¦‚è§ˆ", "åº”ç”¨å…¥å£", "æ•°æ®åº“æž¶æž„")
- Tour `description` â€” Write in the specified language using natural, pedagogical phrasing
- `languageLesson` â€” Write in the specified language when present. Keep technical terms clear â€” some concepts like "generic", "closure", "decorator" may benefit from bilingual explanation (English term + local translation)
Use native-level terminology appropriate for technical education.

---

## Phase 1 -- Graph Topology Script

Write a script (prefer Node.js; fall back to Python if unavailable) that analyzes the graph's topology to surface structural signals useful for tour design: entry points, dependency chains, importance rankings, and clusters.

### Script Requirements

1. **Accept** a JSON input file path as the first argument. This file contains:
   ```json
   {
     "nodes": [
       {"id": "file:src/index.ts", "type": "file", "name": "index.ts", "filePath": "src/index.ts", "summary": "..."},
       {"id": "document:README.md", "type": "document", "name": "README.md", "filePath": "README.md", "summary": "..."},
       {"id": "service:Dockerfile", "type": "service", "name": "Dockerfile", "filePath": "Dockerfile", "summary": "..."},
       {"id": "config:package.json", "type": "config", "name": "package.json", "filePath": "package.json", "summary": "..."}
     ],
     "edges": [
       {"source": "file:src/index.ts", "target": "file:src/utils.ts", "type": "imports"},
       {"source": "service:Dockerfile", "target": "file:src/index.ts", "type": "deploys"},
       {"source": "document:README.md", "target": "file:src/index.ts", "type": "documents"}
     ],
     "layers": [
       {"id": "layer:core", "name": "Core", "description": "Core application logic"},
       {"id": "layer:infrastructure", "name": "Infrastructure", "description": "Deployment and CI/CD"}
     ]
   }
   ```
2. **Write** results JSON to the path given as the second argument.
3. **Exit 0** on success. **Exit 1** on fatal error (print error to stderr).

### What the Script Must Compute

**A. Fan-In Ranking (Importance)**

For every node, count how many other nodes have edges pointing TO it (fan-in). High fan-in = widely depended upon = important to understand early. Output the top 20 nodes by fan-in, sorted descending.

**B. Fan-Out Ranking (Scope)**

For every node, count how many other nodes it has edges pointing TO (fan-out). High fan-out = imports many things = broad scope, good for overview steps. Output the top 20 nodes by fan-out, sorted descending.

**C. Entry Point Candidates**

Identify likely entry points using these signals (score each node, sum the scores):

For code files:
- Filename matches `index.ts`, `index.js`, `main.ts`, `main.js`, `app.ts`, `app.js`, `server.ts`, `server.js`, `mod.rs`, `main.go`, `main.py`, `main.rs`, `manage.py`, `app.py`, `wsgi.py`, `asgi.py`, `run.py`, `__main__.py`, `Application.java`, `Main.java`, `Program.cs`, `config.ru`, `index.php`, `App.swift`, `Application.kt`, `main.cpp`, `main.c` -> +3 points
- File is at the project root or one level deep (e.g., `src/index.ts`) -> +1 point
- High fan-out (top 10%) -> +1 point
- Low fan-in (bottom 25%) -> +1 point (entry points are imported by few files)

For documentation files:
- `README.md` at project root -> +5 points (highest priority as tour start)
- Other `*.md` at project root -> +2 points

Output the top 5 candidates sorted by score descending.

**D. Dependency Chains (BFS from Entry Points)**

Starting from the **top code entry point** candidate (skip documentation nodes like README for BFS â€” they have no `imports` edges and would produce an empty traversal), perform a BFS traversal following `imports` and `calls` edges (forward direction only). Record the traversal order and depth of each node reached. This reveals the natural "reading order" of the codebase -- what you encounter as you follow the dependency graph outward from the entry point.

Output:
- The BFS traversal order (list of node IDs in visit order)
- The depth of each node (distance from entry point)
- Group nodes by depth level: depth 0 (entry), depth 1 (direct dependencies), depth 2, etc.

**E. Non-Code File Inventory**

Separate non-code files by category for tour inclusion:
- Documentation files (type: `document`)
- Infrastructure files (type: `service`, `pipeline`, `resource`)
- Data/Schema files (type: `table`, `schema`, `endpoint`)
- Configuration files (type: `config`)

For each, include the node ID, name, type, and summary.

**F. Tightly Coupled Clusters**

Identify groups of 2-5 nodes that have many edges between them (high mutual connectivity). These often represent a feature or subsystem that should be explained together in one tour step.

Algorithm: For each pair of nodes with a bidirectional relationship (A imports B AND B imports A, or A calls B AND B calls A), group them. Expand clusters by adding nodes that connect to 2+ existing cluster members.

Output the top 5-10 clusters, each as a list of node IDs.

**G. Layer List**

Record the layers provided in the input. Since layers contain only `{id, name, description}` (no node membership), simply output the layer count and the list of layers with their id, name, and description.

**H. Node Summary Index**

Create a lookup of each node ID to its `summary`, `type`, and `name` for easy reference. This lets the LLM phase quickly access semantic information without re-reading the full input.

Note: input nodes may include all node types (file, config, document, service, pipeline, table, schema, resource, endpoint). The nodeSummaryIndex should include all of them.

### Script Output Format

```json
{
  "scriptCompleted": true,
  "entryPointCandidates": [
    {"id": "document:README.md", "score": 5, "name": "README.md", "summary": "Project overview..."},
    {"id": "file:src/index.ts", "score": 7, "name": "index.ts", "summary": "..."}
  ],
  "fanInRanking": [
    {"id": "file:src/utils/format.ts", "fanIn": 15, "name": "format.ts"}
  ],
  "fanOutRanking": [
    {"id": "file:src/app.ts", "fanOut": 10, "name": "app.ts"}
  ],
  "bfsTraversal": {
    "startNode": "file:src/index.ts",
    "order": ["file:src/index.ts", "file:src/config.ts", "file:src/services/auth.ts"],
    "depthMap": {
      "file:src/index.ts": 0,
      "file:src/config.ts": 1,
      "file:src/services/auth.ts": 1
    },
    "byDepth": {
      "0": ["file:src/index.ts"],
      "1": ["file:src/config.ts", "file:src/services/auth.ts"],
      "2": ["file:src/models/user.ts"]
    }
  },
  "nonCodeFiles": {
    "documentation": [
      {"id": "document:README.md", "name": "README.md", "summary": "Project overview..."}
    ],
    "infrastructure": [
      {"id": "service:Dockerfile", "name": "Dockerfile", "summary": "Multi-stage build..."},
      {"id": "pipeline:.github/workflows/ci.yml", "name": "ci.yml", "summary": "CI pipeline..."}
    ],
    "data": [
      {"id": "table:schema.sql:users", "name": "users", "summary": "User table..."}
    ],
    "config": [
      {"id": "config:package.json", "name": "package.json", "summary": "Project manifest..."}
    ]
  },
  "clusters": [
    {"nodes": ["file:src/services/auth.ts", "file:src/models/user.ts"], "edgeCount": 4}
  ],
  "layers": {
    "count": 3,
    "list": [
      {"id": "layer:core", "name": "Core", "description": "Core application logic"},
      {"id": "layer:infrastructure", "name": "Infrastructure", "description": "Deployment and CI/CD"}
    ]
  },
  "nodeSummaryIndex": {
    "file:src/index.ts": {"name": "index.ts", "type": "file", "summary": "Main entry point..."},
    "document:README.md": {"name": "README.md", "type": "document", "summary": "Project overview..."},
    "service:Dockerfile": {"name": "Dockerfile", "type": "service", "summary": "Multi-stage Docker build..."}
  },
  "totalNodes": 42,
  "totalEdges": 87
}
```

### Preparing the Script Input

Before writing the script, create its input JSON file. First resolve the project's data directory once (the legacy `.understand-anything/` when it already exists, otherwise the new `.ua/`) and reuse `$UA_DIR` for every path below:

```bash
UA_DIR="$PROJECT_ROOT/$([ -d "$PROJECT_ROOT/.understand-anything" ] && echo .understand-anything || echo .ua)"
cat > $UA_DIR/tmp/ua-tour-input.json << 'ENDJSON'
{
  "nodes": [<nodes from prompt â€” all types including non-code>],
  "edges": [<edges from prompt â€” all types>],
  "layers": [<layers from prompt>]
}
ENDJSON
```

### Executing the Script

After writing the script, execute it:

```bash
node $UA_DIR/tmp/ua-tour-analyze.js $UA_DIR/tmp/ua-tour-input.json $UA_DIR/tmp/ua-tour-results.json
```

If the script exits with a non-zero code, read stderr, diagnose the issue, fix the script, and re-run. You have up to 2 retry attempts.

---

## Phase 2 -- Pedagogical Tour Design

After the script completes, read `$UA_DIR/tmp/ua-tour-results.json`. Use the structural analysis as your primary guide for designing the tour. Do NOT re-read source files or re-analyze the graph -- trust the script's results entirely.

### Tour Design Goals

Choose a sequence of 5-15 steps that helps a newcomer understand the project's purpose, architecture, and key concepts:

- Start with a project overview, using an informative README when available or a code entry point otherwise.
- Introduce the concepts needed to understand later steps before those steps. Use layer names and descriptions to identify these prerequisites.
- Use `bfsTraversal`, `fanInRanking`, `fanOutRanking`, and `entryPointCandidates` as structural signals. Choose step order and emphasis according to the project's learning needs; BFS depth does not prescribe step numbers.
- Select the most important and illustrative nodes. Group related nodes when explaining them together helps the reader, using `clusters` as a guide.
- Integrate meaningful documentation, infrastructure, data, and configuration from `nonCodeFiles` alongside the concepts they explain.

### Step Descriptions

For each step, use the `nodeSummaryIndex` to access node summaries and names without re-reading files. Each description must:

- Explain WHAT this area does and WHY it matters to the project
- Connect to previous steps (e.g., "Building on the User types from Step 2, this service implements...")
- Highlight key design decisions or patterns
- Be written for someone who has never seen this codebase before
- Be 2-4 sentences long
- Ground project-specific claims in node summaries or other provided project context

**Illustrative non-code descriptions:** Adapt the wording to the project and include only details supported by the provided data.

Bad description: "This is the Dockerfile."
Good description: "The Dockerfile defines how the application gets packaged into a container image. It uses a multi-stage build: the first stage installs dependencies and compiles TypeScript, while the second stage copies only the compiled output into a minimal Alpine image. This separates compilation dependencies from the files needed to run the server."

Bad description: "These are the SQL migrations."
Good description: "The users and orders tables store the records used by the models introduced earlier. The foreign key from orders to users links each purchase to its owner, connecting the data model to the ordering workflow."

### Language Lessons (Optional)

If a step involves notable language-specific or format-specific patterns, include a brief `languageLesson` string. Only add these when genuinely educational:

**For code files:**
- **TypeScript:** generics, discriminated unions, utility types, decorators, template literal types
- **React:** hooks, context, render patterns, suspense, compound components
- **Python:** decorators, generators, context managers, metaclasses, protocols
- **Go:** goroutines, channels, interfaces, embedding, error wrapping
- **Rust:** ownership, lifetimes, traits, pattern matching, async/await

**For non-code files:**
- **Dockerfile:** multi-stage builds reduce image size by separating build and runtime dependencies. Layer ordering matters for Docker cache efficiency â€” put rarely-changing layers (OS packages) before frequently-changing ones (app code).
- **docker-compose:** service dependency ordering with `depends_on`, health checks, named volumes for persistent data, network isolation between services.
- **SQL:** database normalization reduces redundancy through foreign keys. Migrations should be idempotent and reversible. Index placement affects query performance.
- **GraphQL:** type system enforces API contracts at the schema level. Resolvers map schema fields to data sources. Fragments reduce query duplication.
- **Protobuf:** field numbers are permanent (never reuse deleted numbers). Backward compatibility requires only adding optional fields. Services define RPC contracts.
- **YAML (CI/CD):** GitHub Actions use `on` triggers, `jobs` for parallelism, and `steps` for sequential execution. Matrix builds test across multiple OS/language versions. Caching speeds up dependency installation.
- **Terraform:** resources declare desired infrastructure state. State files track what exists. Modules encapsulate reusable infrastructure patterns. Plan before apply to preview changes.
- **Makefile:** targets define build steps with dependency tracking. Phony targets for non-file actions. Variables and pattern rules reduce repetition.
- **Kubernetes:** Deployments manage pod replicas with rolling updates. Services expose pods via stable DNS names. ConfigMaps/Secrets separate config from images.

## Output Format

Produce a single, valid JSON array.

The following example is illustrative. Use node IDs and project facts from the provided graph when creating the actual tour.

```json
[
  {
    "order": 1,
    "title": "Project Overview",
    "description": "Start with README.md to understand the project's purpose, architecture, and how to get started. This document outlines the main components and their relationships, providing a roadmap for the tour ahead.",
    "nodeIds": ["document:README.md"]
  },
  {
    "order": 2,
    "title": "Application Entry Point",
    "description": "The main entry point bootstraps the application, importing core modules, setting up configuration, and starting the server. This file gives you a bird's-eye view of the project's runtime structure.",
    "nodeIds": ["file:src/index.ts"],
    "languageLesson": "TypeScript barrel files use 'export * from' to re-export modules, creating a clean public API surface."
  },
  {
    "order": 3,
    "title": "Core Types and Models",
    "description": "The type system defines the domain model. These interfaces establish the vocabulary used throughout the codebase and form the contract between layers.",
    "nodeIds": ["file:src/types.ts", "file:src/interfaces/user.ts"]
  },
  {
    "order": 4,
    "title": "Database Schema",
    "description": "The SQL migrations define persistent storage for the data model introduced in Step 3. Foreign keys enforce the relationships the code relies on.",
    "nodeIds": ["table:migrations/001.sql:users", "table:migrations/002.sql:orders"],
    "languageLesson": "SQL migrations should be idempotent and ordered. Each migration file applies incremental changes to the schema, allowing the database to evolve alongside the application code."
  },
  {
    "order": 5,
    "title": "Containerization & Deployment",
    "description": "The Dockerfile packages the application into a production-ready container image. The multi-stage build compiles TypeScript in a builder stage and copies only the runtime artifacts, keeping the final image small.",
    "nodeIds": ["service:Dockerfile", "service:docker-compose.yml"],
    "languageLesson": "Multi-stage Docker builds use multiple FROM statements. The builder stage has dev dependencies for compilation, while the final stage only includes runtime dependencies."
  }
]
```

**Required fields for every step:**
- `order` (integer) -- sequential starting from 1, no gaps, no duplicates
- `title` (string) -- short, descriptive title (2-5 words)
- `description` (string) -- 2-4 sentences explaining the area and its importance
- `nodeIds` (string[]) -- 1-5 node IDs from the provided graph, NEVER empty

**Optional fields:**
- `languageLesson` (string) -- brief explanation of a language or format pattern, only when genuinely useful

## Critical Constraints

- NEVER reference node IDs that do not exist in the provided graph data. Every entry in `nodeIds` must match an actual node `id` from the input. Cross-check against the script's `nodeSummaryIndex` keys.
- NEVER create steps with empty `nodeIds` arrays.
- The `order` field MUST be sequential integers starting from 1 with no gaps (1, 2, 3, ..., N).
- Tour MUST have between 5 and 15 steps inclusive.
- Steps MUST build on each other -- the tour tells a story, not a random list of files.
- Not every file needs to appear in the tour. Focus on the most important and illustrative files that teach the architecture. Use the fan-in ranking to identify which files are most worth covering.
- Non-code files are valid tour stops. Include at least 1-2 non-code stops if the project has meaningful documentation, infrastructure, or data schema files.
- ALWAYS start with the project overview (README or entry point) in Step 1.
- Trust the script's structural analysis. Do NOT re-read source files, re-count edges, or re-trace dependencies. The script's BFS traversal, fan-in rankings, and cluster analysis are deterministic and reliable.

## Writing Results

After producing the JSON:

1. Write the JSON array to `$UA_DIR/intermediate/tour.json` inside the project's data directory (`.ua/`, or the legacy `.understand-anything/` when that directory is present). Use the exact output path given in your dispatch prompt if one was provided.
2. The project root will be provided in your prompt.
3. Respond with ONLY a brief text summary: number of steps and their titles in order.

Do NOT include the full JSON in your text response.

</agent_spec>

---

## Dispatch Parameters

Project root: `C:\Users\Jian\Documents\Studio92 v2\snaptrack`
Write output to: `C:\Users\Jian\Documents\Studio92 v2\snaptrack\.ua\intermediate\tour.json`
Project: `SnapTrack` -- Studio 94 SnapTrack, a procedural PHP 8.1+ / MySQL photography studio booking and management system for Studio 94 (a Philippine photography studio; PHP peso currency, Tagalog/English UI).
Languages: php, javascript, css, html, sql, markdown, json, toml
Entry point: `index.php` (single router)

### CRITICAL -- Windows / PowerShell note

Write files with the BOM-free pattern or use Node's `fs.writeFileSync`:

``powershell
[System.IO.File]::WriteAllText("<path>", (\ | ConvertTo-Json -Depth 20), (New-Object System.Text.UTF8Encoding \False))
``

A BOM breaks `JSON.parse`. Keep output ASCII-only (avoid the peso sign and other non-ASCII characters).

### Pre-computed input

``json
{
  "nodes": [
    {
      "id": "config:.env.example",
      "name": ".env.example",
      "filePath": ".env.example",
      "summary": "Template environment file documenting all 18 runtime variables consumed by config.php: app URL, MySQL credentials, VAPID web-push keys, PayMongo and Google OAuth secrets, the Gemini API key, SMTP credentials, and a Resend key. Values here are placeholders only and are read by the hand-rolled loadEnv() in config.php, which populates $_ENV and putenv().",
      "type": "config"
    },
    {
      "id": "document:AGENTS.md",
      "name": "AGENTS.md",
      "filePath": "AGENTS.md",
      "summary": "Agent-facing engineering notes for SnapTrack covering setup commands, the index.php?page= router and its $rolePages allowlist, env/config caveats, the runtime schema mutation in ensureDatabaseSchema(), and known gotchas. Documents that pages/<role>/*.php are not standalone, the root styles.css is dead, and display_errors is off so fatals surface as a blank page logged to php-errors.log.",
      "type": "document"
    },
    {
      "id": "document:GEMINI.md",
      "name": "GEMINI.md",
      "filePath": "GEMINI.md",
      "summary": "Project context document for Studio 94 SnapTrack describing the PHP 8.1 / MySQL stack, the role-based pages/ layout (admin, staff, client, api), and the external integrations (Gemini AI, Google OAuth + TOTP MFA, PayMongo, PHPMailer, Resend, QR codes, TCPDF/FPDF). Includes installation steps, coding-style conventions, responsive breakpoints, and Nixpacks/Railway deployment notes.",
      "type": "document"
    },
    {
      "id": "config:composer.json",
      "name": "composer.json",
      "filePath": "composer.json",
      "summary": "Composer manifest pinning PHP >=8.1 and the pdo, pdo_mysql, mysqli extensions, plus three runtime packages: google/apiclient 2.15.0, phpmailer/phpmailer ^7.1, and endroid/qr-code ^6.0. Notably it has no autoload section, which is why the project's dependency graph is expressed entirely through manual require_once calls rather than PSR-4 autoloading.",
      "type": "config"
    },
    {
      "id": "config:nixpacks.toml",
      "name": "nixpacks.toml",
      "filePath": "nixpacks.toml",
      "summary": "Two-line Nixpacks configuration declaring the PHP extensions to install in the build image: mysqli, pdo_mysql, pdo, gd, curl, and mbstring. This is what makes the Railway deployment image able to serve the app at all, since the base PHP image omits gd and curl.",
      "type": "config"
    },
    {
      "id": "file:styles.css",
      "name": "styles.css",
      "filePath": "styles.css",
      "summary": "DEAD FILE. Root-level copy of the soft light-gray SnapTrack theme defining CSS custom properties, buttons, badges, sidebar layout, tables, modals, calendar, chatbot, login page, and responsive/mobile navigation. Nothing references it: only assets/css/styles.css is linked (by header.php, login.php, and mfa-setup.php), and this file is byte-identical to assets/images/css/styles.css, a third unreferenced copy.",
      "type": "file"
    },
    {
      "id": "file:ai_helper.php",
      "name": "ai_helper.php",
      "filePath": "ai_helper.php",
      "summary": "Google Gemini integration for customer-feedback analysis; requires config.php and posts to the generativelanguage API over raw cURL with a five-model fallback chain.",
      "type": "file"
    },
    {
      "id": "file:app.js",
      "name": "app.js",
      "filePath": "app.js",
      "summary": "DEAD self-contained front-end clickable prototype (~1900 lines, 96 functions) backed entirely by localStorage under s94_* keys. It duplicates the real booking, payment, inventory, loyalty and chatbot UI in the PHP pages but is not referenced by any PHP template -- only assets/js/app.js is actually loaded (by footer.php).",
      "type": "file"
    },
    {
      "id": "file:assets/css/styles.css",
      "name": "styles.css",
      "filePath": "assets/css/styles.css",
      "summary": "The one live stylesheet for the whole app, defining a soft light-gray design system via 52 :root custom properties plus responsive breakpoints. Loaded by header.php, login.php and mfa-setup.php.",
      "type": "file"
    },
    {
      "id": "file:assets/images/css/styles.css",
      "name": "styles.css",
      "filePath": "assets/images/css/styles.css",
      "summary": "DEAD stray stylesheet copy -- byte-identical (MD5 B8897A23..., 35841 bytes) to the unreferenced root styles.css, not to the live assets/css/styles.css. Zero references anywhere in the PHP or HTML.",
      "type": "file"
    },
    {
      "id": "file:assets/js/app.js",
      "name": "app.js",
      "filePath": "assets/js/app.js",
      "summary": "The live ~126-line frontend utility layer providing sidebar toggling, toast notifications, table filtering, modal helpers, form validation and print-section support. Loaded once by footer.php.",
      "type": "file"
    },
    {
      "id": "file:assets/uploads/.gitkeep",
      "name": ".gitkeep",
      "filePath": "assets/uploads/.gitkeep",
      "summary": "Empty placeholder that keeps the UPLOAD_DIR upload directory tracked in git. Contains no code.",
      "type": "file"
    },
    {
      "id": "file:change_password.php",
      "name": "change_password.php",
      "filePath": "change_password.php",
      "summary": "One-shot destructive ops script that resets every row of the users table to a freshly generated hash of the literal password \"password\" and prints the result. Web-accessible and referenced by nothing -- must be deleted before any real deploy.",
      "type": "file"
    },
    {
      "id": "file:config.php",
      "name": "config.php",
      "filePath": "config.php",
      "summary": "Central configuration bootstrap: hand-rolled .env loading, database constants with Railway MYSQL* fallbacks, APP_URL auto-detection, upload/session paths, Gemini and Gmail/Resend mail settings, and the PHP error-logging policy.",
      "type": "file"
    },
    {
      "id": "file:footer.php",
      "name": "footer.php",
      "filePath": "footer.php",
      "summary": "Shared layout tail included by index.php: closes the app-layout wrapper, conditionally injects the SnapBot chatbot widget and a client-only notification toast poller, then loads assets/js/app.js. Assumes config.php and functions.php are already loaded by the router.",
      "type": "file"
    },
    {
      "id": "file:functions.php",
      "name": "functions.php",
      "filePath": "functions.php",
      "summary": "Core procedural helper library and the single shared function surface for the whole app: PDO connection with runtime schema auto-migration, auth/CSRF/flash, notifications, Web Push, loyalty tiers, inventory and view helpers. Required by virtually every page and API endpoint.",
      "type": "file"
    },
    {
      "id": "file:google-callback.php",
      "name": "google-callback.php",
      "filePath": "google-callback.php",
      "summary": "OAuth2 callback endpoint for Google Sign-In: exchanges the auth code for a token via google/apiclient, rejects non-Gmail addresses, auto-creates or email-verifies the user, regenerates the session and redirects into the routed dashboard. Appends verbose traces to google-debug.log.",
      "type": "file"
    },
    {
      "id": "file:google-config.php",
      "name": "google-config.php",
      "filePath": "google-config.php",
      "summary": "Defines GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI from environment variables, delegating to config.php for env loading and the APP_URL-based redirect default. Shared by the Google sign-in start and callback scripts.",
      "type": "file"
    },
    {
      "id": "file:google-login.php",
      "name": "google-login.php",
      "filePath": "google-login.php",
      "summary": "Entry point that initiates the Google OAuth2 authorization-code flow by redirecting to Google's auth endpoint. Bails out to login.php with a session error when the client credentials are absent from the environment.",
      "type": "file"
    },
    {
      "id": "file:header.php",
      "name": "header.php",
      "filePath": "header.php",
      "summary": "Shared HTML <head> partial included by index.php: emits the page title through clean(), links assets/css/styles.css and opens the app-layout wrapper. Not standalone â€” it needs config.php's APP_URL and functions.php's clean() already loaded by the router.",
      "type": "file"
    },
    {
      "id": "file:includes/booking.php",
      "name": "booking.php",
      "filePath": "includes/booking.php",
      "summary": "Booking persistence helpers used by the SnapBot chatbot, each with a JSON-file fallback. BROKEN: saveBooking/getBookings/getBookingStats call getDBConnection(), which is defined nowhere in the project (the only accessor is db() in functions.php), and the file never requires functions.php for the addNotificationByRole() calls it makes.",
      "type": "file"
    },
    {
      "id": "file:includes/chatbot.php",
      "name": "chatbot.php",
      "filePath": "includes/chatbot.php",
      "summary": "Self-contained 'SnapBot AI' chat widget (CSS + HTML + vanilla JS) injected into client pages by footer.php, with a draggable panel, quick replies, localStorage chat history and POSTs to pages/api/chatbot-ai.php. Contains no PHP logic and no require statements.",
      "type": "file"
    },
    {
      "id": "file:includes/deduct_inventory.php",
      "name": "deduct_inventory.php",
      "filePath": "includes/deduct_inventory.php",
      "summary": "Standalone inventory-deduction helper that maps a package name to consumable/reusable item quantities, decrements stock on booking completion, and raises low-stock notifications. It is never `require`d or `include`d anywhere in the repository, so these two functions are currently unreachable dead code; the live equivalent is `deductInventoryOnComplete()` in `functions.php`.",
      "type": "file"
    },
    {
      "id": "file:index.php",
      "name": "index.php",
      "filePath": "index.php",
      "summary": "The single router and authenticated entry point for all SnapTrack UI (`index.php?page=<name>`). It loads config.php/functions.php, re-reads and re-validates the user from the DB on every request, validates the requested page against the per-role `$rolePages` allowlist, resolves the page file through a fallback chain (own role dir -> `pages/<page>.php` -> other role dirs -> shared `walkin.php` -> dashboard), then wraps it in header/sidebar/footer with the photography-themed topbar CSS.",
      "type": "file"
    },
    {
      "id": "file:landing.php",
      "name": "landing.php",
      "filePath": "landing.php",
      "summary": "Public marketing landing page for STUDIO 94 self-shoot studio. Requires config.php/functions.php, branches its call-to-action links based on `isLoggedIn()` and session role (clients go to `index.php?page=booking`, staff/admin to `page=walkin`, anonymous users are sent through `login.php?redirect=...`), and embeds roughly 470 lines of inline CSS/HTML/JS for the hero, package tiers, and how-it-works sections.",
      "type": "file"
    },
    {
      "id": "file:login.php",
      "name": "login.php",
      "filePath": "login.php",
      "summary": "Combined login / register / forgot-password / reset-password page that is the app's authentication entry point. A single CSRF-protected POST handler dispatches on a hidden `action` field: `login` (Gmail-only regex check, `password_verify`, refuses unverified email, `session_regenerate_id`), `register` (creates or refreshes an unverified client, sends a verification email and auto-verifies on 'skipped'/'failed' delivery), `forgot_password` (inserts a `password_resets` row and mails a link) and `reset_password` (consumes the token and rehashes the password).",
      "type": "file"
    },
    {
      "id": "file:logout.php",
      "name": "logout.php",
      "filePath": "logout.php",
      "summary": "Minimal session teardown endpoint that clears `$_SESSION`, expires the session cookie using the existing cookie params, destroys the session, and redirects to `login.php`. It requires only `functions.php` and relies on that file transitively loading `config.php` for the `APP_URL` constant.",
      "type": "file"
    },
    {
      "id": "file:mfa-helper.php",
      "name": "mfa-helper.php",
      "filePath": "mfa-helper.php",
      "summary": "Dependency-free TOTP (RFC 6238) implementation wrapped in the `MFAHelper` class â€” base32 secret generation/decoding, HOTP code derivation, constant-time verification with time-slice discrepancy tolerance, `otpauth://` URI building, QR code rendering via `endroid/qr-code`, and bcrypt-hashed single-use backup codes. Included only by `mfa-setup.php`.",
      "type": "file"
    },
    {
      "id": "file:mfa-setup.php",
      "name": "mfa-setup.php",
      "filePath": "mfa-setup.php",
      "summary": "Standalone two-factor enrollment page that sits outside the router: a CSRF-protected POST handler supports `generate` (stash a new secret in the session and re-render the QR), `verify` (confirm the first TOTP code, persist `mfa_enabled`/`mfa_secret`/hashed backup codes, then show the codes once), and `disable` (requires re-entering the account password). Renders one of four states â€” backup codes, QR scan, enabled, disabled â€” depending on `?step=`, the pending session secret, and the stored `mfa_enabled` flag.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/DFD.HTML",
      "name": "DFD.HTML",
      "filePath": "pages/admin/DFD.HTML",
      "summary": "Static 1800x1200 inline-SVG data-flow diagram titled 'Studio 94 DFD Layout' that maps the system's entities (Client, Studio Staff, Studio Owner), processes 1.0-8.0 (authentication, booking, service, inventory, AI chatbot, payment, feedback, reports) and data stores D1-D7. It contains no PHP and is dead: the `.HTML` extension means `index.php`'s router (which always appends `.php`) can never reach it, and nothing else links to it. Valuable only as design documentation of the intended system.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/bookings.php",
      "name": "bookings.php",
      "filePath": "pages/admin/bookings.php",
      "summary": "Admin read-only bookings listing page. Builds a dynamic WHERE clause from the `status` and `q` query params, then runs a prepared statement joining bookings to users and packages with a correlated subquery that counts `booking_inventory` rows per booking; results are ordered newest-first and rendered as a table with client, package, schedule, type, inventory count and status badges. Deliberately exposes no status-mutation controls â€” an inline banner states admins may view but not approve, complete or cancel bookings, which is staff-only. Not a standalone script: the bare `requireRole('admin')` on line 2 assumes `index.php` has already loaded config.php/functions.php and defined the role, page and user variables, so opening the file directly is a fatal error.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/clients.php",
      "name": "clients.php",
      "filePath": "pages/admin/clients.php",
      "summary": "Admin client directory page: one un-paginated query joins users with bookings, payments and loyalty_cards to derive per-client booking counts, lifetime spend and first/last booking dates, then segments every client as New (0-1 bookings) or Returning (2+) with search and filter tabs. Not standalone - it calls requireRole('admin') on line 6 and relies on index.php having already loaded config.php/functions.php and defined $user.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/dashboard.php",
      "name": "dashboard.php",
      "filePath": "pages/admin/dashboard.php",
      "summary": "Admin sales-and-analytics dashboard, the largest page in the project: computes revenue vs. booked sales, pending approvals, walk-in vs online split, a dynamic (capped at 24 months) monthly sales and booking trend, top-5 packages by revenue, per-package sales, busiest weekday analysis and a low-stock alert list. Roughly two thirds of its 2350 lines are inline CSS and HTML rather than PHP logic.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/feedback.php",
      "name": "feedback.php",
      "filePath": "pages/admin/feedback.php",
      "summary": "Admin feedback console handling delete, resolve, mark-urgent, AI re-analyze and send-reply POST actions (all CSRF-verified), calling analyzeFeedbackWithAI() from ai_helper.php and falling back to a local Tagalog/English keyword sentiment scorer when the AI columns are absent. Renders filterable review stats plus a sentiment-prioritized urgent list.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/inventory.php",
      "name": "inventory.php",
      "filePath": "pages/admin/inventory.php",
      "summary": "Inventory CRUD page open to both admin and staff: adds items, updates quantities in place, and refuses deletion when an item is still referenced by booking_inventory rows. Every mutation calls checkLowStockAndNotify() so alerts fire immediately, and the listing annotates each item with an in-use count derived from unreturned booking_inventory records.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/loyalty-cards.php",
      "name": "loyalty-cards.php",
      "filePath": "pages/admin/loyalty-cards.php",
      "summary": "Read-only admin view of the loyalty program: joins users to loyalty_cards, attaches a tier through getLoyaltyTier(), filters by the corrected 2/4/7/10 booking thresholds, and shows tier badges plus progress toward the next reward. Performs no writes and is not exposed to client or staff navigation.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/notifications.php",
      "name": "notifications.php",
      "filePath": "pages/admin/notifications.php",
      "summary": "Shared notification inbox for admin, staff and clients: opens a notification (marking it read and redirecting to its internal link), marks all as read, deletes single entries, and renders the latest 100 notifications styled per type. Every branch prefers the functions.php helper (getNotifications, markNotificationRead, markAllNotificationsRead, deleteNotification) and falls back to equivalent inline SQL.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/packages.php",
      "name": "packages.php",
      "filePath": "pages/admin/packages.php",
      "summary": "Admin CRUD page over the packages table: creates and updates packages with an image upload, soft-deletes them by setting is_active=0, and renders main packages with their sub-packages grouped through parent_id. Images are stored under assets/packages/ with a validated MIME type and a random filename.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/payments.php",
      "name": "payments.php",
      "filePath": "pages/admin/payments.php",
      "summary": "Admin payment ledger: aggregates total revenue, pending, refunded and unpaid-reservation figures from the payments and bookings tables, then lists transactions joined to clients, bookings, packages and the users who refunded or verified them, with a status filter and derived on-site vs walk-in source labels.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/profile.php",
      "name": "profile.php",
      "filePath": "pages/admin/profile.php",
      "summary": "Shared profile page for any logged-in user: dispatches POST actions to edit name/email/phone, change the password, and upload an avatar, then renders the profile UI in the same file. It is rendered by index.php and depends on the router having already defined $user and $role -- its only guard is an isset() check that redirects to login.php.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/reports.php",
      "name": "reports.php",
      "filePath": "pages/admin/reports.php",
      "summary": "Admin-only reports and analytics module exposing 9 report types (inventory, appointment, payment, sales, loyalty, analytics, evaluation, chatbot, photos) with on-screen tables plus PDF and CSV export. Revenue is derived from actual paid payments rather than booking totals, and the analytics report computes genuine period-over-period deltas instead of hard-coded percentages.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/sales.php",
      "name": "sales.php",
      "filePath": "pages/admin/sales.php",
      "summary": "Admin sales ledger that filters the payments table by date range, status, payment method and free-text search, then derives summary cards plus daily-revenue, per-package and per-method breakdowns for charting. The transaction query, metric aggregates and chart queries all reuse the same WHERE clause so the summary cards always match the table below them.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/settings.php",
      "name": "settings.php",
      "filePath": "pages/admin/settings.php",
      "summary": "Admin-only settings screen that persists studio identity, opening hours and GCash/Maribank account details into the settings key/value table and handles QR-code image uploads. Every text field is written with INSERT ... ON DUPLICATE KEY UPDATE, and each QR upload is MIME-sniffed, capped at 5MB and has its predecessor file deleted.",
      "type": "file"
    },
    {
      "id": "file:pages/admin/staff.php",
      "name": "staff.php",
      "filePath": "pages/admin/staff.php",
      "summary": "Admin page for managing staff accounts: adds a user with the staff role, flips is_active, and deletes staff rows via a CSRF-checked POST handler that redirects back to index.php?page=staff. The listing pulls every staff user together with a count of the bookings each one managed.",
      "type": "file"
    },
    {
      "id": "file:pages/api/chatbot-ai.php",
      "name": "chatbot-ai.php",
      "filePath": "pages/api/chatbot-ai.php",
      "summary": "Standalone SnapBot AI JSON endpoint for the public booking site that runs a three-stage cascade: live availability answered from the bookings table, then a hard-coded Studio 94 FAQ, then a Gemini generateContent call across a four-model fallback chain. Every reply is persisted to chat_history and markdown is stripped before the JSON response is emitted.",
      "type": "file"
    },
    {
      "id": "file:pages/api/check-client-loyalty.php",
      "name": "check-client-loyalty.php",
      "filePath": "pages/api/check-client-loyalty.php",
      "summary": "Standalone staff/admin JSON endpoint that resolves a client by phone or email and returns their loyalty booking count, bonus minutes, discount percentage and tier label for the booking form. The tier thresholds are duplicated inline rather than delegated to getLoyaltyTier() in functions.php, so the two definitions can drift apart.",
      "type": "file"
    },
    {
      "id": "file:pages/api/check_availability.php",
      "name": "check_availability.php",
      "filePath": "pages/api/check_availability.php",
      "summary": "Standalone staff/admin JSON endpoint that builds a 15-minute slot grid between 10:00 AM and 7:00 PM for a given date and package, adding a capped loyalty bonus to the package duration. Slots carry a three-state result -- available, pending approval, or fully booked -- with confirmed bookings taking priority over awaiting-approval ones.",
      "type": "file"
    },
    {
      "id": "file:pages/api/check_new_notifications.php",
      "name": "check_new_notifications.php",
      "filePath": "pages/api/check_new_notifications.php",
      "summary": "Standalone JSON polling endpoint backing the client's notification bell: with ?all=1 it returns the total unread count for the badge, otherwise with ?since=<unix_ts> it returns up to five unread notifications created since that timestamp. Self-requires config.php and functions.php, does its own isLoggedIn() plus role allow-list check, and degrades to a success:false JSON body rather than a non-200 code when the database is unreachable.",
      "type": "file"
    },
    {
      "id": "file:pages/api/client-payments.php",
      "name": "client-payments.php",
      "filePath": "pages/api/client-payments.php",
      "summary": "Client-scoped JSON API with three actions selected by ?action=: 'settings' returns the GCash and Maribank account/QR details from the settings table, 'list' returns the client's payment rows joined to bookings and packages plus computed totals, and 'submit' accepts a CSRF-protected payment-proof screenshot upload. Uploads are extension- and size-validated (5 MB, images only) and written to assets/uploads/payments/ rather than the UPLOAD_DIR constant.",
      "type": "file"
    },
    {
      "id": "file:pages/api/get-day-bookings.php",
      "name": "get-day-bookings.php",
      "filePath": "pages/api/get-day-bookings.php",
      "summary": "Zero-byte placeholder file with no PHP, no requires, and no consumers. It is a dead stub: any request to it returns an empty 200 response, and nothing in the codebase references it.",
      "type": "file"
    },
    {
      "id": "file:pages/api/inventory-usage.php",
      "name": "inventory-usage.php",
      "filePath": "pages/api/inventory-usage.php",
      "summary": "Read-only JSON endpoint that returns up to 50 rows of usage history for a single inventory item by joining booking_inventory to bookings, restricted to admin and staff. Takes the item id from ?id= and returns { success, usage, count } with no-store of state.",
      "type": "file"
    },
    {
      "id": "file:pages/api/record-payment.php",
      "name": "record-payment.php",
      "filePath": "pages/api/record-payment.php",
      "summary": "Staff/admin JSON endpoint for recording on-site payments, accepting both raw JSON bodies and multipart FormData so an optional proof image can accompany the payment. Handles RESERVATION (flat 100 peso), BALANCE, and FULL types, applies the 10-booking 50% loyalty discount, then writes the payment row and flips the booking status to Deposit Paid or Confirmed inside one transaction before firing client and admin notifications.",
      "type": "file"
    },
    {
      "id": "file:pages/client/booking.php",
      "name": "booking.php",
      "filePath": "pages/client/booking.php",
      "summary": "Client booking-creation page reached through the index.php router (index.php?page=booking); it calls requireRole('client') at line 2 with no require_once of its own, so it is not standalone and would fatal if opened directly. The POST handler validates package, date, time, headcount against the studio's 10:00-19:00 window, and detects schedule overlaps before inserting the booking, an UNPAID RESERVATION payment row, and a loyalty card increment, then fires a staff notification. Roughly the last 800 lines are a large inline <script> block implementing the calendar, slot grid, and sub-package drill-down.",
      "type": "file"
    },
    {
      "id": "file:pages/client/bookings.php",
      "name": "bookings.php",
      "filePath": "pages/client/bookings.php",
      "summary": "Client booking-list page rendered via index.php?page=bookings; like the other pages/<role>/ files it opens with requireRole('client') and has no require_once of its own. Handles the POST cancel action (blocked within one day of the shoot and on terminal statuses, cascades the cancellation to UNPAID/PENDING payment rows and notifies admin and staff), then renders every booking joined to its package, parent package, and reservation payment with per-status counts.",
      "type": "file"
    },
    {
      "id": "file:pages/client/dashboard.php",
      "name": "dashboard.php",
      "filePath": "pages/client/dashboard.php",
      "summary": "Client landing page served at index.php?page=dashboard; begins with requireRole('client') and depends on index.php having already loaded config.php and functions.php and defined $user. Assembles the loyalty card summary, the next upcoming session, five recent bookings, three recently delivered photos, and a dynamic 'Welcome back' vs 'Welcome' greeting, then renders it with a page-scoped inline <style> block.",
      "type": "file"
    },
    {
      "id": "file:pages/client/feedback.php",
      "name": "feedback.php",
      "filePath": "pages/client/feedback.php",
      "summary": "Client feedback page where a customer submits a review for a completed booking, with Gemini AI sentiment analysis (local keyword/regex fallback), automatic topic tagging, urgent-escalation notifications, and a filterable review history grouped by service type. Not a standalone script: it opens with requireRole('client') and assumes index.php already loaded config.php/functions.php and defined $user, $role and $page.",
      "type": "file"
    },
    {
      "id": "file:pages/client/notifications.php",
      "name": "notifications.php",
      "filePath": "pages/client/notifications.php",
      "summary": "Client notification inbox that handles mark-all-read, single-read, single-delete and delete-all-read through GET parameters, then queries the notifications table filtered by all/unread/read and renders the list with an unread badge. Not a standalone script: it opens with requireRole('client') and depends on index.php having already loaded config.php and functions.php and defined $user.",
      "type": "file"
    },
    {
      "id": "file:pages/client/payments.php",
      "name": "payments.php",
      "filePath": "pages/client/payments.php",
      "summary": "Client 'My Payments' page that is ~99% inline CSS plus a large vanilla-JS single-page app: it fetches settings and grouped payment lists from pages/api/client-payments.php, renders GCash and Maribank QR codes, handles deposit vs. remaining-balance payment submission, and applies the 10-booking loyalty discount. The only PHP logic is the requireRole('client') auth gate.",
      "type": "file"
    },
    {
      "id": "file:pages/client/photos.php",
      "name": "photos.php",
      "filePath": "pages/client/photos.php",
      "summary": "Client photo-gallery page that lists the user's Completed bookings joined to packages with a photo count and latest upload date, then lazily loads a per-session thumbnail grid on demand. Read-only: it issues no writes and relies on the router having loaded config.php and functions.php.",
      "type": "file"
    },
    {
      "id": "file:pages/client/profile.php",
      "name": "profile.php",
      "filePath": "pages/client/profile.php",
      "summary": "User profile page (header comment marks it 'Shared') that processes three POST actions - update_profile, change_password and upload_avatar - re-reads the user row afterwards, and renders the profile form and avatar. Unlike the other pages in this batch it does not call requireRole(); it falls back to redirecting to login.php when $user or $role is unset, so it serves as the pages/<page>.php fallback when a role-specific profile page is missing.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/bookings.php",
      "name": "bookings.php",
      "filePath": "pages/staff/bookings.php",
      "summary": "Admin/staff bookings management page covering the full booking lifecycle: CSRF-verified approve/complete/cancel transitions that trigger notifications and inventory deduction on completion, walk-in booking creation with checkBookingConflict() and automatic loyalty-card enrollment, plus an on-site payment modal recording deposit or remaining balance. Models a fixed 100-peso reservation fee and shows loyalty milestones (2/4/7/10 bookings).",
      "type": "file"
    },
    {
      "id": "file:pages/staff/clients.php",
      "name": "clients.php",
      "filePath": "pages/staff/clients.php",
      "summary": "Staff client directory supporting a name/email LIKE search that aggregates each client's total booking count, total PAID spend and most recent booking date via LEFT JOINs against bookings and payments. Pure read-only listing page with no POST handling.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/dashboard.php",
      "name": "dashboard.php",
      "filePath": "pages/staff/dashboard.php",
      "summary": "Admin/staff landing dashboard that aggregates booking status counts, same-day conflict detection, pending approvals, confirmed-today bookings, upcoming bookings, today's full schedule and today's walk-ins, then renders them as stat cards and lists. Chooses a 'Welcome back' vs 'Hello' greeting from the session login_count.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/feedback.php",
      "name": "feedback.php",
      "filePath": "pages/staff/feedback.php",
      "summary": "Staff feedback console that lists all client reviews joined to users, normalizes sentiment per row (preferring the stored ai_sentiment column and falling back to a local keyword analyzer), flags urgent items, and lets staff store a reply that fires addNotification() to the client. The only page in this batch that explicitly require_once's ai_helper.php, and the only one that declares PHP functions.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/inventory.php",
      "name": "inventory.php",
      "filePath": "pages/staff/inventory.php",
      "summary": "Staff/admin inventory management page for the `inventory` table: CSRF-protected add / update_quantity / delete actions (delete is blocked when `booking_inventory` references the item), a red low-stock alert banner computed inline from non-reusable items at or below threshold, and an inline JS edit modal. Not standalone â€” it opens with requireRole(['admin','staff']) and relies on index.php having already loaded config.php/functions.php and defined $user/$role.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/notifications.php",
      "name": "notifications.php",
      "filePath": "pages/staff/notifications.php",
      "summary": "Small staff-only notification inbox that lists the last 50 `notifications` rows for the current user with all/unread/read filters, plus a `?markread=1` GET action that flips every row to is_read=1. Only 49 lines of which most is HTML; relies on the router having loaded config.php/functions.php and set $user.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/payments.php",
      "name": "payments.php",
      "filePath": "pages/staff/payments.php",
      "summary": "Payment verification console for staff/admin: handles verify / reject / refund POST actions, recomputing effective price from `loyalty_cards.total_bookings` (50% off at 10+ bookings), flipping `bookings` to Deposit Paid / Confirmed, and notifying the client via addNotification(). Adds a filter+search list query over payments joined to bookings, packages, users and loyalty_cards with status-ordered counts, then renders a list view whose rows open detail / proof / reject / refund modals.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/profile.php",
      "name": "profile.php",
      "filePath": "pages/staff/profile.php",
      "summary": "Shared self-service profile page for staff/admin (identical copy also served under pages/client) handling three POST actions â€” update_profile (with email uniqueness check and session resync), change_password (current-password verify + password_hash), and upload_avatar (finfo MIME sniffing, 2MB cap, writes to assets/uploads/avatars/ and unlinks the old file). Notably it does NOT call requireRole(); it only guards on `isset($user)`/`isset($role)` and redirects to login.php, and it calls db() seven separate times instead of reusing one handle.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/schedule.php",
      "name": "schedule.php",
      "filePath": "pages/staff/schedule.php",
      "summary": "Read-only staff schedule board that groups non-cancelled bookings by date and splits each day into online vs walk-in buckets (detected from booking type or a 'WALK' booking_ref prefix), with upcoming / today / tomorrow / week views driven by a CURDATE()-based WHERE builder. No POST handling and no CSRF surface.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/upload.php",
      "name": "upload.php",
      "filePath": "pages/staff/upload.php",
      "summary": "Staff photo-delivery page: CSRF-protected multi-file upload that filters extensions (jpg/jpeg/png/gif/webp/raw), caps each file at 50MB, writes into UPLOAD_DIR (assets/uploads/), inserts a `photos` row per file, and notifies the client via addNotification() once at least one file lands. Also lists Completed/Deposit Paid sessions eligible for upload and a 10-row upload history.",
      "type": "file"
    },
    {
      "id": "file:pages/staff/walkin.php",
      "name": "walkin.php",
      "filePath": "pages/staff/walkin.php",
      "summary": "The largest page in the batch: a walk-in booking flow that ALTERs `bookings` to add duration_minutes at request time, parses package duration strings into minutes, detects schedule conflicts, finds-or-creates the client user (issuing a random password sent via addNotification), inserts the walk-in booking plus a Pending RESERVATION payment row, bumps loyalty_cards, and notifies admin+staff. Ships ~500 lines of inline JS for package drill-down, a month calendar with availability colouring, quick-time dropdown, and an async loyalty lookup against pages/api/check-client-loyalty.php.",
      "type": "file"
    },
    {
      "id": "file:public/service-workers.js",
      "name": "service-workers.js",
      "filePath": "public/service-workers.js",
      "summary": "Web Push service worker that displays notifications from a JSON push payload with vibrate/sound/action buttons and opens /index.php?page=notifications on click; the fetch handler is network-first with an 'Offline' 503 fallback. Dead in practice â€” no `navigator.serviceWorker.register()` call exists anywhere in the project, so this file is never installed.",
      "type": "file"
    },
    {
      "id": "file:send-verification.php",
      "name": "send-verification.php",
      "filePath": "send-verification.php",
      "summary": "Transactional email helper library required by login.php that delivers account-verification and password-reset messages. Tries the Resend REST API over HTTPS 443 first and falls back to PHPMailer SMTP, because outbound SMTP ports are blocked on Railway.",
      "type": "file"
    },
    {
      "id": "file:sidebar.php",
      "name": "sidebar.php",
      "filePath": "sidebar.php",
      "summary": "Role-aware navigation sidebar rendered on every authenticated page. Hardcodes the $navAdmin/$navStaff/$navClient link tables that must stay in sync with the $rolePages allowlist in index.php, resolves the avatar path, counts unread notifications, and embeds its own inline CSS and JavaScript.",
      "type": "file"
    },
    {
      "id": "file:sql/hash.php",
      "name": "hash.php",
      "filePath": "sql/hash.php",
      "summary": "Nine-line throwaway developer utility that prints a password_hash() value plus a ready-to-run UPDATE statement resetting the password of every seeded demo account. Not part of the application and referenced by nothing.",
      "type": "file"
    },
    {
      "id": "file:sql/install.php",
      "name": "install.php",
      "filePath": "sql/install.php",
      "summary": "Browser-accessible database installer that loads config.php and functions.php, detects whether the users table already exists, then strips CREATE DATABASE/USE statements from install.sql and executes the whole file in one PDO call. Ships with no authentication of any kind, so it must be removed or locked down before any production deploy.",
      "type": "file"
    },
    {
      "id": "table:sql/install.sql",
      "name": "install.sql",
      "filePath": "sql/install.sql",
      "summary": "Complete bootstrap schema for SnapTrack: eleven CREATE TABLE statements followed by seed data for settings, users, packages, sub-packages, inventory, bookings, payments, feedback, notifications and loyalty cards. Comments are in Filipino, and ensureDatabaseSchema() in functions.php must be kept in sync with any column added here.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:users",
      "name": "users",
      "filePath": "sql/install.sql",
      "summary": "Account table covering credentials, role and active state, lockout tracking, email verification tokens, Google OAuth linkage, TOTP MFA secrets and backup codes, and an optional parent_id for family sub-accounts.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:packages",
      "name": "packages",
      "filePath": "sql/install.sql",
      "summary": "Studio package catalogue with a self-referencing parent_id that models sub-packages under a main package, plus price, duration, features JSON, theme colour, popularity flag and max_pax.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:bookings",
      "name": "bookings",
      "filePath": "sql/install.sql",
      "summary": "Session bookings storing the human booking_ref, package snapshot price, scheduling fields, deposit/remaining balance split, fully_paid flag and applied loyalty reward.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:payments",
      "name": "payments",
      "filePath": "sql/install.sql",
      "summary": "Payment records linked to a booking with amount, type, method, reference number, uploaded proof image, verification status, rejection reason and the staff member who verified it.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:inventory",
      "name": "inventory",
      "filePath": "sql/install.sql",
      "summary": "Stock-keeping table of equipment and consumables with category, quantity and a low-stock threshold that drives the admin and staff inventory pages.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:feedback",
      "name": "feedback",
      "filePath": "sql/install.sql",
      "summary": "Post-booking reviews with four separate rating dimensions, free-text comment, AI-derived sentiment and topics, and an is_urgent flag for negative-response escalation.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:photos",
      "name": "photos",
      "filePath": "sql/install.sql",
      "summary": "Uploaded gallery images tied to a booking and uploader, storing filename, filepath and a moderation status.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:notifications",
      "name": "notifications",
      "filePath": "sql/install.sql",
      "summary": "In-app notification feed per user with a type discriminator, title, message, icon and is_read flag; backs the unread badge counted by sidebar.php and getUnreadCount().",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:loyalty_cards",
      "name": "loyalty_cards",
      "filePath": "sql/install.sql",
      "summary": "Per-user loyalty membership holding the card number, lifetime booking count, consumed reward count and card status.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:settings",
      "name": "settings",
      "filePath": "sql/install.sql",
      "summary": "Simple key/value store for admin-editable runtime settings, seeded with the business identity including the San Fernando and Camarines Sur location names.",
      "type": "table"
    },
    {
      "id": "table:sql/install.sql:password_resets",
      "name": "password_resets",
      "filePath": "sql/install.sql",
      "summary": "Single-use password reset tokens keyed to a user and email with an expiry timestamp and a used flag, backing sendPasswordResetEmail().",
      "type": "table"
    },
    {
      "id": "file:uploads/.gitkeep",
      "name": ".gitkeep",
      "filePath": "uploads/.gitkeep",
      "summary": "Zero-byte placeholder that keeps the root uploads/ directory tracked by git. One of several scattered upload directories in this project and not itself written to by any code.",
      "type": "file"
    }
  ],
  "layers": [
    {
      "id": "layer:entry",
      "name": "Entry Points & Routing",
      "description": "Web-reachable entry scripts for SnapTrack: index.php resolves index.php?page=<name> against the $rolePages allowlist and wraps pages in the layout shell, while landing.php, login.php, logout.php, google-login.php, google-callback.php and mfa-setup.php handle the public, credential, OAuth2 and TOTP enrollment flows."
    },
    {
      "id": "layer:core",
      "name": "Shared Kernel",
      "description": "The procedural core every other layer depends on: config.php (hand-rolled .env loading and constants) and functions.php (db(), ensureDatabaseSchema(), auth, CSRF, notifications, inventory, loyalty), plus the external-integration clients ai_helper.php (Gemini), send-verification.php (Resend/PHPMailer), mfa-helper.php (TOTP) and the unused includes/booking.php and includes/deduct_inventory.php."
    },
    {
      "id": "layer:ui",
      "name": "Presentation Shell",
      "description": "The shared chrome included around every routed page - header.php (head, title, stylesheet link), sidebar.php (the $navAdmin/$navStaff/$navClient tables that must stay in sync with the router allowlist), footer.php (chatbot injection and asset loading) and the self-contained SnapBot chat widget in includes/chatbot.php."
    },
    {
      "id": "layer:admin",
      "name": "Admin Portal Pages",
      "description": "Owner-facing router-loaded partials under pages/admin/ covering analytics and read-only oversight: the sales dashboard, 9-type reports module with PDF/CSV export, sales and payment ledgers, client segmentation, loyalty-card review, feedback triage with AI sentiment, package/inventory/staff/settings CRUD, plus the dead DFD.HTML design diagram."
    },
    {
      "id": "layer:staff",
      "name": "Studio Operations Pages",
      "description": "Front-desk router-loaded partials under pages/staff/ that drive the daily session lifecycle: approve/complete/cancel booking transitions, walk-in creation with conflict detection and loyalty enrollment, on-site payment recording, payment proof verification and refunds, inventory CRUD, the schedule board, photo delivery uploads, client directory and notifications."
    },
    {
      "id": "layer:client",
      "name": "Client Portal Pages",
      "description": "Customer-facing router-loaded partials under pages/client/: self-service booking creation with overlap detection, booking list and cancellation, loyalty-aware dashboard, GCash/Maribank payment submission with proof upload, photo gallery, feedback submission, notification inbox and shared profile editing."
    },
    {
      "id": "layer:api",
      "name": "JSON API Endpoints",
      "description": "The pages/api/ endpoints, which unlike pages/<role>/ are standalone scripts that self-require config.php and functions.php and enforce their own session and role checks before emitting JSON: availability slot grids, loyalty lookup, on-site payment recording, client payment actions, inventory usage history, notification polling and the SnapBot AI cascade."
    },
    {
      "id": "layer:assets",
      "name": "Frontend Assets",
      "description": "Client-side presentation and scaffolding: the one live stylesheet assets/css/styles.css that defines the 52-variable soft-gray design system, the live utilities in assets/js/app.js, the never-registered public/service-workers.js, and the unreferenced duplicates root styles.css, assets/images/css/styles.css and root app.js prototype."
    },
    {
      "id": "layer:data",
      "name": "Database Schema & Seed Data",
      "description": "sql/install.sql as a table node plus one node per CREATE TABLE it defines - users, packages, bookings, payments, inventory, feedback, photos, notifications, loyalty_cards, settings and password_resets - forming the single source of truth that ensureDatabaseSchema() in functions.php must be kept in sync with."
    },
    {
      "id": "layer:config",
      "name": "Configuration, Ops Tooling & Docs",
      "description": "Build and environment declarations (.env.example, composer.json, nixpacks.toml), the one-shot database tools that ship unauthenticated and must be removed before production (sql/install.php web installer, change_password.php bulk reset, sql/hash.php), and the agent-facing notes AGENTS.md and GEMINI.md that record the router allowlist and integration contracts."
    }
  ],
  "edges": [
    {
      "source": "config:.env.example",
      "target": "file:config.php",
      "type": "configures"
    },
    {
      "source": "config:composer.json",
      "target": "file:index.php",
      "type": "configures"
    },
    {
      "source": "config:composer.json",
      "target": "file:config.php",
      "type": "configures"
    },
    {
      "source": "config:nixpacks.toml",
      "target": "file:index.php",
      "type": "configures"
    },
    {
      "source": "config:nixpacks.toml",
      "target": "config:composer.json",
      "type": "depends_on"
    },
    {
      "source": "document:AGENTS.md",
      "target": "file:index.php",
      "type": "documents"
    },
    {
      "source": "document:AGENTS.md",
      "target": "file:functions.php",
      "type": "documents"
    },
    {
      "source": "document:AGENTS.md",
      "target": "file:config.php",
      "type": "documents"
    },
    {
      "source": "document:AGENTS.md",
      "target": "file:sql/install.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:index.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:functions.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:config.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:ai_helper.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:includes/chatbot.php",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "file:assets/css/styles.css",
      "type": "documents"
    },
    {
      "source": "document:AGENTS.md",
      "target": "config:.env.example",
      "type": "documents"
    },
    {
      "source": "document:GEMINI.md",
      "target": "config:.env.example",
      "type": "documents"
    },
    {
      "source": "file:styles.css",
      "target": "file:assets/css/styles.css",
      "type": "related"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:initSampleData",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:createWalkinBooking",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:formatTimeAgo",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:exportTableCSV",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:showPage",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:handleLogin",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:handleRegister",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:openPackageModal",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:showBookingStep",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderCalendar",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:handleBookingSubmit",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderClientBookings",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderStaffBookings",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:approveBooking",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:rejectBooking",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:completeBooking",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderClientPayments",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:openPaymentModal",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:togglePaymentAccountInfo",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:copyToClipboard",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:submitPaymentProof",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderStaffPayments",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:filterStaffPayments",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:openVerifyModal",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:toggleRejectSection",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:verifyPaymentNow",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:rejectPaymentWithReason",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:filterAdminPayments",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderAdminPayments",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderInventory",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:updateInventory",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:addInventoryItem",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderAdminAnalytics",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderAdminBookings",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:submitFeedback",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderClientFeedbackHistory",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderFeedbackAnalytics",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:getLoyaltyStatus",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderLoyaltyCard",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderAdminLoyalty",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:showToast",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:previewPhotos",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:handleFileSelect",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderClientPhotos",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderNotifications",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:renderProfile",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:saveProfile",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:confirmVerifyPayment",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:toggleChatbot",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:sendChatMsg",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:getBotResponse",
      "type": "contains"
    },
    {
      "source": "file:app.js",
      "target": "function:app.js:initDraggableChatbot",
      "type": "contains"
    },
    {
      "source": "file:ai_helper.php",
      "target": "function:ai_helper.php:analyzeFeedbackWithAI",
      "type": "contains"
    },
    {
      "source": "file:ai_helper.php",
      "target": "function:ai_helper.php:analyzeFeedbackWithAI",
      "type": "exports"
    },
    {
      "source": "file:ai_helper.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:assets/images/css/styles.css",
      "target": "file:assets/css/styles.css",
      "type": "related"
    },
    {
      "source": "file:assets/js/app.js",
      "target": "function:assets/js/app.js:showToast",
      "type": "contains"
    },
    {
      "source": "file:assets/js/app.js",
      "target": "function:assets/js/app.js:validateForm",
      "type": "contains"
    },
    {
      "source": "file:assets/js/app.js",
      "target": "function:assets/js/app.js:printSection",
      "type": "contains"
    },
    {
      "source": "file:config.php",
      "target": "function:config.php:loadEnv",
      "type": "contains"
    },
    {
      "source": "file:config.php",
      "target": "function:config.php:loadEnv",
      "type": "exports"
    },
    {
      "source": "file:change_password.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:change_password.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:assets/js/app.js",
      "target": "file:assets/css/styles.css",
      "type": "depends_on"
    },
    {
      "source": "file:footer.php",
      "target": "file:includes/chatbot.php",
      "type": "imports"
    },
    {
      "source": "file:footer.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:footer.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:footer.php",
      "target": "file:assets/js/app.js",
      "type": "depends_on"
    },
    {
      "source": "file:functions.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:google-callback.php",
      "target": "file:google-config.php",
      "type": "imports"
    },
    {
      "source": "file:google-callback.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:google-callback.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:google-config.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:google-login.php",
      "target": "file:google-config.php",
      "type": "imports"
    },
    {
      "source": "file:header.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:header.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:includes/booking.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:includes/chatbot.php",
      "target": "file:pages/api/chatbot-ai.php",
      "type": "depends_on"
    },
    {
      "source": "function:includes/booking.php:saveBooking",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:startSession",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:currentUser",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:db",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:ensureDatabaseSchema",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:requireRole",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:redirectToDashboard",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:csrfToken",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:verifyCsrf",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getFlash",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:showFlash",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:timeAgo",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:statusBadge",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:addNotification",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getUnreadCount",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getNotifications",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getNotification",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:markNotificationRead",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:markAllNotificationsRead",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deleteNotification",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deleteReadNotifications",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewBooking",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyPaymentSubmitted",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewFeedback",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyUrgentFeedback",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewClient",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getVapidKeys",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:sendPushNotification",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getLoyaltyRewards",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getLoyaltyProgress",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getClientBookingCount",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:extractHoursFromDuration",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:checkBookingConflict",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:isSlotAvailable",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getBookingStatusBadge",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:paginate",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:outputCSV",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:uploadFile",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getPackageInventory",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deductInventoryOnComplete",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:restoreInventory",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:checkLowStockAndNotify",
      "type": "contains"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:saveBooking",
      "type": "contains"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:saveBookingToFile",
      "type": "contains"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookings",
      "type": "contains"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookingStats",
      "type": "contains"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookingStatsFromFile",
      "type": "contains"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:startSession",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:currentUser",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:db",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:ensureDatabaseSchema",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:requireRole",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:redirectToDashboard",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:csrfToken",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:verifyCsrf",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getFlash",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:showFlash",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:timeAgo",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:statusBadge",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:addNotification",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getUnreadCount",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getNotifications",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getNotification",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:markNotificationRead",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:markAllNotificationsRead",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deleteNotification",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deleteReadNotifications",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewBooking",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyPaymentSubmitted",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewFeedback",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyUrgentFeedback",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:notifyNewClient",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getVapidKeys",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:sendPushNotification",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getLoyaltyRewards",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getLoyaltyProgress",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getClientBookingCount",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:extractHoursFromDuration",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:checkBookingConflict",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:isSlotAvailable",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getBookingStatusBadge",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:paginate",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:outputCSV",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:uploadFile",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:getPackageInventory",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:deductInventoryOnComplete",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:restoreInventory",
      "type": "exports"
    },
    {
      "source": "file:functions.php",
      "target": "function:functions.php:checkLowStockAndNotify",
      "type": "exports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:saveBooking",
      "type": "exports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:saveBookingToFile",
      "type": "exports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookings",
      "type": "exports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookingStats",
      "type": "exports"
    },
    {
      "source": "file:includes/booking.php",
      "target": "function:includes/booking.php:getBookingStatsFromFile",
      "type": "exports"
    },
    {
      "source": "file:includes/deduct_inventory.php",
      "target": "function:includes/deduct_inventory.php:deductInventory",
      "type": "contains"
    },
    {
      "source": "file:includes/deduct_inventory.php",
      "target": "function:includes/deduct_inventory.php:recordReusableItemUsage",
      "type": "contains"
    },
    {
      "source": "file:includes/deduct_inventory.php",
      "target": "function:includes/deduct_inventory.php:deductInventory",
      "type": "exports"
    },
    {
      "source": "file:includes/deduct_inventory.php",
      "target": "function:includes/deduct_inventory.php:recordReusableItemUsage",
      "type": "exports"
    },
    {
      "source": "function:includes/deduct_inventory.php:deductInventory",
      "target": "function:includes/deduct_inventory.php:recordReusableItemUsage",
      "type": "calls"
    },
    {
      "source": "function:includes/deduct_inventory.php:deductInventory",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:index.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:index.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:index.php",
      "target": "file:header.php",
      "type": "imports"
    },
    {
      "source": "file:index.php",
      "target": "file:sidebar.php",
      "type": "imports"
    },
    {
      "source": "file:index.php",
      "target": "file:footer.php",
      "type": "imports"
    },
    {
      "source": "file:landing.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:landing.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:login.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:login.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:login.php",
      "target": "file:send-verification.php",
      "type": "imports"
    },
    {
      "source": "file:login.php",
      "target": "file:assets/css/styles.css",
      "type": "depends_on"
    },
    {
      "source": "file:logout.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "class:mfa-helper.php:MFAHelper",
      "type": "contains"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "class:mfa-helper.php:MFAHelper",
      "type": "exports"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "function:mfa-helper.php:base32Decode",
      "type": "contains"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "function:mfa-helper.php:generateCode",
      "type": "contains"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "function:mfa-helper.php:verifyCode",
      "type": "contains"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "function:mfa-helper.php:getQRCodeDataURI",
      "type": "contains"
    },
    {
      "source": "file:mfa-helper.php",
      "target": "function:mfa-helper.php:verifyBackupCode",
      "type": "contains"
    },
    {
      "source": "function:mfa-helper.php:generateCode",
      "target": "function:mfa-helper.php:base32Decode",
      "type": "calls"
    },
    {
      "source": "function:mfa-helper.php:verifyCode",
      "target": "function:mfa-helper.php:generateCode",
      "type": "calls"
    },
    {
      "source": "file:mfa-setup.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:mfa-setup.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:mfa-setup.php",
      "target": "file:mfa-helper.php",
      "type": "imports"
    },
    {
      "source": "file:mfa-setup.php",
      "target": "class:mfa-helper.php:MFAHelper",
      "type": "depends_on"
    },
    {
      "source": "file:mfa-setup.php",
      "target": "file:assets/css/styles.css",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/DFD.HTML",
      "target": "file:index.php",
      "type": "related"
    },
    {
      "source": "file:pages/admin/bookings.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/bookings.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/clients.php",
      "target": "function:pages/admin/clients.php:getSegmentInfo",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "function:pages/admin/feedback.php:getTopicIcon",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "function:pages/admin/feedback.php:getTopicLabel",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "function:pages/admin/feedback.php:getTopicColor",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "function:pages/admin/feedback.php:analyzeSentimentLocal",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/loyalty-cards.php",
      "target": "function:pages/admin/loyalty-cards.php:getLoyaltyBadge",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/loyalty-cards.php",
      "target": "function:pages/admin/loyalty-cards.php:getProgressToNextTier",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "function:pages/admin/notifications.php:isSafeNotificationLink",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "function:pages/admin/notifications.php:formatNotificationDate",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "function:pages/admin/notifications.php:getNotificationIcon",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "function:pages/admin/notifications.php:getNotificationTypeClass",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/packages.php",
      "target": "function:pages/admin/packages.php:handleImageUpload",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/clients.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/clients.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/dashboard.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/dashboard.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/feedback.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/inventory.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/inventory.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/loyalty-cards.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/loyalty-cards.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/notifications.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/packages.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/packages.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/payments.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/payments.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:pages/admin/reports.php:getReportData",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:pages/admin/reports.php:generateReportPDF",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:pages/admin/reports.php:reportStatusBadge",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:pages/admin/reports.php:reportDate",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:pages/admin/reports.php:validReportDate",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "class:pages/admin/reports.php:Studio94ReportPDF",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/sales.php",
      "target": "function:pages/admin/sales.php:salesStatusClass",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/sales.php",
      "target": "function:pages/admin/sales.php:salesStatusLabel",
      "type": "contains"
    },
    {
      "source": "file:pages/admin/settings.php",
      "target": "function:pages/admin/settings.php:handleQrUpload",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:cleanBotText",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:saveChatLog",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:isAvailabilityQuestion",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:parseRequestedDate",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:parseRequestedTime",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:getBookingsForDate",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:mergeSlots",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:buildAvailabilityResponse",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:answerFAQ",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:pages/api/chatbot-ai.php:callGemini",
      "type": "contains"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/check-client-loyalty.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/check-client-loyalty.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/check_availability.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/check_availability.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/admin/profile.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:functions.php:requireRole",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/reports.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/sales.php",
      "target": "function:functions.php:requireRole",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/sales.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/settings.php",
      "target": "function:functions.php:requireRole",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/settings.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/settings.php",
      "target": "function:functions.php:verifyCsrf",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/staff.php",
      "target": "function:functions.php:requireRole",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/staff.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/admin/staff.php",
      "target": "function:functions.php:verifyCsrf",
      "type": "calls"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/api/chatbot-ai.php",
      "target": "function:functions.php:currentUser",
      "type": "calls"
    },
    {
      "source": "file:pages/api/check-client-loyalty.php",
      "target": "function:functions.php:currentUser",
      "type": "calls"
    },
    {
      "source": "file:pages/api/check-client-loyalty.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/api/check_availability.php",
      "target": "function:functions.php:currentUser",
      "type": "calls"
    },
    {
      "source": "file:pages/api/check_availability.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/api/check_new_notifications.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/check_new_notifications.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/client-payments.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/client-payments.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/inventory-usage.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/inventory-usage.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/record-payment.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:pages/api/record-payment.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/dashboard.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/dashboard.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/dashboard.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/api/record-payment.php",
      "target": "function:functions.php:addNotification",
      "type": "calls"
    },
    {
      "source": "file:pages/api/record-payment.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:pages/client/dashboard.php",
      "target": "function:functions.php:getLoyaltyRewards",
      "type": "calls"
    },
    {
      "source": "file:pages/client/dashboard.php",
      "target": "function:functions.php:getLoyaltyProgress",
      "type": "calls"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:extractHoursFromDuration",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:extractMinutesFromDuration",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:checkBookingConflict",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:getAvailableSlots",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:updateBookedSlotsDisplay",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:checkTimeAvailability",
      "type": "contains"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:renderCalendar",
      "type": "contains"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "function:pages/client/bookings.php:bookingStatusBadge",
      "type": "contains"
    },
    {
      "source": "file:pages/client/bookings.php",
      "target": "function:pages/client/bookings.php:bookingStatusBadge",
      "type": "exports"
    },
    {
      "source": "file:pages/client/booking.php",
      "target": "function:pages/client/booking.php:extractMinutesFromDuration",
      "type": "calls"
    },
    {
      "source": "function:pages/client/booking.php:extractMinutesFromDuration",
      "target": "function:pages/client/booking.php:extractHoursFromDuration",
      "type": "calls"
    },
    {
      "source": "function:pages/client/booking.php:checkBookingConflict",
      "target": "function:pages/client/booking.php:extractMinutesFromDuration",
      "type": "calls"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:analyzeSentimentLocal",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:detectTopicsLocal",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:classifyFeedbackByService",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicIcon",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicLabel",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicColor",
      "type": "contains"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:analyzeSentimentLocal",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:detectTopicsLocal",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:classifyFeedbackByService",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicIcon",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicLabel",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:pages/client/feedback.php:getTopicColor",
      "type": "exports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "file:index.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:ai_helper.php:analyzeFeedbackWithAI",
      "type": "calls"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:functions.php:requireRole",
      "type": "calls"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:functions.php:verifyCsrf",
      "type": "calls"
    },
    {
      "source": "file:pages/client/feedback.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
    },
    {
      "source": "file:pages/client/notifications.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/payments.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/photos.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/profile.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/bookings.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/clients.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/dashboard.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/payments.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/profile.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/client/payments.php",
      "target": "file:pages/api/client-payments.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicIcon",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicLabel",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicColor",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:analyzeSentimentLocal",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentBadge",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentIcon",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentLabel",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentInlineStyle",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:isUrgentFeedback",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicIcon",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicLabel",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getTopicColor",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:analyzeSentimentLocal",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentBadge",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentIcon",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentLabel",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:getSentimentInlineStyle",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "function:pages/staff/feedback.php:isUrgentFeedback",
      "type": "exports"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:pages/staff/walkin.php:parsePackageDurationToMinutes",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:pages/staff/walkin.php:resolveBookingDurationMinutes",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:pages/staff/walkin.php:parseTimeToMinutes",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:pages/staff/walkin.php:calculateWalkinLoyaltyBonus",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:pages/staff/walkin.php:checkWalkinConflict",
      "type": "contains"
    },
    {
      "source": "file:pages/staff/inventory.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/inventory.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/notifications.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/payments.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/payments.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/profile.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/profile.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/schedule.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/upload.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/upload.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "file:pages/api/check-client-loyalty.php",
      "type": "depends_on"
    },
    {
      "source": "file:public/service-workers.js",
      "target": "file:functions.php",
      "type": "related"
    },
    {
      "source": "file:pages/staff/inventory.php",
      "target": "function:functions.php:checkLowStockAndNotify",
      "type": "calls"
    },
    {
      "source": "file:pages/staff/upload.php",
      "target": "function:functions.php:addNotification",
      "type": "calls"
    },
    {
      "source": "file:pages/staff/payments.php",
      "target": "function:functions.php:addNotification",
      "type": "calls"
    },
    {
      "source": "file:pages/staff/walkin.php",
      "target": "function:functions.php:addNotificationByRole",
      "type": "calls"
    },
    {
      "source": "function:pages/staff/walkin.php:checkWalkinConflict",
      "target": "function:functions.php:db",
      "type": "calls"
    },
    {
      "source": "file:send-verification.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:isMailConfigured",
      "type": "contains"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendViaResend",
      "type": "contains"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendViaPhpMailer",
      "type": "contains"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendVerificationEmail",
      "type": "contains"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendPasswordResetEmail",
      "type": "contains"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:isMailConfigured",
      "type": "exports"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendViaResend",
      "type": "exports"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendViaPhpMailer",
      "type": "exports"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendVerificationEmail",
      "type": "exports"
    },
    {
      "source": "file:send-verification.php",
      "target": "function:send-verification.php:sendPasswordResetEmail",
      "type": "exports"
    },
    {
      "source": "file:sidebar.php",
      "target": "file:functions.php",
      "type": "depends_on"
    },
    {
      "source": "file:sidebar.php",
      "target": "file:config.php",
      "type": "depends_on"
    },
    {
      "source": "file:sql/install.php",
      "target": "file:config.php",
      "type": "imports"
    },
    {
      "source": "file:sql/install.php",
      "target": "file:functions.php",
      "type": "imports"
    },
    {
      "source": "file:sql/install.php",
      "target": "table:sql/install.sql",
      "type": "depends_on"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:users",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:packages",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:bookings",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:payments",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:inventory",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:feedback",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:photos",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:notifications",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:loyalty_cards",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:settings",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "table:sql/install.sql:password_resets",
      "type": "defines_schema"
    },
    {
      "source": "table:sql/install.sql",
      "target": "file:functions.php",
      "type": "defines_schema"
    }
  ]
}
``

---

## Project-specific tour guidance

Build a tour that teaches a new developer this codebase in dependency order. The natural progression for this app:

1. **Orientation** -- what SnapTrack is, and the fact that `index.php` is the single router everything flows through.
2. **Kernel** -- `config.php` (hand-rolled `.env` loader, DB constants, APP_URL auto-detection) then `functions.php` (the 1782-line helper kernel: `db()`, auth, CSRF, notifications, inventory, loyalty). Explain that `functions.php` runs `ensureDatabaseSchema()` on the first `db()` call, which ALTERs tables at runtime.
3. **Authentication** -- `login.php`, MFA (`mfa-helper.php`, `mfa-setup.php`), Google OAuth, `send-verification.php`.
4. **The request lifecycle** -- `index.php`'s `` allowlist and how `pages/<role>/<page>.php` partials depend on the router having already loaded config/functions and defined ``/``/``. Contrast with `pages/api/` which IS standalone.
5. **Presentation shell** -- `header.php`, `sidebar.php` (per-role nav arrays), `footer.php`.
6. **Role surfaces** -- admin (analytics/reports/inventory), staff (front-desk bookings/walk-ins/uploads), client (self-service booking/payments/gallery).
7. **API layer** -- `pages/api/` JSON endpoints and how they authenticate.
8. **Data** -- `sql/install.sql` and the 11 tables.
9. **Frontend** -- `assets/css/styles.css` (the only loaded stylesheet) and `assets/js/app.js`.

**Teach the real constraints, not generic advice.** Worth calling out in the tour because they are easy to get wrong:
- Adding a page requires editing THREE places: the `` allowlist and `` in `index.php`, plus the nav arrays in `sidebar.php`. A page missing from the allowlist silently redirects to the dashboard with no visible error.
- `display_errors` is off, so PHP fatals render as a blank page and land in `php-errors.log`.
- Dead/duplicate files exist (root `styles.css`, `assets/images/css/styles.css`, root `app.js`, `includes/deduct_inventory.php`, `pages/admin/DFD.HTML`, 0-byte `pages/api/get-day-bookings.php`) -- noting this saves a future reader real time.
- Known security issues the graph documents: `change_password.php` resets all passwords on an unauthenticated GET; `sql/install.php` is an unauthenticated DB installer; `pages/api/chatbot-ai.php` is unauthenticated with `Access-Control-Allow-Origin: *`.

Aim for 8-11 steps. Each step needs `order`, `title`, `description`, `nodeIds`. Use only node ids that exist in the provided input.