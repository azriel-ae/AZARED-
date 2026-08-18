<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Permission;
use App\Models\Role;

/**
 * AZARED - read-only permission catalog viewer.
 * Actual assignment (which role gets which permission) happens on the
 * Role screen (RoleController::permissionsForm) - see Permission model
 * docblock for why permissions themselves aren't freely editable here.
 */
final class PermissionController
{
    public static function index(): void
    {
        $groupedPermissions = Permission::grouped();
        $roles = Role::all();
        $matrix = Permission::roleMatrix();

        require dirname(__DIR__, 2) . '/views/permissions/index.php';
    }
}
