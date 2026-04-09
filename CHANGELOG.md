# Changelog

## 1.8.2 — 2026-04-09

### Changed
- **CSS fully migrated to UIkit design system variables** — all hardcoded colors replaced with `--pw-*` CSS custom properties from AdminThemeUikit. Dark mode now works automatically. Affected variables: `--pw-blocks-background`, `--pw-inputs-background`, `--pw-border-color`, `--pw-muted-color`, `--pw-text-color`, `--pw-main-background`, `--pw-error-inline-text-color`. All fallback values preserved for environments where variables are not defined.

---

## 1.8.1 — 2026-04-07

### Fixed
- **Default sort not applied on first load** — `sortBy`/`sortDir` from collection settings were only applied when `per_page` was absent from URL. Now always applied as fallback when not explicitly set in request params.
- **Default sort direction ignored** — `QueryParams::fromInput` returned `'asc'` as hardcoded default for `dir`, preventing collection's `sortDir` from taking effect. Now returns empty string so collection default wins.
- **Filter dropdowns not firing** — used `querySelectorAll` at script load time before DOM was ready. Replaced with `document.addEventListener('change')` event delegation.
- **Export ignores active filters** — export links now include all current `filter[]` and `q` params. Export links also update dynamically after AJAX table refresh.
- **Array to string warning** — `PageArray::each('id')` returns array in PW 3.0.240+. Now explicitly converted to pipe-separated string.

### Added
- **"Search in related page titles" option** per collection — controls whether Page reference columns are automatically included in search. Enabled by default (existing behavior preserved). Can be disabled per collection in Configure.

---

## 1.8 — 2026-04-05

### Fixed
- **Admin hooks not firing** — `addCollectionLink` and `addPageListAction` hooks moved from `init()` to `ready()`, where `$page` is fully resolved. Caused "View in Collection" buttons to disappear after module update.
- **Bulk actions failing with WireCSRFException** — `submitBulk()` now posts to `location.pathname + location.search` preserving `?col=key`, which was silently dropped before, causing the server to lose collection context.
- **CSRF token mismatch on bulk/delete** — replaced double `getTokenName()`/`getTokenValue()` calls (which could reset the token) with a single `renderInput()` call; token name and value now extracted once server-side and passed to JS.
- **Quick delete JSON parse error** — row-level delete was calling the REST API without auth headers, receiving an HTML 401 response instead of JSON. Now uses the same form POST mechanism as bulk actions.
- **API tab redirect after key create/delete** — was redirecting to `?configure=1` (Collections tab); now redirects to `?configure=1#tab-api`.

### Changed
- **Action icons replaced** — all FontAwesome icons in the table action column replaced with inline SVG (Heroicons solid + Bootstrap Icons): pencil for edit, eye for view, check-circle/x-circle for status toggle, trash for delete. Fixes invisible icons caused by FontAwesome CSS overriding SVG dimensions.
- **Thead styled with `--pw-main-color`** — table header background uses a 15% tint of the ProcessWire theme color; text and icons use the full theme color. Adapts automatically to any AdminThemeUikit color scheme.
- **Clickable rows** — clicking anywhere on a table row toggles its checkbox. Interactive elements (links, buttons, inputs) are excluded.
- **Row selection highlight** — selected rows get a `color-mix` tint of `--pw-main-color` at 8%; hover on selected rows at 13%.
- **`sticky_header` setting removed** — the sticky header feature was unreliable due to ProcessWire AdminTheme's scroll container blocking CSS `position: sticky`. Setting removed from Global Settings UI and defaults.
- **Configure page icons** — View/Edit/Delete text buttons in the collection list replaced with Heroicons SVG icons. API key delete button also updated.

### Added
- `getCsrf()` helper in `collections.js` — reads CSRF token from `#collections-csrf` hidden input at submit time, used by both bulk form and quick delete.
- `row-selected` CSS class — toggled on `<tr>` when checkbox is checked/unchecked, including select-all and cancel actions.

---

## 1.7 — 2026-03-28

Initial release.

- Configurable collections backed by custom MySQL tables
- Table UI with sortable columns, live search, pagination
- Inline status toggle via AJAX
- Bulk publish / unpublish / delete
- CSV and JSON export
- REST API: list, show, create, update, delete, bulk, schema, export
- API key management with SHA-256 hashing and expiration
- Role-based permissions matrix (global and per-collection)
- Collapsible sidebar with persistent state
- Configuration export / import
- Supports: Text, Textarea, Image, File, Page reference, Options, Checkbox, Color, URL, Email, MapMarker, Profields Table/Textareas/Multiplier
- Integration hooks on page edit and page list
- Cache invalidation on page save/delete
