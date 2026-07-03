# Collections Agent Guide

This file tells AI agents how to understand and use the Collections ProcessWire module when helping build or maintain a website.

It is behavioral guidance for agents. It is not proof that the module is installed, enabled, configured, or safe to use in a specific ProcessWire site. Always verify the live site state before taking action.

## Module Summary

Collections is a ProcessWire module for managing groups of pages as configurable data tables.

It provides:

- an admin section at `Admin > Collections`
- collection definitions mapped to ProcessWire templates
- searchable and filterable table views
- bulk publish, unpublish, and delete actions
- CSV and JSON export
- role-scoped permissions
- an optional REST API for frontend, headless, mobile, and integration use

Use Collections when a site treats ProcessWire pages as records: products, listings, vacancies, menu items, locations, articles, client projects, taxonomy records, or similar structured content.

Do not treat Collections as a replacement for ProcessWire templates, fields, access control, or content modeling. It works on top of existing ProcessWire pages.

## Olivia Ready Status

This module is an Olivia Ready candidate at the Agent-Aware / Olivia-Compatible documentation level.

Relevant documentation:

- `README.md` explains purpose, installation, settings, use cases, and file structure.
- `API.md` documents the public REST API and examples.
- `AGENTS.md` gives AI usage guidance, safety boundaries, and site-building workflows.

Agents should prefer this file for behavior and safety guidance, `API.md` for REST API usage, and the live ProcessWire site state for current facts.

## Source Trust Rules

For current site facts, prefer sources in this order:

1. live ProcessWire state
2. explicit Context or AgentTools output
3. installed module metadata
4. project configuration
5. project documentation
6. module documentation
7. general ProcessWire knowledge

For intended module API usage, prefer sources in this order:

1. `API.md`
2. this `AGENTS.md`
3. `README.md`
4. source code inspection

If documentation conflicts with live site state, surface the conflict. Do not assume the documentation is current.

## Important Files

- `Collections.module.php`: autoload module, install/uninstall, admin hooks, frontend REST interception.
- `ProcessCollections.module.php`: admin Process controller.
- `src/Collection.php`: collection value object and ProcessWire selector builder.
- `src/CollectionConfig.php`: DB-backed collection, global, permissions, and API key configuration.
- `src/CollectionQuery.php`: collection query execution and filter option lookup.
- `src/CollectionRenderer.php`: admin table rendering and field display.
- `src/CollectionPermissions.php`: role/capability checks.
- `src/CollectionExporter.php`: CSV and JSON export.
- `src/Api/CollectionApiRouter.php`: REST route dispatch and rate limit.
- `src/Api/CollectionApiHandler.php`: REST handlers and field serialization.
- `views/`: admin UI templates.
- `assets/collections.js`: admin interactivity.
- `assets/collections.css`: admin styling.
- `install/permissions.php`: ProcessWire permissions installed by the module.
- `API.md`: public REST API reference.
- `README.md`: overview and configuration guide.

## Public Website-Building Workflows

When building a website with this module, use one of these patterns.

### Pattern 1: ProcessWire Template Rendering

Use native ProcessWire APIs for normal server-rendered templates:

```php
$items = $pages->find('template=product, sort=title, limit=24');
```

Use Collections as the admin management layer for editors, not necessarily as the frontend data source.

Recommended when:

- the frontend is rendered by ProcessWire templates
- no public API is needed
- the data model is simple and already available through `$pages`

### Pattern 2: Collections REST API

Use the REST API when the frontend is external, headless, or JavaScript-heavy.

Typical read calls:

```http
GET /api/collections/
GET /api/{key}/
GET /api/{key}/{id}/
GET /api/{key}/schema/
GET /api/{key}/export/?format=csv
GET /api/{key}/export/?format=json
```

Typical write calls:

```http
POST   /api/{key}/
PATCH  /api/{key}/{id}/
DELETE /api/{key}/{id}/
POST   /api/{key}/bulk/
```

Use `Authorization: Bearer <key>` for external clients. Browser sessions may work for same-origin ProcessWire users.

Recommended when:

- a Next.js, Nuxt, SvelteKit, React, mobile, or external app needs ProcessWire data
- the site needs schema discovery
- the frontend should not implement custom ProcessWire endpoint templates

### Pattern 3: Admin Data Operations

Use the Collections admin UI for editor workflows:

