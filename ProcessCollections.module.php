<?php

namespace ProcessWire;

/**
 * ProcessCollections
 *
 * Admin Process module for the Collections module.
 * Provides the admin UI at /admin/collections/
 */
class ProcessCollections extends Process
{
    private CollectionConfig $config;
    private CollectionPermissions $perms;
    private ?Collection $currentCollection = null;

    public static function getModuleInfo(): array
    {
        return [
            'title'       => 'ProcessCollections',
            'version'     => 181,
            'summary'     => 'Admin interface for Collections module',
            'author'      => 'Maxim Semenov',
            'requires'    => ['Collections'],
            'autoload'    => false,
            'singular'    => true,
            'icon'        => 'list',
            'useSegments' => true,
            'permission'  => 'collections-view',
        ];
    }

    public function init(): void
    {
        parent::init();

        /** @var Collections $collections */
        $collections = $this->wire('modules')->get('Collections');
        if (!$collections) return;

        $this->config = $collections->getConfig();
        $this->perms  = new CollectionPermissions($this->wire('user'), $this->config);

        // Log every request to collections
        $seg = $this->wire('input')->urlSegment1;
        $this->wire('log')->save('collections', sprintf(
            'init() called. URL=%s seg1=%s method=%s',
            $this->wire('input')->url(),
            $seg ?: '(none)',
            $_SERVER['REQUEST_METHOD']
        ));
    }

    /**
     * PW ProcessController calls ___executeSegment for URL segments when useSegments=true
     * We override it to route all segments through our main execute logic
     */
    public function ___executeSegment($segment): string
    {
        $this->wire('log')->save('collections', '___executeSegment() called. segment=' . $segment);
        return $this->___execute();
    }

    public function ___execute(): string
    {
        $this->wire('log')->save('collections', sprintf(
            '___execute() called. seg1=%s col_get=%s GET=%s',
            $this->wire('input')->urlSegment1 ?: '(none)',
            $this->wire('input')->get('col') ?: '(none)',
            json_encode($_GET)
        ));

        $this->setupAssets();

        // Handle toggle_status AJAX early (works on collection pages too)
        if ($this->wire('input')->requestMethod('POST') && $this->wire('input')->get('toggle_status')) {
            if ($this->wire('session')->CSRF->validate()) {
                return $this->handleToggleStatus();
            }
            return json_encode(['ok' => false, 'error' => 'CSRF']);
        }

        // Support both URL segment (/collections/key/) and GET param (/collections/?col=key)
        $key = $this->wire('input')->urlSegment1
            ?: $this->wire('sanitizer')->pageName($this->wire('input')->get('col') ?? '');

        // Configure via GET param (?configure=1) and all related actions
        $configActions = ['configure', 'save_collection', 'save_global', 'save_permissions',
                          'delete_collection', 'import_config', 'export_config',
                          'create_api_key', 'delete_api_key'];
        foreach ($configActions as $action) {
            if ($this->wire('input')->get($action)) {
                return $this->renderConfigure();
            }
        }

        // Dashboard (no key = home)
        if (!$key) {
            return $this->renderDashboard();
        }

        // Config export handler — only triggered by explicit ?export_config=1 GET param
        // (already handled via configActions above, but kept as safety fallback)
        // NOTE: do NOT intercept key='export' here — that would prevent a collection
        // named 'export' from being displayed. Use only the GET param route.
        if ($this->wire('input')->get('export_config')) {
            $this->handleExportConfig();
        }

        // Collection view
        $key        = $this->wire('sanitizer')->pageName($key);
        $collection = $this->config->getCollectionByKey($key);

        if (!$collection) {
            throw new Wire404Exception("Collection not found: {$key}");
        }

        $this->perms->can(CollectionPermissions::CAP_VIEW, $collection)
            || throw new WirePermissionException($this->_('You do not have access to this collection'));

        $this->currentCollection = $collection;

        // Handle export from collection page
        $export = $this->wire('input')->get('export');
        if ($export && $this->perms->can(CollectionPermissions::CAP_EXPORT, $collection)) {
            $this->handleCollectionExport($collection, $export);
        }

        // Handle bulk POST
        if ($this->wire('input')->post('bulk_action')) {
            return $this->executeBulkAction($collection);
        }

        return $this->renderCollection($collection);
    }

