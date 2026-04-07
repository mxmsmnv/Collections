<?php

namespace ProcessWire;

final class QueryParams
{
    public function __construct(
        public readonly int    $page     = 1,
        public readonly int    $perPage  = 25,
        public readonly string $search   = '',
        public readonly string $sortBy   = 'title',
        public readonly string $sortDir  = 'asc',
        public readonly array  $filters  = [],
    ) {}

    public static function fromInput($input): self
    {
        $dir = $input->get('dir');

        // Ensure filters is always a clean string-key => string-value array.
        // $input->get('filter') returns mixed; guard against non-array and non-string values.
        $rawFilters = $input->get('filter');
        $filters = [];
        if (is_array($rawFilters)) {
            foreach ($rawFilters as $k => $v) {
                if (is_string($k) && $k !== '' && (is_string($v) || is_numeric($v))) {
                    $filters[$k] = (string)$v;
                }
            }
        }

        return new self(
            page:    max(1, (int) $input->get('page', 'int')),
            perPage: min(500, max(1, (int) ($input->get('per_page', 'int') ?: 25))),
            search:  $input->get('q', 'text') ?? '',
            sortBy:  $input->get('sort', 'fieldName') ?? '',
            sortDir: in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc',
            filters: $filters,
        );
    }

    public function withFilters(array $filters): self
    {
        return new self(
            page:    $this->page,
            perPage: $this->perPage,
            search:  $this->search,
            sortBy:  $this->sortBy,
            sortDir: $this->sortDir,
            filters: $filters,
        );
    }
}

final class QueryResult
{
    public function __construct(
        public $items,
        public readonly int        $total,
        public readonly int        $page,
        public readonly int        $perPage,
        public readonly int        $totalPages,
    ) {}
}