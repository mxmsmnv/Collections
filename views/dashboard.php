<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var Collection[] $allCollections */
/** @var string $adminUrl */

// Group by group field
$groups = [];
foreach ($allCollections as $col) {
    $groups[$col->group][] = $col;
}
?>
<div class="collections-dashboard">

<?php if (empty($allCollections)): ?>
<div class="uk-alert uk-alert-warning">
    <p>No collections configured yet.
       <a href="<?= $adminUrl ?>collections/?configure=1">Configure your first collection</a>.
    </p>
</div>
<?php else: ?>

<?php foreach ($groups as $groupName => $cols): ?>
<div class="dashboard-group">
    <div class="dashboard-group-label"><?= strtoupper($wire->sanitizer->entities($groupName)) ?></div>
    <div class="dashboard-cards">
        <?php foreach ($cols as $col):
            $selector = $col->buildSelector();
            // Strip dynamic parts (search/filters) for count
            $countSel = 'template=' . $col->template . ', include=all';
            if ($col->selector) $countSel .= ', ' . $col->selector;
            $count = $wire->pages->count($countSel);
        ?>
        <a href="<?= $adminUrl ?>collections/?col=<?= $col->key ?>" class="dashboard-card">
            <div class="dashboard-card-label"><?= strtoupper($wire->sanitizer->entities($col->label)) ?></div>
            <div class="dashboard-card-count"><?= number_format($count) ?></div>
            <div class="dashboard-card-sub">Total</div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php if ($wire->user->hasPermission('collections-configure') || $wire->user->isSuperuser()): ?>
<div class="dashboard-footer">
    <a href="<?= $adminUrl ?>collections/?configure=1" class="ui-button ui-state-default ui-priority-secondary">
        <span class="ui-button-text"><i class="fa fa-cog"></i> Configure Collections</span>
    </a>
</div>
<?php endif; ?>
</div>
