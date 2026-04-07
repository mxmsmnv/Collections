<?php

namespace ProcessWire;

class CollectionApiHandler
{
    private $wire;

    public function __construct(
        private readonly CollectionConfig      $config,
        private readonly CollectionPermissions $perms,
        $wire
    ) {
        $this->wire = $wire;
    }

    public function handleList(): CollectionApiResponse
    {
        $collections = $this->perms->filterVisible($this->config->getCollections());
        $data = array_map(fn(Collection $c) => [
            'key'      => $c->key,
            'label'    => $c->label,
            'template' => $c->template,
            'icon'     => $c->icon,
            'group'    => $c->group,
        ], $collections);
        return CollectionApiResponse::success(array_values($data));
    }

    public function handleIndex(Collection $collection, $input): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_VIEW, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $params = QueryParams::fromInput($input);
        $query  = new CollectionQuery($this->wire->pages);
        $result = $query->execute($collection, $params);

        $format = $input->get('format') ?? 'json';
        if ($format === 'table') {
            $global   = $this->config->getGlobal();
            $renderer = new CollectionRenderer($collection, $global, $this->perms, $this->wire);
            return new CollectionApiResponse([
                'ok'   => true,
                'html' => $renderer->renderTable($result, $params),
                'meta' => ['total' => $result->total],
            ]);
        }

        $fields = $input->get('fields') ? explode(',', (string)$input->get('fields')) : null;
        $items  = [];
        foreach ($result->items as $page) {
            $item = ['id' => $page->id, 'name' => $page->name, 'url' => $page->httpUrl, 'status' => $page->status];
            foreach ($fields ?? $collection->columns as $col) {
                $item[$col] = $this->normalizeFieldValue($page, $col);
            }
            $items[] = $item;
        }

