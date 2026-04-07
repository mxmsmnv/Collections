# Collections — ProcessWire Module

**Version:** 1.7 · **Requires:** ProcessWire 3.0.244+, PHP 8.2+  
**Author:** Maxim Semenov · [smnv.org](https://smnv.org) · maxim@smnv.org  
**GitHub:** [github.com/mxmsmnv/Collections](https://github.com/mxmsmnv/Collections)

---

## The Problem

ProcessWire's default Page List is built for site structure — not data management. When you have thousands of pages as data records (products, listings, candidates, menu items), the default admin becomes painful fast:

- **No table view.** You see one page at a time, nested in a tree. Finding a specific record means clicking through folders or memorizing IDs.
- **No inline filters.** Want to see only unpublished products from Italy? You're writing a selector in the URL or building a custom admin page from scratch.
- **No bulk actions.** Publishing 200 seasonal items means 200 individual saves.
- **No export.** Getting your data out requires a custom template or a module.
- **No REST API.** Feeding a mobile app or a headless frontend means building endpoints yourself.
- **No role scoping.** Every editor sees every page. Limiting a franchisee to their own location's menu requires custom code.

Every ProcessWire developer has solved some version of this problem on every project. Collections solves it once.

---

## What It Does

Collections gives any ProcessWire template a configurable admin table — with live search, dropdown filters, inline status toggles, bulk actions, CSV/JSON export, and a REST API — all configured through a UI, without writing code.

It installs as a dedicated section inside the ProcessWire admin. Editors get a professional, responsive interface. Developers get a REST API with zero boilerplate.

---

## Real-World Use Cases

**Product catalog (e-commerce)**
A spirits retailer manages 12,000+ products across ABV, country, region, brand, SKU, and size variants. Collections provides a filterable table where buyers bulk-publish seasonal items, export the filtered view to CSV for distributors, and jump to edit any row — without leaving the list.

**Property listings (real estate)**
An agency runs listings across multiple cities. Each agent sees only their own listings via the permissions matrix and a `created_by` selector. The manager sees everything and exports weekly JSON reports fed to a mobile app through the built-in REST API.

**Job board / HR**
A company posts vacancies across departments. HR manages the full board; department heads see only their own roles. When a hiring round closes, one bulk action archives the filled positions. Status dots show at a glance what's live and what's in draft.

**Restaurant chain / franchise**
A franchise with 40 locations manages its menu centrally. Each location's menu is a separate collection scoped by a selector. The head chef publishes globally; location managers toggle availability for their own restaurant only — same module, different permission roles.

**Media / editorial**
A magazine team of writers and editors. Writers see their own drafts; editors see everything. Publish status is inline — no need to open each article. Bulk-scheduling a campaign batch takes seconds.

**SaaS client portal**
A B2B platform manages client projects as ProcessWire pages. Each account manager sees only their client records. The REST API feeds a React dashboard with paginated, filtered data using Bearer token auth.

**Headless CMS with Next.js / Nuxt / SvelteKit**
ProcessWire as the content backend, Collections as the REST layer. Frontend frameworks fetch data from `/api/products/?filter[category]=42&sort=modified&dir=desc`. No custom API templates needed.

**Mobile app backend**
iOS/Android apps consume the Collections API. API keys with expiration dates and usage tracking provide per-app access control. The `/schema/` endpoint lets the app self-describe available fields without hardcoding.

---

## Features

**Admin UI**
- Configurable table columns per collection with custom labels
- Live search with 300ms debounce, multi-field, including Page reference fields
- Dropdown filters for FieldtypePage and FieldtypeOptions fields
- Inline status toggle (publish / unpublish) via AJAX — no page reload
- Clickable rows with visual selection highlight themed to `--pw-main-color`
- Bulk actions: publish, unpublish, delete with CSRF protection
- Quick delete button per row (optional)
- Collapsible sidebar with persistent state (localStorage)
- "View in Collection" button injected into each page's edit form
- Admin UI colors adapt to ProcessWire's `--pw-main-color` theme variable

**Data**
- CSV and JSON export with current search/filter state preserved
- Configuration export / import as JSON
- Role-based permissions matrix (global and per-collection)

**REST API**
- Bearer token, query param (`?api_key=`), HTTP Basic Auth, and PW session
- API key management with optional expiration dates and per-key capability scopes
- SHA-256 hashed keys — raw key shown only once on creation
- Usage tracking: last used timestamp, request count
- Rate limit: 100 requests/minute per IP per collection (HTTP 429 on excess)
- WireCache support for GET responses (configurable TTL)

**Field Types Supported**
Text, Textarea, Integer, Float, Checkbox, URL, Email, Color (hex), Date/Datetime, Image, File, FieldtypeFileB2, FieldtypePage (single and multi), FieldtypeOptions, MapMarker, Profields Table, Profields Textareas, Profields Multiplier

---

## Installation

1. Copy the `Collections/` folder to `site/modules/`
2. In the admin go to **Modules → Refresh**
3. Install **Collections** — `ProcessCollections` installs automatically
4. Go to **Admin → Collections → Configure** to create your first collection

---

## Quick Start

Go to **Admin → Collections → Configure**, click **New Collection** and fill in:

| Field | Example | Notes |
|---|---|---|
| Key | `products` | Lowercase slug, URL-safe, unique |
| Label | `Products` | Display name in sidebar and header |
| Template | `product` | ProcessWire template name |
| Selector | `parent.name=shop` | Optional extra PW selector to scope results |
| Columns | `title, sku, brand, country, modified` | Comma-separated field names |
| Search fields | `title, sku` | Fields queried by the search bar |
| Sort by | `title` | Default sort field |
| Sort dir | `asc` | `asc` or `desc` |
| Per page | `40` | Overrides global default (0 = use global) |
| Group | `content` | Sidebar group label |
| Icon | `fa-box` | FontAwesome 4 icon class |

---

## Global Settings

**Admin → Collections → Configure → Global Settings**

| Setting | Default | Description |
|---|---|---|
| Show ID column | on | Prepends the page `id` to every table |
| Show Status column | on | Colored dot: green=published, red=unpublished, yellow=hidden |
| Show Name column | off | Page `name` (URL slug) |
| Inline status toggle | on | Publish/unpublish without leaving the list |
| Quick delete button | off | Trash icon in each row's action column |
| Confirm batch delete | on | Confirmation dialog before bulk delete |
| Live search | on | Search fires as you type (300ms debounce) |
| Min search length | 2 | Minimum characters before search fires |
| Default per page | 25 | Rows per page when collection doesn't override |
| Date format | `M j, Y` | PHP `date()` format string for date fields |
| Enable REST API | off | Must be enabled to use any API endpoint |
| API base path | `/api/` | URL prefix for all endpoints |
| Enable API cache | off | Caches GET responses using WireCache |
| Cache TTL | 300 | Cache lifetime in seconds |

---

## REST API

### Setup

1. Go to **Configure → Global Settings**, enable **REST API** and set **API base path**
2. Go to **Configure → API**, enter a key name and create an API key
3. Copy the raw key — it is shown **only once**

### Authentication

Three methods are supported, checked in this order:

```http
# 1. Bearer token in Authorization header (recommended)
Authorization: Bearer col_a1b2c3d4e5f6...

# 2. Query string parameter
GET /api/products/?api_key=col_a1b2c3d4e5f6...

# 3. HTTP Basic Auth (ProcessWire username + password)
Authorization: Basic base64(username:password)
```

Authenticated PW sessions (browser cookie) are also accepted — useful for same-origin requests from PW templates.

### Endpoints

```
GET    /api/collections/              List all visible collections
GET    /api/{key}/                    List pages (paginated, filtered, sorted)
GET    /api/{key}/{id}/               Single page by ID
GET    /api/{key}/schema/             Field definitions for the collection
GET    /api/{key}/export/             Export as CSV or JSON (streams file)
POST   /api/{key}/                    Create a new page
POST   /api/{key}/bulk/               Bulk action on multiple pages
PATCH  /api/{key}/{id}/               Update fields on a page
DELETE /api/{key}/{id}/               Delete a page
```

### Query Parameters (GET /api/{key}/)

| Parameter | Example | Description |
|---|---|---|
| `q` | `q=whiskey` | Full-text search across configured search fields |
| `page` | `page=2` | Page number, 1-indexed (default: 1) |
| `per_page` | `per_page=50` | Items per page (default: 25, max: 500) |
| `sort` | `sort=modified` | Field to sort by |
| `dir` | `dir=desc` | Sort direction: `asc` or `desc` |
| `filter[field]` | `filter[country]=42` | Filter by exact field value (Page ref: use ID) |
| `fields` | `fields=title,sku,abv` | Comma-separated fields to return (overrides collection columns) |
| `format` | `format=table` | `json` (default) or `table` (returns HTML snippet) |

### Response Format

All responses use a consistent envelope:

```json
{
  "ok": true,
  "data": [ ... ],
  "meta": {
    "total": 12878,
    "page": 1,
    "per_page": 25,
    "total_pages": 516,
    "collection": "products"
  }
}
```

Error responses:

```json
{
  "ok": false,
  "error": "NOT_FOUND",
  "message": "Page 9999 not found",
  "status": 404
}
```

### Field Serialization

| Field Type | API Output |
|---|---|
| Text, Textarea, Integer, Float | Scalar value |
| Checkbox | `true` / `false` |
| Date | Unix timestamp (integer) |
| Color | `"#ff6600"` |
| Image / File (single) | `{"url": "...", "name": "...", "size": 12345}` |
| Image / File (multi) | Array of above objects |
| Page reference (single) | `{"id": 42, "title": "...", "url": "..."}` |
| Page reference (multi) | Array of above objects |
| Options | String label |
| Profields Table | Array of row objects |
| MapMarker | Scalar (formatted address string) |

---

## API Examples

### cURL

```bash
BASE="https://example.com/api"
KEY="col_a1b2c3d4e5f6..."

# List all collections
curl -H "Authorization: Bearer $KEY" "$BASE/collections/"

# List products — page 2, 50 per page, sorted by modified desc
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/?page=2&per_page=50&sort=modified&dir=desc"

# Search for "whiskey" in the products collection
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/?q=whiskey"

# Filter by country (Page reference field, use the page ID)
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/?filter[country]=42"

# Combine search + filter + custom fields
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/?q=bourbon&filter[brand]=17&fields=title,abv,country&sort=title"

# Get a single product
curl -H "Authorization: Bearer $KEY" "$BASE/products/15598/"

# Get field schema for the products collection
curl -H "Authorization: Bearer $KEY" "$BASE/products/schema/"

# Export all products as CSV (downloads file)
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/export/?format=csv" -o products.csv

# Export filtered products as JSON
curl -H "Authorization: Bearer $KEY" \
  "$BASE/products/export/?format=json&q=whiskey" -o whiskey.json

# Create a new product
curl -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"title": "Maker'\''s Mark", "abv": 45, "parent": 1042}' \
  "$BASE/products/"

# Update a product (PATCH — only sends changed fields)
curl -X PATCH -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"abv": 46.5, "country": 42}' \
  "$BASE/products/15598/"

# Delete a product
curl -X DELETE -H "Authorization: Bearer $KEY" "$BASE/products/15598/"

# Bulk publish
curl -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"action": "publish", "ids": [1234, 1235, 1236]}' \
  "$BASE/products/bulk/"

# Bulk delete
curl -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"action": "delete", "ids": [9001, 9002]}' \
  "$BASE/products/bulk/"
```

### JavaScript (fetch)

```javascript
const BASE = 'https://example.com/api';
const KEY  = 'col_a1b2c3d4e5f6...';
const headers = { 'Authorization': `Bearer ${KEY}` };

// List products with pagination
async function getProducts(page = 1, perPage = 25) {
  const res = await fetch(
    `${BASE}/products/?page=${page}&per_page=${perPage}&sort=title`,
    { headers }
  );
  const json = await res.json();
  // json.data  → array of products
  // json.meta  → { total, page, per_page, total_pages, collection }
  return json;
}

// Search + filter
async function searchProducts(query, countryId) {
  const params = new URLSearchParams({ q: query, sort: 'title' });
  if (countryId) params.set('filter[country]', countryId);
  const res = await fetch(`${BASE}/products/?${params}`, { headers });
  return res.json();
}

// Get single product
async function getProduct(id) {
  const res = await fetch(`${BASE}/products/${id}/`, { headers });
  return res.json();
}

// Create
async function createProduct(data) {
  const res = await fetch(`${BASE}/products/`, {
    method: 'POST',
    headers: { ...headers, 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

// Update
async function updateProduct(id, data) {
  const res = await fetch(`${BASE}/products/${id}/`, {
    method: 'PATCH',
    headers: { ...headers, 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

// Delete
async function deleteProduct(id) {
  const res = await fetch(`${BASE}/products/${id}/`, {
    method: 'DELETE',
    headers,
  });
  return res.json();
}

// Bulk action
async function bulkAction(action, ids) {
  const res = await fetch(`${BASE}/products/bulk/`, {
    method: 'POST',
    headers: { ...headers, 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ids }),
  });
  return res.json();
}
```

### PHP (with Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://example.com/api/',
    'headers'  => ['Authorization' => 'Bearer col_a1b2c3d4e5f6...'],
]);

