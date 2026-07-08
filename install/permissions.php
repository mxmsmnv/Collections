<?php

namespace ProcessWire;

function installCollectionsPermissions(\ProcessWire\ProcessWire $wire): void
{
    $permissions = [
        'collections-view'      => __('Collections: view pages', __FILE__),
        'collections-create'    => __('Collections: create pages', __FILE__),
        'collections-edit'      => __('Collections: edit pages', __FILE__),
        'collections-delete'    => __('Collections: delete pages', __FILE__),
        'collections-configure' => __('Collections: configure module', __FILE__),
        'collections-export'    => __('Collections: export data', __FILE__),
    ];

    foreach ($permissions as $name => $title) {
        if (!$wire->permissions->get($name)->id) {
            $p = $wire->permissions->add($name);
            $p->title = $title;
            $p->save();
        }
    }
}
