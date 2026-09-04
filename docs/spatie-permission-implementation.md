# Spatie Laravel Permission — Implementation Guide

> **Version:** 6.x (compatible with Laravel 11/12)
> **Package:** `spatie/laravel-permission`
> **Official Docs:** https://spatie.be/docs/laravel-permission/v8

---

## 1. Overview

**Spatie Laravel Permission** is a powerful package for role and permission management in Laravel. It provides:

- Role-based access control (RBAC)
- Direct permission assignment to users
- Role ↔ Permission relationships (many-to-many)
- Middleware for route protection
- Blade directives for view authorization
- Gate integration with Laravel's authorization system
- Built-in caching for performance

### Why Use It?

- Standardized, well-tested authorization layer
- Eliminates ad-hoc `is_admin` boolean checks
- Granular permission control per feature
- Easy to audit and extend
- Large community and active maintenance

---

## 2. Installation

### Step 1: Install via Composer

```bash
composer require spatie/laravel-permission
```

### Step 2: Publish the Migration and Config

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```

### Step 3: Run the Migration

```bash
php artisan migrate
```

### Step 4: Add the Trait to User Model

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

---

## 3. Project Structure

```
app/
├── Enums/
│   ├── RoleEnum.php             # Backed string enum — all role names
│   ├── Concerns/
│   │   └── PermissionEnumTrait.php # Shared trait for all permission enums
│   └── Permissions/
│       ├── UserPermission.php          # user.view, user.create, ...
│       ├── RolePermission.php          # role.view, role.create, ...
│       ├── PermissionPermission.php    # permission.view, ...
│       ├── WorkspacePermission.php     # workspace.view, ...
│       ├── WorkspaceUserPermission.php # workspace-user.view, ...
│       ├── ConversationPermission.php  # conversation.view, ...
│       ├── MessagePermission.php       # message.view, ...
│       └── ChannelPermission.php       # channel.view, ...
│
├── Models/
│   ├── User.php              # Uses HasRoles trait
│   └── Role.php              # Extends Spatie\Permission\Models\Role
│
├── Providers/
│   └── AppServiceProvider.php # Gate::before() for Super Admin
│
bootstrap/
└── app.php                    # Middleware aliases registration
│
database/
├── migrations/
│   └── create_permission_tables.php  # Published from package
│
└── seeders/
    ├── PermissionSeeder.php   # Seeds permissions from all module enums (via PERMISSION_ENUMS list)
    ├── RoleSeeder.php         # Creates roles using RoleEnum, assigns permissions
    └── UserSeeder.php         # Creates default users and assigns roles
│
routes/
└── web.php                    # Route middleware using module-based Permission enums
│
resources/views/
└── admin/                     # Blade templates with @role(RoleEnum::...) directives
```

### Key Files Explained

| File / Directory                             | Purpose                                                                                                                                       |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Enums/RoleEnum.php`                     | **Single source of truth** for all role names. Add new roles here.                                                                            |
| `app/Enums/Concerns/PermissionEnumTrait.php` | Shared trait providing `values()`, `displayName()`, `module()`, `seederData()` for all permission enums.                                      |
| `app/Enums/Permissions/`                     | **Module-based permission enums**. Each module has its own enum file. To add a new module, create a new enum here and register in the seeder. |
| `config/permission.php`                      | Package configuration (models, tables, cache, teams)                                                                                          |
| `app/Models/Role.php`                        | Role model — extends Spatie's Role                                                                                                            |
| `app/Providers/AppServiceProvider.php`       | `Gate::before()` for Super Admin bypass using `RoleEnum::SUPERADMIN->value`                                                                   |
| `bootstrap/app.php`                          | Register `role`, `permission`, `role_or_permission` middleware aliases                                                                        |
| `database/seeders/PermissionSeeder.php`      | Seeds permissions from `PermissionSeeder::PERMISSION_ENUMS` list (all module enums registered here)                                           |
| `database/seeders/RoleSeeder.php`            | Creates roles using `RoleEnum`, assigns permissions using module-based enums                                                                  |
| `database/seeders/UserSeeder.php`            | Creates default users and assigns roles via `RoleEnum`                                                                                        |