// List products
$res  = $client->get('products/', ['query' => ['per_page' => 50, 'sort' => 'title']]);
$data = json_decode($res->getBody(), true);
foreach ($data['data'] as $product) {
    echo $product['title'] . ' — ' . $product['abv'] . "%\n";
}

// Get single
$res     = $client->get('products/15598/');
$product = json_decode($res->getBody(), true)['data'];

// Create
$res = $client->post('products/', [
    'json' => ['title' => "Maker's Mark", 'abv' => 45, 'parent' => 1042],
]);
$created = json_decode($res->getBody(), true)['data'];

// Update
$client->patch('products/15598/', [
    'json' => ['abv' => 46.5],
]);

// Bulk unpublish
$client->post('products/bulk/', [
    'json' => ['action' => 'unpublish', 'ids' => [1234, 1235]],
]);
```

### PHP (native, no dependencies)

```php
function collectionsApi(string $method, string $url, array $data = [], string $key = ''): array {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Accept: application/json',
            ]),
        ],
    ];
    if ($data) {
        $opts['http']['content'] = json_encode($data);
    }
    $result = file_get_contents($url, false, stream_context_create($opts));
    return json_decode($result, true) ?? [];
}

$KEY  = 'col_a1b2c3d4e5f6...';
$BASE = 'https://example.com/api';

