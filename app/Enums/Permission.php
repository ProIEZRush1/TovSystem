<?php

namespace App\Enums;

enum Permission: string
{
    // Dashboard
    case DASHBOARD_VIEW = 'dashboard.view';

    // Contacts
    case CONTACTS_VIEW = 'contacts.view';
    case CONTACTS_UPDATE = 'contacts.update';
    case CONTACTS_DELETE = 'contacts.delete';
    case CONTACTS_EXPORT = 'contacts.export';
    case CONTACTS_BULK_STATUS = 'contacts.bulk_status';

    // Statuses
    case STATUSES_VIEW = 'statuses.view';
    case STATUSES_MANAGE = 'statuses.manage';

    // Import
    case IMPORT_MANAGE = 'import.manage';

    // Admin: Users
    case ADMIN_USERS_VIEW = 'admin.users.view';
    case ADMIN_USERS_CREATE = 'admin.users.create';
    case ADMIN_USERS_UPDATE = 'admin.users.update';
    case ADMIN_USERS_DELETE = 'admin.users.delete';

    // Admin: Roles
    case ADMIN_ROLES_VIEW = 'admin.roles.view';
    case ADMIN_ROLES_MANAGE = 'admin.roles.manage';

    /**
     * Get the dot-prefix group for this permission.
     */
    public function group(): string
    {
        $parts = explode('.', $this->value);

        // admin.users.view -> admin
        // contacts.view -> contacts
        return $parts[0];
    }

    /**
     * Return all permissions grouped by their prefix.
     *
     * @return array<string, Permission[]>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $perm) {
            $groups[$perm->group()][] = $perm;
        }

        return $groups;
    }

    /**
     * Return all permission values as a flat string array.
     *
     * @return string[]
     */
    public static function allValues(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Return the grouped structure for the frontend.
     *
     * @return array<int, array{group: string, scopes: string[]}>
     */
    public static function forFrontend(): array
    {
        $result = [];
        foreach (self::grouped() as $group => $perms) {
            $result[] = [
                'group' => $group,
                'scopes' => array_map(fn (self $p) => $p->value, $perms),
            ];
        }

        return $result;
    }

    /**
     * Return all valid permission strings (individual scopes + group wildcards + global wildcard).
     *
     * @return string[]
     */
    public static function allValidStrings(): array
    {
        $values = self::allValues();
        $wildcards = array_map(fn ($g) => $g . '.*', array_keys(self::grouped()));

        return array_merge(['*'], $values, $wildcards);
    }
}
