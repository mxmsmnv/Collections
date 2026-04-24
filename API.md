# Collections REST API

**Module:** [Collections for ProcessWire](README.md)  
**Author:** Maxim Semenov · [smnv.org](https://smnv.org) · maxim@smnv.org

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

