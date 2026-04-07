<?php

namespace ProcessWire;

class CollectionRenderer
{
    private $sanitizer;
    private string $adminUrl;
    private $wire;

    public function __construct(
        private readonly Collection           $collection,
        private readonly array                $globalConfig,
        private readonly CollectionPermissions $perms,
        $wire
    ) {
        $this->sanitizer = $wire->sanitizer;
        $this->adminUrl  = $wire->config->urls->admin;
        $this->wire      = $wire;
    }

    public function renderTable(QueryResult $result, QueryParams $params): string
    {
        $html  = '<div class="collections-table-wrap" id="collections-table">';
        $html .= '<table class="uk-table uk-table-divider uk-table-hover uk-table-small collections-table">';
        $html .= $this->renderTableHead($params);
        $html .= $this->renderTableBody($result->items);
        $html .= $this->renderTableFoot($result);
        $html .= '</table></div>';
        return $html;
    }

    private function renderTableFoot(QueryResult $result): string
    {
        return ''; // optional: add totals row here
    }

    private function renderTableHead(QueryParams $params): string
    {
        $cols = $this->getColumns();
        $html = '<thead><tr>';

        if ($this->perms->can(CollectionPermissions::CAP_EDIT, $this->collection)) {
            $html .= '<th class="col-check"><input type="checkbox" class="collections-check-all" title="Select all"></th>';
        }

        foreach ($cols as $col) {
            // Status column has no header label, just empty th
            if ($col === 'status') {
                $html .= '<th class="col-status" style="width:24px;"></th>';
                continue;
            }
            $label  = $this->collection->columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $isCurr = $params->sortBy === $col;
            $dir    = $isCurr && $params->sortDir === 'asc' ? 'desc' : 'asc';
            // Build sort URL preserving collection key and other params
            $colParam = isset($_GET['col']) ? '&col=' . htmlspecialchars($_GET['col']) : '';
            $icon   = '';
            if ($isCurr) {
                $icon = $params->sortDir === 'asc'
                    ? ' <i class="fa fa-sort-asc"></i>'
                    : ' <i class="fa fa-sort-desc"></i>';
            }
            $html .= "<th class=\"col-{$col}\" data-sort=\"{$col}\" data-dir=\"{$dir}\">"
                . "<a href=\"?sort={$col}&dir={$dir}{$colParam}\" class=\"collections-sort-link\">{$label}{$icon}</a></th>";
        }

        $html .= '<th class="col-actions">Actions</th>';
        $html .= '</tr></thead>';
        return $html;
    }

    private function renderTableBody($pages): string
    {
        $html = '<tbody>';

        if (!count($pages)) {
            $canEdit = $this->perms->can(CollectionPermissions::CAP_EDIT, $this->collection);
            // +1 for actions column; +1 for checkbox column only when user has edit permission
            $cols = count($this->getColumns()) + 1 + ($canEdit ? 1 : 0);
            $html .= "<tr><td colspan=\"{$cols}\" class=\"uk-text-center uk-text-muted\">No items found</td></tr>";
        }

        foreach ($pages as $page) {
            $html .= $this->renderRow($page);
        }

        $html .= '</tbody>';
        return $html;
    }