---

## 4. Permission Naming Convention

Use a **consistent naming pattern** for permissions. The recommended convention is:

```
{resource}.{action}
```

### Examples

```
user.list
user.create
user.update
user.delete

role.list
role.create
role.update
role.delete

conversation.list
conversation.create
conversation.update
conversation.delete

message.list
message.create
message.update
message.delete

channel.list
channel.create
channel.update
channel.delete
```

### Naming Strategy

| Part          | Convention               | Example                                        |
| ------------- | ------------------------ | ---------------------------------------------- |
| **Resource**  | Lowercase, singular      | `user`, `role`, `conversation`                 |
| **Action**    | Lowercase verb           | `list`, `create`, `update`, `delete`, `assign` |
| **Separator** | Dot (`.`)                | `user.create`                                  |
| **Composite** | Hyphen for sub-resources | `workspace-user.list`                          |

> **Note:** This project uses `kebab-case` (e.g., `role-list`) for backward compatibility. The naming strategy is consistent within the project.

---

## 5. Enum Architecture (Strongly-Typed Roles & Permissions)

This project uses **PHP 8.1 backed enums** as the single source of truth for all role and permission names. This eliminates hardcoded string literals and provides IDE autocompletion, refactoring safety, and compile-time checking.

### RoleEnum (`app/Enums/RoleEnum.php`)

```php
enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
}
```

- `RoleEnum::SUPERADMIN->value` → `'superadmin'` (compatible with Spatie)
- `RoleEnum::from('admin')` → `RoleEnum::ADMIN`
- `RoleEnum::values()` → `['superadmin', 'admin']`
- `RoleEnum::SUPERADMIN->label()` → `'Super Admin'`

### Module-Based Permission Enums (`app/Enums/Permissions/`)

Each module has its own dedicated enum in `app/Enums/Permissions/`. All share the `PermissionEnumTrait` which provides `values()`, `displayName()`, `module()`, and `seederData()`.

```php
namespace App\Enums\Permissions;

use App\Enums\Concerns\PermissionEnumTrait;

enum UserPermission: string
{
    use PermissionEnumTrait;

    case VIEW = 'user.view';
    case CREATE = 'user.create';
    case UPDATE = 'user.update';
    case DELETE = 'user.delete';
}
```

**Why module-based?** — Each module owns its permissions. Adding a new module means creating a new enum file and registering it in `PermissionSeeder::PERMISSION_ENUMS`. No risk of merge conflicts on a single monolithic file.

**Registered modules:**

| Enum Class                | Permission Values                                                                                | Module         |
| ------------------------- | ------------------------------------------------------------------------------------------------ | -------------- |
| `RolePermission`          | `role.view`, `role.create`, `role.update`, `role.delete`                                         | role           |
| `UserPermission`          | `user.view`, `user.create`, `user.update`, `user.delete`                                         | user           |
| `PermissionPermission`    | `permission.view`, `permission.create`, `permission.update`, `permission.delete`                 | permission     |
| `WorkspacePermission`     | `workspace.view`, `workspace.create`, `workspace.update`, `workspace.delete`                     | workspace      |
| `WorkspaceUserPermission` | `workspace-user.view`, `workspace-user.create`, `workspace-user.update`, `workspace-user.delete` | workspace-user |
| `ConversationPermission`  | `conversation.view`, `conversation.create`, `conversation.update`, `conversation.delete`         | conversation   |
| `MessagePermission`       | `message.view`, `message.create`, `message.update`, `message.delete`                             | message        |
| `ChannelPermission`       | `channel.view`, `channel.create`, `channel.update`, `channel.delete`                             | channel        |

**Trait methods** (from `PermissionEnumTrait`):

| Method               | Returns  | Example                                                                       |
| -------------------- | -------- | ----------------------------------------------------------------------------- |
| `->value` (built-in) | `string` | `'user.view'`                                                                 |
| `->displayName()`    | `string` | `'User view'`                                                                 |
| `::values()`         | `array`  | `['user.view', 'user.create', ...]`                                           |
| `::module()`         | `string` | `'user'`                                                                      |
| `::seederData()`     | `array`  | `[['name'=>'user.view', 'display_name'=>'User view', 'module'=>'user'], ...]` |