- browse records in tables
- search and filter records
- publish or unpublish rows
- export data
- perform approved bulk actions
- jump from page edit screens into the relevant collection

Agents should usually describe or configure these workflows rather than simulate large destructive UI operations.

## Collection Configuration Model

A collection maps to a ProcessWire template and optional selector.

Important fields:

- `key`: URL-safe collection key, e.g. `products`
- `label`: human-readable collection label
- `template`: ProcessWire template name
- `selector`: optional extra ProcessWire selector to scope records
- `columns`: visible and returned fields
- `searchFields`: fields used by `q`
- `searchRelated`: whether Page reference titles may be searched
- `sortBy` and `sortDir`: default ordering
- `perPage`: default page size for that collection
- `group`: admin sidebar group
- `icon`: FontAwesome 4 class
- `exportEnabled`: whether export is available
- `roleAccess`: per-collection role capabilities
- `columnLabels` and `columnTypes`: display metadata

When creating collection recommendations, validate that the referenced template and fields exist in the current site.

## Global Settings

Important global settings include:

- `show_id`
- `show_status`
- `show_name`
- `inline_status`
- `quick_delete`
- `confirm_batch_delete`
- `default_per_page`
- `date_format`
- `thumb_width`
- `thumb_height`
- `live_search`
- `min_search_length`
- `export_csv`
- `api_enabled`
- `api_base`
- `cache_enabled`
- `cache_ttl`

Do not assume the REST API is enabled. Check `api_enabled` and `api_base` in live configuration.

## Public PHP Entry Points

Agents may rely on these module-level entry points when working inside ProcessWire:

```php
$collections = $modules->get('Collections');
$config = $collections->getConfig();
```

Configuration methods:

```php
$config->getCollections();
$config->getCollectionByKey('products');
$config->saveCollection($collection);
$config->deleteCollection('products');
$config->getGlobal();
$config->saveGlobal($settings);
$config->getPermissionsMatrix();
$config->savePermissionsMatrix($matrix);
$config->exportAll();
$config->importAll($data);
$config->createApiKey($name, $expirationDays, $caps);
$config->validateApiKey($rawKey);
$config->getApiKeys();
$config->deleteApiKey($id);
```

Query methods:

```php
$query = new CollectionQuery($pages);
$result = $query->execute($collection, $params);
$options = $query->getFilterOptions($collection, 'field_name');
```

Collection object methods:

```php
$collection->toArray();
Collection::fromArray($data);
$collection->canAddNew();
$collection->buildSelector($search, $filters);
```

Prefer the admin UI or documented REST API for site-level usage. Use PHP methods directly only when building module integrations, migrations, tests, or controlled automation.

## REST API Rules

The REST API is active only when global `api_enabled` is enabled.

Default base path:

```http
/api/
```

Authentication methods:

- Bearer token in `Authorization` header, recommended for external clients
- `api_key` query parameter
- HTTP Basic Auth with ProcessWire credentials
- authenticated ProcessWire session

Read query parameters:

- `q`
- `page`
- `per_page`
- `sort`
- `dir`
- `filter[field]`
- `fields`
- `format`

All JSON responses use an `ok` envelope. Check `ok` before using `data`.

The API rate limit is 100 requests per minute per IP per collection key.

Do not expose raw API keys in generated code, commits, logs, screenshots, documentation examples, or client-side bundles unless the user explicitly confirms that the key is public and scoped appropriately. Prefer environment variables.

In the current implementation, API-key authentication may temporarily elevate the request to a superuser so the normal permission checks pass. Treat API keys as powerful server-side secrets unless the installed project code clearly enforces narrower key scopes.

## Permissions

Installed ProcessWire permissions:

- `collections-view`
- `collections-create`
- `collections-edit`
- `collections-delete`
- `collections-configure`
- `collections-export`

Agents must respect both ProcessWire permissions and collection-level role capability rules.

Treat these operations as permission-sensitive:

- creating pages
- updating pages
- deleting pages
- bulk publishing
- bulk unpublishing
- bulk deleting
- exporting private data
- creating or deleting API keys
- changing global API settings
- changing role access

## Safe Operations

Agents may perform or recommend these after verifying current site state:

- read `README.md`, `API.md`, and this file
- inspect installed module metadata
- list configured collections
- inspect collection schema
- perform authenticated read-only API calls
- generate frontend code that consumes read endpoints
- suggest collection configurations
- draft import/export JSON for human review
- explain editor workflows
- add non-destructive frontend templates using existing ProcessWire APIs

