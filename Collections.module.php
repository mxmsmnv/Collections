<?php

namespace ProcessWire;

/**
 * Collections
 *
 * ProcessWire module providing a unified interface for managing groups of pages
 * (collections) with configurable tables, search, filters, and REST API.
 *
 * @author  Maxim Semenov <maxim@smnv.org>
 * @link    https://github.com/mxmsmnv/Collections
 * @version 1.9.1
 */
class Collections extends WireData implements Module
{
    public const VERSION = '1.9.1';

    private ?CollectionConfig $collectionConfig = null;

    public static function getModuleInfo(): array
    {
        return [
            'title'    => 'Collections',
            'version'  => 191,
            'summary'  => 'Configurable page collections with table UI and REST API',
            'author'   => 'Maxim Semenov',
            'href'     => 'https://github.com/mxmsmnv/Collections',
            'requires' => ['ProcessWire>=3.0.244', 'PHP>=8.2'],
            'installs' => ['ProcessCollections'],
            'autoload' => true,
            'singular' => true,
            'icon'     => 'list',
        ];
    }

    public function init(): void
    {
        $this->autoload();

        // Cache invalidation hooks — needed everywhere (frontend saves too)
        $this->addHookAfter('Pages::saved', $this, 'invalidateCache');
        $this->addHookAfter('Pages::deleted', $this, 'invalidateCache');
    }

    /**
     * ready() is called after all modules are init'd but before page render.
     * At this point $page is fully resolved — safe to check template.
     * We register admin UI hooks here, and intercept API requests on frontend.
     */
    public function ready(): void
    {
        $isAdmin = $this->wire('page') && $this->wire('page')->template == 'admin';

        // Admin-only hooks — $page is fully known here (unlike in init())
        if ($isAdmin) {
            $this->addHookAfter('ProcessPageEdit::buildForm', $this, 'addCollectionLink');
            $this->addHookAfter('ProcessPageList::getPageActions', $this, 'addPageListAction');
            return;
        }

        // Frontend: intercept API requests

        $global = $this->getConfig()->getGlobal();
        if (!($global['api_enabled'] ?? true)) return;

        $requestUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $pwRoot     = rtrim($this->wire('config')->urls->root, '/');
        $apiBase    = '/' . trim($global['api_base'] ?? '/api/', '/') . '/';
        $fullBase   = $pwRoot . $apiBase;

        $match = str_starts_with($requestUri, $fullBase);
        if (!$match && $pwRoot !== '') {
            $match = str_starts_with($requestUri, $apiBase);
        }

        if (!$match) return;

        // Only log actual API hits
        $this->wire('log')->save('collections', "API request: {$requestUri}");
        $this->handleApiRequest($apiBase);
    }

