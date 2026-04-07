<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var QueryResult $result */
/** @var QueryParams $params */

if ($result->totalPages <= 1) return;

$buildUrl = function(int $page) use ($params): string {
    $q = [];
    foreach (['col', 'sort', 'dir', 'q', 'per_page'] as $k) {
        if (!empty($_GET[$k])) $q[$k] = $_GET[$k];
    }
    if (!empty($_GET['filter']) && is_array($_GET['filter'])) {
        $q['filter'] = $_GET['filter'];
    }
    $q['page'] = $page;
    return '?' . http_build_query($q);
};
?>
<div class="collections-pagination">
    <ul>
        <?php if ($result->page > 1): ?>
        <li>
            <a href="<?= $buildUrl(1) ?>" title="First page"><i class="fa fa-angle-double-left"></i></a>
        </li>
        <li>
            <a href="<?= $buildUrl($result->page - 1) ?>" title="Previous page"><i class="fa fa-angle-left"></i></a>
        </li>
        <?php endif; ?>

        <?php
        $start = max(1, $result->page - 3);
        $end   = min($result->totalPages, $result->page + 3);
        if ($start > 1): ?>
        <li><a href="<?= $buildUrl(1) ?>">1</a></li>
        <?php if ($start > 2): ?>
        <li class="uk-disabled"><span>&hellip;</span></li>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li<?= $i === $result->page ? ' class="uk-active"' : '' ?>>
            <?php if ($i === $result->page): ?>
            <span><?= $i ?></span>
            <?php else: ?>
            <a href="<?= $buildUrl($i) ?>"><?= $i ?></a>
            <?php endif; ?>
        </li>
        <?php endfor; ?>

        <?php if ($end < $result->totalPages): ?>
        <?php if ($end < $result->totalPages - 1): ?>
        <li class="uk-disabled"><span>&hellip;</span></li>
        <?php endif; ?>
        <li><a href="<?= $buildUrl($result->totalPages) ?>"><?= $result->totalPages ?></a></li>
        <?php endif; ?>

        <?php if ($result->page < $result->totalPages): ?>
        <li>
            <a href="<?= $buildUrl($result->page + 1) ?>" title="Next page"><i class="fa fa-angle-right"></i></a>
        </li>
        <li>
            <a href="<?= $buildUrl($result->totalPages) ?>" title="Last page"><i class="fa fa-angle-double-right"></i></a>
        </li>
        <?php endif; ?>
    </ul>

</div>