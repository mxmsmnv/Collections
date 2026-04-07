<?php

namespace ProcessWire;

class CollectionExporter
{
    public function exportCsv(Collection $collection, QueryResult $result): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $collection->key . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $fh = fopen('php://output', 'w');
        fputs($fh, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        $headers = ['URL'];
        $headers = array_merge($headers, array_map(
            fn($col) => $collection->columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)),
            $collection->columns
        ));
        fputcsv($fh, $headers);

        foreach ($result->items as $page) {
            $row = [$page->httpUrl];
            foreach ($collection->columns as $col) {
                $row[] = $this->flattenForCsv($page, $col);
            }
            fputcsv($fh, $row);
        }

        fclose($fh);
    }

    public function exportJson(Collection $collection, QueryResult $result): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $collection->key . '-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $items = [];
        foreach ($result->items as $page) {
            $item = ['id' => $page->id, 'name' => $page->name, 'url' => $page->httpUrl];
            foreach ($collection->columns as $col) {
                $item[$col] = $this->normalizeForJson($page, $col);
            }
            $items[] = $item;
        }

        echo json_encode([
            'collection' => $collection->key,
            'exported'   => date('c'),
            'total'      => $result->total,
            'items'      => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function getFieldType(string $fieldName): string
    {
        $field = wire('fields')->get($fieldName);
        return $field ? $field->type->className() : '';
    }

    private function flattenForCsv($page, string $col): string
    {
        $ftName = $this->getFieldType($col);

        // Image fields — need of(true) for formatted value with URL access
        if (in_array($ftName, ['FieldtypeImage', 'FieldtypeFile', 'FieldtypeFileB2'])) {
            $wasOf = $page->of();
            $page->of(true);
            $val = $page->get($col);
            $page->of($wasOf);
            if (!$val) return '';
            if (is_string($val)) return $val;
            $urls = [];
            if ($val instanceof \Traversable) {
                foreach ($val as $item) {
                    $urls[] = $item->httpUrl ?? ($item->url ?? (string)$item);
                }
            } elseif (is_object($val) && isset($val->url)) {
                $urls[] = $val->httpUrl ?? $val->url;
            }
            return implode(' | ', $urls);
        }

        // Page reference fields
        if (in_array($ftName, ['FieldtypePage', 'FieldtypePageIDs'])) {
            $val = $page->get($col);
            if (!$val) return '';
            if ($val instanceof PageArray) return $val->each('title', ' | ');
            if ($val instanceof Page) return $val->title ?: $val->name;
            return (string)$val;
        }

        // Options field
        if ($ftName === 'FieldtypeOptions') {
            return (string)$page->get($col);
        }

        // Default
        $val = $page->get($col);
        if ($val === null || $val === false) return '';
        if (is_object($val)) return (string)$val;
        return strip_tags((string)$val);
    }

    private function normalizeForJson($page, string $col): mixed
    {
        $ftName = $this->getFieldType($col);

        // Image/File fields — need of(true) for formatted value with URL access
        if (in_array($ftName, ['FieldtypeImage', 'FieldtypeFile', 'FieldtypeFileB2'])) {
            $wasOf = $page->of();
            $page->of(true);
            $val = $page->get($col);
            $page->of($wasOf);
            if (!$val) return null;
            if (is_string($val)) return $val;
            $items = [];
            if ($val instanceof \Traversable) {
                foreach ($val as $item) {
                    $items[] = $item->httpUrl ?? ($item->url ?? (string)$item);
                }
            } elseif (is_object($val) && isset($val->url)) {
                return $val->httpUrl ?? $val->url;
            }
            return $items;
        }

        // Page reference
        if (in_array($ftName, ['FieldtypePage', 'FieldtypePageIDs'])) {
            $val = $page->get($col);
            if (!$val) return null;
            if ($val instanceof PageArray) {
                $refs = [];
                foreach ($val as $ref) {
                    $refs[] = ['id' => $ref->id, 'title' => $ref->title, 'url' => $ref->httpUrl];
                }
                return $refs;
            }
            if ($val instanceof Page) {
                return ['id' => $val->id, 'title' => $val->title, 'url' => $val->httpUrl];
            }
            return (string)$val;
        }

        // Options
        if ($ftName === 'FieldtypeOptions') {
            return (string)$page->get($col);
        }

        // Profields Table
        if ($ftName === 'FieldtypeTable') {
            $val = $page->get($col);
            if (!$val) return null;
            $rows = [];
            foreach ($val as $row) {
                $rows[] = is_object($row) && method_exists($row, 'getArray') ? $row->getArray() : (string)$row;
            }
            return $rows;
        }

        // Default
        $val = $page->get($col);
        if ($val === null || $val === false) return null;
        if (is_object($val)) return (string)$val;
        return $val;
    }
}