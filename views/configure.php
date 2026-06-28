<?php

namespace ProcessWire;

/** @var ProcessWire\ProcessWire $wire */
/** @var CollectionConfig $config */
/** @var array $collections */
/** @var array $globalConfig */
/** @var array $matrix */
/** @var array $templates */
/** @var string $adminUrl */

$groupSuggestions = ['content', 'taxonomy', 'custom'];
foreach ($collections as $existingCol) {
    $group = $existingCol->group;
    if ($group !== '' && !in_array($group, $groupSuggestions, true)) {
        $groupSuggestions[] = $group;
    }
}
?>

<div class="collections-configure">
    <div class="collections-page-header">
        <div class="header-title">
            <i class="fa fa-cog"></i>
            <h1>Configure Collections</h1>
        </div>
        <div class="header-actions">
            <button class="ui-button ui-state-default" id="btn-add-collection">
                <span class="ui-button-text"><i class="fa fa-plus"></i> New Collection</span>
            </button>
            <a href="?export_config=1" class="ui-button ui-state-default ui-priority-secondary">
                <span class="ui-button-text"><i class="fa fa-download"></i> Export Config</span>
            </a>
        </div>
    </div>

    <ul uk-tab id="collections-configure-tabs">
        <li><a href="#tab-collections"><i class="fa fa-list"></i> Collections</a></li>
        <li><a href="#tab-global"><i class="fa fa-sliders"></i> Global Settings</a></li>
        <li><a href="#tab-api"><i class="fa fa-plug"></i> API</a></li>
        <li><a href="#tab-permissions"><i class="fa fa-lock"></i> Permissions</a></li>
        <li><a href="#tab-import"><i class="fa fa-upload"></i> Import / Export</a></li>
    </ul>

    <ul class="uk-switcher uk-margin">

        <!-- Tab: Collections -->
        <li>
            <?php if (empty($collections)): ?>
            <div class="uk-alert uk-alert-default">
                <p>No collections defined yet. Click "New Collection" to get started.</p>
            </div>
            <?php else: ?>
            <table class="uk-table uk-table-divider uk-table-hover uk-table-small">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Label</th>
                        <th>Template</th>
                        <th>Columns</th>
                        <th>Items</th>
                        <th>Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="collections-list-tbody">
                    <?php foreach ($collections as $col): ?>
                    <tr data-key="<?= $wire->sanitizer->entities($col->key) ?>">
                        <td><code><?= $wire->sanitizer->entities($col->key) ?></code></td>
                        <td>
                            <i class="fa <?= $wire->sanitizer->entities($col->icon) ?>"></i>
                            <?= $wire->sanitizer->entities($col->label) ?>
                        </td>
                        <td><code><?= $wire->sanitizer->entities($col->template) ?></code></td>
                        <td class="uk-text-small uk-text-muted"><?= $wire->sanitizer->entities(implode(', ', $col->columns)) ?></td>
                        <td class="uk-text-center">
                            <?php
                            $countSel = 'template=' . $col->template . ', include=all';
                            if ($col->selector) $countSel .= ', ' . $col->selector;
                            echo number_format($wire->pages->count($countSel));
                            ?>
                        </td>
                        <td><?= (int)$col->order ?></td>
                        <td>
                            <ul class="PageListActions actions collections-actions">
                                <li class="PageListActionView"><a href="<?= $adminUrl ?>collections/?col=<?= $col->key ?>" title="View"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd"/></svg></a></li>
                                <li class="PageListActionEdit"><button class="btn-edit-collection" title="Edit"
                                        data-collection='<?= htmlspecialchars(json_encode($col->toArray()), ENT_QUOTES) ?>'><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-12.15 12.15a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32L19.513 8.2Z"/></svg></button></li>
                                <li class="PageListActionDelete"><button class="btn-delete-collection" title="Delete"
                                        data-key="<?= $wire->sanitizer->entities($col->key) ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd"/></svg></button></li>
                            </ul>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </li>

        <!-- Tab: Global Settings -->
        <li>
            <form method="post" action="?save_global=1" id="form-global">
                <?php echo $wire->session->CSRF->renderInput(); ?>
                <div class="uk-grid uk-grid-small uk-child-width-1-2@m" uk-grid>
                    <div>
                        <h3 class="uk-heading-divider">Table Display</h3>
                        <?php
                        $boolFields = [
                            'show_id'              => 'Show ID column',
                            'show_status'          => 'Show Status column',
                            'show_name'            => 'Show Name column',
                            'inline_status'        => 'Inline status toggle',
                            'quick_delete'         => 'Quick delete button',
                            'confirm_batch_delete' => 'Confirm batch delete',
                        ];
                        foreach ($boolFields as $key => $label): ?>
                        <div class="uk-margin-small">
                            <label>
                                <input type="checkbox" name="<?= $key ?>" value="1"
                                    <?= ($globalConfig[$key] ?? false) ? 'checked' : '' ?>
                                    class="uk-checkbox">
                                <?= $label ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">Default per page</label>
                            <input type="number" name="default_per_page" min="5" max="500"
                                   value="<?= (int)($globalConfig['default_per_page'] ?? 25) ?>"
                                   class="uk-input uk-form-width-small">
                        </div>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">Date format (PHP)</label>
                            <input type="text" name="date_format"
                                   value="<?= $wire->sanitizer->entities($globalConfig['date_format'] ?? 'M j, Y') ?>"
                                   class="uk-input uk-form-width-medium">
                        </div>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">Thumbnail size (px)</label>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <input type="number" name="thumb_width" min="32" max="128"
                                       value="<?= (int)($globalConfig['thumb_width'] ?? 32) ?>"
                                       class="uk-input uk-form-width-xsmall" style="width:64px;-moz-appearance:textfield;appearance:textfield;">
                                <span class="uk-text-muted">×</span>
                                <input type="number" name="thumb_height" min="32" max="128"
                                       value="<?= (int)($globalConfig['thumb_height'] ?? 32) ?>"
                                       class="uk-input uk-form-width-xsmall" style="width:64px;-moz-appearance:textfield;appearance:textfield;">
                                <span class="uk-text-muted uk-text-small">width × height</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="uk-heading-divider">Search</h3>
                        <div class="uk-margin-small">
                            <label>
                                <input type="checkbox" name="live_search" value="1"
                                    <?= ($globalConfig['live_search'] ?? true) ? 'checked' : '' ?>
                                    class="uk-checkbox">
                                Live search (debounced)
                            </label>
                        </div>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">Min search length</label>
                            <input type="number" name="min_search_length" min="1" max="10"
                                   value="<?= (int)($globalConfig['min_search_length'] ?? 2) ?>"
                                   class="uk-input uk-form-width-xsmall">
                        </div>
                        <h3 class="uk-heading-divider">REST API</h3>
                        <div class="uk-margin-small">
                            <label>
                                <input type="checkbox" name="api_enabled" value="1"
                                    <?= ($globalConfig['api_enabled'] ?? true) ? 'checked' : '' ?>
                                    class="uk-checkbox">
                                Enable REST API
                            </label>
                        </div>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">API base path</label>
                            <input type="text" name="api_base"
                                   value="<?= $wire->sanitizer->entities($globalConfig['api_base'] ?? '/api/') ?>"
                                   class="uk-input uk-form-width-medium">
                        </div>
                        <h3 class="uk-heading-divider">Cache</h3>
                        <div class="uk-margin-small">
                            <label>
                                <input type="checkbox" name="cache_enabled" value="1"
                                    <?= ($globalConfig['cache_enabled'] ?? false) ? 'checked' : '' ?>
                                    class="uk-checkbox">
                                Enable API cache
                            </label>
                        </div>
                        <div class="uk-margin-small">
                            <label class="uk-form-label">Cache TTL (seconds)</label>
                            <input type="number" name="cache_ttl" min="60" max="86400"
                                   value="<?= (int)($globalConfig['cache_ttl'] ?? 300) ?>"
                                   class="uk-input uk-form-width-small">
                        </div>
                    </div>
                </div>
                <div class="uk-margin">
                    <button type="submit" class="ui-button ui-state-default"><span class="ui-button-text">Save Global Settings</span></button>
                </div>
            </form>

        </li>

        <!-- Tab: API -->
        <li>
            <?php
            $apiBase = $globalConfig['api_base'] ?? '/api/';
            $httpHost   = $wire->config->httpHost ?: $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme     = $wire->config->https ? 'https' : 'http';
            $pwRoot     = rtrim($wire->config->urls->root, '/');
            $fullApiUrl = $scheme . '://' . $httpHost . $pwRoot . '/' . trim($apiBase, '/') . '/';
            $allCols    = $config->getCollections();
            $apiKeys    = $config->getApiKeys();
            ?>

            <!-- API Status -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:12px 16px;background:<?= ($globalConfig['api_enabled'] ?? true) ? '#d4edda' : '#f8d7da' ?>;border-radius:6px;">
                <i class="fa fa-<?= ($globalConfig['api_enabled'] ?? true) ? 'check-circle' : 'times-circle' ?>" style="font-size:20px;color:<?= ($globalConfig['api_enabled'] ?? true) ? '#28a745' : '#dc3545' ?>;"></i>
                <div>
                    <strong>API is <?= ($globalConfig['api_enabled'] ?? true) ? 'enabled' : 'disabled' ?></strong>
                    <?php if ($globalConfig['api_enabled'] ?? true): ?>
                    <span style="color:#666;margin-left:8px;">Base URL: <code><?= htmlspecialchars($fullApiUrl) ?></code></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="uk-grid uk-grid-medium" uk-grid>

                <!-- Left column: Endpoints + Examples -->
                <div class="uk-width-2-3@m">
                    <h3 class="uk-heading-divider">Endpoints</h3>
                    <table class="uk-table uk-table-small uk-table-divider" style="font-size:13px;">
                        <thead><tr><th style="width:80px;">Method</th><th>URL</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><span style="color:#28a745;font-weight:600;">GET</span></td><td><code>collections</code></td><td>List all collections</td></tr>
                            <tr><td><span style="color:#28a745;font-weight:600;">GET</span></td><td><code>{key}</code></td><td>List items with pagination, search, sort</td></tr>
                            <tr><td><span style="color:#28a745;font-weight:600;">GET</span></td><td><code>{key}/{id}</code></td><td>Single item by ID</td></tr>
                            <tr><td><span style="color:#28a745;font-weight:600;">GET</span></td><td><code>{key}/schema</code></td><td>Field definitions for collection</td></tr>
                            <tr><td><span style="color:#28a745;font-weight:600;">GET</span></td><td><code>{key}/export?format=csv</code></td><td>Export as CSV or JSON</td></tr>
                            <tr><td><span style="color:#007bff;font-weight:600;">POST</span></td><td><code>{key}</code></td><td>Create new item (JSON body)</td></tr>
                            <tr><td><span style="color:#fd7e14;font-weight:600;">PATCH</span></td><td><code>{key}/{id}</code></td><td>Update item fields</td></tr>
                            <tr><td><span style="color:#dc3545;font-weight:600;">DELETE</span></td><td><code>{key}/{id}</code></td><td>Delete item</td></tr>
                        </tbody>
                    </table>

                    <h4 style="margin-top:24px;">Query Parameters</h4>
                    <table class="uk-table uk-table-small uk-table-divider" style="font-size:13px;">
                        <thead><tr><th>Param</th><th>Example</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>q</code></td><td><code>?q=whiskey</code></td><td>Search by title and configured search fields</td></tr>
                            <tr><td><code>page</code></td><td><code>?page=2</code></td><td>Page number (default: 1)</td></tr>
                            <tr><td><code>per_page</code></td><td><code>?per_page=50</code></td><td>Items per page (default: 25, max: 500)</td></tr>
                            <tr><td><code>sort</code></td><td><code>?sort=title</code></td><td>Sort field</td></tr>
                            <tr><td><code>dir</code></td><td><code>?dir=desc</code></td><td>Sort direction: asc or desc</td></tr>
                            <tr><td><code>fields</code></td><td><code>?fields=title,brand</code></td><td>Limit returned fields (comma-separated)</td></tr>
                            <tr><td><code>format</code></td><td><code>?format=table</code></td><td>Return HTML table instead of JSON</td></tr>
                        </tbody>
                    </table>

                    <h4 style="margin-top:24px;">Examples</h4>
                    <div style="background:#1e1e1e;border-radius:6px;padding:14px 18px;font-family:monospace;font-size:12px;color:#d4d4d4;overflow-x:auto;margin-bottom:12px;">
                        <span style="color:#6a9955;"># List all collections</span><br>
                        <span style="color:#569cd6;">curl</span> -H "Authorization: Bearer <span style="color:#ce9178;">YOUR_KEY</span>" <?= htmlspecialchars($fullApiUrl) ?>collections<br><br>
                        <span style="color:#6a9955;"># Search products</span><br>
                        <span style="color:#569cd6;">curl</span> -H "Authorization: Bearer <span style="color:#ce9178;">YOUR_KEY</span>" "<?= htmlspecialchars($fullApiUrl) ?>products?q=whiskey&per_page=10"<br><br>
                        <span style="color:#6a9955;"># Get single product</span><br>
                        <span style="color:#569cd6;">curl</span> -H "Authorization: Bearer <span style="color:#ce9178;">YOUR_KEY</span>" <?= htmlspecialchars($fullApiUrl) ?>products/12345<br><br>
                        <span style="color:#6a9955;"># Create a page</span><br>
                        <span style="color:#569cd6;">curl</span> -X POST -H "Authorization: Bearer <span style="color:#ce9178;">YOUR_KEY</span>" \<br>
                        &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                        &nbsp;&nbsp;-d '{"title":"New Product"}' \<br>
                        &nbsp;&nbsp;<?= htmlspecialchars($fullApiUrl) ?>products
                    </div>

                    <?php if ($allCols): ?>
                    <h4 style="margin-top:24px;">Available Collections</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php foreach ($allCols as $c): ?>
                        <a href="<?= htmlspecialchars($fullApiUrl . $c->key) ?>" target="_blank" 
                           style="display:inline-block;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:4px;font-size:12px;text-decoration:none;color:#333;">
                            <i class="fa <?= $wire->sanitizer->entities($c->icon) ?>"></i>
                            <?= $wire->sanitizer->entities($c->key) ?>
                            <span style="color:#999;margin-left:2px;">(<?= $wire->sanitizer->entities($c->template) ?>)</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right column: Auth + Keys -->
                <div class="uk-width-1-3@m">
                    <h3 class="uk-heading-divider">Authentication</h3>
                    <div style="font-size:13px;line-height:1.8;">
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:4px;margin-bottom:12px;">
                            <strong><i class="fa fa-key"></i> API Key</strong> <span style="background:#28a745;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;">recommended</span><br>
                            <code style="font-size:11px;">Authorization: Bearer col_xxx...</code><br>
                            <span style="color:#888;">or</span> <code style="font-size:11px;">?api_key=col_xxx...</code>
                        </div>
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:4px;margin-bottom:12px;">
                            <strong><i class="fa fa-user"></i> HTTP Basic Auth</strong><br>
                            <span style="color:#666;font-size:12px;">ProcessWire username &amp; password</span>
                        </div>
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:4px;margin-bottom:12px;">
                            <strong><i class="fa fa-cookie"></i> Session</strong><br>
                            <span style="color:#666;font-size:12px;">Logged-in PW session cookie</span>
                        </div>
                    </div>

                    <h3 class="uk-heading-divider" style="margin-top:24px;">API Keys</h3>
                    <?php
                    $newKey = $wire->session->get('collections_new_api_key');
                    if ($newKey):
                        $wire->session->remove('collections_new_api_key');
                    ?>
                    <div style="background:#d4edda;border:1px solid #c3e6cb;padding:10px 14px;border-radius:4px;margin-bottom:12px;">
                        <strong style="font-size:12px;">New key — copy now, shown once:</strong><br>
                        <code style="display:block;margin-top:4px;padding:6px 10px;background:#fff;border:1px solid #ddd;border-radius:3px;font-size:12px;word-break:break-all;user-select:all;"><?= htmlspecialchars($newKey) ?></code>
                    </div>
                    <?php endif; ?>

                    <?php if ($apiKeys): ?>
                    <table class="uk-table uk-table-small uk-table-divider" style="font-size:12px;">
                        <thead><tr><th>Name</th><th>Prefix</th><th>Expires</th><th>Uses</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($apiKeys as $key): ?>
                        <tr<?= $key['expired'] ? ' style="opacity:0.4;"' : '' ?>>
                            <td><strong><?= $wire->sanitizer->entities($key['name']) ?></strong></td>
                            <td><code style="font-size:11px;"><?= htmlspecialchars($key['key_prefix']) ?>…</code></td>
                            <td><?= $key['expires'] ? date('M j', strtotime($key['expires'])) . ($key['expired'] ? ' <span style="color:red;">✗</span>' : '') : '∞' ?></td>
                            <td class="uk-text-center"><?= number_format($key['use_count']) ?></td>
                            <td>
                                <form method="post" action="?delete_api_key=1" style="display:inline;" onsubmit="return confirm('Delete this API key?');">
                                    <?= $wire->session->CSRF->renderInput() ?>
                                    <input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>">
                                    <button type="submit" style="border:none;background:none;color:#dc3545;cursor:pointer;font-size:13px;" title="Delete"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#dc3545" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="uk-text-muted uk-text-small">No API keys yet.</p>
                    <?php endif; ?>

                    <form method="post" action="?create_api_key=1" style="margin-top:10px;">
                        <?= $wire->session->CSRF->renderInput() ?>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <input type="text" name="key_name" required placeholder="Key name" class="uk-input">
                            <div style="display:flex;gap:6px;">
                                <input type="number" name="key_expiration" value="0" min="0" max="3650" class="uk-input" style="width:80px;" title="Days (0 = never)">
                                <span style="font-size:11px;color:#888;line-height:30px;">days</span>
                                <button type="submit" class="ui-button ui-state-default" style="flex:1;">
                                    <i class="fa fa-plus"></i> Create Key
                                </button>
                            </div>
                        </div>
                    </form>

                    <h3 class="uk-heading-divider" style="margin-top:24px;">Response Format</h3>
                    <div style="background:#1e1e1e;border-radius:4px;padding:10px 14px;font-family:monospace;font-size:11px;color:#d4d4d4;line-height:1.6;">
                        {<br>
                        &nbsp;&nbsp;<span style="color:#9cdcfe;">"ok"</span>: <span style="color:#569cd6;">true</span>,<br>
                        &nbsp;&nbsp;<span style="color:#9cdcfe;">"data"</span>: [ ... ],<br>
                        &nbsp;&nbsp;<span style="color:#9cdcfe;">"meta"</span>: {<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#9cdcfe;">"total"</span>: <span style="color:#b5cea8;">12878</span>,<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#9cdcfe;">"page"</span>: <span style="color:#b5cea8;">1</span>,<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#9cdcfe;">"per_page"</span>: <span style="color:#b5cea8;">25</span>,<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#9cdcfe;">"total_pages"</span>: <span style="color:#b5cea8;">516</span><br>
                        &nbsp;&nbsp;}<br>
                        }
                    </div>
                </div>
            </div>
        </li>

        <!-- Tab: Permissions -->
        <li>
            <form method="post" action="?save_permissions=1" id="form-permissions">
                <?php echo $wire->session->CSRF->renderInput(); ?>
                <p class="uk-text-muted uk-text-small">
                    Assign capabilities to roles. Superuser always has full access.
                    Rules are additive — if a role has a capability globally, it applies to all collections.
                </p>
                <?php
                $caps  = ['view', 'create', 'edit', 'delete', 'configure', 'export'];
                $roles = [];
                foreach ($wire->roles->find("name!=guest, sort=name") as $role) {
                    if ($role->name !== 'superuser') $roles[] = $role;
                }
                $rolesMatrix = $matrix['roles'] ?? [];
                ?>
                <h4>Global Role Permissions</h4>
                <table class="uk-table uk-table-small uk-table-divider uk-table-striped">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <?php foreach ($caps as $cap): ?>
                            <th class="uk-text-center uk-text-small"><?= ucfirst($cap) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><strong><?= $wire->sanitizer->entities($role->name) ?></strong></td>
                            <?php foreach ($caps as $cap): ?>
                            <td class="uk-text-center">
                                <input type="checkbox"
                                       name="roles[<?= $role->name ?>][]"
                                       value="<?= $cap ?>"
                                       class="uk-checkbox"
                                    <?= in_array($cap, $rolesMatrix[$role->name] ?? [], true) ? 'checked' : '' ?>>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="uk-margin">
                    <button type="submit" class="ui-button ui-state-default"><span class="ui-button-text">Save Permissions</span></button>
                </div>
            </form>
        </li>

        <!-- Tab: Import / Export -->
        <li>
            <div class="uk-grid uk-grid-medium uk-child-width-1-2@m" uk-grid>
                <div>
                    <h4>Export Configuration</h4>
                    <p class="uk-text-muted uk-text-small">Download all collections, global settings, and permissions as a JSON file.</p>
                    <a href="?export_config=1" class="ui-button ui-state-default ui-priority-secondary">
                        <i class="fa fa-download"></i> Download JSON
                    </a>
                </div>
                <div>
                    <h4>Import Configuration</h4>
                    <p class="uk-text-muted uk-text-small">Import collections from a previously exported JSON file. Existing collections with the same key will be replaced.</p>
                    <form method="post" action="?import_config=1" enctype="multipart/form-data">
                        <?php echo $wire->session->CSRF->renderInput(); ?>
                        <div class="uk-margin-small">
                            <input type="file" name="config_file" accept=".json" class="uk-input">
                        </div>
                        <button type="submit" class="ui-button ui-state-default">
                            <span class="ui-button-text"><i class="fa fa-upload"></i> Import</span>
                        </button>
                    </form>
                </div>
            </div>
        </li>
    </ul>