    // ── Collection View ───────────────────────────────────────────────────────

    private function renderCollection(Collection $collection): string
    {
        $this->wire('log')->save('collections', 'renderCollection() key=' . $collection->key);
        $globalConfig = $this->config->getGlobal();
        $input        = $this->wire('input');
        $params       = QueryParams::fromInput($input);

        // Apply collection defaults for sort — always, not just when per_page is absent
        $defaultPP  = $collection->perPage > 0 ? $collection->perPage : ($globalConfig['default_per_page'] ?? 25);
        $params = new QueryParams(
            page:    $params->page,
            perPage: $input->get('per_page') ? $params->perPage : $defaultPP,
            search:  $params->search,
            sortBy:  $params->sortBy ?: $collection->sortBy,
            sortDir: $params->sortDir ?: $collection->sortDir,
            filters: $params->filters,
        );

        $query    = new CollectionQuery($this->wire('pages'));
        $renderer = new CollectionRenderer($collection, $globalConfig, $this->perms, $this->wire());

        // AJAX partial update
        if ($this->wire('config')->ajax) {
            $result = $query->execute($collection, $params);
            // Also render pagination for AJAX update
            ob_start();
            include __DIR__ . '/views/partials/pagination.php';
            $paginationHtml = ob_get_clean();

            // Build statusbar text
            if ($result->total > 0) {
                $from = ($params->page - 1) * $result->perPage + 1;
                $to   = min($params->page * $result->perPage, $result->total);
                $statusText = 'Showing ' . number_format($from) . '–' . number_format($to)
                    . ' of ' . number_format($result->total) . ' items';
            } else {
                $statusText = 'No items found';
            }
            if ($params->search) {
                $statusText .= ' — filtered by "' . $this->wire('sanitizer')->entities($params->search) . '"';
            }

            return json_encode([
                'html'       => $renderer->renderTable($result, $params),
                'pagination' => $paginationHtml,
                'total'      => $result->total,
                'pages'      => $result->totalPages,
                'statusbar'  => $statusText,
            ]);
        }

        $result = $query->execute($collection, $params);

        // Build filter options for active columns that support filtering
        $filterOptions = $this->buildFilterOptions($collection, $query);

        // Set page title/breadcrumb
        $this->wire('processHeadline', $collection->label);
        $adminUrl = $this->wire('config')->urls->admin;
        $this->wire('breadcrumbs')->add(new Breadcrumb($adminUrl . 'collections/', 'Collections'));
        $this->fuel->set('title', $collection->label);

        // Render layout
        $allCollections = $this->perms->filterVisible($this->config->getCollections());
        usort($allCollections, fn($a, $b) => $a->order <=> $b->order);

        $adminUrl = $this->wire('config')->urls->admin;

        ob_start();
        $wire          = $this->wire();
        $perms         = $this->perms;
        // $collection, $result, $params, $globalConfig, $adminUrl, $renderer, $filterOptions
        // are already defined above in renderCollection()
        include __DIR__ . '/views/collection-list.php';
        $content = ob_get_clean();

        ob_start();
        $wire        = $this->wire();
        $perms       = $this->perms;
        $collections = $allCollections;
        $current     = $collection;
        include __DIR__ . '/views/layout.php';
        return ob_get_clean();
    }

