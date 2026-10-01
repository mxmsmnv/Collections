<?php

namespace ProcessWire;

/**
 * Collections
 *
 * ProcessWire module providing a unified interface for managing groups of pages
 * (collections) with configurable tables, search, filters, and REST API.
 *
 * @author  Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @link    https://github.com/mxmsmnv/Collections
 * @version 1.9.10
 */
class Collections extends WireData implements Module
{
    public const VERSION = '1.9.10';

    private ?CollectionConfig $collectionConfig = null;

    public static function getModuleInfo(): array
    {
        return [
            'title'    => __('Collections', __FILE__),
            'version'  => '1.9.10',
            'summary'  => __('Configurable page collections with table UI and REST API', __FILE__),
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
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
            $this->addHookAfter('ProcessPageListRender::getPageActions', $this, 'addPageListAction');
            return;
        }

        // Frontend: intercept API requests

        $global = $this->getConfig()->getGlobal();
        if (!($global['api_enabled'] ?? false)) return;

        $requestUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $pwRoot     = rtrim($this->wire('config')->urls->root, '/');
        $apiBase    = '/' . trim($global['api_base'] ?? '/api/', '/') . '/';
        $fullBase   = $pwRoot . $apiBase;

        $match = str_starts_with($requestUri, $fullBase);
        if (!$match && $pwRoot !== '') {
            $match = str_starts_with($requestUri, $apiBase);
        }

        if (!$match) return;

        // Only log in debug mode — per-request logging causes unbounded log growth in production
        if ($this->wire('config')->debug) {
            $this->wire('log')->save('collections', "API request: {$requestUri}");
        }
        $this->handleApiRequest($apiBase);
    }

    private function handleApiRequest(string $apiBase): void
    {
        if (!$this->consumePreAuthRateLimit()) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: 60');
            echo json_encode([
                'ok'      => false,
                'error'   => 'RATE_LIMITED',
                'message' => 'Rate limit exceeded.',
                'status'  => 429,
            ]);
            exit;
        }

        // Check auth: API key (Bearer token or ?api_key=), Basic Auth, or PW session
        $authMode   = 'session';
        $apiKeyCaps = null;
        $keyData    = null;
        $authenticated = false;