        return CollectionApiResponse::success($items, [
            'total'       => $result->total,
            'page'        => $result->page,
            'per_page'    => $result->perPage,
            'total_pages' => $result->totalPages,
            'collection'  => $collection->key,
        ]);
    }

    public function handleShow(Collection $collection, int $id): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_VIEW, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $page = $this->wire->pages->get("id={$id}, template={$collection->template}");
        if (!$page->id) throw new CollectionApiException("Page {$id} not found", 404);

        $item = ['id' => $page->id, 'name' => $page->name, 'url' => $page->httpUrl, 'status' => $page->status];
        foreach ($collection->columns as $col) {
            $item[$col] = $this->normalizeFieldValue($page, $col);
        }
        return CollectionApiResponse::success($item);
    }

    public function handleCreate(Collection $collection, array $body): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_CREATE, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $template = $this->wire->templates->get($collection->template);
        if (!$template) throw new CollectionApiException("Template not found: {$collection->template}", 500);

        // Determine parent: from request body, or default to root (id=1)
        $parentId = isset($body['parent']) ? (int)$body['parent'] : 0;
        $parent   = $parentId > 0
            ? $this->wire->pages->get($parentId)
            : $this->wire->pages->get(1);
        if (!$parent->id) throw new CollectionApiException("Parent page not found", 500);

        $page = new Page($template);
        $page->parent = $parent;
        $page->of(false);
        foreach ($body as $field => $value) {
            if ($field === 'title') { $page->title = $value; continue; }
            if (!$template->fieldgroup->has($field)) continue;
            $page->set($field, $value);
        }
        $this->wire->pages->save($page);

        $item = ['id' => $page->id, 'name' => $page->name, 'url' => $page->httpUrl];
        foreach ($collection->columns as $col) {
            $item[$col] = $this->normalizeFieldValue($page, $col);
        }
        return CollectionApiResponse::created($item);
    }

    public function handleUpdate(Collection $collection, int $id, array $body): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_EDIT, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $page = $this->wire->pages->get("id={$id}, template={$collection->template}");
        if (!$page->id) throw new CollectionApiException("Page {$id} not found", 404);

        $page->of(false);
        foreach ($body as $field => $value) {
            if ($field === 'title') { $page->title = $value; continue; }
            if (!$page->template->fieldgroup->has($field)) continue;
            $page->set($field, $value);
        }
        $this->wire->pages->save($page);

        $item = ['id' => $page->id, 'name' => $page->name, 'url' => $page->httpUrl];
        foreach ($collection->columns as $col) {
            $item[$col] = $this->normalizeFieldValue($page, $col);
        }
        return CollectionApiResponse::success($item);
    }

    public function handleDelete(Collection $collection, int $id): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_DELETE, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $page = $this->wire->pages->get("id={$id}, template={$collection->template}");
        if (!$page->id) throw new CollectionApiException("Page {$id} not found", 404);

        $this->wire->pages->delete($page, true);
        return CollectionApiResponse::success(['deleted' => true, 'id' => $id]);
    }

    public function handleBulk(Collection $collection, array $body): CollectionApiResponse
    {
        $action = $body['action'] ?? '';
        $ids    = array_map('intval', (array)($body['ids'] ?? []));
        if (!$ids) throw new CollectionApiException('No IDs provided', 422);

        $cap = match($action) {
            'publish', 'unpublish' => CollectionPermissions::CAP_EDIT,
            'delete'               => CollectionPermissions::CAP_DELETE,
            default                => throw new CollectionApiException("Unknown action: {$action}", 422),
        };
        $this->perms->can($cap, $collection) || throw new CollectionApiException('Forbidden', 403);

        $results = ['success' => 0, 'errors' => []];
        foreach ($this->wire->pages->getById($ids) as $page) {
            try {
                match($action) {
                    'publish'   => (function() use ($page) { $page->of(false); $page->removeStatus(Page::statusUnpublished); $page->save(); })(),
                    'unpublish' => (function() use ($page) { $page->of(false); $page->addStatus(Page::statusUnpublished); $page->save(); })(),
                    'delete'    => $this->wire->pages->delete($page, true),
                };
                $results['success']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "Page {$page->id}: {$e->getMessage()}";
            }
        }
        return CollectionApiResponse::success($results);
    }

    public function handleSchema(Collection $collection): CollectionApiResponse
    {
        $this->perms->can(CollectionPermissions::CAP_VIEW, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $template = $this->wire->templates->get($collection->template);
        $fields   = [];
        if ($template) {
            foreach ($template->fieldgroup as $field) {
                $fd = [
                    'name'     => $field->name,
                    'type'     => $field->type->className(),
                    'required' => (bool)$field->required,
                    'label'    => $collection->columnLabels[$field->name] ?? ($field->label ?: ucfirst($field->name)),
                ];
                $fields[] = $fd;
            }
        }
        return CollectionApiResponse::success([
            'key'      => $collection->key,
            'label'    => $collection->label,
            'template' => $collection->template,
            'fields'   => $fields,
        ]);
    }

    public function handleExport(Collection $collection, $input): void
    {
        $this->perms->can(CollectionPermissions::CAP_EXPORT, $collection)
            || throw new CollectionApiException('Forbidden', 403);

        $params = new QueryParams(1, 10000, (string)($input->get('q') ?? ''), 'title', 'asc', []);
        $query  = new CollectionQuery($this->wire->pages);
        $result = $query->execute($collection, $params);

        $exporter = new CollectionExporter();
        $format   = $input->get('format') ?? 'csv';
        if ($format === 'json') {
            $exporter->exportJson($collection, $result);
        } else {
            $exporter->exportCsv($collection, $result);
        }
        exit;
    }

    private function getFieldType(string $fieldName): string
    {
        $field = $this->wire->fields->get($fieldName);
        return $field ? $field->type->className() : '';
    }

    private function normalizeFieldValue($page, string $col): mixed
    {
        $field  = $this->wire->fields->get($col);
        if (!$field) {
            return $page->get($col);
        }

        $ftClass  = $field->type->className();
        $httpHost = rtrim(($this->wire->config->https ? 'https://' : 'http://') . $this->wire->config->httpHost, '/');

        // ── Image / File ──────────────────────────────────────────────────
        if (in_array($ftClass, ['FieldtypeImage', 'FieldtypeFile'])) {
            $wasOf = $page->of();
            $page->of(true);
            $val = $page->get($col);
            $page->of($wasOf);

            if (!$val) return null;
            $valClass = is_object($val) ? get_class($val) : '';
            // Strip namespace: ProcessWire\Pageimages → Pageimages
            $shortClass = substr(strrchr('\\' . $valClass, '\\'), 1);

            // Single Pageimage object
            if ($shortClass === 'Pageimage') {
                return ['url' => $httpHost . $val->url, 'name' => $val->name, 'size' => (int)$val->filesize];
            }

            // Pageimages / Pagefiles collection
            if (in_array($shortClass, ['Pageimages', 'Pagefiles'])) {
                if (!count($val)) return null;
                $items = [];
                foreach ($val as $file) {
                    $items[] = ['url' => $httpHost . $file->url, 'name' => $file->name, 'size' => (int)$file->filesize];
                }
                return $this->isSingleField($col) && count($items) === 1 ? $items[0] : $items;
            }

            // String fallback
            if (is_string($val) && strlen($val) > 0) {
                $filesUrl = $this->wire->config->urls->files . $page->id . '/';
                return ['url' => $httpHost . $filesUrl . $val, 'name' => $val, 'size' => 0];
            }

            return null;
        }

        // ── FieldtypeFileB2 ───────────────────────────────────────────────
        if ($ftClass === 'FieldtypeFileB2') {
            $val = $page->get($col);
            if (!$val) return null;
            if (is_object($val) && method_exists($val, 'httpUrl')) {
                return ['url' => $val->httpUrl, 'name' => $val->name ?? ''];
            }
            return (string)$val;
        }

        // ── Page reference ────────────────────────────────────────────────
        if ($ftClass === 'FieldtypePage') {
            $raw      = $page->getUnformatted($col);
            $rawClass = is_object($raw) ? substr(strrchr('\\' . get_class($raw), '\\'), 1) : '';

            if ($rawClass === 'Page') {
                return $raw->id ? ['id' => $raw->id, 'title' => $raw->title, 'url' => $raw->httpUrl] : null;
            }
            if ($rawClass === 'PageArray') {
                $refs = [];
                foreach ($raw as $ref) {
                    if ($ref->id) $refs[] = ['id' => $ref->id, 'title' => $ref->title, 'url' => $ref->httpUrl];
                }
                return $refs ?: null;
            }
            // Numeric ID
            if (is_numeric($raw) && (int)$raw > 0) {
                $ref = $this->wire->pages->get((int)$raw);
                return $ref->id ? ['id' => $ref->id, 'title' => $ref->title, 'url' => $ref->httpUrl] : null;
            }
            // Pipe-separated IDs
            if (is_string($raw) && strpos($raw, '|') !== false) {
                $refs = [];
                foreach (explode('|', $raw) as $sid) {
                    $ref = $this->wire->pages->get((int)trim($sid));
                    if ($ref->id) $refs[] = ['id' => $ref->id, 'title' => $ref->title, 'url' => $ref->httpUrl];
                }
                return $refs ?: null;
            }
            // Boolean false = empty page ref
            if ($raw === false || $raw === 0 || $raw === '') return null;

            return (string)$raw;
        }

        // ── Options ───────────────────────────────────────────────────────
        if ($ftClass === 'FieldtypeOptions') {
            $wasOf = $page->of();
            $page->of(true);
            $val = (string)$page->get($col);
            $page->of($wasOf);
            return $val ?: null;
        }

        // ── Table (Profields) ─────────────────────────────────────────────
        if ($ftClass === 'FieldtypeTable') {
            $val = $page->get($col);
            if (!$val || !count($val)) return null;
            $rows = [];
            foreach ($val as $row) {
                $rows[] = is_object($row) && method_exists($row, 'getArray') ? $row->getArray() : (string)$row;
            }
            return $rows;
        }

        // ── Default ───────────────────────────────────────────────────────
        $val = $page->get($col);
        if ($val === null || $val === false || $val === '') return null;
        if (is_object($val)) return (string)$val;
        return $val;
    }

    private function isSingleField(string $fieldName): bool
    {
        $field = $this->wire->fields->get($fieldName);
        return $field && ((int)$field->get('maxFiles') === 1);
    }
}