# Dynamic Permission Management Guide

This document explains how to properly manage permissions in the multi-source-chatbot application using Spatie Laravel Permission.

## Overview

The permission system is built on three key components:
1. **PermissionSeeder** - Defines all available permissions
2. **RoleSeeder** - Creates roles (superadmin, admin, etc.)
3. **UserSeeder** - Assigns roles and syncs permissions to roles

## Adding New Permissions

To add new permissions dynamically, follow these steps:

### Step 1: Add Permission to PermissionSeeder

Edit `database/seeders/PermissionSeeder.php` and add your permission to the `$permissions` array:

```php
// Example: Add CRM-related permissions
[
    'name' => 'crm-contact-list',
    'display_name' => 'CRM Contact list',
    'module' => 'crm'
],
[
    'name' => 'crm-contact-create',
    'display_name' => 'CRM Contact create',
    'module' => 'crm'
],
```

### Step 2: Run Migration and Seed

```bash
php artisan migrate:fresh --seed
```

This will:
1. Create all new permission records in the database
2. Clear the permission cache (handled in PermissionSeeder)
3. Sync all permissions to the superadmin and admin roles
4. Create default users

## Permission Naming Convention

Permissions follow a consistent naming pattern:

```
{module}-{action}
```

- **module**: The feature/module (role, user, permission, workspace, conversation, message, channel)
- **action**: The operation (list, create, edit, delete)

### Module Categories

Current modules:
- `role` - Role management
- `user` - User management
- `permission` - Permission management
- `workspace` - Workspace management
- `workspace-user` - Workspace user management
- `conversation` - Conversation management
- `message` - Message management
- `channel` - Channel management

Add new modules following this pattern.

## Checking Permissions in Code

### Using Middleware

Protect routes using permission middleware:

```php
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:user-list');
```

### Using Methods on User

```php
// Check single permission
if (auth()->user()->can('user-create')) {
    // User has permission
}

// Check if user has any of multiple permissions
if (auth()->user()->canAny(['user-edit', 'user-delete'])) {
    // User has at least one permission
}

// Check if user has all permissions
if (auth()->user()->canAll(['user-edit', 'user-delete'])) {
    // User has all permissions
}
```

### Using Helper Functions

```php
// Check permission directly
if (auth()->user()->hasPermissionTo('user-list')) {
    // User has permission
}
```

## Role-Permission Relationship

### Current Roles

1. **superadmin**
   - Description: Superadmin - Full access to all features and permissions
   - Permissions: ALL permissions

2. **admin**
   - Description: Admin - Can manage system and users
   - Permissions: ALL permissions

### Customizing Role Permissions

To assign specific permissions to a role after creation:

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$adminRole = Role::findByName('admin');

// Give specific permissions
$adminRole->givePermissionTo([
    'user-list',
    'user-create',
    'user-edit',
    'user-delete',
]);

// Or sync (replace existing permissions)
$adminRole->syncPermissions([
    'user-list',
    'user-create',
]);
```

## Database Schema

### Permissions Table

```sql
id              - Primary key
display_name    - Human-readable name
name            - Permission identifier (kebab-case)
module          - Module grouping
guard_name      - Auth guard (usually 'web')
created_at      - Created timestamp
updated_at      - Updated timestamp
```

### Roles Table

```sql
id              - Primary key
name            - Role identifier
description     - Role description
guard_name      - Auth guard (usually 'web')
created_at      - Created timestamp
updated_at      - Updated timestamp
```

### Role-Permission Relationship

```sql
role_has_permissions
├── role_id      - Foreign key to roles table
└── permission_id - Foreign key to permissions table
```

## Best Practices

1. **Naming Convention**: Always use kebab-case for permission names (e.g., `user-create`)

2. **Module Organization**: Group related permissions by module

3. **Seeding**: Always run `migrate:fresh --seed` after adding new permissions

4. **Cache Clearing**: Permission cache is automatically cleared when:
   - New permissions are created
   - Permissions are updated
   - Permissions are deleted
   - PermissionSeeder runs (explicit call)

5. **User Model**: Ensure the User model has the `HasRoles` trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {
    use HasRoles;
    // ...
}
```

## Example: Adding CRM Module Permissions

```php
// In PermissionSeeder.php
$permissions = [
    // ... existing permissions ...
    
    // crm
    ['name' => 'crm-contact-list', 'display_name' => 'CRM Contact list', 'module' => 'crm'],
    ['name' => 'crm-contact-create', 'display_name' => 'CRM Contact create', 'module' => 'crm'],
    ['name' => 'crm-contact-edit', 'display_name' => 'CRM Contact edit', 'module' => 'crm'],
    ['name' => 'crm-contact-delete', 'display_name' => 'CRM Contact delete', 'module' => 'crm'],
];
```

Then run:
```bash
php artisan migrate:fresh --seed
```

## Troubleshooting

### Permission not found

If you get "There is no permission named `xxx` for guard `web`":

1. Ensure the permission is defined in PermissionSeeder
2. Run `php artisan migrate:fresh --seed`
3. Clear application cache: `php artisan cache:clear`

### Permissions not syncing to roles

If permissions aren't syncing during seeding:

1. Check that PermissionSeeder runs before UserSeeder
2. Verify cache is being cleared: `app(PermissionRegistrar::class)->forgetCachedPermissions()`
3. Ensure syncPermissions() is called with permission names, not IDs

## References

- [Spatie Laravel Permission Docs](https://spatie.be/docs/laravel-permission/v6/introduction)
- Database Migrations: `database/migrations/2026_07_05_101729_create_permission_tables.php`
- Seeders: `database/seeders/`