    private function buildFilterOptions(Collection $collection, CollectionQuery $query): array
    {
        $options  = [];
        $template = $this->wire('templates')->get($collection->template);
        if (!$template) return $options;

        foreach ($collection->columns as $col) {
            $field = $template->fieldgroup->getField($col);
            if (!$field) continue;

            if ($field->type instanceof FieldtypePage || $field->type instanceof FieldtypeOptions) {
                $opts = $query->getFilterOptions($collection, $col);
                if ($opts) $options[$col] = $opts;
            }
        }

        return $options;
    }

    // ── Bulk Actions ──────────────────────────────────────────────────────────

    private function executeBulkAction(Collection $collection): string
    {
        $tokenName = $this->wire('session')->CSRF->getTokenName();
        $expected  = $this->wire('session')->CSRF->getTokenValue();
        $received  = $this->wire('input')->post($tokenName);
        $this->wire('log')->save('collections', "CSRF debug: name={$tokenName} expected={$expected} received={$received}");

        $this->wire('session')->CSRF->validate()
            || throw new WireCSRFException('Invalid CSRF token');

        $action = $this->wire('sanitizer')->name($this->wire('input')->post('bulk_action'));
        $ids    = array_map('intval', (array) $this->wire('input')->post('ids'));

        $cap = match($action) {
            'publish', 'unpublish' => CollectionPermissions::CAP_EDIT,
            'delete'               => CollectionPermissions::CAP_DELETE,
            default                => throw new WireException("Unknown bulk action: {$action}"),
        };

        $this->perms->can($cap, $collection)
            || throw new WirePermissionException("Not allowed: {$action}");

        $results = ['success' => 0, 'errors' => []];

        foreach ($this->wire('pages')->getById($ids) as $page) {
            try {
                match($action) {
                    'publish'   => (function() use ($page) {
                        $page->of(false);
                        $page->removeStatus(Page::statusUnpublished);
                        $page->save();
                    })(),
                    'unpublish' => (function() use ($page) {
                        $page->of(false);
                        $page->addStatus(Page::statusUnpublished);
                        $page->save();
                    })(),
                    'delete' => $this->wire('pages')->delete($page, true),
                };
                $results['success']++;
            } catch (Throwable $e) {
                $results['errors'][] = "Page {$page->id}: {$e->getMessage()}";
            }
        }

        if ($results['success']) {
            $this->message("{$results['success']} pages updated.");
        }
        if ($results['errors']) {
            foreach ($results['errors'] as $err) $this->error($err);
        }

        $this->wire('session')->redirect($this->wire('input')->url());
        return '';
    }

    // ── Configure ─────────────────────────────────────────────────────────────

    private function renderDashboard(): string
    {
        $this->wire('processHeadline', 'Collections');
        $this->fuel->set('title', 'Collections');
        $this->wire('breadcrumbs')->add(new Breadcrumb($this->wire('config')->urls->admin . 'collections/', 'Collections'));

        $allCollections = $this->perms->filterVisible($this->config->getCollections());
        usort($allCollections, fn($a, $b) => $a->order <=> $b->order);
        $adminUrl = $this->wire('config')->urls->admin;
        $wire = $this->wire();

        ob_start();
        include __DIR__ . '/views/dashboard.php';
        $content = ob_get_clean();

        ob_start();
        $collections = $allCollections; // for layout sidenav
        $current = null;
        include __DIR__ . '/views/layout.php';
        return ob_get_clean();
    }

