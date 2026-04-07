<?php

namespace ProcessWire;

function installCollectionsPermissions(\ProcessWire\ProcessWire $wire): void
{
    $permissions = [
        'collections-view'      => 'Collections: view pages',
        'collections-create'    => 'Collections: create pages',
        'collections-edit'      => 'Collections: edit pages',
        'collections-delete'    => 'Collections: delete pages',
        'collections-configure' => 'Collections: configure module',
        'collections-export'    => 'Collections: export data',
    ];

    foreach ($permissions as $name => $title) {
        if (!$wire->permissions->get($name)->id) {
            $p = $wire->permissions->add($name);
            $p->title = $title;
            $p->save();
        }
    }
}