        // Explicit API credentials take precedence over any ambient browser session.
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $keyData = $this->getConfig()->validateApiKey($m[1]);
            if ($keyData) {
                $authenticated = true;
                $authMode = 'api_key';
                $apiKeyCaps = is_array($keyData['caps'] ?? null) ? $keyData['caps'] : [];
            }
        } elseif (!empty($_GET['api_key'])) {
            $keyData = $this->getConfig()->validateApiKey((string)$_GET['api_key']);
            if ($keyData) {
                $authenticated = true;
                $authMode = 'api_key';
                $apiKeyCaps = is_array($keyData['caps'] ?? null) ? $keyData['caps'] : [];
            }
        } elseif ($this->wire('user')->isLoggedin()) {
            $authenticated = true;
        } else {
            $user = $_SERVER['PHP_AUTH_USER'] ?? null;
            $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
            if ($user && $pass) {
                $u = $this->wire('session')->login($user, $pass);
                if ($u) {
                    $authenticated = true;
                    $authMode = 'basic';
                }
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

        if ($authMode === 'api_key' && $this->wire('config')->debug) {
            $name = $this->wire('sanitizer')->text((string)($keyData['name'] ?? ''));
            $prefix = $this->wire('sanitizer')->text((string)($keyData['key_prefix'] ?? ''));
            $this->wire('log')->save('collections', "API key auth: {$name} ({$prefix}...)");
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

        $router   = new CollectionApiRouter(
            $this->wire(),
            $this->getConfig(),
            $apiBase,
            $path,
            $authMode,
            $apiKeyCaps
        );
        $response = $router->dispatch($this->wire('input'));

        header('Content-Type: application/json; charset=utf-8');
        header('X-Collections-Version: ' . self::VERSION);

        if ($response->status !== 200) {
            http_response_code($response->status);
        }

        echo json_encode($response->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function consumePreAuthRateLimit(): bool
    {
        $ip = method_exists($this->wire('session'), 'getIP')
            ? $this->wire('session')->getIP()
            : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $cacheKey = 'collections_rate_auth_' . hash('sha256', (string)$ip);
        $lockName = 'collections:' . substr(hash('sha256', $cacheKey), 0, 40);
        $db = $this->wire('database');
        $locked = false;

        try {
            $stmt = $db->prepare('SELECT GET_LOCK(?, 1)');
            $stmt->execute([$lockName]);
            $locked = (int)$stmt->fetchColumn() === 1;
            if (!$locked) return false;

            $count = (int)($this->wire('cache')->get($cacheKey) ?? 0);
            if ($count >= 120) return false;
            $this->wire('cache')->save($cacheKey, $count + 1, 60);
            return true;
        } catch (\Throwable $e) {
            if ($this->wire('config')->debug) {
                $this->wire('log')->save('collections', 'Pre-auth rate limiter error: ' . $e->getMessage());
            }
            return false;
        } finally {
            if ($locked) {
                $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$lockName]);
            }
        }
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
        if (!($this->getConfig()->getGlobal()['show_page_edit_link'] ?? false)) return;

        $form = $event->return;
        $page = $event->object->getPage();
        $links = [];
        $perms = new CollectionPermissions($this->wire('user'), $this->getConfig());

        foreach ($this->getConfig()->getCollections() as $col) {
            if ($col->template !== $page->template->name) continue;
            if (!$perms->can(CollectionPermissions::CAP_VIEW, $col)) continue;

            // If collection has extra selector, check if page matches it
            if (!$col->containsPage($page)) continue;

            $safeKey   = $this->wire('sanitizer')->pageName($col->key);
            $url       = $this->wire('sanitizer')->entities($this->collectionsAdminUrl() . "?col={$safeKey}");
            $safeIcon  = $this->wire('sanitizer')->entities($this->wire('sanitizer')->name($col->icon));
            $safeLabel = $this->wire('sanitizer')->entities($col->label);
            $links[] = "<a href='{$url}' class='uk-button uk-button-default uk-button-small'>"
                . "<i class='fa {$safeIcon}'></i> " . sprintf($this->_('View in %s'), $safeLabel) . '</a>';
        }

        if ($links) {
            $field = $this->wire('modules')->get('InputfieldMarkup');
            $field->label = $this->_('Collections');
            $field->attr('id+name', 'collections_link');
            $field->value = implode(' ', $links);
            $form->insertBefore($field, $form->children->first());
        }
    }

    public function addPageListAction(HookEvent $event): void
    {
        $page    = $event->arguments('page');
        $actions = $event->return;
        $perms   = new CollectionPermissions($this->wire('user'), $this->getConfig());

        foreach ($this->getConfig()->getCollections() as $col) {
            if ($col->template !== $page->template->name) continue;
            if (!$perms->can(CollectionPermissions::CAP_VIEW, $col)) continue;

            // If collection has extra selector, check if page matches
            if (!$col->containsPage($page)) continue;

            $safeKey   = $this->wire('sanitizer')->pageName($col->key);
            $url       = $this->collectionsAdminUrl() . "?col={$safeKey}";
            $actions[] = [
                'cn'   => $this->_('Collection'),
                'name' => sprintf($this->_('Open in %s'), $this->wire('sanitizer')->text($col->label)),
                'url'  => $url,
            ];
        }

        $event->return = $actions;
    }

    private function collectionsAdminUrl(): string
    {
        $page = $this->wire('pages')->get('template=admin, process=ProcessCollections, include=all');
        if ($page && $page->id) return rtrim($page->url, '/') . '/';
        return rtrim($this->wire('config')->urls->admin, '/') . '/collections/';
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
            $existing->process = $this->wire('modules')->getModule('ProcessCollections', [
                'noPermissionCheck' => true,
                'noInit' => true,
            ]);
            $existing->removeStatus(Page::statusHidden);
            $existing->save();
            $this->message($this->_('Collections admin page updated.'));
        } else {
            $collectionsPage = new Page();
            $collectionsPage->template = 'admin';
            $collectionsPage->parent   = $admin;
            $collectionsPage->name     = 'collections';
            $collectionsPage->title    = 'Collections';
            $collectionsPage->process  = $this->wire('modules')->getModule('ProcessCollections', [
                'noPermissionCheck' => true,
                'noInit' => true,
            ]);
            $collectionsPage->save();
            $this->message(sprintf($this->_('Collections admin page created at: %s'), $collectionsPage->url));
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

        $this->message($this->_('Collections installed. Go to Admin > Collections to get started.'));
    }

    public function ___uninstall(): void
    {
        $page = $this->wire('pages')->get('name=collections, template=admin');
        if ($page->id) {
            $this->wire('pages')->delete($page, true);
        }

        // Drop custom tables
        $this->getConfig()->dropTables();

        $this->message($this->_('Collections uninstalled. Custom tables dropped.'));
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        // Fix admin page process on upgrade
        $admin    = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
        $existing = $this->wire('pages')->get('name=collections, template=admin, parent=' . $admin->id);
        if ($existing->id) {
            $existing->of(false);
            $existing->process = $this->wire('modules')->getModule('ProcessCollections', [
                'noPermissionCheck' => true,
                'noInit' => true,
            ]);
            $existing->removeStatus(Page::statusHidden);
            $existing->save();
            $this->message($this->_('Collections admin page process updated.'));
        } else {
            // Page missing - recreate
            $p = new Page();
            $p->template = 'admin';
            $p->parent   = $admin;
            $p->name     = 'collections';
            $p->title    = 'Collections';
            $p->process  = $this->wire('modules')->getModule('ProcessCollections', [
                'noPermissionCheck' => true,
                'noInit' => true,
            ]);
            $p->save();
            $this->message($this->_('Collections admin page recreated.'));
        }
    }

}
