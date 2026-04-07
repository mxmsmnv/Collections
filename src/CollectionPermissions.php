<?php

namespace ProcessWire;

class CollectionPermissions
{
    public const CAP_VIEW      = 'view';
    public const CAP_CREATE    = 'create';
    public const CAP_EDIT      = 'edit';
    public const CAP_DELETE    = 'delete';
    public const CAP_CONFIGURE = 'configure';
    public const CAP_EXPORT    = 'export';

    public function __construct(
        private $user,
        private readonly CollectionConfig $config
    ) {}

    public function can(string $capability, ?Collection $collection = null): bool
    {
        if ($this->user->isSuperuser()) return true;

        // Check PW permission
        $pwPerm = "collections-{$capability}";
        if ($this->user->hasPermission($pwPerm)) return true;

        $matrix = $this->config->getPermissionsMatrix();

        foreach ($this->user->roles as $role) {
            $roleName = $role->name;

            // Global role caps
            $globalCaps = $matrix['roles'][$roleName] ?? [];
            if (in_array($capability, $globalCaps, true)) return true;

            // Per-collection caps
            if ($collection) {
                $colCaps = $matrix['collections'][$collection->key][$roleName] ?? [];
                if (in_array($capability, $colCaps, true)) return true;
            }
        }

        return false;
    }

    public function filterVisible(array $collections): array
    {
        if ($this->user->isSuperuser()) return $collections;
        return array_values(array_filter(
            $collections,
            fn(Collection $c) => $this->can(self::CAP_VIEW, $c)
        ));
    }

    public function getCaps(Collection $collection): array
    {
        $all = [
            self::CAP_VIEW,
            self::CAP_CREATE,
            self::CAP_EDIT,
            self::CAP_DELETE,
            self::CAP_CONFIGURE,
            self::CAP_EXPORT,
        ];
        return array_values(array_filter($all, fn($cap) => $this->can($cap, $collection)));
    }
}
