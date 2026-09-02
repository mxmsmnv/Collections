<?php

declare(strict_types=1);

namespace ProcessWire;

final class TestSanitizer
{
    public function selectorValue(string $value): string
    {
        return str_replace(',', '\\,', $value);
    }
}

final class TestPages
{
    public string $lastSelector = '';

    public function count(string $selector): int
    {
        $this->lastSelector = $selector;
        return 1;
    }
}

final class TestConfig
{
    public bool $debug = false;
}

final class CollectionConfig
{
    public function getPermissionsMatrix(): array
    {
        return ['roles' => []];
    }
}

final class TestUser
{
    public array $roles = [];

    public function isSuperuser(): bool
    {
        throw new \RuntimeException('API-key authorization must not consult superuser state');
    }

    public function hasPermission(string $permission): bool
    {
        return false;
    }
}

$services = [
    'sanitizer' => new TestSanitizer(),
    'pages' => new TestPages(),
    'config' => new TestConfig(),
];

function wire(string $name)
{
    global $services;
    return $services[$name] ?? null;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new \RuntimeException($message);
}

require_once __DIR__ . '/../src/Collection.php';
require_once __DIR__ . '/../src/CollectionPermissions.php';
require_once __DIR__ . '/../src/CollectionExporter.php';

$collection = new Collection(
    key: 'products',
    label: 'Products',
    template: 'product',
    selector: 'parent=/shop/',
    icon: 'fa-list',
    group: 'content',
    columns: ['title', 'country'],
    searchFields: ['title'],
    sortBy: 'title',
    sortDir: 'asc',
    perPage: 25,
    roleAccess: [],
    columnLabels: [],
    columnTypes: [],
    exportEnabled: true,
    order: 0,
    searchRelated: false,
);

$selector = $collection->buildSelector('', ['country' => '42', 'private_notes' => 'yes']);
assertTrue(str_contains($selector, 'include=all'), 'Admin selector must include hidden/unpublished records');
assertTrue(str_contains($selector, 'check_access=1'), 'Admin selector must preserve ProcessWire access checks');
assertTrue(str_contains($selector, 'country=42'), 'Configured-column filter was removed');
assertTrue(!str_contains($selector, 'private_notes'), 'Unconfigured filter field was accepted');

$apiSelector = $collection->buildSelector('', [], false);
assertTrue(!str_contains($apiSelector, 'check_access=1'), 'API-key selector unexpectedly uses guest access');

$page = (object)['id' => 42, 'template' => (object)['name' => 'product']];
assertTrue($collection->containsPage($page), 'Valid collection member was rejected');
assertTrue(str_contains($services['pages']->lastSelector, 'id=42'), 'Membership selector omitted the page ID');
assertTrue(str_contains($services['pages']->lastSelector, 'parent=/shop/'), 'Membership selector omitted collection scope');

$perms = new CollectionPermissions(new TestUser(), new CollectionConfig(), ['view']);
assertTrue($perms->can(CollectionPermissions::CAP_VIEW, $collection), 'View-only API key lost view capability');
assertTrue(!$perms->can(CollectionPermissions::CAP_DELETE, $collection), 'View-only API key gained delete capability');
assertTrue($perms->isApiKeyAuth(), 'API-key authentication mode was not retained');

$exporter = new CollectionExporter();
$sanitizeCsvCell = new \ReflectionMethod($exporter, 'sanitizeCsvCell');
assertTrue($sanitizeCsvCell->invoke($exporter, '=1+1') === "'=1+1", 'CSV formula was not neutralized');
assertTrue($sanitizeCsvCell->invoke($exporter, 'ordinary') === 'ordinary', 'Ordinary CSV value was changed');

$moduleSource = file_get_contents(__DIR__ . '/../Collections.module.php');
$processSource = file_get_contents(__DIR__ . '/../ProcessCollections.module.php');
$handlerSource = file_get_contents(__DIR__ . '/../src/Api/CollectionApiHandler.php');
$routerSource = file_get_contents(__DIR__ . '/../src/Api/CollectionApiRouter.php');
$rendererSource = file_get_contents(__DIR__ . '/../src/CollectionRenderer.php');

assertTrue(!str_contains($moduleSource, 'setCurrentUser'), 'API-key path still changes the current user');
assertTrue(str_contains($processSource, 'handleToggleStatus(Collection $collection)'), 'Toggle handler lacks collection context');
assertTrue(str_contains($handlerSource, 'assertPageAccess($collection, $page, $cap)'), 'API bulk path lacks per-page access checks');
assertTrue(str_contains($routerSource, 'X-Collections-CSRF'), 'Session write CSRF header is not enforced');
assertTrue(str_contains($routerSource, "application/json"), 'Write content type is not enforced');
assertTrue(str_contains($handlerSource, 'Create is disabled for collections with an additional selector'), 'Scoped API creation is not blocked');
assertTrue(str_contains($rendererSource, '$safeLabel'), 'Configurable table headings are not escaped');
assertTrue(str_contains($rendererSource, "['http', 'https']"), 'Renderer does not restrict URL schemes');

echo "Security regression checks passed.\n";
