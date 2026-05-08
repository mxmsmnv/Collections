<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var Collection $collection */
/** @var QueryResult $result */
/** @var QueryParams $params */
/** @var array $globalConfig */
/** @var CollectionPermissions $perms */
/** @var CollectionRenderer $renderer */
/** @var string $adminUrl */

$canCreate = $perms->can(CollectionPermissions::CAP_CREATE, $collection);
$canExport = $perms->can(CollectionPermissions::CAP_EXPORT, $collection);
$canEdit   = $perms->can(CollectionPermissions::CAP_EDIT, $collection);
?>

<div class="collections-page-header">
    <div class="header-title">
        <i class="fa <?= $wire->sanitizer->entities($collection->icon) ?>"></i>
        <h1><?= $wire->sanitizer->entities($collection->label) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($canExport && $collection->exportEnabled): ?>
        <div class="uk-inline">
            <button class="ui-button ui-state-default ui-priority-secondary" type="button">
                <span class="ui-button-text"><i class="fa fa-download"></i> Export</span>
            </button>
            <div uk-dropdown="mode: click; pos: bottom-right">
                <ul class="uk-nav uk-dropdown-nav">
                    <li><a href="?col=<?= urlencode($collection->key) ?>&export=csv&q=<?= urlencode($params->search) ?>" class="collections-export" data-format="csv">
                        <i class="fa fa-file-text-o"></i> Export CSV
                    </a></li>
                    <li><a href="?col=<?= urlencode($collection->key) ?>&export=json&q=<?= urlencode($params->search) ?>" class="collections-export" data-format="json">
                        <i class="fa fa-file-code-o"></i> Export JSON
                    </a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canCreate): ?>
        <?php
        $addTemplate = $wire->templates->get($collection->template);
        $addUrl = $adminUrl . 'page/add/';
        if ($addTemplate && $addTemplate->id) $addUrl .= '?template_id=' . $addTemplate->id;
        ?>
        <a href="<?= $addUrl ?>" class="ui-button ui-state-default">
            <span class="ui-button-text"><i class="fa fa-plus"></i> Add <?= $wire->sanitizer->entities($collection->label) ?></span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/toolbar.php'; ?>

<?php
// Build the effective selector for display
$displaySelector = $collection->buildSelector($params->search, array_filter($params->filters ?? []));
// Strip dynamic search/filter parts for the "base" selector note
$baseSelector = $collection->buildSelector();
// Effective sort (URL params override collection defaults)
$effectiveSortBy  = ($params->sortBy && $wire->fields->get($params->sortBy)) ? $params->sortBy : ($collection->sortBy ?: 'title');
$effectiveSortDir = ($params->sortBy && $wire->fields->get($params->sortBy)) ? $params->sortDir : ($collection->sortDir ?: 'asc');
?>
<details class="collections-selector-note">
    <summary>
        <i class="fa fa-info-circle"></i>
        Selector: <code><?= htmlspecialchars($baseSelector) ?></code>
        &nbsp;·&nbsp;
        Sort: <code><?= htmlspecialchars($effectiveSortBy) ?> <?= strtoupper(htmlspecialchars($effectiveSortDir)) ?></code>
    </summary>
    <div class="selector-note-body">
        <p><strong>Base selector:</strong> <code><?= htmlspecialchars($baseSelector) ?></code></p>
        <?php if ($params->search || !empty(array_filter($params->filters ?? []))): ?>
        <p><strong>Current query:</strong> <code><?= htmlspecialchars($displaySelector) ?></code></p>
        <?php endif; ?>
        <p><strong>Sort:</strong> <code><?= htmlspecialchars($effectiveSortBy) ?> <?= strtoupper(htmlspecialchars($effectiveSortDir)) ?></code></p>
        <p class="selector-note-hint">This selector defines what pages appear in this collection.</p>
    </div>
</details>

<div id="collections-result">
    <?= $renderer->renderTable($result, $params) ?>
</div>
<div id="collections-pagination">
    <?php include __DIR__ . '/partials/pagination.php'; ?>
</div>

<div class="collections-statusbar">
    <?php if ($result->total > 0): ?>
    Showing <?= number_format(($params->page - 1) * $result->perPage + 1) ?>–<?= number_format(min($params->page * $result->perPage, $result->total)) ?>
    of <?= number_format($result->total) ?> items
    <?php else: ?>
    No items found
    <?php endif; ?>
    <?php if ($params->search): ?>
    — filtered by "<strong><?= $wire->sanitizer->entities($params->search) ?></strong>"
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="collections-bulk-bar" id="collections-bulk-bar" style="display:none">
    <span class="bulk-count"><span id="bulk-count-num">0</span> selected</span>
    <button class="ui-button ui-state-default" data-bulk-action="publish" type="button">
        <span class="ui-button-text"><i class="fa fa-eye"></i> Publish</span>
    </button>
    <button class="ui-button ui-state-default ui-priority-secondary" data-bulk-action="unpublish" type="button">
        <span class="ui-button-text"><i class="fa fa-eye-slash"></i> Unpublish</span>
    </button>
    <?php if ($perms->can(CollectionPermissions::CAP_DELETE, $collection)): ?>
    <button class="ui-button ui-state-default" data-bulk-action="delete" type="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">
        <span class="ui-button-text"><i class="fa fa-trash"></i> Delete</span>
    </button>
    <?php endif; ?>
    <button class="ui-button ui-state-default ui-priority-secondary" id="collections-bulk-cancel" type="button">
        <span class="ui-button-text">Cancel</span>
    </button>
</div>
<?php endif; ?>

<?php
// Render CSRF input ONCE — getTokenName/Value called separately would reset the token
$csrfInput = $wire->session->CSRF->renderInput();
// Extract name and value from the rendered input for use in JS
preg_match('/name="([^"]+)"/', $csrfInput, $nameMatch);
preg_match('/value="([^"]+)"/', $csrfInput, $valueMatch);
$csrfName  = $nameMatch[1]  ?? '';
$csrfValue = $valueMatch[1] ?? '';
?>
<div id="collections-csrf" style="display:none"><?= $csrfInput ?></div>

<script>
window.CollectionsConfig = {
    key: <?= json_encode($collection->key) ?>,
    liveSearch: <?= json_encode($globalConfig['live_search'] ?? true) ?>,
    minSearchLength: <?= (int)($globalConfig['min_search_length'] ?? 2) ?>,
    confirmBatchDelete: <?= json_encode($globalConfig['confirm_batch_delete'] ?? true) ?>,
    apiBase: <?= json_encode($globalConfig['api_base'] ?? '/api/') ?>,
    csrfName: <?= json_encode($csrfName) ?>,
    csrfValue: <?= json_encode($csrfValue) ?>,
};
</script>
</script>