// List
$products = collectionsApi('GET', "$BASE/products/?per_page=50", [], $KEY);

// Create
$new = collectionsApi('POST', "$BASE/products/", [
    'title'  => "Knob Creek",
    'abv'    => 50,
    'parent' => 1042,
], $KEY);

// Update
collectionsApi('PATCH', "$BASE/products/15598/", ['abv' => 51], $KEY);
```

### Python

```python
import requests

BASE = "https://example.com/api"
KEY  = "col_a1b2c3d4e5f6..."
HEADERS = {"Authorization": f"Bearer {KEY}"}

# List with pagination
def get_products(page=1, per_page=25, **filters):
    params = {"page": page, "per_page": per_page, "sort": "title"}
    for k, v in filters.items():
        params[f"filter[{k}]"] = v
    r = requests.get(f"{BASE}/products/", headers=HEADERS, params=params)
    r.raise_for_status()
    return r.json()

# All products (iterate pages)
def get_all_products():
    page, results = 1, []
    while True:
        data = get_products(page=page, per_page=100)
        results.extend(data["data"])
        if page >= data["meta"]["total_pages"]:
            break
        page += 1
    return results

# Create
def create_product(title, abv, parent_id):
    r = requests.post(
        f"{BASE}/products/",
        headers={**HEADERS, "Content-Type": "application/json"},
        json={"title": title, "abv": abv, "parent": parent_id},
    )
    r.raise_for_status()
    return r.json()["data"]