### Where Enums Are Used

| Context             | Pattern                                                      |
| ------------------- | ------------------------------------------------------------ |
| Controllers         | `$user->hasRole(RoleEnum::SUPERADMIN->value)`                |
| Seeders             | `RoleEnum::SUPERADMIN->value`, `UserPermission::VIEW->value` |
| Routes              | `'permission:' . ConversationPermission::VIEW->value`        |
| Blade               | `@role(\App\Enums\RoleEnum::SUPERADMIN->value)`              |
| AppServiceProvider  | `$user->hasRole(RoleEnum::SUPERADMIN->value)`                |
| JavaScript in Blade | `'{{ \App\Enums\RoleEnum::SUPERADMIN->value }}'`             |

### Adding New Roles

1. Add a case to `RoleEnum`
2. Define its permissions in `RoleSeeder`
3. Run: `php artisan db:seed --class=RoleSeeder`

### Adding a New Module (with permissions)

1. Create `app/Enums/Permissions/{ModuleName}Permission.php` using the trait
2. Register it in `PermissionSeeder::PERMISSION_ENUMS`
3. Run: `php artisan db:seed --class=PermissionSeeder`

No string literals to search-and-replace — the enums are the single source of truth.

---

## 6. Role Strategy

### Role Hierarchy (Conceptual)

```
Super Admin ─── Full access (bypasses all checks via Gate::before())
     │
     ▼
Admin ───────── Operational access (users, conversations, channels)
     │
     ▼
Manager ─────── Departmental access (future)
     │
     ▼
Employee ────── Limited read/action access (future)
```

### Implemented Roles

| Role           | Description                                                              | Permissions                                                     |
| -------------- | ------------------------------------------------------------------------ | --------------------------------------------------------------- |
| **superadmin** | Full system access. Bypasses all permission checks via `Gate::before()`. | All permissions implicitly granted                              |
| **admin**      | Day-to-day operational management.                                       | user._, conversation._, message._, channel._, workspace-user.\* |

### Adding a New Role

1. Add the role to `database/seeders/RoleSeeder.php`
2. Define its permissions using `syncPermissions()`
3. Run: `php artisan db:seed --class=RoleSeeder`

```php
$manager = Role::updateOrCreate(['name' => 'manager', 'guard_name' => 'web'], [
    'description' => 'Department manager',
]);

$manager->syncPermissions([
    'conversation-list',
    'conversation-edit',
    'message-list',
    'message-create',
]);
```

---

## 7. Authorization Flow

```
HTTP Request
     │
     ▼
┌─────────────────────┐
│  1. Middleware       │  Route middleware (auth + permission)
│  (bootstrap/app.php) │  e.g., ->middleware('permission:role-list')
└─────────┬───────────┘
          │ Pass / Fail
          ▼
┌─────────────────────┐
│  2. Gate::before()   │  Super Admin check — returns true for superadmin
│  (AppServiceProvider)│  Skips all further checks
└─────────┬───────────┘
          │ Pass (or Super Admin)
          ▼
┌─────────────────────┐
│  3. Controller       │  Business logic (minimal auth checks here)
│  (RoleController)    │  Uses Spatie methods (syncPermissions, etc.)
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  4. View / Response  │  Blade directives hide/show UI elements
│  (@role, @can)      │  e.g., @role('superadmin') Create Button @endrole
└─────────────────────┘
          │
          ▼
     HTTP Response
```

### Flow Explanation

1. **Request → Middleware**: Route middleware checks if the user has the required permission. `Gate::before()` lets superadmin pass all checks.
2. **Super Admin Bypass**: `Gate::before()` in `AppServiceProvider` returns `true` for any user with `superadmin` role, skipping all further authorization.
3. **Controller**: Handles business logic. Uses `syncPermissions()` and `syncRoles()` instead of raw DB queries.
4. **Blade**: `@role` and `@can` directives control UI visibility without inline PHP.

---

## 8. Best Practices

### Roles vs Permissions

