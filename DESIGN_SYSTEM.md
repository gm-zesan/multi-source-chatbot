# Admin Panel Design System

A modern, premium, reusable design system for the Laravel admin panel. Inspired by industry standards like Laravel Nova, Filament, Linear, Vercel, and Stripe.

## Overview

This design system prioritizes **consistency over creativity**, ensuring that every new module (Users, Roles, Permissions, Categories, Settings, Reports, etc.) automatically inherits the same visual language without requiring additional styling.

## Quick Start

### Import Styles

The design system is automatically included in `resources/scss/app.scss` via:

```scss
@import 'admin/style';
```

### Using Components

```html
<!-- Button -->
<button class="btn btn-primary">Primary Button</button>
<button class="btn btn-secondary">Secondary Button</button>

<!-- Card -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Card Title</h2>
  </div>
  <div class="card-body">
    Content goes here
  </div>
</div>

<!-- Alert -->
<div class="alert alert-success">
  Success message
</div>

<!-- Badge -->
<span class="badge badge-primary">Active</span>
```

## Design Tokens

All design values are centralized in `resources/scss/admin/_variables.scss`:

### Colors

- **Primary**: `$primary` (#3b82f6) - Main action color
- **Success**: `$success` (#10b981) - Positive actions/states
- **Danger**: `$danger` (#ef4444) - Destructive actions/states
- **Warning**: `$warning` (#f59e0b) - Caution states
- **Info**: `$info` (#06b6d4) - Information states

### Spacing

All spacing follows a 4px base unit:

- `$space-xs`: 4px
- `$space-sm`: 8px
- `$space-md`: 12px
- `$space-lg`: 16px
- `$space-xl`: 20px
- `$space-2xl`: 24px
- `$space-3xl`: 32px

### Border Radius

- `$radius-xs`: 4px
- `$radius-sm`: 6px
- `$radius-md`: 8px
- `$radius-lg`: 10px
- `$radius-xl`: 12px
- `$radius-button`: 8px
- `$radius-card`: 12px
- `$radius-modal`: 16px
- `$radius-badge`: 999px

### Shadows

- `$shadow-xs`: Subtle (1px 2px)
- `$shadow-sm`: Light (1px 3px)
- `$shadow-md`: Medium (4px 6px)
- `$shadow-lg`: Large (10px 15px)
- `$shadow-xl`: Extra Large (20px 25px)
- `$shadow-2xl`: Maximum (25px 50px)

## Reusable Mixins

Located in `resources/scss/admin/_mixins.scss`:

```scss
// Flexbox
@include flex-box(flex, center, space-between, $space-lg);

// Grid
@include grid(12, $space-lg);

// Positioning
@include center-absolute;
@include absolute-fill;

// Text
@include truncate;
@include line-clamp(3);
@include text($text-base, $font-medium);

// Buttons
@include button-base;
@include button-variant($bg, $text, $hover-bg, $hover-text);

// Forms
@include input-base;
@include label-styles;
@include form-field;

// Cards
@include card-base;
@include card-elevated;

// Responsive
@include respond-to(md);
@include mobile-only;
@include tablet-up;
@include desktop-up;

// Utilities
@include shadow(lg);
@include rounded($radius-lg);
@include focus-ring($primary);
@include elevation-hover;
@include custom-scrollbar($track, $thumb, $radius);
```

## Component Library

### Buttons

```html
<!-- Variants -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-info">Info</button>

<!-- Outline -->
<button class="btn btn-outline-primary">Outline</button>

<!-- Sizes -->
<button class="btn btn-sm">Small</button>
<button class="btn btn-md">Medium</button>
<button class="btn btn-lg">Large</button>

<!-- Icon Button -->
<button class="btn btn-icon btn-primary">
  <i class="ri-add-line"></i>
</button>

<!-- Disabled -->
<button class="btn btn-primary" disabled>Disabled</button>

<!-- Loading -->
<button class="btn btn-primary is-loading">Loading</button>
```

### Cards

```html
<!-- Basic Card -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Card Title</h3>
  </div>
  <div class="card-body">
    Content
  </div>
  <div class="card-footer">
    Footer content
  </div>
</div>

<!-- Elevated Card -->
<div class="card card-elevated">
  <!-- content -->
</div>

<!-- Variant Cards -->
<div class="card card-primary"><!-- Primary card --></div>
<div class="card card-success"><!-- Success card --></div>
<div class="card card-danger"><!-- Danger card --></div>

<!-- Dashboard Card -->
<div class="dashboard-card">
  <div class="card-stat">
    <div class="stat-value">1,234</div>
    <div class="stat-label">Total Users</div>
    <div class="stat-change positive">+12% from last month</div>
  </div>
</div>

<!-- Table Card -->
<div class="table-card">
  <div class="table-header">
    <div class="table-title">Users</div>
    <a href="#" class="add-new">Create User <i class="ri-add-line"></i></a>
  </div>
  <div class="card-body">
    <table><!-- table content --></table>
  </div>
</div>
```

### Forms

```html
<!-- Text Input -->
<div class="form-group">
  <label class="form-label">Email</label>
  <input type="email" class="form-control" placeholder="Enter email">
</div>

<!-- Textarea -->
<div class="form-group">
  <label class="form-label">Message</label>
  <textarea class="form-control" rows="5"></textarea>
</div>

<!-- Select -->
<div class="form-group">
  <label class="form-label">Category</label>
  <select class="form-control">
    <option>Select category</option>
  </select>
</div>

<!-- Checkbox -->
<div class="form-check">
  <input type="checkbox" id="remember" class="form-check-input">
  <label class="form-check-label" for="remember">Remember me</label>
</div>

<!-- Radio -->
<div class="form-radio">
  <input type="radio" name="choice" id="choice1">
  <label class="form-check-label" for="choice1">Choice 1</label>
</div>

<!-- Switch Toggle -->
<div class="form-switch">
  <input type="checkbox" id="toggle">
  <label class="form-check-label" for="toggle">Enable feature</label>
</div>

<!-- Input Group -->
<div class="input-group">
  <span class="input-group-icon">
    <i class="ri-mail-line"></i>
  </span>
  <input type="email" class="form-control" placeholder="Email">
</div>

<!-- Validation -->
<div class="form-group">
  <label class="form-label">Username</label>
  <input type="text" class="form-control is-invalid">
  <div class="invalid-feedback">Username is required</div>
</div>

<!-- Form Layout -->
<div class="form-row">
  <div>
    <label class="form-label">First Name</label>
    <input type="text" class="form-control">
  </div>
  <div>
    <label class="form-label">Last Name</label>
    <input type="text" class="form-control">
  </div>
</div>
```

### Tables

```html
<!-- Basic Table -->
<div class="table-card">
  <div class="table-header">
    <div class="table-title">Users</div>
    <a href="#" class="add-new">Create User <i class="ri-add-line"></i></a>
  </div>
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>John Doe</td>
          <td>john@example.com</td>
          <td><span class="badge badge-primary">Admin</span></td>
          <td>
            <div class="table-actions">
              <a href="#" class="action-btn btn-edit" title="Edit">
                <i class="ri-edit-line"></i>
              </a>
              <a href="#" class="action-btn btn-delete" title="Delete">
                <i class="ri-delete-bin-line"></i>
              </a>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<div>
  <ul class="pagination">
    <li class="page-item"><a href="#" class="page-link">Previous</a></li>
    <li class="page-item active"><a href="#" class="page-link">1</a></li>
    <li class="page-item"><a href="#" class="page-link">2</a></li>
    <li class="page-item"><a href="#" class="page-link">Next</a></li>
  </ul>
</div>
```

### Badges & Status Badges

```html
<!-- Color Badges -->
<span class="badge badge-primary">Primary</span>
<span class="badge badge-success">Success</span>
<span class="badge badge-danger">Danger</span>
<span class="badge badge-warning">Warning</span>

<!-- Status Badges -->
<span class="badge badge-status status-active">Active</span>
<span class="badge badge-status status-inactive">Inactive</span>
<span class="badge badge-status status-pending">Pending</span>
<span class="badge badge-status status-completed">Completed</span>
<span class="badge badge-status status-draft">Draft</span>
<span class="badge badge-status status-cancelled">Cancelled</span>

<!-- Outline Badges -->
<span class="badge badge-outline-primary">Outline</span>

<!-- Light/Soft Badges -->
<span class="badge badge-light-primary">Light</span>
```

### Alerts

```html
<!-- Basic Alerts -->
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Error message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-info">Info message</div>

<!-- With Icon -->
<div class="alert alert-success alert-icon">
  <div class="alert-icon-content">
    <div class="icon">✓</div>
    <div class="content">
      <div class="alert-title">Success</div>
      <div class="alert-message">Operation completed successfully</div>
    </div>
  </div>
</div>

<!-- Closeable -->
<div class="alert alert-success alert-closeable">
  Success message
  <button class="alert-close">&times;</button>
</div>

<!-- Outline -->
<div class="alert alert-outline alert-outline-primary">Outline alert</div>

<!-- Soft -->
<div class="alert alert-soft alert-soft-success">Soft alert</div>
```

### Modals

```html
<!-- Modal Backdrop -->
<div class="modal-backdrop show"></div>

<!-- Modal Dialog -->
<div class="modal-dialog">
  <div class="modal-header">
    <h2 class="modal-title">Modal Title</h2>
    <button class="modal-close">&times;</button>
  </div>
  <div class="modal-body">
    <!-- Modal content -->
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary">Cancel</button>
    <button class="btn btn-primary">Confirm</button>
  </div>
</div>

<!-- Modal Sizes -->
<div class="modal-dialog modal-sm"><!-- Small --></div>
<div class="modal-dialog modal-lg"><!-- Large --></div>
<div class="modal-dialog modal-xl"><!-- Extra Large --></div>

<!-- Confirmation Modal -->
<div class="modal-dialog modal-confirm">
  <div class="modal-body">
    <div class="modal-icon icon-warning">⚠️</div>
    <h2 class="modal-message">Confirm Action</h2>
    <p class="modal-description">Are you sure you want to proceed?</p>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary">Cancel</button>
    <button class="btn btn-danger">Delete</button>
  </div>
</div>
```

### Dropdowns

```html
<!-- Basic Dropdown -->
<div class="dropdown">
  <button class="btn btn-secondary dropdown-toggle">
    Actions
  </button>
  <div class="dropdown-menu">
    <a href="#" class="dropdown-item">Edit</a>
    <a href="#" class="dropdown-item">View</a>
    <div class="dropdown-divider"></div>
    <a href="#" class="dropdown-item">Delete</a>
  </div>
</div>

<!-- With Icons -->
<div class="dropdown-menu">
  <a href="#" class="dropdown-item dropdown-item-with-icon">
    <i class="dropdown-icon ri-edit-line"></i>
    <span class="dropdown-label">Edit</span>
  </a>
</div>

<!-- Dropdown Variants -->
<div class="dropup"><!-- Opens upward --></div>
<div class="dropend"><!-- Opens right --></div>
<div class="dropstart"><!-- Opens left --></div>
```

### Layout Components

```html
<!-- Page Container -->
<div class="container-fluid">
  <div class="row">
    <div class="col-md-8">
      <!-- Main content -->
    </div>
    <div class="col-md-4">
      <!-- Sidebar -->
    </div>
  </div>
</div>

<!-- Page Header -->
<div class="page-header">
  <h1>Page Title</h1>
  <p>Page description</p>
</div>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">Home</a></li>
    <li class="breadcrumb-item"><a href="#">Users</a></li>
    <li class="breadcrumb-item active">Edit User</li>
  </ol>
</nav>

<!-- Section -->
<section class="section">
  <h2 class="section-title">Section Title</h2>
  <p class="section-description">Section description</p>
  <!-- content -->
</section>
```

## Responsive Design

The design system includes responsive utilities:

```scss
// Responsive classes
@include respond-to(md) { /* medium screens */ }
@include respond-to(lg) { /* large screens */ }
@include mobile-only { /* mobile only */ }
@include tablet-up { /* tablet and up */ }
@include desktop-up { /* desktop and up */ }
```

## File Structure

```
resources/scss/admin/
├── style.scss              # Main entry point
├── _variables.scss         # Design tokens
├── _mixins.scss           # Reusable utilities
├── _layout.scss           # Layout & containers
├── _buttons.scss          # Button components
├── _cards.scss            # Card components
├── _forms.scss            # Form components
├── _tables.scss           # Table components
├── _badges.scss           # Badge components
├── _alerts.scss           # Alert components
├── _modals.scss           # Modal components
├── _dropdowns.scss        # Dropdown components
└── table.scss             # Legacy DataTables styles (deprecated)
```

## Best Practices

### 1. Use Design Tokens

Always use variables instead of hard-coded values:

```scss
// ✅ Good
background-color: $primary;
padding: $space-lg;
border-radius: $radius-button;

// ❌ Avoid
background-color: #3b82f6;
padding: 16px;
border-radius: 8px;
```

### 2. Use Mixins

Leverage mixins for common patterns:

```scss
// ✅ Good
@include flex-box(flex, center, space-between);
@include input-base;
@include button-base;

// ❌ Avoid
display: flex;
align-items: center;
justify-content: space-between;
```

### 3. Maintain Component Consistency

Use pre-built classes instead of creating custom styles:

```html
<!-- ✅ Good -->
<button class="btn btn-primary">Save</button>

<!-- ❌ Avoid -->
<button style="background: #3b82f6; padding: 12px 16px;">Save</button>
```

### 4. Follow Responsive Guidelines

Use the responsive mixins consistently:

```scss
.component {
    padding: $space-lg;
    
    @include mobile-only {
        padding: $space-md;
    }
    
    @include desktop-up {
        padding: $space-2xl;
    }
}
```

## Customization

### Change Primary Color

Edit `resources/scss/admin/_variables.scss`:

```scss
$primary: #your-color;
```

### Add New Component

1. Create new file: `resources/scss/admin/_component-name.scss`
2. Import in `style.scss`:

```scss
@import 'component-name';
```

### Override Variables

All variables can be customized in `_variables.scss`. The system is built to be flexible while maintaining consistency.

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- IE 11 not supported

## Performance

- **CSS Size**: ~70KB (uncompressed), ~11.68KB (gzipped)
- **Minimal HTTP requests**: All styles bundled in single file
- **Optimized**: No unused styles, all CSS is utilized
- **Fast load times**: Efficient selectors and minimal nesting

## Migration Guide

If updating from legacy styles:

1. Replace inline styles with utility classes
2. Use `btn` class instead of `custom-button`
3. Use `form-control` instead of `custom-input`
4. Use `card` instead of `table-card` wrapper
5. Use standard badge classes instead of custom styles

## Contributing

When adding new features to the admin panel:

1. **Don't create new custom styles**
2. Use existing classes and components
3. If you need a new component, add it to the appropriate SCSS file
4. Follow the naming conventions and spacing system
5. Ensure responsive design is implemented
6. Test across desktop, tablet, and mobile

## Support

For questions or issues with the design system:

1. Check this documentation
2. Review the component examples
3. Check the SCSS files for implementation details
4. Ask the development team

## Version

Current Version: 1.0.0 (2024)

Modern Premium Admin Panel Design System