# Bulk publish
def bulk_publish(ids: list[int]):
    r = requests.post(
        f"{BASE}/products/bulk/",
        headers={**HEADERS, "Content-Type": "application/json"},
        json={"action": "publish", "ids": ids},
    )
    return r.json()

# Export CSV to file
def export_csv(filepath="products.csv"):
    r = requests.get(
        f"{BASE}/products/export/?format=csv",
        headers=HEADERS, stream=True
    )
    with open(filepath, "wb") as f:
        for chunk in r.iter_content(chunk_size=8192):
            f.write(chunk)
```

### Next.js (App Router)

```typescript
// lib/collections.ts
const BASE = process.env.COLLECTIONS_API_URL!;
const KEY  = process.env.COLLECTIONS_API_KEY!;

async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Authorization: `Bearer ${KEY}`,
      'Content-Type': 'application/json',
      ...init?.headers,
    },
    next: { revalidate: 60 }, // ISR: revalidate every 60s
  });
  if (!res.ok) throw new Error(`API error ${res.status}`);
  return res.json();
}

interface Meta {
  total: number; page: number; per_page: number;
  total_pages: number; collection: string;
}
interface ApiResponse<T> { ok: boolean; data: T; meta?: Meta; }

// app/products/page.tsx
export default async function ProductsPage({
  searchParams,
}: {
  searchParams: { page?: string; q?: string; country?: string };
}) {
  const params = new URLSearchParams({
    page: searchParams.page ?? '1',
    per_page: '24',
    sort: 'title',
    ...(searchParams.q       ? { q: searchParams.q } : {}),
    ...(searchParams.country ? { 'filter[country]': searchParams.country } : {}),
  });

  const { data: products, meta } = await api<ApiResponse<Product[]>>(
    `/products/?${params}`
  );

  return (
    <div>
      <p>{meta?.total} products</p>
      {products.map(p => <ProductCard key={p.id} product={p} />)}
    </div>
  );
}
```

### React Query (client-side)

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

const API = 'https://example.com/api';
const KEY = 'col_a1b2c3d4e5f6...';
const headers = { Authorization: `Bearer ${KEY}` };

// Hook: paginated products list
export function useProducts(page = 1, filters = {}) {
  const params = new URLSearchParams({
    page: String(page),
    per_page: '25',
    ...Object.fromEntries(
      Object.entries(filters).map(([k, v]) => [`filter[${k}]`, String(v)])
    ),
  });

  return useQuery({
    queryKey: ['products', page, filters],
    queryFn: async () => {
      const res = await fetch(`${API}/products/?${params}`, { headers });
      return res.json();
    },
  });
}

// Hook: create product
export function useCreateProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<Product>) => {
      const res = await fetch(`${API}/products/`, {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      return res.json();
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['products'] }),
  });
}
```