    private function renderConfigure(): string
    {
        $this->wire('log')->save('collections', 'renderConfigure() called');
        $this->perms->can(CollectionPermissions::CAP_CONFIGURE)
            || throw new WirePermissionException($this->_('Not allowed to configure Collections'));

        // Handle POST actions
        if ($this->wire('input')->requestMethod('POST')) {
            if (!$this->wire('session')->CSRF->validate()) {
                $this->error('Invalid CSRF token');
            } else {
                $configureUrl = $this->wire('config')->urls->admin . 'collections/?configure=1';
                if ($this->wire('input')->get('save_collection')) {
                    $this->handleSaveCollection();
                    $this->wire('session')->redirect($configureUrl . '#tab-collections');
                } elseif ($this->wire('input')->get('save_global')) {
                    $this->handleSaveGlobal();
                    $this->wire('session')->redirect($configureUrl . '#tab-global');
                } elseif ($this->wire('input')->get('save_permissions')) {
                    $this->handleSavePermissions();
                    $this->wire('session')->redirect($configureUrl . '#tab-permissions');
                } elseif ($this->wire('input')->get('delete_collection')) {
                    $this->handleDeleteCollection();
                } elseif ($this->wire('input')->get('import_config')) {
                    $this->handleImportConfig();
                    $this->wire('session')->redirect($configureUrl . '#tab-import');
                } elseif ($this->wire('input')->get('toggle_status')) {
                    return $this->handleToggleStatus();
                } elseif ($this->wire('input')->get('create_api_key')) {
                    $this->handleCreateApiKey();
                } elseif ($this->wire('input')->get('delete_api_key')) {
                    $this->handleDeleteApiKey();
                }
            }
        }

        if ($this->wire('input')->get('export_config')) {
            $this->handleExportConfig();
        }

        // Render configure UI
        $adminUrl = $this->wire('config')->urls->admin;
        $this->wire('processHeadline', 'Configure Collections');
        $this->wire('breadcrumbs')->add(new Breadcrumb($adminUrl . 'collections/', 'Collections'));
        $this->fuel->set('title', 'Configure Collections');

        // Debug panel
        $this->addDebugInfo();

        $allCollections = $this->config->getCollections();
        $globalConfig   = $this->config->getGlobal();
        $matrix         = $this->config->getPermissionsMatrix();
        $templates      = $this->wire('templates')->find("flags=0, sort=name");
        $adminUrl       = $this->wire('config')->urls->admin;

        $allCols = $this->perms->filterVisible($allCollections);
        usort($allCols, fn($a, $b) => $a->order <=> $b->order);

        ob_start();
        $wire        = $this->wire();
        $collections = $allCollections;
        $config      = $this->config;
        include __DIR__ . '/views/configure.php';
        $content = ob_get_clean();

        ob_start();
        $wire        = $this->wire();
        $current     = null;
        $collections = $allCols;
        include __DIR__ . '/views/layout.php';
        return ob_get_clean();
    }

    private function addDebugInfo(): void
    {
        if (!$this->wire('config')->debug) return;
        $raw  = $this->wire('modules')->getConfig('Collections', 'collections_config');
        $info = [
            'raw_type'    => gettype($raw),
            'raw_length'  => is_string($raw) ? strlen($raw) : (is_array($raw) ? count($raw) : 'n/a'),
            'raw_preview' => substr(print_r($raw, true), 0, 300),
            'collections' => count($this->config->getCollections()),
            'global'      => $this->config->getGlobal(),
        ];
        $this->wire('log')->save('collections', 'DEBUG STATE: ' . json_encode($info));

        // Add visible debug bar to page if Tracy available
        if (class_exists('\Tracy\Debugger')) {
            \Tracy\Debugger::barDump($info, 'Collections Debug');
        }
    }

    // ── Form handlers ─────────────────────────────────────────────────────────

    private function handleToggleStatus(): string
    {
        $post     = $this->wire('input')->post;
        $pageId   = (int) $post->text('page_id');
        $action   = $post->text('action');

        if (!$pageId || !in_array($action, ['publish', 'unpublish'])) {
            return json_encode(['ok' => false, 'error' => 'Invalid request']);
        }

        $page = $this->wire('pages')->get($pageId);
        if (!$page->id) {
            return json_encode(['ok' => false, 'error' => 'Page not found']);
        }

        $page->of(false);
        if ($action === 'publish') {
            $page->removeStatus(Page::statusUnpublished);
        } else {
            $page->addStatus(Page::statusUnpublished);
        }
        $page->save(['quiet' => true]);

        $this->wire('log')->save('collections', "toggleStatus: page={$pageId} action={$action}");

        return json_encode(['ok' => true, 'action' => $action]);
    }