    public function renderRow($page): string
    {
        $cols      = $this->getColumns();
        $canEdit   = $this->perms->can(CollectionPermissions::CAP_EDIT, $this->collection);
        $canDelete = $this->perms->can(CollectionPermissions::CAP_DELETE, $this->collection);
        $status    = $page->isUnpublished() ? 'unpublished' : ($page->isHidden() ? 'hidden' : 'published');

        $html = "<tr class=\"collections-row status-{$status}\" data-id=\"{$page->id}\">";

        if ($canEdit) {
            $html .= "<td class=\"col-check\"><input type=\"checkbox\" class=\"collections-check\" value=\"{$page->id}\"></td>";
        }

        foreach ($cols as $col) {
            $value = $this->renderCellValue($page, $col);
            $html .= "<td class=\"col-{$col}\">{$value}</td>";
        }

        $editUrl     = $this->adminUrl . "page/edit/?id={$page->id}";
        $viewUrl     = $page->url;
        $isPublished  = !$page->isUnpublished();
        $toggleStatus = $isPublished ? 'published' : 'unpublished';
        $pubLabel     = $isPublished ? 'Unpub' : 'Pub';

        $svgEdit       = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-12.15 12.15a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32L19.513 8.2Z"/></svg>';
        $svgView       = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd"/></svg>';
        $svgToggleOn   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg>';
        $svgToggleOff  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#aaa" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd"/></svg>';
        $svgTrash      = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd"/></svg>';

        // Only show View link for non-admin, non-root pages that have a real URL
        $showViewLink = $viewUrl && $viewUrl !== '/' && $page->template->name !== 'admin';

        $html .= '<td class="col-actions"><ul class="PageListActions actions collections-actions">';
        $html .= "<li class=\"PageListActionEdit\"><a href=\"{$editUrl}\" title=\"Edit\">{$svgEdit}</a></li>";
        if ($showViewLink) {
            $html .= "<li class=\"PageListActionView\"><a href=\"{$viewUrl}\" title=\"View\">{$svgView}</a></li>";
        }
        $html .= "<li class=\"PageListActionStatus\">"
            . "<button class=\"collections-toggle-status toggle-{$toggleStatus}\" "
            . "data-id=\"{$page->id}\" data-status=\"{$toggleStatus}\" title=\"{$pubLabel}\">"
            . ($isPublished ? $svgToggleOn : $svgToggleOff)
            . "</button></li>";
        if ($canDelete && ($this->globalConfig['quick_delete'] ?? false)) {
            $html .= "<li class=\"PageListActionDelete\">"
                . "<button class=\"collections-delete\" data-id=\"{$page->id}\" title=\"Trash\">{$svgTrash}</button></li>";
        }
        $html .= '</ul></td></tr>';
        return $html;
    }

    public function renderCellValue($page, string $field): string
    {
        if ($field === 'id')     return (string) $page->id;
        if ($field === 'name')   return $this->sanitizer->entities($page->name);
        if ($field === 'status') return $this->renderStatus($page->status);

        $type  = $this->collection->columnTypes[$field] ?? 'auto';

        // Detect field type from template for smarter rendering
        $fieldObj = $this->wire->fields->get($field);
        $ftName   = $fieldObj ? $fieldObj->type->className() : '';

        // For image/file fields use formatted value (gives Pageimages with URL access)
        $isImageField = in_array($ftName, ['FieldtypeImage', 'FieldtypeFile']);
        $value = $isImageField ? $page->get($field) : $page->getUnformatted($field);

        // Explicit type overrides from column config
        if ($type === 'date')   return $this->formatDate($value);
        if ($type === 'number') return '<span class="uk-text-right">' . number_format((float)$value, 2) . '</span>';
        if ($type === 'status') return $this->renderStatus($value);
        if ($type === 'bool')   return $this->renderBool((bool)$value);

        // ── Images ────────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeImage') {
            if ($value instanceof Pageimage) {
                return $this->renderThumbnail($value);
            }
            if ($value instanceof Pageimages) {
                return count($value) ? $this->renderImageSet($value) : '<span class="uk-text-muted">—</span>';
            }
            return '<span class="uk-text-muted">—</span>';
        }
        if ($value instanceof Pageimages) {
            return count($value) ? $this->renderImageSet($value) : '<span class="uk-text-muted">—</span>';
        }
        if ($value instanceof Pageimage) {
            return $this->renderThumbnail($value);
        }

        // Generic empty check after type-specific branches
        if ($value === null || $value === '' || $value === false ||
            ($value instanceof \ProcessWire\WireArray && !count($value))) {
            return '<span class="uk-text-muted">—</span>';
        }

        // ── Page references ───────────────────────────────────────────────────
        if ($value instanceof Page)      return $this->renderPageRef($value);
        if ($value instanceof PageArray) return $this->renderPageArray($value);

