<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

final class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'parent';
    public array $groups = [
        'admin' => ['title' => 'Administrator', 'description' => 'Manages the health center system and users.'],
        'bhw' => ['title' => 'Barangay Health Worker', 'description' => 'Manages child health records and daily services.'],
        'parent' => ['title' => 'Parent / Guardian', 'description' => 'Views linked children and requests services.'],
    ];
    public array $permissions = [
        'staff.access' => 'Access staff tools', 'children.manage' => 'Manage child health records',
        'reports.view' => 'View and export reports', 'users.manage' => 'Manage user accounts', 'settings.manage' => 'Manage system settings',
    ];
    public array $matrix = [
        'admin' => ['staff.access', 'children.manage', 'reports.view', 'users.manage', 'settings.manage'],
        'bhw' => ['staff.access', 'children.manage', 'reports.view'],
        'parent' => [],
    ];
}
