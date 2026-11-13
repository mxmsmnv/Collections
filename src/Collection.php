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

    public function buildSelector(string $search = '', array $filters = [], bool $checkAccess = true): string
    {
        $parts = ["template={$this->template}"];

        if ($this->selector) {
            $parts[] = $this->selector;
        }

        // Add include=all so hidden/system taxonomy pages appear
        if (!str_contains($this->selector, 'include=')) {
            $parts[] = 'include=all';
        }
        if ($checkAccess) {
            $parts[] = 'check_access=1';
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

            // ProcessWire accepts a field list joined by | before a shared operator.
            $textFields = [];
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
                    $textFields[] = $f;
                }
                // Non-searchable types (Table, Matrix, Combo…) are silently skipped
            }

            // For Page ref fields: find matching referenced pages ONCE (not per-field)
            $pageRefParts = [];
            if ($pageRefFields) {
                $accessSelector = $checkAccess ? ', check_access=1' : '';
                $refMatches = wire('pages')->find("title%={$safeSearch}, include=all{$accessSelector}, limit=50");
                if ($refMatches->count()) {
                    $refIdRaw = $refMatches->each('id');
                    // each('id') returns array in newer PW versions, string "1|2|3" in older
                    $refIdStr = is_array($refIdRaw) ? implode('|', $refIdRaw) : (string)$refIdRaw;
                    foreach ($pageRefFields as $f) {
                        $pageRefParts[$f] = $refIdStr;
                    }
                }
            }

            $textSelector = $textFields ? implode('|', $textFields) . "%={$safeSearch}" : '';

            if ($textSelector !== '' && !$pageRefParts) {
                $parts[] = $textSelector;
            } elseif ($pageRefParts) {
                // Combine matches from text and reference fields before applying pagination.
                $baseSelector = implode(', ', $parts);
                $matchedIds = [];

                if ($textSelector !== '') {
                    $matchedIds = wire('pages')->findIDs($baseSelector . ', ' . $textSelector);
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
            } elseif ($pageRefFields) {
                $parts[] = 'id=0';
            }
        }

        foreach ($filters as $field => $value) {
            if ($value !== '' && $value !== null) {
                // Sanitize field name (only allow valid field name chars)
                $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field);
                if (!$safeField) continue;
                if (!in_array($safeField, $this->columns, true)) continue;
                // Sanitize value using PW's selectorValue sanitizer
                $safeValue = wire('sanitizer')->selectorValue((string)$value);
                $parts[] = "{$safeField}={$safeValue}";
            }
        }

        return implode(', ', $parts);
    }

    public function containsPage($page, bool $checkAccess = true): bool
    {
        if (!$page || !$page->id || $page->template->name !== $this->template) return false;
        $selector = 'id=' . (int)$page->id . ', ' . $this->buildSelector('', [], $checkAccess);
        return wire('pages')->count($selector) > 0;
    }
}