</div>

<!-- Collection Edit/Add Modal -->
<div id="modal-collection-edit" uk-modal>
    <div class="uk-modal-dialog uk-modal-body uk-width-2-3@m">
        <h2 class="uk-modal-title" id="modal-collection-title">Add Collection</h2>
        <form id="form-collection-edit" method="post" action="?save_collection=1">
            <?php echo $wire->session->CSRF->renderInput(); ?>
            <input type="hidden" name="_original_key" id="field-original-key" value="">
            <div class="uk-grid uk-grid-small uk-child-width-1-2@m" uk-grid>
                <div>
                    <label class="uk-form-label">Key <span class="uk-text-danger">*</span></label>
                    <input type="text" name="key" id="field-key" required pattern="[a-z0-9_]+"
                           placeholder="products" class="uk-input">
                    <span class="uk-text-small uk-text-muted">Lowercase, a-z 0-9 _ only</span>
                </div>
                <div>
                    <label class="uk-form-label">Label <span class="uk-text-danger">*</span></label>
                    <input type="text" name="label" id="field-label" required
                           placeholder="Products" class="uk-input">
                </div>
                <div>
                    <label class="uk-form-label">Template <span class="uk-text-danger">*</span></label>
                    <select name="template" id="field-template" required class="uk-select" data-load-fields="1">
                        <option value="">— select template —</option>
                        <?php foreach ($templates as $t): ?>
                        <option value="<?= $wire->sanitizer->entities($t->name) ?>"><?= $wire->sanitizer->entities($t->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="uk-form-label">Extra selector</label>
                    <input type="text" name="selector" id="field-selector"
                           placeholder="status=1" class="uk-input">
                </div>
                <div>
                    <label class="uk-form-label">Icon (FontAwesome)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span id="icon-preview" style="font-size:18px;width:24px;text-align:center;"><i class="fa fa-list"></i></span>
                        <input type="text" name="icon" id="field-icon"
                               placeholder="fa-list" class="uk-input" style="flex:1;">
                    </div>
                    <a href="#" id="icon-picker-toggle" style="font-size:12px;color:#0432ff;">Show All Icons</a>
                    <div id="icon-picker" style="display:none;max-height:250px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px;margin-top:4px;background:#fff;line-height:1;"></div>
                </div>
                <div>
                    <label class="uk-form-label">Group</label>
                    <input type="text" name="group" id="field-group" class="uk-input"
                           list="group-options" autocomplete="off"
                           placeholder="content, taxonomy, custom, or a new name">
                    <datalist id="group-options">
                        <?php foreach ($groupSuggestions as $group): ?>
                        <option value="<?= $wire->sanitizer->entities($group) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="uk-form-label">Sort by / Direction</label>
                    <div class="uk-grid uk-grid-small" uk-grid>
                        <div class="uk-width-2-3">
                            <input type="text" name="sortBy" id="field-sortBy"
                                   placeholder="title" class="uk-input">
                        </div>
                        <div class="uk-width-1-3">
                            <select name="sortDir" id="field-sortDir" class="uk-select">
                                <option value="asc">ASC</option>
                                <option value="desc">DESC</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="uk-form-label">Per page (0 = global)</label>
                    <input type="number" name="perPage" id="field-perPage" min="0" max="500"
                           value="0" class="uk-input uk-form-width-small">
                </div>
                <div>
                    <label class="uk-form-label">Order (position in sidenav)</label>
                    <input type="number" name="order" id="field-order" min="0"
                           value="0" class="uk-input uk-form-width-xsmall">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label">Columns (comma-separated field names)</label>
                <input type="text" name="columns" id="field-columns"
                       placeholder="title,sku,brand,modified" class="uk-input">
            </div>
            <div class="uk-margin">
                <label class="uk-form-label">Search fields (comma-separated)</label>
                <input type="text" name="searchFields" id="field-searchFields"
                       placeholder="title,sku" class="uk-input">
            </div>
            <div class="uk-margin">
                <label>
                    <input type="checkbox" name="exportEnabled" value="1" id="field-exportEnabled" class="uk-checkbox" checked>
                    Enable CSV / JSON export for this collection
                </label>
            </div>
            <div class="uk-margin">
                <label>
                    <input type="checkbox" name="searchRelated" value="1" id="field-searchRelated" class="uk-checkbox" checked>
                    Search in related page titles (e.g. find by category name)
                </label>
            </div>

            <div class="uk-modal-footer uk-text-right">
                <button type="button" class="ui-button ui-state-default ui-priority-secondary uk-modal-close"><span class="ui-button-text">Cancel</span></button>
                <button type="submit" class="ui-button ui-state-default"><span class="ui-button-text">Save Collection</span></button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm modal -->
<div id="modal-delete-confirm" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">Delete Collection</h2>
        <p>Are you sure you want to delete this collection? <strong>This only removes the collection configuration, not the actual pages.</strong></p>
        <form method="post" action="?delete_collection=1">
            <?php echo $wire->session->CSRF->renderInput(); ?>
            <input type="hidden" name="key" id="delete-key" value="">
            <div class="uk-modal-footer uk-text-right">
                <button type="button" class="ui-button ui-state-default ui-priority-secondary uk-modal-close"><span class="ui-button-text">Cancel</span></button>
                <button type="submit" class="ui-button ui-state-default" style="background:#dc3545;color:#fff;border-color:#dc3545;">Delete</button>
            </div>
        </form>
    </div>
</div>
