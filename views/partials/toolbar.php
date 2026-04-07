<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var Collection $collection */
/** @var QueryParams $params */
/** @var array $filterOptions */
?>
<div class="collections-toolbar">
    <div class="toolbar-search">
        <form method="get" action="" id="collections-search-form">
            <div class="uk-inline">
                <span class="uk-form-icon" uk-icon="search"></span>
                <?php if (!empty($_GET['col'])): ?>
            <input type="hidden" name="col" value="<?= htmlspecialchars($_GET['col']) ?>">
            <?php endif; ?>
            <input
                    type="search"
                    name="q"
                    value="<?= $wire->sanitizer->entities($params->search) ?>"
                    placeholder="<?= $wire->sanitizer->entities($collection->label) ?>…"
                    class="uk-input"
                    id="collections-search-input"
                    autocomplete="off"
                >
            </div>
            <?php foreach ($params->filters as $field => $val): ?>
            <input type="hidden" name="filter[<?= $wire->sanitizer->entities($field) ?>]" value="<?= $wire->sanitizer->entities($val) ?>">
            <?php endforeach; ?>
        </form>
    </div>

    <?php if (!empty($filterOptions)): ?>
    <div class="toolbar-filters">
        <?php foreach ($filterOptions as $field => $options): ?>
        <select name="filter[<?= $wire->sanitizer->entities($field) ?>]"
                class="uk-select collections-filter"
                data-field="<?= $wire->sanitizer->entities($field) ?>">
            <?php
            $pwField = $wire->fields->get($field);
            $fieldLabel = ($pwField && $pwField->label) ? $pwField->label : ucfirst(str_replace('_', ' ', $field));
            ?>
            <option value="">All <?= $wire->sanitizer->entities($fieldLabel) ?></option>
            <?php foreach ($options as $val => $label): ?>
            <option value="<?= $wire->sanitizer->entities($val) ?>"
                <?= isset($params->filters[$field]) && $params->filters[$field] == $val ? 'selected' : '' ?>>
                <?= $wire->sanitizer->entities((string)$label) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($params->search || !empty(array_filter($params->filters))): ?>
    <div class="toolbar-clear">
        <a href="?<?= !empty($_GET['col']) ? 'col=' . htmlspecialchars($_GET['col']) : '' ?>" class="uk-button uk-button-link uk-button-small">
            <i class="fa fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
</div>