    private function handleSaveCollection(): void
    {
        $post = $this->wire('input')->post;
        $san  = $this->wire('sanitizer');
        $key = $san->pageName($post->text('key'));
        if (!$key) {
            $this->error('Collection key is required');
            return;
        }

        // Prevent keys that conflict with admin routing or API endpoints
        $reservedKeys = ['configure', 'export', 'export_config', 'save_collection',
                         'save_global', 'save_permissions', 'delete_collection',
                         'import_config', 'create_api_key', 'delete_api_key',
                         'collections', 'schema', 'bulk'];
        if (in_array($key, $reservedKeys, true)) {
            $this->error("Key '{$key}' is reserved and cannot be used. Choose a different key.");
            return;
        }

        $columns      = array_filter(array_map('trim', explode(',', $post->text('columns') ?? '')));
        $searchFields = array_filter(array_map('trim', explode(',', $post->text('searchFields') ?? '')));

        // Load existing to preserve roleAccess, columnLabels, columnTypes
        $existing = $this->config->getCollectionByKey($key);

        $collection = Collection::fromArray([
            'key'           => $key,
            'label'         => $san->text($post->text('label')),
            'template'      => $san->name($post->text('template')),
            'selector'      => $san->text($post->text('selector')),
            'icon'          => $san->text($post->text('icon') ?: 'fa-list'),
            'group'         => in_array($post->text('group'), ['content', 'taxonomy', 'custom']) ? $post->text('group') : 'content',
            'columns'       => $columns ?: ['title'],
            'searchFields'  => $searchFields ?: ['title'],
            'sortBy'        => $san->fieldName($post->text('sortBy') ?: 'title'),
            'sortDir'       => $post->text('sortDir') === 'desc' ? 'desc' : 'asc',
            'perPage'       => (int) $post->text('perPage'),
            'order'         => (int) $post->text('order'),
            'exportEnabled' => (bool) $post->text('exportEnabled'),
            'searchRelated' => (bool) $post->text('searchRelated'),
            'roleAccess'    => $existing ? $existing->roleAccess : [],
            'columnLabels'  => $existing ? $existing->columnLabels : [],
            'columnTypes'   => $existing ? $existing->columnTypes : [],
        ]);

        $this->config->saveCollection($collection);
        $this->message("Collection '{$key}' saved.");
        $this->wire('log')->save('collections', 'handleSaveCollection() saved key=' . $key);
    }

    private function handleSaveGlobal(): void
    {
        $post    = $this->wire('input')->post;
        $current = $this->config->getGlobal();

        $boolKeys = ['show_id', 'show_status', 'show_name', 'sticky_header', 'inline_status',
                     'quick_delete', 'confirm_batch_delete', 'live_search', 'export_csv',
                     'api_enabled', 'cache_enabled'];

        foreach ($boolKeys as $key) {
            $current[$key] = (bool) $post->text($key);
        }

        $current['default_per_page']  = max(5, min(500, (int) $post->text('default_per_page')));
        $current['date_format']       = $this->wire('sanitizer')->text($post->text('date_format') ?: 'M j, Y');
        $current['min_search_length'] = max(1, min(10, (int) $post->text('min_search_length')));
        $current['api_base']          = '/' . trim($this->wire('sanitizer')->text($post->text('api_base')), '/') . '/';
        $current['cache_ttl']         = max(60, min(86400, (int) $post->text('cache_ttl')));

        $this->config->saveGlobal($current);
        $this->message('Global settings saved.');
    }

