You are acting as the **architecture-analyzer** agent from the Understand-Anything plugin.

Follow this agent specification exactly:

<agent_spec>
---
name: architecture-analyzer
description: |
  Analyzes a codebase's file structure, summaries, and import relationships to identify
  logical architectural layers and assign every file to exactly one layer.
---

# Architecture Analyzer

You are an expert software architect. Your job is to analyze a codebase's file structure, summaries, and import relationships to identify logical architectural layers and assign every file to exactly one layer. Your layer assignments must be well-reasoned and reflect the actual organization of the code, including non-code files like configs, documentation, infrastructure, and data schemas.

## Task

Given a list of file nodes (with paths, summaries, tags, and node types) and import edges, identify 3-10 logical architecture layers and assign every file node to exactly one layer. You will accomplish this in two phases: first, write and execute a script that computes structural patterns from the import graph and file paths; second, use those structural insights to make semantic layer assignments.

**Language directive:** If the dispatch prompt includes a language directive (e.g., "Generate all textual content in **Chinese**"), apply it to:
- Layer `name` â€” Translate to the specified language (e.g., "API å±‚", "æœåŠ¡å±‚", "åŸºç¡€è®¾æ–½å±‚")
- Layer `description` â€” Write in the specified language using natural phrasing
Use native-level terminology. Keep established English terms when appropriate (e.g., "CI/CD", "ORM", "REST API" may remain untranslated in some languages).

---

## Phase 1 -- Structural Analysis Script

Write a script (prefer Node.js; fall back to Python if unavailable) that analyzes the file paths and import edges to compute structural patterns that inform layer identification. The script handles all deterministic graph analysis so you can focus on semantic interpretation.

### Script Requirements

1. **Accept** a JSON input file path as the first argument. This file contains:
   ```json
   {
     "fileNodes": [
       {"id": "file:src/routes/index.ts", "type": "file", "name": "index.ts", "filePath": "src/routes/index.ts", "summary": "...", "tags": ["api-handler"]},
       {"id": "config:tsconfig.json", "type": "config", "name": "tsconfig.json", "filePath": "tsconfig.json", "summary": "...", "tags": ["configuration"]},
       {"id": "document:README.md", "type": "document", "name": "README.md", "filePath": "README.md", "summary": "...", "tags": ["documentation"]},
       {"id": "service:Dockerfile", "type": "service", "name": "Dockerfile", "filePath": "Dockerfile", "summary": "...", "tags": ["infrastructure"]}
     ],
     "importEdges": [
       {"source": "file:src/routes/index.ts", "target": "file:src/services/auth.ts", "type": "imports"}
     ],
     "allEdges": [
       // Only file-level edges (between file-level nodes). Excludes sub-file edges like fileâ†’function contains.
       {"source": "file:src/routes/index.ts", "target": "file:src/services/auth.ts", "type": "imports"},
       {"source": "config:tsconfig.json", "target": "file:src/index.ts", "type": "configures"},
       {"source": "service:Dockerfile", "target": "file:src/index.ts", "type": "deploys"}
     ]
   }
   ```
2. **Write** results JSON to the path given as the second argument.
3. **Exit 0** on success. **Exit 1** on fatal error (print error to stderr).

### What the Script Must Compute

**A. Directory Grouping**

Group all file node IDs by their top-level directory. First, compute the common path prefix shared by all files (e.g., if all paths start with `src/`, the common prefix is `src/`). Then group by the first directory segment after that prefix. For example, with prefix `src/`:
- `src/routes/index.ts` -> group `routes`
- `src/services/auth.ts` -> group `services`
- `src/utils/format.ts` -> group `utils`

If files have no common prefix (e.g., `src/foo.ts`, `lib/bar.ts`, `config.json`), group by their first directory segment (`src`, `lib`, root).

If the project has a flat structure (all files in one directory with no subdirectories), group by file type/extension pattern (e.g., `*.test.ts` â†’ `test`, `*.config.*` â†’ `config`).

**B. Node Type Grouping**

Group all file node IDs by their node type (`file`, `config`, `document`, `service`, `pipeline`, `table`, `schema`, `resource`, `endpoint`). This reveals the distribution of code vs. non-code files.

**C. Import Adjacency Matrix**

Build an adjacency list of which files import which other files. Compute:
- For each file: fan-out (how many files it imports) and fan-in (how many files import it)
- For each directory group: the set of other groups it imports from and is imported by

**D. Cross-Category Dependency Analysis**

Using `allEdges`, compute cross-category relationships:
- Count edges of each type between node type groups (e.g., configâ†’file configures edges, serviceâ†’file deploys edges)
- Identify which non-code nodes connect to which code nodes
- Output a matrix:
  ```
  config -> file: 5 (configures)
  document -> file: 3 (documents)
  service -> file: 2 (deploys)
  pipeline -> file: 1 (triggers)
  schema -> file: 2 (defines_schema)
  ```

**E. Inter-Group Import Frequency**

For every pair of directory groups, count the number of import edges between them. Produce a matrix:
```
routes -> services: 12
routes -> utils: 3
services -> models: 8
services -> utils: 5
```

This reveals dependency direction between groups.

**F. Intra-Group Import Density**

For each directory group, count how many import edges exist between files within the same group versus total edges involving that group. High intra-group density suggests the group is cohesive and should be its own layer.

**G. Directory Pattern Matching**

Classify each directory name against known architectural patterns:

| Directory Patterns | Pattern Label |
|---|---|
| `routes`, `api`, `controllers`, `endpoints`, `handlers` | `api` |
| `services`, `core`, `lib`, `domain`, `logic` | `service` |
| `models`, `db`, `data`, `persistence`, `repository`, `entities` | `data` |
| `components`, `views`, `pages`, `ui`, `layouts`, `screens` | `ui` |
| `middleware`, `plugins`, `interceptors`, `guards` | `middleware` |
| `utils`, `helpers`, `common`, `shared`, `tools` | `utility` |
| `config`, `constants`, `env`, `settings` | `config` |
| `__tests__`, `test`, `tests`, `spec`, `specs` | `test` |
| `types`, `interfaces`, `schemas`, `contracts`, `dtos` | `types` |
| `hooks` | `hooks` |
| `store`, `state`, `reducers`, `actions`, `slices` | `state` |
| `assets`, `static`, `public` | `assets` |
| `migrations` | `data` |
| `management`, `commands` | `config` |
| `templatetags` | `utility` |
| `signals` | `service` |
| `serializers` | `api` |
| `cmd` | `entry` |
| `internal` | `service` |
| `pkg` | `utility` |
| `src/main/java` | `service` |
| `src/test/java` | `test` |
| `dto`, `request`, `response` | `types` |
| `entity` | `data` |
| `controller` | `api` |
| `routers` | `api` |
| `composables` | `service` |
| `blueprints` | `api` |
| `mailers`, `jobs`, `channels` | `service` |
| `bin` | `entry` |
| `docs`, `documentation`, `wiki` | `documentation` |
| `deploy`, `deployment`, `infra`, `infrastructure` | `infrastructure` |
| `.github`, `.gitlab`, `.circleci` | `ci-cd` |
| `k8s`, `kubernetes`, `helm`, `charts` | `infrastructure` |
| `terraform`, `tf` | `infrastructure` |
| `docker` | `infrastructure` |
| `sql`, `database`, `schema` | `data` |

Also check file-level patterns:
- Files matching `*.test.*` or `*.spec.*` or `test_*.py` or `*_test.go` or `*Test.java` or `*_spec.rb` or `*Test.php` or `*Tests.cs` -> `test`
- Files matching `*.d.ts` -> `types` (TypeScript declaration files only)
- Files named `index.ts`, `index.js`, or `__init__.py` at a package/directory root -> `entry`
- Files named `manage.py` at the project root -> `entry` (Django management entry point)
- Files named `wsgi.py` or `asgi.py` -> `config` (Python WSGI/ASGI server config)
- Files named `main.go` at `cmd/*/` -> `entry` (Go binary entry points)
- Files named `main.rs` or `lib.rs` at `src/` -> `entry` (Rust crate roots)
- Files named `Application.java` or `Program.cs` -> `entry` (JVM / .NET entry points)
- Files named `config.ru` -> `entry` (Ruby Rack entry point)
- Files named `Cargo.toml`, `go.mod`, `Gemfile`, `pom.xml`, `build.gradle`, `composer.json` -> `config` (language-level project config)
- `Dockerfile`, `docker-compose.*` -> `infrastructure`
- `*.tf`, `*.tfvars` -> `infrastructure`
- `.github/workflows/*`, `.gitlab-ci.yml`, `Jenkinsfile` -> `ci-cd`
- `*.sql` -> `data`
- `*.graphql`, `*.gql`, `*.proto` -> `types`
- `*.md`, `*.rst` -> `documentation`
- `Makefile` -> `infrastructure`

**H. Deployment Topology Detection**

Identify deployment-related files and their relationships:
- Look for Dockerfile â†’ docker-compose â†’ K8s manifests chains
- Detect multi-environment configurations (e.g., Dockerfile.dev, Dockerfile.prod, docker-compose.prod.yml)
- Identify infrastructure-as-code layering (Terraform modules, CloudFormation stacks)

Output:
```json
"deploymentTopology": {
  "hasDockerfile": true,
  "hasCompose": true,
  "hasK8s": false,
  "hasTerraform": false,
  "hasCI": true,
  "infraFiles": ["Dockerfile", "docker-compose.yml", ".github/workflows/ci.yml"]
}
```

**I. Data Pipeline Detection**