- **Roles** = Groups of permissions. Assign roles to users.
- **Permissions** = Granular abilities. Assign permissions to roles, not directly to users (unless absolutely necessary).
- Use `$user->hasRole('admin')` for role checks.
- Use `$user->can('user.create')` for permission checks.
- Do NOT check permissions directly in controllers — use middleware or policies.

### Using Middleware

Always prefer middleware over controller-level checks:

```php
// Route-level protection (recommended)
Route::resource('/users', UserController::class)
    ->middleware('permission:user-list|user-create|user-edit|user-delete');
```

Available middleware aliases:

| Middleware            | Purpose                                  | Example                               |
| --------------------- | ---------------------------------------- | ------------------------------------- |
| `role:`               | User must have ALL specified roles       | `role:superadmin\|admin`              |
| `permission:`         | User must have ALL specified permissions | `permission:user-list`                |
| `role_or_permission:` | User must have role OR permission        | `role_or_permission:admin\|user-list` |

### Performance Optimization

1. **Caching**: The package caches permissions for 24 hours by default. Cache is flushed automatically when permissions/roles are updated.
2. **Avoid repeated lookups**: Use `load('roles.permissions')` when eager loading.
3. **Use `Gate::before()` for Super Admin**: Avoids checking against hundreds of permissions for superadmin users.
4. **Disable in config** if not needed: `'register_permission_check_method' => true` is fine for most cases.

### Security

1. Always use `Gate::before()` for Super Admin (do NOT assign all permissions explicitly).
2. Never trust client-side role checks — always enforce on the server.
3. Use Form Requests for validation (optional but recommended).
4. Middleware > Controller checks > Blade checks (defense in depth).
5. Keep `'display_permission_in_exception' => false` in production to avoid information leaks.

---

## 9. Common Mistakes

| Mistake                                          | Why It's Wrong                  | Correct Approach                            |
| ------------------------------------------------ | ------------------------------- | ------------------------------------------- |
| `Auth::user()->hasRole('superadmin')` in Blade   | Manual PHP in templates         | Use `@role('superadmin')` directive         |
| Using `detach()` + `assignRole()`                | Two queries instead of one      | Use `syncRoles()`                           |
| Looping `givePermissionTo()` for each permission | N+1 queries                     | Use `syncPermissions([...])`                |
| `DB::table('role_has_permissions')->delete()`    | Bypasses Spatie's logic         | Use `$role->syncPermissions([...])`         |
| Putting permission sync in UserSeeder            | Violates separation of concerns | Permission assignment belongs in RoleSeeder |
| Checking `is_admin` column                       | Not granular, not scalable      | Use Spatie roles and permissions            |
| Assigning all permissions to admin               | Defeats RBAC purpose            | Assign only needed permissions per role     |

---

## 10. Reusable Checklist

Use this checklist for implementing Spatie Permission in any Laravel project:

### Enums (Single Source of Truth)

- [ ] Create `app/Enums/RoleEnum.php` — backed string enum with all role names
- [ ] Create `app/Enums/PermissionEnum.php` — backed string enum with all permission names, `displayName()`, `module()`, `seederData()`, `values()`
- [ ] All role/permission references use `Enum::CASE->value` — no hardcoded strings

### Installation

- [ ] `composer require spatie/laravel-permission`
- [ ] Publish migration: `vendor:publish --tag="migrations"`
- [ ] Publish config: `vendor:publish --tag="config"`
- [ ] Run `php artisan migrate`
- [ ] Add `HasRoles` trait to User model

### Configuration

- [ ] Register middleware aliases in `bootstrap/app.php`:
    - `role` → `RoleMiddleware::class`
    - `permission` → `PermissionMiddleware::class`
    - `role_or_permission` → `RoleOrPermissionMiddleware::class`
- [ ] Add `Gate::before()` in `AppServiceProvider` for Super Admin
- [ ] Configure cache settings in `config/permission.php`

### Database

- [ ] Create `PermissionSeeder` with all permissions
- [ ] Create `RoleSeeder` with roles and `syncPermissions()`
- [ ] Create `UserSeeder` with default users and `assignRole()`
- [ ] Call seeders in correct order: PermissionSeeder → RoleSeeder → UserSeeder