### WordPress → Collections migration (PHP script)

```php
// One-time migration: pull WP posts into PW via Collections API
$wpPosts = json_decode(file_get_contents('https://old-site.com/wp-json/wp/v2/posts?per_page=100'), true);

foreach ($wpPosts as $post) {
    collectionsApi('POST', 'https://new-site.com/api/blog/', [
        'title'  => $post['title']['rendered'],
        'body'   => $post['content']['rendered'],
        'parent' => 1099, // blog parent page ID in PW
    ], 'col_migration_key...');
}
```

---

## Permissions

| Permission slug | Description |
|---|---|
| `collections-view` | View collection pages and use the table UI |
| `collections-create` | Create new pages via admin or API |
| `collections-edit` | Edit pages, toggle status, bulk publish/unpublish |
| `collections-delete` | Delete pages individually or in bulk |
| `collections-configure` | Access the Configure tab, create/delete collections |
| `collections-export` | Export collection data as CSV or JSON |

Assign permissions to roles under **Access → Roles** in ProcessWire, then configure which roles have which capabilities per collection under **Configure → Permissions**.

---

## Database Tables

Four custom tables are created on install and dropped on uninstall:

| Table | Contents |
|---|---|
| `collections_items` | Collection definitions stored as JSON rows |
| `collections_global` | Global settings as key/value pairs |
| `collections_permissions` | Role capability matrix (JSON per role) |
| `collections_api_keys` | API keys — SHA-256 hash, prefix, expiry, usage stats |

---

## Compatibility

Tested with:

- **ProcessWire** 3.0.244, 3.0.261+
- **PHP** 8.2, 8.3
- **AdminThemeUikit** (default PW admin theme) — UI adapts to `--pw-main-color`
- **Profields** Table, Textareas, Multiplier — rendered in table and exported via API
- **FieldtypeFileB2** — custom Backblaze B2 field type supported in API output

---

## File Structure

```
site/modules/Collections/
├── Collections.module.php           Autoload module: hooks, API routing, cache invalidation
├── ProcessCollections.module.php    Admin Process: UI, bulk actions, configure
├── src/
│   ├── Collection.php               Value object + PW selector builder
│   ├── CollectionConfig.php         DB-backed settings storage (4 tables)
│   ├── CollectionRenderer.php       HTML table + row renderer
│   ├── CollectionQuery.php          PW selector query execution + pagination
│   ├── CollectionPermissions.php    Role + capability permission checks
│   ├── CollectionExporter.php       CSV / JSON streaming export
│   ├── QueryParams.php              QueryParams + QueryResult value objects
│   └── Api/
│       ├── CollectionApiRouter.php  REST router + rate limiter (100 req/min)
│       ├── CollectionApiHandler.php CRUD handlers + field serialization
│       └── CollectionApiResponse.php JSON response wrapper
├── views/
│   ├── layout.php                   Sidenav + main layout wrapper
│   ├── dashboard.php                Dashboard with collection cards + counts
│   ├── collection-list.php          Collection table view + bulk bar
│   ├── configure.php                Configure UI: tabs for collections, global, API, permissions, import/export
│   └── partials/
│       ├── toolbar.php              Search input + filter dropdowns
│       └── pagination.php           Pagination widget
├── assets/
│   ├── collections.js               Frontend JS: live search, AJAX toggle, bulk actions, row selection
│   └── collections.css              Styles: layout, table, sidebar, bulk bar, theming
├── install/
│   └── permissions.php              Creates 6 PW permissions on install
├── CHANGELOG.md
└── README.md
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Author

**Maxim Semenov**  
[smnv.org](https://smnv.org) · maxim@smnv.org  
GitHub: [github.com/mxmsmnv/Collections](https://github.com/mxmsmnv/Collections)