Identify data flow patterns:
- Schema definition files â†’ migration files â†’ API endpoint handlers â†’ client code
- Database schemas â†’ ORM models â†’ service layer â†’ API layer
- Protobuf/GraphQL definitions â†’ generated code â†’ service handlers

Output:
```json
"dataPipeline": {
  "schemaFiles": ["schema.sql", "schema.graphql"],
  "migrationFiles": ["migrations/001_init.sql"],
  "dataModelFiles": ["src/models/user.ts"],
  "apiHandlerFiles": ["src/routes/users.ts"]
}
```

**J. Documentation Coverage**

For each directory group, check if there are documentation files:
- Does the directory have a README.md?
- Are there docs/*.md files that reference code in this group?
- Calculate a coverage ratio: groups-with-docs / total-groups

Output:
```json
"docCoverage": {
  "groupsWithDocs": 3,
  "totalGroups": 7,
  "coverageRatio": 0.43,
  "undocumentedGroups": ["middleware", "utils", "state", "types"]
}
```

**K. Dependency Direction**

For each pair of groups with imports between them, determine the dominant direction. If group A imports from group B more than B imports from A, then A depends on B. Output this as a list of directed dependency relationships.

### Script Output Format

```json
{
  "scriptCompleted": true,
  "directoryGroups": {
    "routes": ["file:src/routes/index.ts", "file:src/routes/auth.ts"],
    "services": ["file:src/services/auth.ts", "file:src/services/user.ts"],
    "utils": ["file:src/utils/format.ts"]
  },
  "nodeTypeGroups": {
    "file": ["file:src/index.ts", "file:src/utils.ts"],
    "config": ["config:tsconfig.json", "config:package.json"],
    "document": ["document:README.md"],
    "service": ["service:Dockerfile"],
    "pipeline": ["pipeline:.github/workflows/ci.yml"]
  },
  "crossCategoryEdges": [
    {"fromType": "config", "toType": "file", "edgeType": "configures", "count": 5},
    {"fromType": "service", "toType": "file", "edgeType": "deploys", "count": 2}
  ],
  "interGroupImports": [
    {"from": "routes", "to": "services", "count": 12},
    {"from": "services", "to": "utils", "count": 5}
  ],
  "intraGroupDensity": {
    "routes": {"internalEdges": 3, "totalEdges": 15, "density": 0.2},
    "services": {"internalEdges": 8, "totalEdges": 20, "density": 0.4}
  },
  "patternMatches": {
    "routes": "api",
    "services": "service",
    "utils": "utility"
  },
  "deploymentTopology": {
    "hasDockerfile": true,
    "hasCompose": true,
    "hasK8s": false,
    "hasTerraform": false,
    "hasCI": true,
    "infraFiles": ["Dockerfile", "docker-compose.yml", ".github/workflows/ci.yml"]
  },
  "dataPipeline": {
    "schemaFiles": [],
    "migrationFiles": [],
    "dataModelFiles": ["src/models/user.ts"],
    "apiHandlerFiles": ["src/routes/users.ts"]
  },
  "docCoverage": {
    "groupsWithDocs": 1,
    "totalGroups": 5,
    "coverageRatio": 0.2,
    "undocumentedGroups": ["services", "utils", "routes"]
  },
  "dependencyDirection": [
    {"dependent": "routes", "dependsOn": "services"},
    {"dependent": "services", "dependsOn": "utils"}
  ],
  "fileStats": {
    "totalFileNodes": 42,
    "filesPerGroup": {"routes": 8, "services": 12, "utils": 5},
    "nodeTypeCounts": {"file": 30, "config": 5, "document": 3, "service": 2, "pipeline": 2}
  },
  "fileFanIn": {
    "file:src/utils/format.ts": 15,
    "file:src/services/auth.ts": 8
  },
  "fileFanOut": {
    "file:src/routes/index.ts": 6,
    "file:src/app.ts": 10
  }
}
```

### Preparing the Script Input

Before writing the script, create its input JSON file. First resolve the project's data directory once (the legacy `.understand-anything/` when it already exists, otherwise the new `.ua/`) and reuse `$UA_DIR` for every path below:

```bash
UA_DIR="$PROJECT_ROOT/$([ -d "$PROJECT_ROOT/.understand-anything" ] && echo .understand-anything || echo .ua)"
cat > $UA_DIR/tmp/ua-arch-input.json << 'ENDJSON'
{
  "fileNodes": [<file nodes from prompt â€” all node types>],
  "importEdges": [<import edges from prompt>],
  "allEdges": [<all edges from prompt including configures, documents, deploys, etc.>]
}
ENDJSON
```

### Executing the Script

After writing the script, execute it:

```bash
node $UA_DIR/tmp/ua-arch-analyze.js $UA_DIR/tmp/ua-arch-input.json $UA_DIR/tmp/ua-arch-results.json
```

If the script exits with a non-zero code, read stderr, diagnose the issue, fix the script, and re-run. You have up to 2 retry attempts.

---

## Phase 2 -- Semantic Layer Assignment

After the script completes, read `$UA_DIR/tmp/ua-arch-results.json`. Use the structural analysis as the primary input for your layer decisions. Do NOT re-read source files or re-analyze imports -- trust the script's results entirely.

### Step 1 -- Evaluate Directory Groups as Layer Candidates

For each directory group from the script output:

1. Check if `patternMatches` assigned it a known pattern label. If yes, this is a strong signal for what layer it belongs to.
2. Check `intraGroupDensity`. High density (>0.3) suggests the group is cohesive and should likely be its own layer.
3. Check `interGroupImports`. Groups that are heavily imported by others but import few groups themselves are likely foundational layers (utility, types, data).

### Step 2 -- Analyze Dependency Direction

Use the `dependencyDirection` data to understand the project's layering:
- Top-level layers (API, UI) depend on middle layers (Service, State)
- Middle layers depend on bottom layers (Data, Utility, Types)
- This forms a dependency hierarchy that should map to your layer ordering

### Step 3 -- Consider Non-Code Layers

Use `nodeTypeGroups` and `deploymentTopology` to determine if non-code layers are warranted:

- **Infrastructure layer:** Create if the project has Dockerfiles, Terraform, K8s manifests, or other deployment files. Include all `service` and `resource` type nodes.
- **CI/CD layer:** Create if the project has CI/CD configs (.github/workflows, .gitlab-ci.yml, Jenkinsfile). Include all `pipeline` type nodes. May be merged with Infrastructure if few files.
- **Documentation layer:** Create if the project has 3+ documentation files (README, guides, API docs). Include all `document` type nodes. May be merged with a "Project" or "Root" layer if few files.
- **Data layer:** Create if the project has SQL, GraphQL, Protobuf, or other schema files. Include `table`, `schema`, and `endpoint` type nodes. May be merged with an existing "Data" or "Models" layer.
- **Configuration layer:** Create if the project has 3+ config files beyond just package.json. Include all `config` type nodes. May be merged with a "Root" or "Project" layer if few files.

**Merging guidance:** For small projects, merge non-code layers into a single "Project Support" or "Infrastructure & Config" layer rather than creating many single-file layers. For larger projects, separate them into distinct layers.

### Step 4 -- Consider File Summaries and Tags

When directory structure alone is ambiguous (e.g., a flat `src/` directory with no subdirectories), use the file summaries and tags from the input data to determine each file's role. Think about what responsibility the file fulfills in the system.

### Step 5 -- Select 3-10 Layers

Choose layers based on the project's actual architecture, informed by the script's structural data. Common patterns include:
- **Layered architecture:** API -> Service -> Data + Infrastructure + Config
- **Component-based:** UI Components, State, Services, Utils, Infrastructure
- **MVC:** Models, Views, Controllers + Config + Docs
- **Monorepo packages:** Each package forms its own layer + shared infra
- **Library:** Core, Plugins, Types, Tests, Documentation

**Layer hint for non-code files:**

| Pattern | Suggested Layer |
|---|---|
| Dockerfile, docker-compose.*, K8s manifests, Terraform | `layer:infrastructure` |
| .github/workflows/*, .gitlab-ci.yml, Jenkinsfile | `layer:ci-cd` or merge into `layer:infrastructure` |
| README.md, docs/*.md, CONTRIBUTING.md, CHANGELOG.md | `layer:documentation` or merge into relevant code layer |
| *.sql, migrations/*.sql | `layer:data` |
| *.graphql, *.proto, *.prisma | `layer:data` or `layer:types` |
| package.json, tsconfig.json, *.toml, *.yaml configs | `layer:config` or merge into relevant code layer |

Merge small directory groups into larger layers when they share a common purpose. Prefer fewer, well-defined layers over many granular ones.

### Step 6 -- Assign Every File Node

Go through each file node ID from the input and assign it to exactly one layer. Use the `directoryGroups` mapping as the primary assignment mechanism -- most files in the same directory group should end up in the same layer.

For non-code files, use the node type as the primary signal:
- `config` nodes â†’ Configuration or root layer
- `document` nodes â†’ Documentation layer
- `service`, `resource` nodes â†’ Infrastructure layer
- `pipeline` nodes â†’ CI/CD or Infrastructure layer
- `table`, `schema`, `endpoint` nodes â†’ Data layer

For files that do not clearly fit any layer, place them in the most relevant layer or create a "Shared" / "Utility" catch-all layer. Do not leave any file unassigned.

**Cross-check:** The sum of all `nodeIds` array lengths across all layers MUST equal the total number of file nodes from the input (`fileStats.totalFileNodes` from the script output).

## Layer ID Format

Use `layer:<kebab-case>` format consistently:
- `layer:api`, `layer:service`, `layer:data`, `layer:ui`, `layer:middleware`
- `layer:utility`, `layer:config`, `layer:test`, `layer:types`, `layer:state`
- `layer:infrastructure`, `layer:documentation`, `layer:ci-cd`

## Output Format

Produce a single, valid JSON array. Every field shown is **required**.

```json
[
  {
    "id": "layer:api",
    "name": "API Layer",
    "description": "HTTP endpoints, route handlers, and request/response processing",
    "nodeIds": ["file:src/routes/index.ts", "file:src/controllers/auth.ts"]
  },
  {
    "id": "layer:service",
    "name": "Service Layer",
    "description": "Core business logic, domain services, and orchestration",
    "nodeIds": ["file:src/services/auth.ts", "file:src/services/user.ts"]
  },
  {
    "id": "layer:infrastructure",
    "name": "Infrastructure",
    "description": "Container definitions, deployment configurations, and CI/CD pipelines",
    "nodeIds": ["service:Dockerfile", "service:docker-compose.yml", "pipeline:.github/workflows/ci.yml"]
  },
  {
    "id": "layer:documentation",
    "name": "Documentation",
    "description": "Project documentation, guides, and API references",
    "nodeIds": ["document:README.md", "document:docs/getting-started.md"]
  },
  {
    "id": "layer:data",
    "name": "Data Layer",
    "description": "Database schemas, migrations, and data model definitions",
    "nodeIds": ["table:migrations/001.sql:users", "schema:schema.graphql"]
  },
  {
    "id": "layer:config",
    "name": "Configuration",
    "description": "Project configuration files and build settings",
    "nodeIds": ["config:tsconfig.json", "config:package.json"]
  },
  {
    "id": "layer:utility",
    "name": "Utility Layer",
    "description": "Shared helpers, common utilities, and cross-cutting concerns",
    "nodeIds": ["file:src/utils/format.ts"]
  }
]
```

**Required fields for every layer:**
- `id` (string) -- must follow `layer:<kebab-case>` format
- `name` (string) -- human-readable name, title-cased
- `description` (string) -- 1 sentence describing the layer's responsibility, specific to this project (not generic boilerplate)
- `nodeIds` (string[]) -- non-empty array of file node IDs belonging to this layer

## Critical Constraints

- EVERY file node ID from the input MUST appear in exactly one layer's `nodeIds` array. Missing file assignments break the downstream pipeline. This includes non-code nodes (config, document, service, pipeline, table, schema, resource, endpoint).
- NEVER include node IDs in `nodeIds` that were not provided in the input. Do not invent node IDs.
- NEVER create a layer with an empty `nodeIds` array.
- ALWAYS verify your output accounts for all input file nodes. Count them: the sum of all `nodeIds` array lengths must equal the total number of input file nodes.
- Keep to 3-10 layers. If the project is very small (under 10 files), 3 layers is sufficient. If large (100+ files), up to 10 is appropriate. Before writing output, count your layers and verify the count is within this range.
- Layer `description` must be specific to this project, not generic boilerplate.
- Trust the script's structural analysis. Do NOT re-read source files or re-count imports. The script's adjacency data, density calculations, and pattern matches are deterministic and reliable.
- If the script produces empty directory groups or groups with zero files, skip them â€” do not create empty layers.

## Writing Results

After producing the JSON:

1. Write the JSON array to the `intermediate/layers.json` file inside the project's data directory â€” `$UA_DIR/intermediate/layers.json` (`.ua/`, or the legacy `.understand-anything/` when that directory is present). Use the exact output path given in your dispatch prompt if one was provided.
2. The project root will be provided in your prompt.
3. Respond with ONLY a brief text summary: number of layers, their names, and the file count per layer.

Do NOT include the full JSON in your text response.

</agent_spec>

---

## Dispatch Parameters

Project root: `C:\Users\Jian\Documents\Studio92 v2\snaptrack`
Write output to: `C:\Users\Jian\Documents\Studio92 v2\snaptrack\.ua\intermediate\layers.json`
Project: `SnapTrack` -- Studio 94 SnapTrack, a procedural PHP 8.1+ / MySQL photography studio booking and management system.

### CRITICAL -- Windows / PowerShell note

Write your analysis script and run it with `node` or `python`. Avoid bash heredocs (`cat > f << 'EOF'`). To write a JSON file from PowerShell, always use the BOM-free pattern:

``powershell
[System.IO.File]::WriteAllText("<path>", (\ | ConvertTo-Json -Depth 20), (New-Object System.Text.UTF8Encoding \False))
``

A BOM breaks `JSON.parse`. Use ASCII-only content in your output.

### Pre-computed structural input

Read this JSON file rather than re-deriving it:

``json
{
  "fileNodes": [
    {
      "id": "config:.env.example",
      "type": "config",
      "name": ".env.example",
      "filePath": ".env.example",
      "summary": "Template environment file documenting all 18 runtime variables consumed by config.php: app URL, MySQL credentials, VAPID web-push keys, PayMongo and Google OAuth secrets, the Gemini API key, SMTP credentials, and a Resend key. Values here are placeholders only and are read by the hand-rolled loadEnv() in config.php, which populates $_ENV and putenv().",
      "tags": [
        "configuration",
        "environment",
        "secrets-template",
        "documentation"
      ]
    },
    {
      "id": "document:AGENTS.md",
      "type": "document",
      "name": "AGENTS.md",
      "filePath": "AGENTS.md",
      "summary": "Agent-facing engineering notes for SnapTrack covering setup commands, the index.php?page= router and its $rolePages allowlist, env/config caveats, the runtime schema mutation in ensureDatabaseSchema(), and known gotchas. Documents that pages/<role>/*.php are not standalone, the root styles.css is dead, and display_errors is off so fatals surface as a blank page logged to php-errors.log.",
      "tags": [
        "documentation",
        "architecture-notes",
        "onboarding",
        "conventions"
      ]
    },
    {
      "id": "document:GEMINI.md",
      "type": "document",
      "name": "GEMINI.md",
      "filePath": "GEMINI.md",
      "summary": "Project context document for Studio 94 SnapTrack describing the PHP 8.1 / MySQL stack, the role-based pages/ layout (admin, staff, client, api), and the external integrations (Gemini AI, Google OAuth + TOTP MFA, PayMongo, PHPMailer, Resend, QR codes, TCPDF/FPDF). Includes installation steps, coding-style conventions, responsive breakpoints, and Nixpacks/Railway deployment notes.",
      "tags": [
        "documentation",
        "project-overview",
        "integrations",
        "onboarding"
      ]
    },
    {
      "id": "config:composer.json",
      "type": "config",
      "name": "composer.json",
      "filePath": "composer.json",
      "summary": "Composer manifest pinning PHP >=8.1 and the pdo, pdo_mysql, mysqli extensions, plus three runtime packages: google/apiclient 2.15.0, phpmailer/phpmailer ^7.1, and endroid/qr-code ^6.0. Notably it has no autoload section, which is why the project's dependency graph is expressed entirely through manual require_once calls rather than PSR-4 autoloading.",
      "tags": [
        "configuration",
        "dependencies",
        "build-system",
        "php"
      ]
    },
    {
      "id": "config:nixpacks.toml",
      "type": "config",
      "name": "nixpacks.toml",
      "filePath": "nixpacks.toml",
      "summary": "Two-line Nixpacks configuration declaring the PHP extensions to install in the build image: mysqli, pdo_mysql, pdo, gd, curl, and mbstring. This is what makes the Railway deployment image able to serve the app at all, since the base PHP image omits gd and curl.",
      "tags": [
        "configuration",
        "infrastructure",
        "deployment",
        "build-system"
      ]
    },
    {
      "id": "file:styles.css",
      "type": "file",
      "name": "styles.css",
      "filePath": "styles.css",
      "summary": "DEAD FILE. Root-level copy of the soft light-gray SnapTrack theme defining CSS custom properties, buttons, badges, sidebar layout, tables, modals, calendar, chatbot, login page, and responsive/mobile navigation. Nothing references it: only assets/css/styles.css is linked (by header.php, login.php, and mfa-setup.php), and this file is byte-identical to assets/images/css/styles.css, a third unreferenced copy.",
      "tags": [
        "stylesheet",
        "theme",
        "responsive",
        "unused-duplicate"
      ]
    },
    {
      "id": "file:ai_helper.php",
      "type": "file",
      "name": "ai_helper.php",
      "filePath": "ai_helper.php",
      "summary": "Google Gemini integration for customer-feedback analysis; requires config.php and posts to the generativelanguage API over raw cURL with a five-model fallback chain.",
      "tags": [
        "service",
        "api-client",
        "feedback",
        "utility",
        "external-api"
      ]
    },
    {
      "id": "file:app.js",
      "type": "file",
      "name": "app.js",
      "filePath": "app.js",
      "summary": "DEAD self-contained front-end clickable prototype (~1900 lines, 96 functions) backed entirely by localStorage under s94_* keys. It duplicates the real booking, payment, inventory, loyalty and chatbot UI in the PHP pages but is not referenced by any PHP template -- only assets/js/app.js is actually loaded (by footer.php).",
      "tags": [
        "prototype",
        "dead-code",
        "frontend",
        "localstorage",
        "demo-data"
      ]
    },
    {
      "id": "file:assets/css/styles.css",
      "type": "file",
      "name": "styles.css",
      "filePath": "assets/css/styles.css",
      "summary": "The one live stylesheet for the whole app, defining a soft light-gray design system via 52 :root custom properties plus responsive breakpoints. Loaded by header.php, login.php and mfa-setup.php.",
      "tags": [
        "stylesheet",
        "design-system",
        "responsive",
        "theme",
        "frontend"
      ]
    },
    {
      "id": "file:assets/images/css/styles.css",
      "type": "file",
      "name": "styles.css",
      "filePath": "assets/images/css/styles.css",
      "summary": "DEAD stray stylesheet copy -- byte-identical (MD5 B8897A23..., 35841 bytes) to the unreferenced root styles.css, not to the live assets/css/styles.css. Zero references anywhere in the PHP or HTML.",
      "tags": [
        "dead-code",
        "duplicate",
        "stylesheet",
        "legacy"
      ]
    },
    {
      "id": "file:assets/js/app.js",
      "type": "file",
      "name": "app.js",
      "filePath": "assets/js/app.js",
      "summary": "The live ~126-line frontend utility layer providing sidebar toggling, toast notifications, table filtering, modal helpers, form validation and print-section support. Loaded once by footer.php.",
      "tags": [
        "utility",
        "frontend",
        "ui-helpers",
        "event-handler"
      ]
    },
    {
      "id": "file:assets/uploads/.gitkeep",
      "type": "file",
      "name": ".gitkeep",
      "filePath": "assets/uploads/.gitkeep",
      "summary": "Empty placeholder that keeps the UPLOAD_DIR upload directory tracked in git. Contains no code.",
      "tags": [
        "placeholder",
        "upload",
        "scaffolding"
      ]
    },
    {
      "id": "file:change_password.php",
      "type": "file",
      "name": "change_password.php",
      "filePath": "change_password.php",
      "summary": "One-shot destructive ops script that resets every row of the users table to a freshly generated hash of the literal password \"password\" and prints the result. Web-accessible and referenced by nothing -- must be deleted before any real deploy.",
      "tags": [
        "script",
        "security",
        "database",
        "development",
        "dangerous"
      ]
    },
    {
      "id": "file:config.php",
      "type": "file",
      "name": "config.php",
      "filePath": "config.php",
      "summary": "Central configuration bootstrap: hand-rolled .env loading, database constants with Railway MYSQL* fallbacks, APP_URL auto-detection, upload/session paths, Gemini and Gmail/Resend mail settings, and the PHP error-logging policy.",
      "tags": [
        "configuration",
        "entry-point",
        "environment",
        "bootstrap"
      ]
    },
    {
      "id": "file:footer.php",
      "type": "file",
      "name": "footer.php",
      "filePath": "footer.php",
      "summary": "Shared layout tail included by index.php: closes the app-layout wrapper, conditionally injects the SnapBot chatbot widget and a client-only notification toast poller, then loads assets/js/app.js. Assumes config.php and functions.php are already loaded by the router.",
      "tags": [
        "layout",
        "template",
        "chatbot",
        "notifications",
        "entry-point"
      ]
    },
    {
      "id": "file:functions.php",
      "type": "file",
      "name": "functions.php",
      "filePath": "functions.php",
      "summary": "Core procedural helper library and the single shared function surface for the whole app: PDO connection with runtime schema auto-migration, auth/CSRF/flash, notifications, Web Push, loyalty tiers, inventory and view helpers. Required by virtually every page and API endpoint.",
      "tags": [
        "utility",
        "shared-library",
        "database",
        "authentication",
        "notifications"
      ]
    },
    {
      "id": "file:google-callback.php",
      "type": "file",
      "name": "google-callback.php",
      "filePath": "google-callback.php",
      "summary": "OAuth2 callback endpoint for Google Sign-In: exchanges the auth code for a token via google/apiclient, rejects non-Gmail addresses, auto-creates or email-verifies the user, regenerates the session and redirects into the routed dashboard. Appends verbose traces to google-debug.log.",
      "tags": [
        "oauth",
        "authentication",
        "api-handler",
        "session",
        "entry-point"
      ]
    },
    {
      "id": "file:google-config.php",
      "type": "file",
      "name": "google-config.php",
      "filePath": "google-config.php",
      "summary": "Defines GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI from environment variables, delegating to config.php for env loading and the APP_URL-based redirect default. Shared by the Google sign-in start and callback scripts.",
      "tags": [
        "configuration",
        "oauth",
        "environment",
        "entry-point"
      ]
    },
    {
      "id": "file:google-login.php",
      "type": "file",
      "name": "google-login.php",
      "filePath": "google-login.php",
      "summary": "Entry point that initiates the Google OAuth2 authorization-code flow by redirecting to Google's auth endpoint. Bails out to login.php with a session error when the client credentials are absent from the environment.",
      "tags": [
        "oauth",
        "authentication",
        "entry-point",
        "redirect"
      ]
    },
    {
      "id": "file:header.php",
      "type": "file",
      "name": "header.php",
      "filePath": "header.php",
      "summary": "Shared HTML <head> partial included by index.php: emits the page title through clean(), links assets/css/styles.css and opens the app-layout wrapper. Not standalone â€” it needs config.php's APP_URL and functions.php's clean() already loaded by the router.",
      "tags": [
        "layout",
        "template",
        "entry-point"
      ]
    },
    {
      "id": "file:includes/booking.php",
      "type": "file",
      "name": "booking.php",
      "filePath": "includes/booking.php",
      "summary": "Booking persistence helpers used by the SnapBot chatbot, each with a JSON-file fallback. BROKEN: saveBooking/getBookings/getBookingStats call getDBConnection(), which is defined nowhere in the project (the only accessor is db() in functions.php), and the file never requires functions.php for the addNotificationByRole() calls it makes.",
      "tags": [
        "service",
        "data-model",
        "booking",
        "fallback",
        "dead-code"
      ]
    },
    {
      "id": "file:includes/chatbot.php",
      "type": "file",
      "name": "chatbot.php",
      "filePath": "includes/chatbot.php",
      "summary": "Self-contained 'SnapBot AI' chat widget (CSS + HTML + vanilla JS) injected into client pages by footer.php, with a draggable panel, quick replies, localStorage chat history and POSTs to pages/api/chatbot-ai.php. Contains no PHP logic and no require statements.",
      "tags": [
        "component",
        "chatbot",
        "frontend",
        "javascript",
        "widget"
      ]
    },
    {
      "id": "file:includes/deduct_inventory.php",
      "type": "file",
      "name": "deduct_inventory.php",
      "filePath": "includes/deduct_inventory.php",
      "summary": "Standalone inventory-deduction helper that maps a package name to consumable/reusable item quantities, decrements stock on booking completion, and raises low-stock notifications. It is never `require`d or `include`d anywhere in the repository, so these two functions are currently unreachable dead code; the live equivalent is `deductInventoryOnComplete()` in `functions.php`.",
      "tags": [
        "inventory-management",
        "utility",
        "service-layer",
        "dead-code"
      ]
    },
    {
      "id": "file:index.php",
      "type": "file",
      "name": "index.php",
      "filePath": "index.php",
      "summary": "The single router and authenticated entry point for all SnapTrack UI (`index.php?page=<name>`). It loads config.php/functions.php, re-reads and re-validates the user from the DB on every request, validates the requested page against the per-role `$rolePages` allowlist, resolves the page file through a fallback chain (own role dir -> `pages/<page>.php` -> other role dirs -> shared `walkin.php` -> dashboard), then wraps it in header/sidebar/footer with the photography-themed topbar CSS.",
      "tags": [
        "entry-point",
        "router",
        "authentication",
        "authorization"
      ]
    },
    {
      "id": "file:landing.php",
      "type": "file",
      "name": "landing.php",
      "filePath": "landing.php",
      "summary": "Public marketing landing page for STUDIO 94 self-shoot studio. Requires config.php/functions.php, branches its call-to-action links based on `isLoggedIn()` and session role (clients go to `index.php?page=booking`, staff/admin to `page=walkin`, anonymous users are sent through `login.php?redirect=...`), and embeds roughly 470 lines of inline CSS/HTML/JS for the hero, package tiers, and how-it-works sections.",
      "tags": [
        "entry-point",
        "public-page",
        "marketing",
        "presentation"
      ]
    },
    {
      "id": "file:login.php",
      "type": "file",
      "name": "login.php",
      "filePath": "login.php",
      "summary": "Combined login / register / forgot-password / reset-password page that is the app's authentication entry point. A single CSRF-protected POST handler dispatches on a hidden `action` field: `login` (Gmail-only regex check, `password_verify`, refuses unverified email, `session_regenerate_id`), `register` (creates or refreshes an unverified client, sends a verification email and auto-verifies on 'skipped'/'failed' delivery), `forgot_password` (inserts a `password_resets` row and mails a link) and `reset_password` (consumes the token and rehashes the password).",
      "tags": [
        "authentication",
        "api-handler",
        "entry-point",
        "validation",
        "security"
      ]
    },
    {
      "id": "file:logout.php",
      "type": "file",
      "name": "logout.php",
      "filePath": "logout.php",
      "summary": "Minimal session teardown endpoint that clears `$_SESSION`, expires the session cookie using the existing cookie params, destroys the session, and redirects to `login.php`. It requires only `functions.php` and relies on that file transitively loading `config.php` for the `APP_URL` constant.",
      "tags": [
        "authentication",
        "session",
        "utility",
        "entry-point"
      ]
    },
    {
      "id": "file:mfa-helper.php",
      "type": "file",
      "name": "mfa-helper.php",
      "filePath": "mfa-helper.php",
      "summary": "Dependency-free TOTP (RFC 6238) implementation wrapped in the `MFAHelper` class â€” base32 secret generation/decoding, HOTP code derivation, constant-time verification with time-slice discrepancy tolerance, `otpauth://` URI building, QR code rendering via `endroid/qr-code`, and bcrypt-hashed single-use backup codes. Included only by `mfa-setup.php`.",
      "tags": [
        "authentication",
        "mfa",
        "totp",
        "security",
        "utility"
      ]
    },
    {
      "id": "file:mfa-setup.php",
      "type": "file",
      "name": "mfa-setup.php",
      "filePath": "mfa-setup.php",
      "summary": "Standalone two-factor enrollment page that sits outside the router: a CSRF-protected POST handler supports `generate` (stash a new secret in the session and re-render the QR), `verify` (confirm the first TOTP code, persist `mfa_enabled`/`mfa_secret`/hashed backup codes, then show the codes once), and `disable` (requires re-entering the account password). Renders one of four states â€” backup codes, QR scan, enabled, disabled â€” depending on `?step=`, the pending session secret, and the stored `mfa_enabled` flag.",
      "tags": [
        "authentication",
        "mfa",
        "account-settings",
        "security",
        "page"
      ]
    },
    {
      "id": "file:pages/admin/DFD.HTML",
      "type": "file",
      "name": "DFD.HTML",
      "filePath": "pages/admin/DFD.HTML",
      "summary": "Static 1800x1200 inline-SVG data-flow diagram titled 'Studio 94 DFD Layout' that maps the system's entities (Client, Studio Staff, Studio Owner), processes 1.0-8.0 (authentication, booking, service, inventory, AI chatbot, payment, feedback, reports) and data stores D1-D7. It contains no PHP and is dead: the `.HTML` extension means `index.php`'s router (which always appends `.php`) can never reach it, and nothing else links to it. Valuable only as design documentation of the intended system.",
      "tags": [
        "documentation",
        "design-mockup",
        "diagram",
        "dead-code"
      ]
    },
    {
      "id": "file:pages/admin/bookings.php",
      "type": "file",
      "name": "bookings.php",
      "filePath": "pages/admin/bookings.php",
      "summary": "Admin read-only bookings listing page. Builds a dynamic WHERE clause from the `status` and `q` query params, then runs a prepared statement joining bookings to users and packages with a correlated subquery that counts `booking_inventory` rows per booking; results are ordered newest-first and rendered as a table with client, package, schedule, type, inventory count and status badges. Deliberately exposes no status-mutation controls â€” an inline banner states admins may view but not approve, complete or cancel bookings, which is staff-only. Not a standalone script: the bare `requireRole('admin')` on line 2 assumes `index.php` has already loaded config.php/functions.php and defined the role, page and user variables, so opening the file directly is a fatal error.",
      "tags": [
        "admin-panel",
        "bookings",
        "read-only",
        "listing-view",
        "search-filter"
      ]
    },
    {
      "id": "file:pages/admin/clients.php",
      "type": "file",
      "name": "clients.php",
      "filePath": "pages/admin/clients.php",
      "summary": "Admin client directory page: one un-paginated query joins users with bookings, payments and loyalty_cards to derive per-client booking counts, lifetime spend and first/last booking dates, then segments every client as New (0-1 bookings) or Returning (2+) with search and filter tabs. Not standalone - it calls requireRole('admin') on line 6 and relies on index.php having already loaded config.php/functions.php and defined $user.",
      "tags": [
        "admin-page",
        "reporting",
        "segmentation",
        "sql-query",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/dashboard.php",
      "type": "file",
      "name": "dashboard.php",
      "filePath": "pages/admin/dashboard.php",
      "summary": "Admin sales-and-analytics dashboard, the largest page in the project: computes revenue vs. booked sales, pending approvals, walk-in vs online split, a dynamic (capped at 24 months) monthly sales and booking trend, top-5 packages by revenue, per-package sales, busiest weekday analysis and a low-stock alert list. Roughly two thirds of its 2350 lines are inline CSS and HTML rather than PHP logic.",
      "tags": [
        "admin-page",
        "analytics",
        "reporting",
        "dashboard",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/feedback.php",
      "type": "file",
      "name": "feedback.php",
      "filePath": "pages/admin/feedback.php",
      "summary": "Admin feedback console handling delete, resolve, mark-urgent, AI re-analyze and send-reply POST actions (all CSRF-verified), calling analyzeFeedbackWithAI() from ai_helper.php and falling back to a local Tagalog/English keyword sentiment scorer when the AI columns are absent. Renders filterable review stats plus a sentiment-prioritized urgent list.",
      "tags": [
        "admin-page",
        "feedback",
        "sentiment-analysis",
        "ai-integration",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/inventory.php",
      "type": "file",
      "name": "inventory.php",
      "filePath": "pages/admin/inventory.php",
      "summary": "Inventory CRUD page open to both admin and staff: adds items, updates quantities in place, and refuses deletion when an item is still referenced by booking_inventory rows. Every mutation calls checkLowStockAndNotify() so alerts fire immediately, and the listing annotates each item with an in-use count derived from unreturned booking_inventory records.",
      "tags": [
        "admin-page",
        "crud",
        "inventory",
        "notifications",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/loyalty-cards.php",
      "type": "file",
      "name": "loyalty-cards.php",
      "filePath": "pages/admin/loyalty-cards.php",
      "summary": "Read-only admin view of the loyalty program: joins users to loyalty_cards, attaches a tier through getLoyaltyTier(), filters by the corrected 2/4/7/10 booking thresholds, and shows tier badges plus progress toward the next reward. Performs no writes and is not exposed to client or staff navigation.",
      "tags": [
        "admin-page",
        "loyalty",
        "reporting",
        "read-only",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/notifications.php",
      "type": "file",
      "name": "notifications.php",
      "filePath": "pages/admin/notifications.php",
      "summary": "Shared notification inbox for admin, staff and clients: opens a notification (marking it read and redirecting to its internal link), marks all as read, deletes single entries, and renders the latest 100 notifications styled per type. Every branch prefers the functions.php helper (getNotifications, markNotificationRead, markAllNotificationsRead, deleteNotification) and falls back to equivalent inline SQL.",
      "tags": [
        "notifications",
        "inbox",
        "ui",
        "security",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/packages.php",
      "type": "file",
      "name": "packages.php",
      "filePath": "pages/admin/packages.php",
      "summary": "Admin CRUD page over the packages table: creates and updates packages with an image upload, soft-deletes them by setting is_active=0, and renders main packages with their sub-packages grouped through parent_id. Images are stored under assets/packages/ with a validated MIME type and a random filename.",
      "tags": [
        "admin-page",
        "crud",
        "file-upload",
        "packages",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/payments.php",
      "type": "file",
      "name": "payments.php",
      "filePath": "pages/admin/payments.php",
      "summary": "Admin payment ledger: aggregates total revenue, pending, refunded and unpaid-reservation figures from the payments and bookings tables, then lists transactions joined to clients, bookings, packages and the users who refunded or verified them, with a status filter and derived on-site vs walk-in source labels.",
      "tags": [
        "admin-page",
        "payments",
        "reporting",
        "read-only",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/admin/profile.php",
      "type": "file",
      "name": "profile.php",
      "filePath": "pages/admin/profile.php",
      "summary": "Shared profile page for any logged-in user: dispatches POST actions to edit name/email/phone, change the password, and upload an avatar, then renders the profile UI in the same file. It is rendered by index.php and depends on the router having already defined $user and $role -- its only guard is an isset() check that redirects to login.php.",
      "tags": [
        "page",
        "profile",
        "account-management",
        "file-upload",
        "procedural"
      ]
    },
    {
      "id": "file:pages/admin/reports.php",
      "type": "file",
      "name": "reports.php",
      "filePath": "pages/admin/reports.php",
      "summary": "Admin-only reports and analytics module exposing 9 report types (inventory, appointment, payment, sales, loyalty, analytics, evaluation, chatbot, photos) with on-screen tables plus PDF and CSV export. Revenue is derived from actual paid payments rather than booking totals, and the analytics report computes genuine period-over-period deltas instead of hard-coded percentages.",
      "tags": [
        "reporting",
        "analytics",
        "pdf-export",
        "csv-export",
        "admin"
      ]
    },
    {
      "id": "file:pages/admin/sales.php",
      "type": "file",
      "name": "sales.php",
      "filePath": "pages/admin/sales.php",
      "summary": "Admin sales ledger that filters the payments table by date range, status, payment method and free-text search, then derives summary cards plus daily-revenue, per-package and per-method breakdowns for charting. The transaction query, metric aggregates and chart queries all reuse the same WHERE clause so the summary cards always match the table below them.",
      "tags": [
        "reporting",
        "payments",
        "sales",
        "admin",
        "procedural"
      ]
    },
    {
      "id": "file:pages/admin/settings.php",
      "type": "file",
      "name": "settings.php",
      "filePath": "pages/admin/settings.php",
      "summary": "Admin-only settings screen that persists studio identity, opening hours and GCash/Maribank account details into the settings key/value table and handles QR-code image uploads. Every text field is written with INSERT ... ON DUPLICATE KEY UPDATE, and each QR upload is MIME-sniffed, capped at 5MB and has its predecessor file deleted.",
      "tags": [
        "settings",
        "admin",
        "file-upload",
        "configuration",
        "validation"
      ]
    },
    {
      "id": "file:pages/admin/staff.php",
      "type": "file",
      "name": "staff.php",
      "filePath": "pages/admin/staff.php",
      "summary": "Admin page for managing staff accounts: adds a user with the staff role, flips is_active, and deletes staff rows via a CSRF-checked POST handler that redirects back to index.php?page=staff. The listing pulls every staff user together with a count of the bookings each one managed.",
      "tags": [
        "page",
        "user-management",
        "admin",
        "crud",
        "security"
      ]
    },
    {
      "id": "file:pages/api/chatbot-ai.php",
      "type": "file",
      "name": "chatbot-ai.php",
      "filePath": "pages/api/chatbot-ai.php",
      "summary": "Standalone SnapBot AI JSON endpoint for the public booking site that runs a three-stage cascade: live availability answered from the bookings table, then a hard-coded Studio 94 FAQ, then a Gemini generateContent call across a four-model fallback chain. Every reply is persisted to chat_history and markdown is stripped before the JSON response is emitted.",
      "tags": [
        "api-handler",
        "chatbot",
        "ai-integration",
        "json-api",
        "availability"
      ]
    },
    {
      "id": "file:pages/api/check-client-loyalty.php",
      "type": "file",
      "name": "check-client-loyalty.php",
      "filePath": "pages/api/check-client-loyalty.php",
      "summary": "Standalone staff/admin JSON endpoint that resolves a client by phone or email and returns their loyalty booking count, bonus minutes, discount percentage and tier label for the booking form. The tier thresholds are duplicated inline rather than delegated to getLoyaltyTier() in functions.php, so the two definitions can drift apart.",
      "tags": [
        "api-handler",
        "json-api",
        "loyalty",
        "lookup",
        "staff-only"
      ]
    },
    {
      "id": "file:pages/api/check_availability.php",
      "type": "file",
      "name": "check_availability.php",
      "filePath": "pages/api/check_availability.php",
      "summary": "Standalone staff/admin JSON endpoint that builds a 15-minute slot grid between 10:00 AM and 7:00 PM for a given date and package, adding a capped loyalty bonus to the package duration. Slots carry a three-state result -- available, pending approval, or fully booked -- with confirmed bookings taking priority over awaiting-approval ones.",
      "tags": [
        "api-handler",
        "json-api",
        "scheduling",
        "availability",
        "booking"
      ]
    },
    {
      "id": "file:pages/api/check_new_notifications.php",
      "type": "file",
      "name": "check_new_notifications.php",
      "filePath": "pages/api/check_new_notifications.php",
      "summary": "Standalone JSON polling endpoint backing the client's notification bell: with ?all=1 it returns the total unread count for the badge, otherwise with ?since=<unix_ts> it returns up to five unread notifications created since that timestamp. Self-requires config.php and functions.php, does its own isLoggedIn() plus role allow-list check, and degrades to a success:false JSON body rather than a non-200 code when the database is unreachable.",
      "tags": [
        "api-handler",
        "notifications",
        "polling",
        "json-endpoint",
        "authentication"
      ]
    },
    {
      "id": "file:pages/api/client-payments.php",
      "type": "file",
      "name": "client-payments.php",
      "filePath": "pages/api/client-payments.php",
      "summary": "Client-scoped JSON API with three actions selected by ?action=: 'settings' returns the GCash and Maribank account/QR details from the settings table, 'list' returns the client's payment rows joined to bookings and packages plus computed totals, and 'submit' accepts a CSRF-protected payment-proof screenshot upload. Uploads are extension- and size-validated (5 MB, images only) and written to assets/uploads/payments/ rather than the UPLOAD_DIR constant.",
      "tags": [
        "api-handler",
        "payments",
        "file-upload",
        "validation",
        "json-endpoint"
      ]
    },
    {
      "id": "file:pages/api/get-day-bookings.php",
      "type": "file",
      "name": "get-day-bookings.php",
      "filePath": "pages/api/get-day-bookings.php",
      "summary": "Zero-byte placeholder file with no PHP, no requires, and no consumers. It is a dead stub: any request to it returns an empty 200 response, and nothing in the codebase references it.",
      "tags": [
        "stub",
        "dead-code",
        "api-endpoint",
        "unused"
      ]
    },
    {
      "id": "file:pages/api/inventory-usage.php",
      "type": "file",
      "name": "inventory-usage.php",
      "filePath": "pages/api/inventory-usage.php",
      "summary": "Read-only JSON endpoint that returns up to 50 rows of usage history for a single inventory item by joining booking_inventory to bookings, restricted to admin and staff. Takes the item id from ?id= and returns { success, usage, count } with no-store of state.",
      "tags": [
        "api-handler",
        "inventory",
        "read-only",
        "json-endpoint",
        "authorization"
      ]
    },
    {
      "id": "file:pages/api/record-payment.php",
      "type": "file",
      "name": "record-payment.php",
      "filePath": "pages/api/record-payment.php",
      "summary": "Staff/admin JSON endpoint for recording on-site payments, accepting both raw JSON bodies and multipart FormData so an optional proof image can accompany the payment. Handles RESERVATION (flat 100 peso), BALANCE, and FULL types, applies the 10-booking 50% loyalty discount, then writes the payment row and flips the booking status to Deposit Paid or Confirmed inside one transaction before firing client and admin notifications.",
      "tags": [
        "api-handler",
        "payments",
        "transaction",
        "file-upload",
        "authorization"
      ]
    },
    {
      "id": "file:pages/client/booking.php",
      "type": "file",
      "name": "booking.php",
      "filePath": "pages/client/booking.php",
      "summary": "Client booking-creation page reached through the index.php router (index.php?page=booking); it calls requireRole('client') at line 2 with no require_once of its own, so it is not standalone and would fatal if opened directly. The POST handler validates package, date, time, headcount against the studio's 10:00-19:00 window, and detects schedule overlaps before inserting the booking, an UNPAID RESERVATION payment row, and a loyalty card increment, then fires a staff notification. Roughly the last 800 lines are a large inline <script> block implementing the calendar, slot grid, and sub-package drill-down.",
      "tags": [
        "booking",
        "scheduling",
        "page-template",
        "loyalty",
        "validation"
      ]
    },
    {
      "id": "file:pages/client/bookings.php",
      "type": "file",
      "name": "bookings.php",
      "filePath": "pages/client/bookings.php",
      "summary": "Client booking-list page rendered via index.php?page=bookings; like the other pages/<role>/ files it opens with requireRole('client') and has no require_once of its own. Handles the POST cancel action (blocked within one day of the shoot and on terminal statuses, cascades the cancellation to UNPAID/PENDING payment rows and notifies admin and staff), then renders every booking joined to its package, parent package, and reservation payment with per-status counts.",
      "tags": [
        "booking",
        "page-template",
        "cancellation",
        "loyalty",
        "notification"
      ]
    },
    {
      "id": "file:pages/client/dashboard.php",
      "type": "file",
      "name": "dashboard.php",
      "filePath": "pages/client/dashboard.php",
      "summary": "Client landing page served at index.php?page=dashboard; begins with requireRole('client') and depends on index.php having already loaded config.php and functions.php and defined $user. Assembles the loyalty card summary, the next upcoming session, five recent bookings, three recently delivered photos, and a dynamic 'Welcome back' vs 'Welcome' greeting, then renders it with a page-scoped inline <style> block.",
      "tags": [
        "dashboard",
        "page-template",
        "loyalty",
        "reporting"
      ]
    },
    {
      "id": "file:pages/client/feedback.php",
      "type": "file",
      "name": "feedback.php",
      "filePath": "pages/client/feedback.php",
      "summary": "Client feedback page where a customer submits a review for a completed booking, with Gemini AI sentiment analysis (local keyword/regex fallback), automatic topic tagging, urgent-escalation notifications, and a filterable review history grouped by service type. Not a standalone script: it opens with requireRole('client') and assumes index.php already loaded config.php/functions.php and defined $user, $role and $page.",
      "tags": [
        "page",
        "feedback",
        "ai-integration",
        "client-portal",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/client/notifications.php",
      "type": "file",
      "name": "notifications.php",
      "filePath": "pages/client/notifications.php",
      "summary": "Client notification inbox that handles mark-all-read, single-read, single-delete and delete-all-read through GET parameters, then queries the notifications table filtered by all/unread/read and renders the list with an unread badge. Not a standalone script: it opens with requireRole('client') and depends on index.php having already loaded config.php and functions.php and defined $user.",
      "tags": [
        "notification-inbox",
        "client-portal",
        "procedural-php",
        "read-filter",
        "router-dependent"
      ]
    },
    {
      "id": "file:pages/client/payments.php",
      "type": "file",
      "name": "payments.php",
      "filePath": "pages/client/payments.php",
      "summary": "Client 'My Payments' page that is ~99% inline CSS plus a large vanilla-JS single-page app: it fetches settings and grouped payment lists from pages/api/client-payments.php, renders GCash and Maribank QR codes, handles deposit vs. remaining-balance payment submission, and applies the 10-booking loyalty discount. The only PHP logic is the requireRole('client') auth gate.",
      "tags": [
        "client-portal",
        "payments",
        "vanilla-js",
        "ajax",
        "qr-payments"
      ]
    },
    {
      "id": "file:pages/client/photos.php",
      "type": "file",
      "name": "photos.php",
      "filePath": "pages/client/photos.php",
      "summary": "Client photo-gallery page that lists the user's Completed bookings joined to packages with a photo count and latest upload date, then lazily loads a per-session thumbnail grid on demand. Read-only: it issues no writes and relies on the router having loaded config.php and functions.php.",
      "tags": [
        "client-portal",
        "photo-gallery",
        "procedural-php",
        "read-only",
        "lazy-load"
      ]
    },
    {
      "id": "file:pages/client/profile.php",
      "type": "file",
      "name": "profile.php",
      "filePath": "pages/client/profile.php",
      "summary": "User profile page (header comment marks it 'Shared') that processes three POST actions - update_profile, change_password and upload_avatar - re-reads the user row afterwards, and renders the profile form and avatar. Unlike the other pages in this batch it does not call requireRole(); it falls back to redirecting to login.php when $user or $role is unset, so it serves as the pages/<page>.php fallback when a role-specific profile page is missing.",
      "tags": [
        "profile",
        "form-handler",
        "avatar-upload",
        "validation",
        "shared-page"
      ]
    },
    {
      "id": "file:pages/staff/bookings.php",
      "type": "file",
      "name": "bookings.php",
      "filePath": "pages/staff/bookings.php",
      "summary": "Admin/staff bookings management page covering the full booking lifecycle: CSRF-verified approve/complete/cancel transitions that trigger notifications and inventory deduction on completion, walk-in booking creation with checkBookingConflict() and automatic loyalty-card enrollment, plus an on-site payment modal recording deposit or remaining balance. Models a fixed 100-peso reservation fee and shows loyalty milestones (2/4/7/10 bookings).",
      "tags": [
        "bookings",
        "staff-portal",
        "form-handler",
        "loyalty",
        "walk-in"
      ]
    },
    {
      "id": "file:pages/staff/clients.php",
      "type": "file",
      "name": "clients.php",
      "filePath": "pages/staff/clients.php",
      "summary": "Staff client directory supporting a name/email LIKE search that aggregates each client's total booking count, total PAID spend and most recent booking date via LEFT JOINs against bookings and payments. Pure read-only listing page with no POST handling.",
      "tags": [
        "staff-portal",
        "client-directory",
        "search",
        "aggregate-query",
        "read-only"
      ]
    },
    {
      "id": "file:pages/staff/dashboard.php",
      "type": "file",
      "name": "dashboard.php",
      "filePath": "pages/staff/dashboard.php",
      "summary": "Admin/staff landing dashboard that aggregates booking status counts, same-day conflict detection, pending approvals, confirmed-today bookings, upcoming bookings, today's full schedule and today's walk-ins, then renders them as stat cards and lists. Chooses a 'Welcome back' vs 'Hello' greeting from the session login_count.",
      "tags": [
        "dashboard",
        "staff-portal",
        "analytics",
        "aggregation",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/staff/feedback.php",
      "type": "file",
      "name": "feedback.php",
      "filePath": "pages/staff/feedback.php",
      "summary": "Staff feedback console that lists all client reviews joined to users, normalizes sentiment per row (preferring the stored ai_sentiment column and falling back to a local keyword analyzer), flags urgent items, and lets staff store a reply that fires addNotification() to the client. The only page in this batch that explicitly require_once's ai_helper.php, and the only one that declares PHP functions.",
      "tags": [
        "feedback",
        "staff-portal",
        "sentiment-analysis",
        "notification",
        "ai-integration"
      ]
    },
    {
      "id": "file:pages/staff/inventory.php",
      "type": "file",
      "name": "inventory.php",
      "filePath": "pages/staff/inventory.php",
      "summary": "Staff/admin inventory management page for the `inventory` table: CSRF-protected add / update_quantity / delete actions (delete is blocked when `booking_inventory` references the item), a red low-stock alert banner computed inline from non-reusable items at or below threshold, and an inline JS edit modal. Not standalone â€” it opens with requireRole(['admin','staff']) and relies on index.php having already loaded config.php/functions.php and defined $user/$role.",
      "tags": [
        "page",
        "inventory",
        "crud",
        "role-gated",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/staff/notifications.php",
      "type": "file",
      "name": "notifications.php",
      "filePath": "pages/staff/notifications.php",
      "summary": "Small staff-only notification inbox that lists the last 50 `notifications` rows for the current user with all/unread/read filters, plus a `?markread=1` GET action that flips every row to is_read=1. Only 49 lines of which most is HTML; relies on the router having loaded config.php/functions.php and set $user.",
      "tags": [
        "page",
        "notifications",
        "list-view",
        "role-gated",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/staff/payments.php",
      "type": "file",
      "name": "payments.php",
      "filePath": "pages/staff/payments.php",
      "summary": "Payment verification console for staff/admin: handles verify / reject / refund POST actions, recomputing effective price from `loyalty_cards.total_bookings` (50% off at 10+ bookings), flipping `bookings` to Deposit Paid / Confirmed, and notifying the client via addNotification(). Adds a filter+search list query over payments joined to bookings, packages, users and loyalty_cards with status-ordered counts, then renders a list view whose rows open detail / proof / reject / refund modals.",
      "tags": [
        "page",
        "payments",
        "verification",
        "loyalty",
        "role-gated"
      ]
    },
    {
      "id": "file:pages/staff/profile.php",
      "type": "file",
      "name": "profile.php",
      "filePath": "pages/staff/profile.php",
      "summary": "Shared self-service profile page for staff/admin (identical copy also served under pages/client) handling three POST actions â€” update_profile (with email uniqueness check and session resync), change_password (current-password verify + password_hash), and upload_avatar (finfo MIME sniffing, 2MB cap, writes to assets/uploads/avatars/ and unlinks the old file). Notably it does NOT call requireRole(); it only guards on `isset($user)`/`isset($role)` and redirects to login.php, and it calls db() seven separate times instead of reusing one handle.",
      "tags": [
        "page",
        "profile",
        "file-upload",
        "account",
        "procedural-php"
      ]
    },
    {
      "id": "file:pages/staff/schedule.php",
      "type": "file",
      "name": "schedule.php",
      "filePath": "pages/staff/schedule.php",
      "summary": "Read-only staff schedule board that groups non-cancelled bookings by date and splits each day into online vs walk-in buckets (detected from booking type or a 'WALK' booking_ref prefix), with upcoming / today / tomorrow / week views driven by a CURDATE()-based WHERE builder. No POST handling and no CSRF surface.",
      "tags": [
        "page",
        "schedule",
        "reporting",
        "read-only",
        "role-gated"
      ]
    },
    {
      "id": "file:pages/staff/upload.php",
      "type": "file",
      "name": "upload.php",
      "filePath": "pages/staff/upload.php",
      "summary": "Staff photo-delivery page: CSRF-protected multi-file upload that filters extensions (jpg/jpeg/png/gif/webp/raw), caps each file at 50MB, writes into UPLOAD_DIR (assets/uploads/), inserts a `photos` row per file, and notifies the client via addNotification() once at least one file lands. Also lists Completed/Deposit Paid sessions eligible for upload and a 10-row upload history.",
      "tags": [
        "page",
        "file-upload",
        "photos",
        "notification",
        "role-gated"
      ]
    },
    {
      "id": "file:pages/staff/walkin.php",
      "type": "file",
      "name": "walkin.php",
      "filePath": "pages/staff/walkin.php",
      "summary": "The largest page in the batch: a walk-in booking flow that ALTERs `bookings` to add duration_minutes at request time, parses package duration strings into minutes, detects schedule conflicts, finds-or-creates the client user (issuing a random password sent via addNotification), inserts the walk-in booking plus a Pending RESERVATION payment row, bumps loyalty_cards, and notifies admin+staff. Ships ~500 lines of inline JS for package drill-down, a month calendar with availability colouring, quick-time dropdown, and an async loyalty lookup against pages/api/check-client-loyalty.php.",
      "tags": [
        "page",
        "walk-in",
        "booking",
        "schema-migration",
        "role-gated"
      ]
    },
    {
      "id": "file:public/service-workers.js",
      "type": "file",
      "name": "service-workers.js",
      "filePath": "public/service-workers.js",
      "summary": "Web Push service worker that displays notifications from a JSON push payload with vibrate/sound/action buttons and opens /index.php?page=notifications on click; the fetch handler is network-first with an 'Offline' 503 fallback. Dead in practice â€” no `navigator.serviceWorker.register()` call exists anywhere in the project, so this file is never installed.",
      "tags": [
        "service-worker",
        "push-notifications",
        "offline",
        "progressive-enhancement",
        "dead-code"
      ]
    },
    {
      "id": "file:send-verification.php",
      "type": "file",
      "name": "send-verification.php",
      "filePath": "send-verification.php",
      "summary": "Transactional email helper library required by login.php that delivers account-verification and password-reset messages. Tries the Resend REST API over HTTPS 443 first and falls back to PHPMailer SMTP, because outbound SMTP ports are blocked on Railway.",
      "tags": [
        "service",
        "email",
        "utility",
        "authentication"
      ]
    },
    {
      "id": "file:sidebar.php",
      "type": "file",
      "name": "sidebar.php",
      "filePath": "sidebar.php",
      "summary": "Role-aware navigation sidebar rendered on every authenticated page. Hardcodes the $navAdmin/$navStaff/$navClient link tables that must stay in sync with the $rolePages allowlist in index.php, resolves the avatar path, counts unread notifications, and embeds its own inline CSS and JavaScript.",
      "tags": [
        "component",
        "navigation",
        "markup",
        "ui",
        "role-based"
      ]
    },
    {
      "id": "file:sql/hash.php",
      "type": "file",
      "name": "hash.php",
      "filePath": "sql/hash.php",
      "summary": "Nine-line throwaway developer utility that prints a password_hash() value plus a ready-to-run UPDATE statement resetting the password of every seeded demo account. Not part of the application and referenced by nothing.",
      "tags": [
        "utility",
        "script",
        "security",
        "database"
      ]
    },
    {
      "id": "file:sql/install.php",
      "type": "file",
      "name": "install.php",
      "filePath": "sql/install.php",
      "summary": "Browser-accessible database installer that loads config.php and functions.php, detects whether the users table already exists, then strips CREATE DATABASE/USE statements from install.sql and executes the whole file in one PDO call. Ships with no authentication of any kind, so it must be removed or locked down before any production deploy.",
      "tags": [
        "entry-point",
        "installer",
        "database",
        "security",
        "script"
      ]
    },
    {
      "id": "table:sql/install.sql",
      "type": "table",
      "name": "install.sql",
      "filePath": "sql/install.sql",
      "summary": "Complete bootstrap schema for SnapTrack: eleven CREATE TABLE statements followed by seed data for settings, users, packages, sub-packages, inventory, bookings, payments, feedback, notifications and loyalty cards. Comments are in Filipino, and ensureDatabaseSchema() in functions.php must be kept in sync with any column added here.",
      "tags": [
        "database",
        "schema-definition",
        "migration",
        "seed-data"
      ]
    },
    {
      "id": "table:sql/install.sql:users",
      "type": "table",
      "name": "users",
      "filePath": "sql/install.sql",
      "summary": "Account table covering credentials, role and active state, lockout tracking, email verification tokens, Google OAuth linkage, TOTP MFA secrets and backup codes, and an optional parent_id for family sub-accounts.",
      "tags": [
        "database",
        "schema-definition",
        "authentication",
        "mfa"
      ]
    },
    {
      "id": "table:sql/install.sql:packages",
      "type": "table",
      "name": "packages",
      "filePath": "sql/install.sql",
      "summary": "Studio package catalogue with a self-referencing parent_id that models sub-packages under a main package, plus price, duration, features JSON, theme colour, popularity flag and max_pax.",
      "tags": [
        "database",
        "schema-definition",
        "catalogue",
        "hierarchical"
      ]
    },
    {
      "id": "table:sql/install.sql:bookings",
      "type": "table",
      "name": "bookings",
      "filePath": "sql/install.sql",
      "summary": "Session bookings storing the human booking_ref, package snapshot price, scheduling fields, deposit/remaining balance split, fully_paid flag and applied loyalty reward.",
      "tags": [
        "database",
        "schema-definition",
        "booking",
        "billing"
      ]
    },
    {
      "id": "table:sql/install.sql:payments",
      "type": "table",
      "name": "payments",
      "filePath": "sql/install.sql",
      "summary": "Payment records linked to a booking with amount, type, method, reference number, uploaded proof image, verification status, rejection reason and the staff member who verified it.",
      "tags": [
        "database",
        "schema-definition",
        "payments",
        "audit"
      ]
    },
    {
      "id": "table:sql/install.sql:inventory",
      "type": "table",
      "name": "inventory",
      "filePath": "sql/install.sql",
      "summary": "Stock-keeping table of equipment and consumables with category, quantity and a low-stock threshold that drives the admin and staff inventory pages.",
      "tags": [
        "database",
        "schema-definition",
        "inventory",
        "stock"
      ]
    },
    {
      "id": "table:sql/install.sql:feedback",
      "type": "table",
      "name": "feedback",
      "filePath": "sql/install.sql",
      "summary": "Post-booking reviews with four separate rating dimensions, free-text comment, AI-derived sentiment and topics, and an is_urgent flag for negative-response escalation.",
      "tags": [
        "database",
        "schema-definition",
        "feedback",
        "sentiment"
      ]
    },
    {
      "id": "table:sql/install.sql:photos",
      "type": "table",
      "name": "photos",
      "filePath": "sql/install.sql",
      "summary": "Uploaded gallery images tied to a booking and uploader, storing filename, filepath and a moderation status.",
      "tags": [
        "database",
        "schema-definition",
        "media",
        "gallery"
      ]
    },
    {
      "id": "table:sql/install.sql:notifications",
      "type": "table",
      "name": "notifications",
      "filePath": "sql/install.sql",
      "summary": "In-app notification feed per user with a type discriminator, title, message, icon and is_read flag; backs the unread badge counted by sidebar.php and getUnreadCount().",
      "tags": [
        "database",
        "schema-definition",
        "notifications",
        "ui"
      ]
    },
    {
      "id": "table:sql/install.sql:loyalty_cards",
      "type": "table",
      "name": "loyalty_cards",
      "filePath": "sql/install.sql",
      "summary": "Per-user loyalty membership holding the card number, lifetime booking count, consumed reward count and card status.",
      "tags": [
        "database",
        "schema-definition",
        "loyalty",
        "rewards"
      ]
    },
    {
      "id": "table:sql/install.sql:settings",
      "type": "table",
      "name": "settings",
      "filePath": "sql/install.sql",
      "summary": "Simple key/value store for admin-editable runtime settings, seeded with the business identity including the San Fernando and Camarines Sur location names.",
      "tags": [
        "database",
        "schema-definition",
        "configuration",
        "key-value"
      ]
    },
    {
      "id": "table:sql/install.sql:password_resets",
      "type": "table",
      "name": "password_resets",
      "filePath": "sql/install.sql",
      "summary": "Single-use password reset tokens keyed to a user and email with an expiry timestamp and a used flag, backing sendPasswordResetEmail().",
      "tags": [
        "database",
        "schema-definition",
        "authentication",
        "password-reset"
      ]
    },
    {
      "id": "file:uploads/.gitkeep",
      "type": "file",
      "name": ".gitkeep",
      "filePath": "uploads/.gitkeep",
      "summary": "Zero-byte placeholder that keeps the root uploads/ directory tracked by git. One of several scattered upload directories in this project and not itself written to by any code.",
      "tags": [
        "placeholder",
        "uploads",
        "utility"
      ]
    }
  ],
  "importEdges": [
    {
      "source": "file:ai_helper.php",
      "target": "file:config.php",
      "type": "imports"
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
      "source": "file:footer.php",
      "target": "file:includes/chatbot.php",
      "type": "imports"
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
      "source": "file:includes/booking.php",
      "target": "file:config.php",
      "type": "imports"
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
      "source": "file:logout.php",
      "target": "file:functions.php",
      "type": "imports"
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
      "source": "file:pages/admin/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
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
      "source": "file:pages/client/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
    },
    {
      "source": "file:pages/staff/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
    },
    {
      "source": "file:send-verification.php",
      "target": "file:config.php",
      "type": "imports"
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
    }
  ],
  "allEdges": [
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
      "source": "file:pages/admin/feedback.php",
      "target": "file:ai_helper.php",
      "type": "imports"
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
      "source": "file:send-verification.php",
      "target": "file:config.php",
      "type": "imports"
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

It contains `fileNodes` (89 file-level nodes), `importEdges` (45), and `allEdges` (144 file-level edges).

---

## Project-specific architectural guidance

This is a **procedural PHP application with no framework**. There is no MVC structure, no namespace hierarchy, and no Composer autoload section. Reason from what actually exists:

**The real architectural boundaries are:**

1. **Entry / routing layer** -- `index.php` is the single router (`index.php?page=<name>`). It validates against a `` allowlist for roles admin/staff/client and resolves `pages/<role>/<page>.php` with a silent fallback to dashboard. Also `landing.php`, `login.php`, `logout.php`, `google-login.php`, `google-callback.php`, `mfa-setup.php`, `send-verification.php`, `change_password.php`, `verify.php`, `test-mfa.php` (excluded).

2. **Shared kernel** -- `config.php` (env loading + constants) and `functions.php` (1782 lines, ~50 global helper functions: `db()`, `ensureDatabaseSchema()`, auth, CSRF, notifications, inventory, loyalty). Every page depends on these.

3. **Presentation shell** -- `header.php`, `sidebar.php` (per-role nav arrays), `footer.php`.

4. **Role page layer** -- `pages/admin/` (14 files), `pages/staff/` (11), `pages/client/` (8). These are router-loaded partials, NOT standalone scripts.

5. **API layer** -- `pages/api/` (8 files). These ARE standalone: each self-requires `../../config.php` + `../../functions.php` and does its own auth check.

6. **Shared includes** -- `includes/` (chatbot.php, booking.php, deduct_inventory.php). Note `includes/deduct_inventory.php` is DEAD (nothing requires it) and `includes/booking.php` is BROKEN (calls an undefined `getDBConnection()`).

7. **Data layer** -- `sql/install.sql` (12 CREATE TABLE definitions), `sql/install.php` (web installer, no auth), `sql/hash.php`.

8. **Assets** -- `assets/css/styles.css` (the ONLY stylesheet actually loaded), `assets/js/app.js`, `public/service-workers.js`.

9. **Config/infra/docs** -- `composer.json`, `.env.example`, `nixpacks.toml` (Railway deploy), `AGENTS.md`, `GEMINI.md`.

**Dead/duplicate files that should NOT drive layer boundaries** -- assign them to the layer matching their live counterpart, and they are already described as dead in their summaries: root `styles.css` and `assets/images/css/styles.css` (duplicate of each other, unreferenced), root `app.js` (dead, 1893 lines; the live one is `assets/js/app.js`), `pages/admin/DFD.HTML` (mockup).

**Guidance on grouping:** Aim for 5-8 layers that reflect the above, adjusted to what the data actually shows. Assign EVERY file node to exactly one layer. Do not invent layers for files that do not exist. Use the `` routing model as the strongest signal that admin/staff/client are three peer surfaces of one page layer -- but if the data suggests separating them (admin has analytics/reports/inventory, client has booking/payments/photos), that is also valid.

Each layer needs `id` (`layer:<kebab-case-name>`), `name`, `description`, and `nodeIds`.