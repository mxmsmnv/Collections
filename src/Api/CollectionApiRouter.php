<?php

namespace ProcessWire;

class CollectionApiRouter
{
    private CollectionApiHandler $handler;
    private $wire;
    private string $apiBase;
    private string $preParsedPath;

    public function __construct(
        $wire,
        private readonly CollectionConfig $config,
        string $apiBase = '',
        string $preParsedPath = '',
        private readonly string $authMode = 'session',
        ?array $apiKeyCaps = null
    ) {
        $this->wire          = $wire;
        $this->apiBase       = $apiBase;
        $this->preParsedPath = $preParsedPath;
        $perms               = new CollectionPermissions($wire->user, $config, $apiKeyCaps);
        $this->handler       = new CollectionApiHandler($config, $perms, $wire);
    }

    public function dispatch($input): CollectionApiResponse
    {
        $method = strtoupper($this->wire->input->requestMethod() ?: 'GET');

        // Use pre-parsed path if provided, otherwise parse from input
        if ($this->preParsedPath !== '') {
            $path = trim($this->preParsedPath, '/');
        } else {
            $url  = $input->url();
            $base = $this->apiBase ?: ('/' . trim($this->config->getGlobal()['api_base'], '/') . '/');
            $path = trim(substr($url, strlen($base)), '/');
        }

        $segments = $path ? explode('/', $path) : [];

        try {
            $this->validateRequestSecurity($method);
            $this->checkRateLimit($segments[0] ?? 'global');

            // 'collections' is the list endpoint — but only if there's no actual collection with that key
            if (count($segments) === 1 && $segments[0] === 'collections') {
                $maybeCol = $this->config->getCollectionByKey('collections');
                if (!$maybeCol) {
                    return $this->handler->handleList();
                }
                // Fall through to normal collection handling below
            }

            $key = $this->wire->sanitizer->pageName($segments[0] ?? '');
            if (!$key) {
                return CollectionApiResponse::error('NOT_FOUND', 'Missing collection key', 404);
            }

            if (($segments[1] ?? '') === 'export' && $method === 'GET') {
                $collection = $this->config->getCollectionByKey($key)
                    ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);
                $this->handler->handleExport($collection, $input);
                return new CollectionApiResponse(['ok' => true]);
            }

            if (($segments[1] ?? '') === 'schema' && $method === 'GET') {
                $collection = $this->config->getCollectionByKey($key)
                    ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);
                return $this->handler->handleSchema($collection);
            }

            if (count($segments) === 1 && $method === 'GET') {
                $collection = $this->config->getCollectionByKey($key)
                    ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);
                return $this->handler->handleIndex($collection, $input);
            }

            if (count($segments) === 1 && $method === 'POST') {
                $collection = $this->config->getCollectionByKey($key)
                    ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);
                $body = $this->getJsonBody();
                if (isset($body['action'])) {
                    return $this->handler->handleBulk($collection, $body);
                }
                return $this->handler->handleCreate($collection, $body);
            }

            if (($segments[1] ?? '') === 'bulk' && $method === 'POST') {
                $collection = $this->config->getCollectionByKey($key)
                    ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);
                return $this->handler->handleBulk($collection, $this->getJsonBody());
            }

            $id = isset($segments[1]) ? (int)$segments[1] : 0;
            if (!$id) {
                return CollectionApiResponse::error('NOT_FOUND', 'Invalid or missing ID', 404);
            }

            $collection = $this->config->getCollectionByKey($key)
                ?? throw new CollectionApiException("Collection '{$key}' does not exist", 404);

            return match($method) {
                'GET'    => $this->handler->handleShow($collection, $id),
                'PATCH',
                'PUT'    => $this->handler->handleUpdate($collection, $id, $this->getJsonBody()),
                'DELETE' => $this->handler->handleDelete($collection, $id),
                default  => CollectionApiResponse::error('NOT_FOUND', 'Method not allowed', 405),
            };

        } catch (CollectionApiException $e) {
            $codes = [400 => 'BAD_REQUEST', 401 => 'UNAUTHORIZED', 403 => 'FORBIDDEN',
                      404 => 'NOT_FOUND', 415 => 'UNSUPPORTED_MEDIA_TYPE',
                      422 => 'VALIDATION', 429 => 'RATE_LIMITED'];
            $code  = $codes[$e->getCode()] ?? 'SERVER_ERROR';
            return CollectionApiResponse::error($code, $e->getMessage(), $e->getCode() ?: 500);
        } catch (\WirePermissionException $e) {
            return CollectionApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        } catch (\Throwable $e) {
            $this->wire->log->save('collections', "API error: " . $e->getMessage());
            return CollectionApiResponse::error('SERVER_ERROR', 'Internal server error', 500);
        }
    }

    private function validateRequestSecurity(string $method): void
    {
        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) return;

        if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
            if ($contentType !== 'application/json') {
                throw new CollectionApiException('Write requests require application/json', 415);
            }
        }

        if ($this->authMode !== 'session') return;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $expectedOrigin = ($this->wire->config->https ? 'https://' : 'http://') . $this->wire->config->httpHost;
            if (!hash_equals(strtolower(rtrim($expectedOrigin, '/')), strtolower(rtrim($origin, '/')))) {
                throw new CollectionApiException('Cross-origin session write denied', 403);
            }
        }

        $token = $this->wire->session->CSRF->getToken();
        $received = $_SERVER['HTTP_X_COLLECTIONS_CSRF'] ?? '';
        if ($received === '' || !hash_equals((string)$token['value'], (string)$received)) {
            throw new CollectionApiException('Valid X-Collections-CSRF header required for session writes', 403);
        }
    }

    private function checkRateLimit(string $key): void
    {
        // WireInput has no ->ip property; use PW session IP or server fallback
        $ip = method_exists($this->wire->session, 'getIP')
            ? $this->wire->session->getIP()
            : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $cacheKey = 'collections_rate_' . hash('sha256', $ip . '_' . $key);
        $lockName = 'collections:' . substr(hash('sha256', $cacheKey), 0, 40);
        $db = $this->wire->database;
        $locked = false;

        try {
            $stmt = $db->prepare('SELECT GET_LOCK(?, 1)');
            $stmt->execute([$lockName]);
            $locked = (int)$stmt->fetchColumn() === 1;
            if (!$locked) throw new CollectionApiException('Rate limiter busy', 429);

            $count = (int)($this->wire->cache->get($cacheKey) ?? 0);
            if ($count >= 100) {
                throw new CollectionApiException('Rate limit exceeded', 429);
            }
            $this->wire->cache->save($cacheKey, $count + 1, 60);
        } finally {
            if ($locked) {
                $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$lockName]);
            }
        }
    }

    private function getJsonBody(): array
    {
        $raw  = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
