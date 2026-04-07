<?php

namespace ProcessWire;

class CollectionQuery
{
    public function __construct(
        private $pages
    ) {}

    public function execute(Collection $collection, QueryParams $params): QueryResult
    {
        $selector = $collection->buildSelector($params->search, $params->filters);

        // Sort - accept any valid field name
        $sortField = $params->sortBy;
        $systemSortable = ['id', 'name', 'status', 'created', 'modified', 'sort', 'title'];
        if (!in_array($sortField, $collection->columns, true)
            && !in_array($sortField, $systemSortable, true)
            && !wire('fields')->get($sortField)) {
            $sortField = $collection->sortBy ?: 'title';
        }
        $sortDir  = $params->sortDir === 'desc' ? '-' : '';
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

        // For Page reference fields: get unique referenced page IDs via SQL
        if ($ftClass === 'FieldtypePage') {
            $template = wire('templates')->get($collection->template);
            if (!$template) return [];

            // Get the DB table for this field
            $table = $pwField->getTable();
            $templateId = $template->id;

            try {
                $db = wire('database');
                // Join with pages table to filter by template, and get unique referenced page IDs
                $sql = "SELECT DISTINCT f.data FROM `{$table}` f
                        INNER JOIN pages p ON p.id = f.pages_id
                        WHERE p.templates_id = :tid AND f.data > 0";
                $params = [':tid' => $templateId];

                // If collection has extra selector with simple field=value, add to query
                // (complex selectors can't be easily translated to SQL)

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $refIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                if (!$refIds) return [];

                // Load referenced pages and build options
                $options = [];
                $refs = wire('pages')->getById($refIds);
                foreach ($refs as $ref) {
                    if ($ref->id) $options[$ref->id] = $ref->title;
                }
                asort($options); // Sort alphabetically by title
                return $options;
            } catch (\Throwable $e) {
                wire('log')->save('collections', 'getFilterOptions SQL error: ' . $e->getMessage());
                return [];
            }
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
        $selector = "template={$collection->template}, include=all";
        if ($collection->selector) $selector .= ", {$collection->selector}";
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