### Routes

- [ ] Apply `permission:` middleware to resource routes
- [ ] Use `role:` middleware for superadmin-only routes
- [ ] Use `permission:A|B` (pipe = OR) for multi-permission access

### Controllers

- [ ] Use `syncPermissions()` instead of loops
- [ ] Use `syncRoles()` instead of `detach()` + `assignRole()`
- [ ] Use `$role->permissions` instead of raw `DB::table('role_has_permissions')`
- [ ] Use `$this->authorize()` or middleware for authorization
- [ ] Remove hardcoded permission name filters

### Views

- [ ] Replace `Auth::user()->hasRole()` with `@role` directive
- [ ] Replace `Auth::user()->can()` with `@can` directive
- [ ] Use `@cannot` for negative checks
- [ ] Use `@hasanyrole` for multiple role checks
- [ ] Add permission-based visibility to sidebar menus (optional)

### Security

- [ ] Verify `Gate::before()` returns `true` for superadmin
- [ ] Verify unauthorized users get 403 on protected routes
- [ ] Verify no hardcoded admin checks remain
- [ ] Check `display_permission_in_exception` is `false` in production
- [ ] Flush permission cache after seeding: `app(PermissionRegistrar::class)->forgetCachedPermissions()`

---

## 11. Quick Reference

### Using Enums (Recommended)

```php
use App\Enums\RoleEnum;
use App\Enums\PermissionEnum;

// Check role
$user->hasRole(RoleEnum::ADMIN->value);
$user->hasAnyRole([RoleEnum::ADMIN->value, RoleEnum::SUPERADMIN->value]);

// Check permission
$user->can(PermissionEnum::USER_LIST->value);

// Assign role
$user->assignRole(RoleEnum::ADMIN->value);
$user->syncRoles([RoleEnum::ADMIN->value]);       // Replaces all roles
$user->removeRole(RoleEnum::ADMIN->value);

// Assign permission
$user->givePermissionTo(PermissionEnum::USER_LIST->value);
$user->syncPermissions([
    PermissionEnum::USER_LIST->value,
    PermissionEnum::USER_CREATE->value,
]);

// Role ↔ Permission
$role = Role::findByName(RoleEnum::ADMIN->value);
$role->syncPermissions([
    PermissionEnum::USER_LIST->value,
    PermissionEnum::USER_CREATE->value,
]);

// Super Admin (in AppServiceProvider)
Gate::before(function (User $user, string $ability) {
    return $user->hasRole(RoleEnum::SUPERADMIN->value) ? true : null;
});
```

### Using String Literals (Legacy)

```php
$user->hasRole('admin');
$user->can('user.list');
$user->assignRole('admin');
$user->syncPermissions(['user.list', 'user.create']);
```

### Blade Directives

```blade
@role(\App\Enums\RoleEnum::SUPERADMIN->value)
    {{-- User is superadmin --}}
@endrole

@hasrole(\App\Enums\RoleEnum::ADMIN->value)
    {{-- User is admin --}}
@endhasrole

@hasanyrole(\App\Enums\RoleEnum::SUPERADMIN->value . '|' . \App\Enums\RoleEnum::ADMIN->value)
    {{-- User is superadmin OR admin --}}
@endhasanyrole

@can(\App\Enums\PermissionEnum::USER_LIST->value)
    {{-- User can list users --}}
@endcan

@cannot(\App\Enums\PermissionEnum::USER_LIST->value)
    {{-- User cannot list users --}}
@endcannot
```

### Routes with Enums

```php
use App\Enums\PermissionEnum;

Route::resource('/users', UserController::class)
    ->middleware('permission:' . PermissionEnum::USER_LIST->value);

// Multiple permissions (OR logic with |)
Route::resource('/users', UserController::class)
    ->middleware('permission:' . implode('|', [
        PermissionEnum::USER_LIST->value,
        PermissionEnum::USER_CREATE->value,
    ]));
```

---

> **Note:** This document follows Spatie Laravel Permission v8+ documentation.
> Always refer to the [official documentation](https://spatie.be/docs/laravel-permission/v8) for the latest updates.
