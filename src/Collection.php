<?php

namespace ProcessWire;

final class Collection
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $template,
        public readonly string $selector,
        public readonly string $icon,
        public readonly string $group,
        public readonly array  $columns,
        public readonly array  $searchFields,
        public readonly string $sortBy,
        public readonly string $sortDir,
        public readonly int    $perPage,
        public readonly array  $roleAccess,
        public readonly array  $columnLabels,
        public readonly array  $columnTypes,
        public readonly bool   $exportEnabled,
        public readonly int    $order,
        public readonly bool   $searchRelated,
    ) {}

    public function toArray(): array
    {
        return [
            'key'           => $this->key,
            'label'         => $this->label,
            'template'      => $this->template,
            'selector'      => $this->selector,
            'icon'          => $this->icon,
            'group'         => $this->group,
            'columns'       => $this->columns,
            'searchFields'  => $this->searchFields,
            'sortBy'        => $this->sortBy,
            'sortDir'       => $this->sortDir,
            'perPage'       => $this->perPage,
            'roleAccess'    => $this->roleAccess,
            'columnLabels'  => $this->columnLabels,
            'columnTypes'   => $this->columnTypes,
            'exportEnabled' => $this->exportEnabled,
            'order'         => $this->order,
            'searchRelated' => $this->searchRelated,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            key:           $data['key'] ?? '',
            label:         $data['label'] ?? '',
            template:      $data['template'] ?? '',
            // 'parent' ignored — removed from schema, kept here for backward compat reads
            selector:      $data['selector'] ?? '',
            icon:          $data['icon'] ?? 'fa-list',
            group:         $data['group'] ?? 'content',
            columns:       $data['columns'] ?? ['title'],
            searchFields:  $data['searchFields'] ?? ['title'],
            sortBy:        $data['sortBy'] ?? 'title',
            sortDir:       $data['sortDir'] ?? 'asc',
            perPage:       (int) ($data['perPage'] ?? 0),
            roleAccess:    $data['roleAccess'] ?? [],
            columnLabels:  $data['columnLabels'] ?? [],
            columnTypes:   $data['columnTypes'] ?? [],
            exportEnabled: (bool) ($data['exportEnabled'] ?? true),
            order:         (int) ($data['order'] ?? 0),
            searchRelated: (bool) ($data['searchRelated'] ?? true),
        );
    }

    /**
     * Whether the "Add" button should be shown for this collection.
     * Respects the template noParents setting:
     *   1  → no new pages allowed
     *  -1  → singleton; only allowed while no page using it exists
     */
    public function canAddNew(): bool
    {
        $template = wire('templates')->get($this->template);
        if (!$template || !$template->id) return false;

        $noParents = (int) $template->noParents;
        if ($noParents === 1) return false;
        if ($noParents === -1) {
            return $template->getNumPages() === 0;
        }
        return true;
    }

    public function buildSelector(string $search = '', array $filters = []): string
    {
        $parts = ["template={$this->template}"];

        if ($this->selector) {
            $parts[] = $this->selector;
        }

        // Add include=all so hidden/system taxonomy pages appear
        if (!str_contains($this->selector, 'include=')) {
            $parts[] = 'include=all';
        }

        if ($search !== '') {
            $fields = $this->searchFields ?: ['title'];

            // Sanitize search value
            $safeSearch = wire('sanitizer')->selectorValue($search);

            // Field types that cannot be searched with %= selector (causes Array-to-string in PW internals)
            $nonSearchable = ['FieldtypeTable', 'FieldtypeRepeaterMatrix', 'FieldtypeCombo',
                              'FieldtypeFile', 'FieldtypeImage', 'FieldtypeRepeater'];

            // Collect all searchable fields, optionally including Page ref columns
            $allSearchFields = $fields;
            if ($this->searchRelated) {
                foreach ($this->columns as $col) {
                    // Skip dot-notation sub-fields (address.city etc.)
                    if (str_contains($col, '.')) continue;
                    $field = wire('fields')->get($col);
                    if ($field && $field->type->className() === 'FieldtypePage' && !in_array($col, $allSearchFields)) {
                        $allSearchFields[] = $col;
                    }
                }
            }

            // Build text field OR parts (these can use | safely)
            $textOrParts = [];
            $pageRefFields = []; // fields of type FieldtypePage that need ref-title search

            foreach ($allSearchFields as $f) {
                // Skip dot-notation sub-fields
                if (str_contains($f, '.')) continue;
                $field = wire('fields')->get($f);
                if (!$field) continue; // unknown field — skip to avoid PW selector errors
                $ftName = $field->type->className();
                if ($ftName === 'FieldtypePage') {
                    $pageRefFields[] = $f;
                } elseif (!in_array($ftName, $nonSearchable, true)) {
                    $textOrParts[] = "{$f}%={$safeSearch}";
                }
                // Non-searchable types (Table, Matrix, Combo…) are silently skipped
            }

            // For Page ref fields: find matching referenced pages ONCE (not per-field)
            $pageRefParts = [];
            if ($pageRefFields) {
                $refMatches = wire('pages')->find("title%={$safeSearch}, include=all, limit=50");
                if ($refMatches->count()) {
                    $refIdRaw = $refMatches->each('id');
                    // each('id') returns array in newer PW versions, string "1|2|3" in older
                    $refIdStr = is_array($refIdRaw) ? implode('|', $refIdRaw) : (string)$refIdRaw;
                    foreach ($pageRefFields as $f) {
                        $pageRefParts[$f] = $refIdStr;
                    }
                }
            }

            // PW can't do cross-field OR with multi-value page refs in one group
            // Solution: use text OR group, then add each page ref as OR-group
            // Final: (title%=x), (country=id1|id2), (brand=id1|id2) — but these are AND!
            // PW true OR requires: https://processwire.com/docs/selectors/#or-groups
            // or-group syntax: selector1, selector2, or_selector1

            if ($textOrParts && !$pageRefParts) {
                // Simple case: only text fields
                $parts[] = '(' . implode('|', $textOrParts) . ')';
            } elseif ($pageRefParts) {
                // Complex case: need to combine text + page ref with OR
                // Find all page IDs matching ANY condition, then use id=x|y|z selector
                $baseSelector = "template={$this->template}";
                if ($this->selector) $baseSelector .= ", {$this->selector}";
                $baseSelector .= ", include=all";

                $matchedIds = [];

                // Search by text fields — no limit here, paging is applied later in execute()
                if ($textOrParts) {
                    $textSelector = $baseSelector . ', (' . implode('|', $textOrParts) . ')';
                    $textMatches  = wire('pages')->findIDs($textSelector);
                    $matchedIds   = array_merge($matchedIds, $textMatches);
                }

                // Search by each page ref field
                foreach ($pageRefParts as $refField => $refIds) {
                    $refSelector = $baseSelector . ", {$refField}=" . $refIds;
                    $refMatches  = wire('pages')->findIDs($refSelector);
                    $matchedIds  = array_merge($matchedIds, $refMatches);
                }

                $matchedIds = array_unique($matchedIds);

                if ($matchedIds) {
                    // Safety cap: very large ID lists slow down MySQL; 5000 is generous
                    if (count($matchedIds) > 5000) $matchedIds = array_slice($matchedIds, 0, 5000);
                    $parts[] = 'id=' . implode('|', $matchedIds);
                } else {
                    $parts[] = 'id=0'; // No results
                }
            }
        }

        foreach ($filters as $field => $value) {
            if ($value !== '' && $value !== null) {
                // Sanitize field name (only allow valid field name chars)
                $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field);
                if (!$safeField) continue;
                // Sanitize value using PW's selectorValue sanitizer
                $safeValue = wire('sanitizer')->selectorValue((string)$value);
                $parts[] = "{$safeField}={$safeValue}";
            }
        }

        return implode(', ', $parts);
    }
}