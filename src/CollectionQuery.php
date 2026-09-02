<?php

namespace ProcessWire;

class CollectionQuery
{
    public function __construct(
        private $pages
    ) {}

    public function execute(Collection $collection, QueryParams $params, bool $checkAccess = true): QueryResult
    {
        $selector = $collection->buildSelector($params->search, $params->filters, $checkAccess);

        // Sort - accept any valid field name
        $sortField = $params->sortBy;
        $systemSortable = ['id', 'name', 'status', 'created', 'modified', 'sort', 'title'];
        if (!in_array($sortField, $collection->columns, true)
            && !in_array($sortField, $systemSortable, true)) {
            $configuredSort = $collection->sortBy ?: 'title';
            $sortField = in_array($configuredSort, $collection->columns, true)
                || in_array($configuredSort, $systemSortable, true)
                ? $configuredSort
                : 'title';
        }
        $sortDir = strtolower((string) $params->sortDir) === 'desc' ? '-' : '';
        $selector .= ", sort={$sortDir}{$sortField}";

        // Pagination
        $perPage = $params->perPage > 0 ? $params->perPage : ($collection->perPage > 0 ? $collection->perPage : 25);
        $offset  = ($params->page - 1) * $perPage;
        $selector .= ", limit={$perPage}, start={$offset}";

        $pageArray = $this->pages->find($selector);

        return new QueryResult(
            items:      $pageArray,
            total:      $pageArray->getTotal(),
            page:       $params->page,
            perPage:    $perPage,
            totalPages: (int) ceil($pageArray->getTotal() / $perPage),
        );
    }

    public function getFilterOptions(Collection $collection, string $field): array
    {
        $pwField = wire('fields')->get($field);
        if (!$pwField) return [];

        $ftClass = $pwField->type->className();

        // For Page reference fields: inspect only collection pages the current
        // ProcessWire user may access. Direct field-table SQL would bypass page access.
        if ($ftClass === 'FieldtypePage') {
            $selector = $collection->buildSelector() . ', limit=500';
            $options = [];
            foreach ($this->pages->find($selector) as $page) {
                $value = $page->get($field);
                $refs = $value instanceof PageArray ? $value : [$value];
                foreach ($refs as $ref) {
                    if ($ref instanceof Page && $ref->id && $ref->viewable()) {
                        $options[$ref->id] = $ref->title ?: $ref->name;
                    }
                }
            }
            asort($options);
            return $options;
        }

        // For Options fields
        if ($ftClass === 'FieldtypeOptions') {
            $manager = wire('modules')->get('FieldtypeOptions');
            if ($manager) {
                $opts = [];
                foreach ($manager->getOptions($pwField) as $opt) {
                    $opts[$opt->id] = $opt->title;
                }
                return $opts;
            }
        }

        // Fallback: scan pages (limited)
        $selector = $collection->buildSelector();
        $selector .= ", limit=500";

        $options = [];
        foreach ($this->pages->find($selector) as $page) {
            $val = $page->get($field);
            if (is_string($val) && $val !== '') {
                $options[$val] = $val;
            }
        }
        asort($options);
        return $options;
    }
}
