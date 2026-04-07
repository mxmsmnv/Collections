<?php

namespace ProcessWire;

class CollectionConfig
{
    // Custom DB tables instead of modules config
    private const TABLE_COLLECTIONS = 'collections_items';
    private const TABLE_GLOBAL      = 'collections_global';
    private const TABLE_PERMISSIONS = 'collections_permissions';
    private const TABLE_API_KEYS    = 'collections_api_keys';

    public function __construct(
        private $modules
    ) {
        $this->ensureTables();
    }

    // ── Table setup ───────────────────────────────────────────────────────────

    private function ensureTables(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $db = wire('database');
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_COLLECTIONS . "` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ckey`       VARCHAR(128) NOT NULL,
                `label`      VARCHAR(255) NOT NULL DEFAULT '',
                `data`       MEDIUMTEXT   NOT NULL,
                `sort_order` INT          NOT NULL DEFAULT 0,
                `created`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ckey` (`ckey`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_GLOBAL . "` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`     VARCHAR(128) NOT NULL,
                `value`    TEXT         NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_PERMISSIONS . "` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `role`     VARCHAR(128) NOT NULL,
                `caps`     TEXT         NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `role` (`role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_API_KEYS . "` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(255) NOT NULL DEFAULT '',
                `key_hash`   VARCHAR(64)  NOT NULL,
                `key_prefix` VARCHAR(8)   NOT NULL DEFAULT '',
                `caps`       TEXT         NOT NULL,
                `created`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires`    DATETIME     DEFAULT NULL,
                `last_used`  DATETIME     DEFAULT NULL,
                `use_count`  INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `key_hash` (`key_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'ensureTables error: ' . $e->getMessage());
        }
    }

    // ── Collections CRUD ─────────────────────────────────────────────────────

    public function getCollections(): array
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT `data` FROM `" . self::TABLE_COLLECTIONS . "` ORDER BY `sort_order` ASC, `ckey` ASC");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $result = [];
            foreach ($rows as $json) {
                $arr = json_decode($json, true);
                if ($arr) $result[] = Collection::fromArray($arr);
            }
            wire('log')->save('collections', 'getCollections from DB: count=' . count($result));
            return $result;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'getCollections error: ' . $e->getMessage());
            return [];
        }
    }

    public function getCollectionByKey(string $key): ?Collection
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT `data` FROM `" . self::TABLE_COLLECTIONS . "` WHERE `ckey` = ?");
            $stmt->execute([$key]);
            $json = $stmt->fetchColumn();
            if (!$json) return null;
            $arr = json_decode($json, true);
            return $arr ? Collection::fromArray($arr) : null;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'getCollectionByKey error: ' . $e->getMessage());
            return null;
        }
    }

    public function saveCollection(Collection $collection): void
    {
        try {
            $db   = wire('database');
            $json = json_encode($collection->toArray(), JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare("INSERT INTO `" . self::TABLE_COLLECTIONS . "` (`ckey`, `label`, `data`, `sort_order`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `data` = VALUES(`data`), `sort_order` = VALUES(`sort_order`)");
            $stmt->execute([$collection->key, $collection->label, $json, $collection->order]);
            wire('log')->save('collections', 'saveCollection OK: key=' . $collection->key);
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'saveCollection error: ' . $e->getMessage());
        }
    }

    public function saveCollections(array $collections): void
    {
        foreach ($collections as $c) {
            $this->saveCollection($c);
        }
    }

    public function deleteCollection(string $key): void
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("DELETE FROM `" . self::TABLE_COLLECTIONS . "` WHERE `ckey` = ?");
            $stmt->execute([$key]);
            wire('log')->save('collections', 'deleteCollection OK: key=' . $key);
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'deleteCollection error: ' . $e->getMessage());
        }
    }

    // ── Global settings ───────────────────────────────────────────────────────

    public function getGlobal(): array
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT `name`, `value` FROM `" . self::TABLE_GLOBAL . "`");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $defaults = $this->defaultGlobal();
            foreach ($rows as $k => $v) {
                $decoded = json_decode($v, true);
                $defaults[$k] = ($decoded !== null) ? $decoded : $v;
            }
            return $defaults;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'getGlobal error: ' . $e->getMessage());
            return $this->defaultGlobal();
        }
    }

    public function saveGlobal(array $settings): void
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("INSERT INTO `" . self::TABLE_GLOBAL . "` (`name`, `value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, json_encode($v)]);
            }
            wire('log')->save('collections', 'saveGlobal OK: keys=' . implode(',', array_keys($settings)));
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'saveGlobal error: ' . $e->getMessage());
        }
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    public function getPermissionsMatrix(): array
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT `role`, `caps` FROM `" . self::TABLE_PERMISSIONS . "`");
            $stmt->execute();
            $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $roles = [];
            foreach ($rows as $row) {
                $roles[$row['role']] = json_decode($row['caps'], true) ?? [];
            }
            return ['roles' => $roles];
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'getPermissionsMatrix error: ' . $e->getMessage());
            return ['roles' => []];
        }
    }

    public function savePermissionsMatrix(array $matrix): void
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("INSERT INTO `" . self::TABLE_PERMISSIONS . "` (`role`, `caps`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `caps` = VALUES(`caps`)");
            foreach ($matrix['roles'] ?? [] as $role => $caps) {
                $stmt->execute([$role, json_encode(array_values($caps))]);
            }
            wire('log')->save('collections', 'savePermissionsMatrix OK: roles=' . implode(',', array_keys($matrix['roles'] ?? [])));
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'savePermissionsMatrix error: ' . $e->getMessage());
        }
    }

    // ── Import / Export ───────────────────────────────────────────────────────

    public function exportAll(): array
    {
        return [
            'collections' => array_map(fn(Collection $c) => $c->toArray(), $this->getCollections()),
            'global'      => $this->getGlobal(),
            'permissions' => $this->getPermissionsMatrix(),
        ];
    }

    public function importAll(array $data): array
    {
        $errors = [];

        if (!empty($data['collections'])) {
            foreach ($data['collections'] as $item) {
                try {
                    $this->saveCollection(Collection::fromArray($item));
                } catch (\Throwable $e) {
                    $errors[] = 'Collection import error: ' . $e->getMessage();
                }
            }
        }
        if (!empty($data['global'])) {
            $this->saveGlobal($data['global']);
        }
        if (!empty($data['permissions'])) {
            $this->savePermissionsMatrix($data['permissions']);
        }

        return $errors;
    }

    // ── API Keys ───────────────────────────────────────────────────────────────

    /**
     * Generate a new API key. Returns the raw key (only shown once).
     */
    public function createApiKey(string $name, ?int $expirationDays = null, array $caps = ['view']): string
    {
        $rawKey    = 'col_' . bin2hex(random_bytes(24)); // 52 chars: col_ + 48 hex
        $keyHash   = hash('sha256', $rawKey);
        $keyPrefix = substr($rawKey, 0, 8);
        $expires   = $expirationDays ? date('Y-m-d H:i:s', time() + $expirationDays * 86400) : null;

        try {
            $db   = wire('database');
            $stmt = $db->prepare("INSERT INTO `" . self::TABLE_API_KEYS . "`
                (`name`, `key_hash`, `key_prefix`, `caps`, `expires`)
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $keyHash, $keyPrefix, json_encode($caps), $expires]);
            wire('log')->save('collections', "API key created: name={$name} prefix={$keyPrefix}");
            return $rawKey;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'createApiKey error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Validate an API key. Returns key data array or null.
     */
    public function validateApiKey(string $rawKey): ?array
    {
        $keyHash = hash('sha256', $rawKey);
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_API_KEYS . "` WHERE `key_hash` = ?");
            $stmt->execute([$keyHash]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return null;

            // Check expiration
            if ($row['expires'] && strtotime($row['expires']) < time()) return null;

            // Update last_used and use_count
            $db->prepare("UPDATE `" . self::TABLE_API_KEYS . "`
                SET `last_used` = NOW(), `use_count` = `use_count` + 1
                WHERE `id` = ?")->execute([$row['id']]);

            $row['caps'] = json_decode($row['caps'], true) ?: [];
            return $row;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'validateApiKey error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all API keys (without hashes, for display).
     */
    public function getApiKeys(): array
    {
        try {
            $db   = wire('database');
            $stmt = $db->prepare("SELECT `id`, `name`, `key_prefix`, `caps`, `created`, `expires`, `last_used`, `use_count`
                FROM `" . self::TABLE_API_KEYS . "` ORDER BY `created` DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['caps']    = json_decode($row['caps'], true) ?: [];
                $row['expired'] = $row['expires'] && strtotime($row['expires']) < time();
            }
            return $rows;
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'getApiKeys error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete an API key by ID.
     */
    public function deleteApiKey(int $id): void
    {
        try {
            $db = wire('database');
            $db->prepare("DELETE FROM `" . self::TABLE_API_KEYS . "` WHERE `id` = ?")->execute([$id]);
            wire('log')->save('collections', 'API key deleted: id=' . $id);
        } catch (\Throwable $e) {
            wire('log')->save('collections', 'deleteApiKey error: ' . $e->getMessage());
        }
    }

    // ── Drop tables (on uninstall) ────────────────────────────────────────────

    public function dropTables(): void
    {
        $db = wire('database');
        $db->exec("DROP TABLE IF EXISTS `" . self::TABLE_COLLECTIONS . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::TABLE_GLOBAL . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::TABLE_PERMISSIONS . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::TABLE_API_KEYS . "`");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function defaultGlobal(): array
    {
        return [
            'show_id'               => true,
            'show_status'           => true,
            'show_name'             => false,
            'inline_status'         => true,
            'quick_delete'          => false,
            'confirm_batch_delete'  => true,
            'default_per_page'      => 25,
            'date_format'           => 'M j, Y',
            'live_search'           => true,
            'min_search_length'     => 2,
            'export_csv'            => true,
            'api_enabled'           => false,
            'api_base'              => '/api/',
            'cache_enabled'         => false,
            'cache_ttl'             => 300,
        ];
    }
}