        // ── Files ─────────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeFile' || $value instanceof Pagefiles) {
            return $this->renderFiles($value);
        }

        // ── Profields: Table ──────────────────────────────────────────────────
        if ($ftName === 'FieldtypeTable') {
            return $this->renderProTable($value);
        }

        // ── Profields: Textareas ──────────────────────────────────────────────
        if ($ftName === 'FieldtypeTextareas') {
            return $this->renderTextareas($value, $fieldObj);
        }

        // ── Profields: Multiplier ─────────────────────────────────────────────
        if ($ftName === 'FieldtypeMultiplier') {
            return $this->renderMultiplier($value);
        }

        // ── Options (select) ──────────────────────────────────────────────────
        if ($ftName === 'FieldtypeOptions') {
            return $this->renderOptions($value);
        }

        // ── Checkbox / Toggle ─────────────────────────────────────────────────
        if ($ftName === 'FieldtypeCheckbox' || is_bool($value)) {
            return $this->renderBool((bool)$value);
        }

        // ── Color ─────────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeColor' || $ftName === 'FieldtypeColorPicker') {
            return $this->renderColor($value);
        }

        // ── URL ───────────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeURL' && $value) {
            $esc = $this->sanitizer->entities((string)$value);
            return "<a href='{$esc}' target='_blank' rel='noopener'>{$esc}</a>";
        }

        // ── Email ─────────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeEmail' && $value) {
            $esc = $this->sanitizer->entities((string)$value);
            return "<a href='mailto:{$esc}'>{$esc}</a>";
        }

        // ── MapMarker ─────────────────────────────────────────────────────────
        if ($ftName === 'FieldtypeMapMarker' && is_object($value)) {
            if (!empty($value->lat) && !empty($value->lng)) {
                $label = $this->sanitizer->entities($value->address ?: "{$value->lat}, {$value->lng}");
                $url   = "https://maps.google.com/?q={$value->lat},{$value->lng}";
                return "<a href='{$url}' target='_blank' rel='noopener' title='{$label}'><i class='fa fa-map-marker'></i> {$label}</a>";
            }
            return '<span class="uk-text-muted">—</span>';
        }

        // ── WireArray fallback ────────────────────────────────────────────────
        if ($value instanceof WireArray && count($value)) {
            return (string) count($value) . ' items';
        }

        // ── Long text / HTML ──────────────────────────────────────────────────
        if (is_string($value) && strlen($value) > 80) {
            return $this->renderTextarea($value);
        }

        if ($value === null || $value === '' || $value === false || $value === 0) {
            return '<span class="uk-text-muted">—</span>';
        }

        return $this->sanitizer->entities((string)$value);
    }

    private function getColumns(): array
    {
        $cols = $this->collection->columns;
        if ($this->globalConfig['show_id'] ?? true)     array_unshift($cols, 'id');
        if ($this->globalConfig['show_status'] ?? true)  array_unshift($cols, 'status');
        if ($this->globalConfig['show_name'] ?? false)   $cols[] = 'name';
        return array_values(array_unique($cols));
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) return '<span class="uk-text-muted">—</span>';
        $ts  = is_numeric($value) ? (int)$value : strtotime((string)$value);
        $fmt = $this->globalConfig['date_format'] ?? 'M j, Y';
        return '<time datetime="' . date('c', $ts) . '">' . date($fmt, $ts) . '</time>';
    }

    private function renderStatus(mixed $status): string
    {
        if ($status === null) return '';
        $s = (int)$status;
        if ($s & Page::statusUnpublished) return '<span class="collections-dot dot-unpublished" title="Unpublished"></span>';
        if ($s & Page::statusHidden) return '<span class="collections-dot dot-hidden" title="Hidden"></span>';
        return '<span class="collections-dot dot-published" title="Published"></span>';
    }

    private function renderBool(bool $value): string
    {
        return $value ? '<i class="fa fa-check uk-text-success"></i>' : '<span class="uk-text-muted">—</span>';
    }

    private function renderPageRef($page): string
    {
        if (!$page->id) return '<span class="uk-text-muted">—</span>';
        $title = $this->sanitizer->entities($page->title ?: $page->name);
        $url   = $this->adminUrl . "page/edit/?id={$page->id}";
        return "<a href=\"{$url}\">{$title}</a>";
    }

    private function renderPageArray($pages): string
    {
        if (!count($pages)) return '<span class="uk-text-muted">—</span>';
        $links = [];
        foreach ($pages as $p) {
            $title   = $this->sanitizer->entities($p->title ?: $p->name);
            $url     = $this->adminUrl . "page/edit/?id={$p->id}";
            $links[] = "<a href=\"{$url}\">{$title}</a>";
        }
        return implode(', ', $links);
    }

    private function renderThumbnail(mixed $image): string
    {
        if (!$image) return '<span class="uk-text-muted">—</span>';
        try {
            $thumb = $image->size(32, 32);
            return "<img src=\"{$thumb->url}\" width=\"32\" height=\"32\" class=\"collections-thumb\" alt=\"\">";
        } catch (\Throwable) {
            return '<i class="fa fa-image uk-text-muted"></i>';
        }
    }

    private function renderTextarea(string $value): string
    {
        $short = $this->sanitizer->entities(mb_substr(strip_tags($value), 0, 80));
        $full  = $this->sanitizer->entities(strip_tags($value));
        return "<span title=\"{$full}\">{$short}…</span>";
    }

    private function renderImageSet($images): string
    {
        if (!count($images)) return '<span class="uk-text-muted">—</span>';
        $first = $images->first();
        $html  = $this->renderThumbnail($first);
        $extra = count($images) - 1;
        if ($extra > 0) $html .= ' <span class="collections-img-count">+' . $extra . '</span>';
        return $html;
    }

    private function renderFiles($files): string
    {
        if (!$files || !count($files)) return '<span class="uk-text-muted">—</span>';
        $items = [];
        foreach ($files as $f) {
            $name = $this->sanitizer->entities($f->basename);
            $ext  = strtolower($f->ext);
            $icon = in_array($ext, ['pdf']) ? 'fa-file-pdf-o' : (in_array($ext, ['doc','docx']) ? 'fa-file-word-o' : 'fa-file-o');
            $items[] = "<a href='{$f->url}' target='_blank' title='{$name}'><i class='fa {$icon}'></i> {$name}</a>";
        }
        return implode(', ', array_slice($items, 0, 2)) . (count($items) > 2 ? ' +' . (count($items)-2) : '');
    }

    private function renderProTable($value): string
    {
        if (!$value || !count($value)) return '<span class="uk-text-muted">—</span>';
        return '<span class="uk-badge">' . count($value) . ' rows</span>';
    }

    private function renderTextareas($value, $fieldObj): string
    {
        if (!$value) return '<span class="uk-text-muted">—</span>';
        // Show first non-empty subfield value
        try {
            foreach ($fieldObj->type->getTextareaFields($fieldObj) as $sub) {
                $v = $value->get($sub['name']);
                if ($v) return $this->renderTextarea((string)$v);
            }
        } catch (\Throwable) {}
        return '<span class="uk-text-muted">—</span>';
    }

    private function renderMultiplier($value): string
    {
        if (!$value) return '<span class="uk-text-muted">—</span>';
        if (is_array($value)) {
            $first = reset($value);
            $extra = count($value) - 1;
            $out   = $this->sanitizer->entities((string)$first);
            if ($extra > 0) $out .= ' <span class="uk-text-muted">+' . $extra . '</span>';
            return $out;
        }
        return $this->sanitizer->entities((string)$value);
    }

    private function renderOptions($value): string
    {
        if (!$value) return '<span class="uk-text-muted">—</span>';
        if ($value instanceof WireArray) {
            $labels = [];
            foreach ($value as $opt) {
                $labels[] = '<span class="collections-tag">' . $this->sanitizer->entities((string)$opt->title) . '</span>';
            }
            return $labels ? implode(' ', $labels) : '<span class="uk-text-muted">—</span>';
        }
        return '<span class="collections-tag">' . $this->sanitizer->entities((string)$value) . '</span>';
    }

    private function renderColor($value): string
    {
        if (!$value) return '<span class="uk-text-muted">—</span>';
        $hex = is_numeric($value)
            ? '#' . str_pad(dechex((int)$value), 6, '0', STR_PAD_LEFT)
            : (string)$value;
        if (strpos($hex, '#') === false) $hex = '#' . ltrim($hex, '#');
        $esc = $this->sanitizer->entities($hex);
        return "<span class='collections-color-swatch' style='background:{$esc}' title='{$esc}'></span> <code>{$esc}</code>";
    }
}