    private function handleApiRequest(string $apiBase): void
    {
        // Check auth: API key (Bearer token or ?api_key=), Basic Auth, or PW session
        $apiKeyAuth = false;

        if (!$this->wire('user')->isLoggedin()) {
            $authenticated = false;
            $keyData = null;

            // 1. Try API key from Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
                $keyData = $this->getConfig()->validateApiKey($m[1]);
                if ($keyData) {
                    $authenticated = true;
                    $apiKeyAuth = true;
                }
            }

            // 2. Try API key from query param
            if (!$authenticated && !empty($_GET['api_key'])) {
                $keyData = $this->getConfig()->validateApiKey($_GET['api_key']);
                if ($keyData) {
                    $authenticated = true;
                    $apiKeyAuth = true;
                }
            }

            // 3. Try HTTP Basic Auth
            if (!$authenticated) {
                $user = $_SERVER['PHP_AUTH_USER'] ?? null;
                $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
                if ($user && $pass) {
                    $u = $this->wire('session')->login($user, $pass);
                    if ($u) $authenticated = true;
                }
            }

            if (!$authenticated) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'ok'      => false,
                    'error'   => 'UNAUTHORIZED',
                    'message' => 'Authentication required.',
                    'status'  => 401,
                ]);
                exit;
            }

            // For API key auth: temporarily set superuser so permission checks pass
            if ($apiKeyAuth) {
                $su = $this->wire('users')->get('name=superuser, include=all');
                if (!$su || !$su->id) $su = $this->wire('users')->get($this->wire('config')->superUserPageID);
                if ($su && $su->id) {
                    $this->wire('users')->setCurrentUser($su);
                }
                $this->wire('log')->save('collections', "API key auth: {$keyData['name']} ({$keyData['key_prefix']}...)");
            }
        }

        // Parse the path from REQUEST_URI relative to apiBase
        $requestUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $pwRoot     = rtrim($this->wire('config')->urls->root, '/');
        $fullBase   = $pwRoot . $apiBase;

        // Extract path after base
        $path = '';
        if (str_starts_with($requestUri, $fullBase)) {
            $path = substr($requestUri, strlen($fullBase));
        } elseif (str_starts_with($requestUri, $apiBase)) {
            $path = substr($requestUri, strlen($apiBase));
        }

        $router   = new CollectionApiRouter($this->wire(), $this->getConfig(), $apiBase, $path);
        $response = $router->dispatch($this->wire('input'));

        header('Content-Type: application/json; charset=utf-8');
        header('X-Collections-Version: ' . self::VERSION);

        if ($response->status !== 200) {
            http_response_code($response->status);
        }

        echo json_encode($response->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function autoload(): void
    {
        $srcDir = __DIR__ . '/src/';
        $files = [
            $srcDir . 'QueryParams.php',
            $srcDir . 'Collection.php',
            $srcDir . 'CollectionConfig.php',
            $srcDir . 'CollectionQuery.php',
            $srcDir . 'CollectionPermissions.php',
            $srcDir . 'CollectionRenderer.php',
            $srcDir . 'CollectionExporter.php',
            $srcDir . 'Api/CollectionApiResponse.php',
            $srcDir . 'Api/CollectionApiHandler.php',
            $srcDir . 'Api/CollectionApiRouter.php',
        ];
        foreach ($files as $file) {
            require_once $file;
        }
    }

    public function getConfig(): CollectionConfig
    {
        if (!$this->collectionConfig) {
            $this->autoload();
            $this->collectionConfig = new CollectionConfig($this->wire('modules'));
        }
        return $this->collectionConfig;
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────

    public function invalidateCache(HookEvent $event): void
    {
        if (!($this->getConfig()->getGlobal()['cache_enabled'] ?? false)) return;

        $page = $event->arguments(0);
        if (!$page instanceof Page) return;

        foreach ($this->getConfig()->getCollections() as $collection) {
            if ($collection->template === $page->template->name) {
                $this->wire('cache')->deleteFor('Collections', "collections_api_{$collection->key}_*");
            }
        }
    }

    public function addCollectionLink(HookEvent $event): void
    {
        $form = $event->return;
        $page = $event->object->getPage();
        $links = [];

        foreach ($this->getConfig()->getCollections() as $col) {
            if ($col->template !== $page->template->name) continue;

            // If collection has extra selector, check if page matches it
            if (!empty($col->selector)) {
                $fullSelector = "id={$page->id}, template={$col->template}, {$col->selector}, include=all";
                $match = $this->wire('pages')->count($fullSelector);
                if (!$match) continue;
            }

            $adminUrl = $this->wire('config')->urls->admin;
            $url      = $adminUrl . "collections/?col={$col->key}";
            $links[] = "<a href='{$url}' class='uk-button uk-button-default uk-button-small'>"
                . "<i class='fa {$col->icon}'></i> View in {$col->label}</a>";
        }

        if ($links) {
            $field = $this->wire('modules')->get('InputfieldMarkup');
            $field->label = 'Collections';
            $field->attr('id+name', 'collections_link');
            $field->value = implode(' ', $links);
            $form->insertBefore($field, $form->children->first());
        }
    }

    public function addPageListAction(HookEvent $event): void
    {
        $page    = $event->arguments('page');
        $actions = $event->return;

        foreach ($this->getConfig()->getCollections() as $col) {
            if ($col->template !== $page->template->name) continue;

            // If collection has extra selector, check if page matches
            if (!empty($col->selector)) {
                $fullSelector = "id={$page->id}, template={$col->template}, {$col->selector}, include=all";
                if (!$this->wire('pages')->count($fullSelector)) continue;
            }

            $url       = $this->wire('config')->urls->admin . "collections/?col={$col->key}";
            $actions[] = [
                'cn'   => 'Collection',
                'name' => "Open in {$col->label}",
                'url'  => $url,
            ];
        }

        $event->return = $actions;
    }

    // ── Install / Uninstall ───────────────────────────────────────────────────

    public function ___install(): void
    {
        require_once __DIR__ . '/install/permissions.php';
        installCollectionsPermissions($this->wire());

        // Create /admin/collections/ page
        $admin = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
        $existing = $this->wire('pages')->get('name=collections, template=admin, parent=' . $admin->id);

        if ($existing->id) {
            $existing->of(false);
            $existing->process = $this->wire('modules')->get('ProcessCollections');
            $existing->removeStatus(Page::statusHidden);
            $existing->save();
            $this->message('Collections admin page updated.');
        } else {
            $collectionsPage = new Page();
            $collectionsPage->template = 'admin';
            $collectionsPage->parent   = $admin;
            $collectionsPage->name     = 'collections';
            $collectionsPage->title    = 'Collections';
            $collectionsPage->process  = $this->wire('modules')->get('ProcessCollections');
            $collectionsPage->save();
            $this->message('Collections admin page created at: ' . $collectionsPage->url);
        }

        // Enable URL segments on admin template so /collections/key/ works
        $adminTemplate = $this->wire('templates')->get('admin');
        if ($adminTemplate && !$adminTemplate->urlSegments) {
            $adminTemplate->urlSegments = true;
            $adminTemplate->save();
        }

        $this->getConfig()->saveCollections([]);
        $this->getConfig()->saveGlobal([]);
        $this->getConfig()->savePermissionsMatrix([]);

        $this->message('Collections installed. Go to Admin > Collections to get started.');
    }

    public function ___uninstall(): void
    {
        $page = $this->wire('pages')->get('name=collections, template=admin');
        if ($page->id) {
            $this->wire('pages')->delete($page, true);
        }

        // Drop custom tables
        $this->getConfig()->dropTables();

        $this->message('Collections uninstalled. Custom tables dropped.');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        // Fix admin page process on upgrade
        $admin    = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
        $existing = $this->wire('pages')->get('name=collections, template=admin, parent=' . $admin->id);
        if ($existing->id) {
            $existing->of(false);
            $existing->process = $this->wire('modules')->get('ProcessCollections');
            $existing->removeStatus(Page::statusHidden);
            $existing->save();
            $this->message('Collections admin page process updated.');
        } else {
            // Page missing - recreate
            $p = new Page();
            $p->template = 'admin';
            $p->parent   = $admin;
            $p->name     = 'collections';
            $p->title    = 'Collections';
            $p->process  = $this->wire('modules')->get('ProcessCollections');
            $p->save();
            $this->message('Collections admin page recreated.');
        }
    }

}