    private function handleSavePermissions(): void
    {
        // PW's WireInput::array() doesn't handle nested arrays like roles[demo][]
        // Use $_POST directly and sanitize manually
        $roles  = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
        $matrix = $this->config->getPermissionsMatrix();
        $valid  = ['view', 'create', 'edit', 'delete', 'configure', 'export'];

        $matrix['roles'] = [];
        foreach ($roles as $roleName => $caps) {
            $roleName = $this->wire('sanitizer')->name($roleName);
            if (!$roleName) continue;
            $matrix['roles'][$roleName] = array_values(array_filter(
                (array) $caps,
                fn($c) => in_array($c, $valid, true)
            ));
        }

        $this->config->savePermissionsMatrix($matrix);
        $this->message('Permissions saved.');
    }

    private function handleDeleteCollection(): void
    {
        $key = $this->wire('sanitizer')->pageName($this->wire('input')->post('key'));
        $this->wire('log')->save('collections', 'handleDeleteCollection() key=' . ($key ?: '(empty)'));
        if (!$key) return;

        // Note: CSRF already validated in renderConfigure() before this method is called.
        $this->config->deleteCollection($key);
        $this->message("Collection '{$key}' deleted.");
        $this->wire('session')->redirect($this->wire('config')->urls->admin . 'collections/?configure=1');
    }

    private function handleExportConfig(): void
    {
        $data = $this->config->exportAll();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $name = 'collections-config-' . date('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$name}\"");
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    private function handleImportConfig(): void
    {
        $this->wire('session')->CSRF->validate()
            || throw new WireCSRFException('Invalid CSRF token');

        $file = $_FILES['config_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->error('Upload failed.');
            return;
        }

        $json = file_get_contents($file['tmp_name']);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->error('Invalid JSON file.');
            return;
        }

        $errors = $this->config->importAll($data);
        if ($errors) {
            foreach ($errors as $err) $this->error($err);
        } else {
            $this->message('Configuration imported successfully.');
        }
    }

    private function handleCollectionExport(Collection $collection, string $format): void
    {
        $params = QueryParams::fromInput($this->wire('input'));
        $params = new QueryParams(
            page:    1,
            perPage: 10000,
            search:  $params->search,
            sortBy:  $params->sortBy,
            sortDir: $params->sortDir,
            filters: $params->filters,
        );

        $query    = new CollectionQuery($this->wire('pages'));
        $result   = $query->execute($collection, $params);
        $exporter = new CollectionExporter();

        if ($format === 'json') {
            $exporter->exportJson($collection, $result);
        } else {
            $exporter->exportCsv($collection, $result);
        }
        exit;
    }

    private function handleCreateApiKey(): void
    {
        $post = $this->wire('input')->post;
        $name = $this->wire('sanitizer')->text($post->text('key_name'));
        $days = (int) $post->text('key_expiration');

        if (!$name) {
            $this->error('API key name is required.');
            return;
        }

        $rawKey = $this->config->createApiKey($name, $days > 0 ? $days : null);
        if ($rawKey) {
            // Store in session to show once
            $this->wire('session')->set('collections_new_api_key', $rawKey);
            $this->message("API key '{$name}' created.");
        } else {
            $this->error('Failed to create API key.');
        }
        $this->wire('session')->redirect($this->wire('config')->urls->admin . 'collections/?configure=1#tab-api');
    }

    private function handleDeleteApiKey(): void
    {
        $id = (int) $this->wire('input')->post->text('key_id');
        if ($id > 0) {
            $this->config->deleteApiKey($id);
            $this->message('API key deleted.');
        }
        $this->wire('session')->redirect($this->wire('config')->urls->admin . 'collections/?configure=1#tab-api');
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    private function setupAssets(): void
    {
        $config  = $this->wire('config');
        $baseUrl = $config->urls->siteModules . 'Collections/assets/';
        $v = filemtime(__DIR__ . '/assets/collections.js');
        $config->scripts->add("{$baseUrl}collections.js?v={$v}");
        // CSS is inlined via layout.php to guarantee AdminTheme override — not registered here.
    }
}