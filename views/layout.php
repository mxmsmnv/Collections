<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var array $collections */
/** @var Collection|null $current */
/** @var string $content */

$adminUrl = $wire->config->urls->admin;
$groups   = [];

foreach ($collections as $c) {
    $groups[$c->group][] = $c;
}
?>
<div class="collections-layout" id="collections-app">
<div class="collections-sidenav" id="collections-sidenav"><script>if(localStorage.getItem('collections_sidenav_collapsed')==='1')document.currentScript.parentElement.classList.add('collapsed');</script>
        <div class="sidenav-header">
            <span class="sidenav-header-text">Collections</span>
            <button class="sidenav-toggle" id="sidenav-toggle" title="Toggle sidebar"><i class="fa fa-bars"></i></button>
        </div>
        <nav class="sidenav-nav">
            <?php foreach ($groups as $group => $cols): ?>
            <div class="sidenav-group">
                <span class="sidenav-group-label"><?= ucfirst($group) ?></span>
                <?php foreach ($cols as $col): ?>
                <a href="<?= $adminUrl ?>collections/?col=<?= $col->key ?>"
                   class="sidenav-item<?= ($current && $current->key === $col->key) ? ' active' : '' ?>"
                   title="<?= $wire->sanitizer->entities($col->label) ?>">
                    <i class="fa <?= $wire->sanitizer->entities($col->icon) ?>"></i>
                    <span class="sidenav-item-label"><?= $wire->sanitizer->entities($col->label) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
        <?php if ($wire->user->hasPermission('collections-configure') || $wire->user->isSuperuser()): ?>
        <div class="sidenav-footer">
            <a href="<?= $adminUrl ?>collections/?configure=1" class="sidenav-configure<?= (!$current) ? ' active' : '' ?>" title="Configure">
                <i class="fa fa-cog"></i><span class="sidenav-item-label"> Configure</span>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <div class="collections-main" id="collections-main">
        <?= $content ?>
    </div>
</div>

<?php
// Inline CSS injected after AdminTheme to guarantee override
$cssFile = dirname(__DIR__) . '/assets/collections.css';
if (file_exists($cssFile)) {
    echo '<style id="collections-inline">' . file_get_contents($cssFile) . '</style>';
}
?>