## Approval Required

Require explicit user approval before:

- enabling the REST API on a live site
- creating or deleting API keys
- changing permissions or role access
- creating, editing, deleting, publishing, or unpublishing pages
- running bulk actions
- importing collection configuration
- deleting collections
- uninstalling the module
- dropping module tables
- exporting sensitive data
- changing API base paths on a live site

## Dangerous Operations

Avoid these unless the user specifically requests them and confirms the risk:

- direct SQL writes to `collections_items`, `collections_global`, `collections_permissions`, or `collections_api_keys`
- direct deletion of rows from module tables
- hardcoding API keys into frontend JavaScript
- assuming `DELETE /api/{key}/{id}/` moves pages to trash; the handler performs deletion through ProcessWire's page delete API
- running broad selectors without limits
- bulk deleting records based only on search results
- bypassing `CollectionPermissions`
- disabling confirmation safeguards for editors without approval

## Frontend Integration Guidance

When using Collections to build a frontend:

1. Verify the collection key exists.
2. Fetch `/api/{key}/schema/` or inspect ProcessWire fields before assuming field names.
3. Use pagination and a reasonable `per_page`.
4. Use `filter[field]` with Page reference IDs, not titles.
5. Use `fields` to request only fields needed by the UI.
6. Treat image and file values as objects with `url`, `name`, and `size` where applicable.
7. Treat Page reference values as objects with `id`, `title`, and `url`.
8. Handle `ok: false` errors and HTTP status codes.
9. Keep API keys server-side for SSR, build servers, and API routes.
10. For public browser-only apps, ask the developer to provide a safe public access strategy.

If the site is rendered by ProcessWire templates, prefer native `$pages->find()` unless the project specifically wants the frontend to consume the REST API.

## Collection Design Guidance

When proposing collections for a site:

- one collection should usually map to one primary template
- keep `key` stable because it becomes part of API URLs
- keep `columns` practical for editor scanning
- include `title`, status-relevant fields, and business identifiers such as SKU or location
- use `selector` for scoping, e.g. `parent=/shop/` or `created_users_id=123`
- keep selectors explicit and explain what they include or exclude
- avoid exposing fields that contain secrets, private notes, tokens, or sensitive personal data
- define permissions per role before enabling write operations
- enable export only when the user understands the data exposure

## Testing And Verification

Before declaring work complete, verify relevant behavior:

- module is installed and available through `$modules->get('Collections')`
- `/admin/collections/` exists if admin UI is expected
- collection keys resolve through `getCollectionByKey()`
- referenced templates and fields exist
- REST API base path matches global settings
- API calls return the documented `ok` envelope
- permissions match the intended editor or API role
- frontend code handles empty lists, pagination, errors, and missing fields

For destructive changes, test on a non-production site or with a small known record set.

## Conflict Handling

If this file says a workflow should be available but live site state disagrees:

- state the mismatch
- do not proceed with risky operations
- ask whether documentation is outdated or configuration is incomplete

If `API.md` and `README.md` disagree about an endpoint or parameter:

- prefer `API.md`
- inspect source code if needed
- avoid write operations until confirmed

If project-level `AGENTS.md` or user instructions are stricter than this file:

- follow the stricter project or user rule

## Agent Do Not List

Do not:

- invent collection keys
- invent field names
- invent permissions
- assume REST is enabled
- assume an API key is safe for browser exposure
- assume module documentation reflects the current site
- expose secrets
- bypass ProcessWire APIs for normal page writes
- edit generated admin UI output when a configuration change is the correct solution
- perform bulk actions without explicit confirmation

## Quick Reference

Read all visible collections:

```http
GET /api/collections/
```

List records:

```http
GET /api/{key}/?page=1&per_page=25&sort=title&dir=asc
```

Search:

```http
GET /api/{key}/?q=search
```

Filter:

```http
GET /api/{key}/?filter[country]=42
```

Schema:

```http
GET /api/{key}/schema/
```

Export:

```http
GET /api/{key}/export/?format=csv
GET /api/{key}/export/?format=json
```

Create:

```http
POST /api/{key}/
```

Update:

```http
PATCH /api/{key}/{id}/
```

Delete:

```http
DELETE /api/{key}/{id}/
```

Bulk:

```http
POST /api/{key}/bulk/
```
