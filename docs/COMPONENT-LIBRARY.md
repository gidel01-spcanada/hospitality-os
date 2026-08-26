# Component Library Documentation

This document describes all reusable Blade components available in the Afrik Appart design system.

## Overview

Components are located in `resources/views/components/` and can be used with the `<x-component-name>` syntax in Blade templates.

---

## Button Component

**File:** `resources/views/components/button.blade.php`

### Basic Usage

```blade
<x-button>Click Me</x-button>

<!-- With href (link button) -->
<x-button tag="a" href="/dashboard">Dashboard</x-button>
```

### Props

| Prop | Type | Default | Options | Description |
|------|------|---------|---------|-------------|
| `variant` | string | `primary` | `primary`, `secondary`, `ghost`, `danger`, `success`, `warning` | Button color variant |
| `size` | string | `md` | `sm`, `md`, `lg` | Button size |
| `full` | boolean | `false` | - | Full width button |
| `disabled` | boolean | `false` | - | Disable button |
| `loading` | boolean | `false` | - | Show loading spinner |
| `tag` | string | `button` | `button`, `a`, `submit` | HTML tag type |
| `href` | string | `null` | - | Link destination (for tag="a") |
| `type` | string | `button` | `button`, `submit`, `reset` | Button type |

### Examples

```blade
<!-- Primary button (default) -->
<x-button>Save</x-button>

<!-- Secondary outline button -->
<x-button variant="secondary">Cancel</x-button>

<!-- Ghost (text) button -->
<x-button variant="ghost">Learn More</x-button>

<!-- Danger (red) button -->
<x-button variant="danger">Delete</x-button>

<!-- Different sizes -->
<x-button size="sm">Small</x-button>
<x-button size="lg">Large</x-button>

<!-- Full width -->
<x-button full>Continue</x-button>

<!-- Loading state -->
<x-button loading>Processing...</x-button>

<!-- Disabled -->
<x-button disabled>Unavailable</x-button>

<!-- With icon -->
<x-button>
    <x-icon name="plus" />
    Add Item
</x-button>

<!-- Link styled as button -->
<x-button tag="a" href="/profile">My Profile</x-button>

<!-- Form submit -->
<x-button type="submit" variant="primary">Submit Form</x-button>
```

---

## Input Component

**File:** `resources/views/components/input.blade.php`

### Basic Usage

```blade
<x-input name="email" type="email" label="Email Address" />

<x-input name="password" type="password" label="Password" required />
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `type` | string | `text` | Input type (text, email, password, number, date, etc.) |
| `name` | string | - | Input name and ID (required) |
| `label` | string | `null` | Display label |
| `value` | string | `null` | Initial value |
| `placeholder` | string | `null` | Placeholder text |
| `required` | boolean | `false` | Mark as required |
| `disabled` | boolean | `false` | Disable input |
| `size` | string | `md` | Input size: `sm`, `md`, `lg` |
| `error` | string | `null` | Show error message below input |
| `help` | string | `null` | Show help text below input |
| `prefix` | string | `null` | Icon/text prefix |
| `suffix` | string | `null` | Icon/text suffix |

### Examples

```blade
<!-- Basic email input -->
<x-input name="email" type="email" label="Email" />

<!-- With placeholder and help text -->
<x-input 
    name="username" 
    label="Username"
    placeholder="john_doe"
    help="Username must be 3-20 characters"
/>

<!-- Required field -->
<x-input name="name" label="Full Name" required />

<!-- With error message -->
<x-input 
    name="email" 
    label="Email"
    error="Invalid email address"
/>

<!-- Date input -->
<x-input name="birth_date" type="date" label="Date of Birth" />

<!-- Number input -->
<x-input 
    name="guests"
    type="number"
    label="Number of Guests"
    value="2"
    min="1"
    max="8"
/>

<!-- With prefix/suffix -->
<x-input 
    name="price" 
    type="number" 
    label="Price"
    prefix="$"
    placeholder="0.00"
/>

<x-input 
    name="search"
    label="Search"
    suffix="🔍"
/>

<!-- Different sizes -->
<x-input name="sm" size="sm" label="Small" />
<x-input name="md" size="md" label="Medium" />
<x-input name="lg" size="lg" label="Large" />

<!-- Disabled -->
<x-input name="readonly" label="Display Only" disabled value="Cannot edit" />
```

### Textarea

Use regular HTML for textarea with input styles:

```blade
<div class="form-group">
    <label for="bio">Biography</label>
    <textarea 
        name="bio" 
        id="bio" 
        class="input-md"
        placeholder="Tell us about yourself..."
    ></textarea>
    <span class="form-help">Max 500 characters</span>
</div>
```

### Select Dropdown

Use regular HTML for select with input styles:

```blade
<div class="form-group">
    <label for="role">User Role</label>
    <select name="role" id="role" class="input-md">
        <option value="">Select a role...</option>
        <option value="admin">Administrator</option>
        <option value="staff">Staff</option>
        <option value="customer">Customer</option>
    </select>
</div>
```

---

## Card Component

**File:** `resources/views/components/card.blade.php`

### Basic Usage

```blade
<x-card>
    <h3>Card Title</h3>
    <p>Card content goes here.</p>
</x-card>

<!-- With header and footer -->
<x-card>
    <div class="card-header">
        <h3>Settings</h3>
    </div>
    <div class="card-body">
        <!-- Form or content -->
    </div>
    <div class="card-footer">
        <x-button>Save</x-button>
    </div>
</x-card>
```

### Props

| Prop | Type | Default | Options | Description |
|------|------|---------|---------|-------------|
| `variant` | string | `default` | `default`, `elevated`, `outlined`, `flat` | Card style variant |
| `padding` | number | `6` | `4`, `6`, `8` | Padding scale (space tokens) |
| `clickable` | boolean | `false` | - | Add hover effect and click handler |
| `href` | string | `null` | - | Link destination (if clickable) |

### Examples

```blade
<!-- Basic card -->
<x-card>
    <h3>Title</h3>
    <p>Content</p>
</x-card>

<!-- Elevated card with shadow -->
<x-card variant="elevated">
    <h3>Featured</h3>
    <p>This card has a shadow.</p>
</x-card>

<!-- Outlined card -->
<x-card variant="outlined">
    <h3>Important</h3>
    <p>This card has a strong border.</p>
</x-card>

<!-- Flat card -->
<x-card variant="flat">
    <h3>Subtle</h3>
    <p>Minimal styling.</p>
</x-card>

<!-- Clickable card (link) -->
<x-card clickable href="/property/123">
    <img src="property.jpg" alt="Property" />
    <h3>Apartment 401</h3>
    <p>3 bedrooms, available now</p>
</x-card>

<!-- Card with structured sections -->
<x-card>
    <div class="card-header">
        <h3>User Profile</h3>
        <p>Manage your account settings</p>
    </div>
    
    <div class="card-body">
        <ul>
            <li><strong>Name:</strong> <span>John Doe</span></li>
            <li><strong>Email:</strong> <span>john@example.com</span></li>
            <li><strong>Status:</strong> <span>Active</span></li>
        </ul>
    </div>
    
    <div class="card-footer">
        <x-button variant="secondary">Cancel</x-button>
        <x-button>Edit Profile</x-button>
    </div>
</x-card>

<!-- Different padding -->
<x-card padding="4">Compact padding</x-card>
<x-card padding="6">Normal padding</x-card>
<x-card padding="8">Large padding</x-card>
```

---

## Badge Component

**File:** `resources/views/components/badge.blade.php`

### Basic Usage

```blade
<x-badge>New</x-badge>

<x-badge variant="success">Approved</x-badge>

<x-badge variant="error">Error</x-badge>
```

### Props

| Prop | Type | Default | Options | Description |
|------|------|---------|---------|-------------|
| `variant` | string | `primary` | `primary`, `secondary`, `success`, `error`, `warning`, `info`, `neutral` | Badge color |
| `size` | string | `md` | `sm`, `md`, `lg` | Badge size |
| `outline` | boolean | `false` | - | Outline style instead of solid |
| `dot` | boolean | `false` | - | Add indicator dot |
| `closeable` | boolean | `false` | - | Add close button |

### Examples

```blade
<!-- Color variants -->
<x-badge variant="primary">Primary</x-badge>
<x-badge variant="secondary">Secondary</x-badge>
<x-badge variant="success">Success</x-badge>
<x-badge variant="error">Error</x-badge>
<x-badge variant="warning">Warning</x-badge>
<x-badge variant="info">Info</x-badge>
<x-badge variant="neutral">Neutral</x-badge>

<!-- Different sizes -->
<x-badge size="sm">Small</x-badge>
<x-badge size="md">Medium</x-badge>
<x-badge size="lg">Large</x-badge>

<!-- Outline style -->
<x-badge outline variant="primary">Outlined Primary</x-badge>
<x-badge outline variant="success">Outlined Success</x-badge>

<!-- With dot indicator -->
<x-badge dot>Available</x-badge>
<x-badge dot variant="error">Booked</x-badge>

<!-- Closeable -->
<x-badge closeable variant="info">Dismissible Alert</x-badge>

<!-- In lists -->
<ul>
    @foreach ($tags as $tag)
        <li>
            {{ $tag->name }}
            <x-badge variant="secondary" size="sm" closeable>{{ $tag->count }}</x-badge>
        </li>
    @endforeach
</ul>
```

---

## Icon Component

**File:** `resources/views/components/icon.blade.php`

### Basic Usage

```blade
<x-icon name="check" />

<x-icon name="check" size="lg" color="success" />
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `name` | string | - | Icon name (without .svg extension) |
| `size` | string | `md` | Icon size: `xs`, `sm`, `md`, `lg`, `xl`, `2xl` |
| `color` | string | `null` | Color class: `primary`, `success`, `error`, `warning`, `info`, `muted` |

### Examples

```blade
<!-- Basic icon -->
<x-icon name="check" />

<!-- Different sizes -->
<x-icon name="check" size="xs" />
<x-icon name="check" size="sm" />
<x-icon name="check" size="md" />
<x-icon name="check" size="lg" />
<x-icon name="check" size="xl" />

<!-- Color variants -->
<x-icon name="check" color="success" />
<x-icon name="alert" color="error" />
<x-icon name="warning" color="warning" />
<x-icon name="info" color="info" />

<!-- With custom classes -->
<x-icon name="spinner" class="animate-spin text-emerald-600" />

<!-- In buttons -->
<x-button>
    <x-icon name="plus" />
    Add New
</x-button>

<!-- With text -->
<span class="icon-text">
    <x-icon name="check" />
    <span>Completed</span>
</span>

<!-- Icon only with aria-label (accessibility) -->
<button aria-label="Delete item">
    <x-icon name="trash" />
</button>
```

---

## Form Group Component

**File:** `resources/views/components/form-group.blade.php`

### Basic Usage

```blade
<x-form-group title="Personal Information">
    <x-input name="first_name" label="First Name" required />
    <x-input name="last_name" label="Last Name" required />
</x-form-group>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | string | `null` | Section title |
| `description` | string | `null` | Section description/help text |
| `cols` | number | `1` | Number of columns for layout |

### Examples

```blade
<!-- Simple form section -->
<x-form-group title="Contact Information">
    <x-input name="email" type="email" label="Email" required />
    <x-input name="phone" type="tel" label="Phone Number" />
</x-form-group>

<!-- With description -->
<x-form-group 
    title="Billing Address"
    description="Where should we send your invoices?"
    cols="2"
>
    <x-input name="street" label="Street Address" required />
    <x-input name="city" label="City" required />
    <x-input name="state" label="State" required />
    <x-input name="zip" label="ZIP Code" required />
</x-form-group>

<!-- Two-column layout -->
<x-form-group cols="2">
    <x-input name="first_name" label="First Name" />
    <x-input name="last_name" label="Last Name" />
</x-form-group>

<!-- Full form -->
<form method="POST" action="/profile">
    @csrf
    
    <x-form-group title="Personal">
        <x-input 
            name="name" 
            label="Full Name" 
            value="{{ $user->name }}" 
            required 
        />
    </x-form-group>
    
    <x-form-group title="Contact" cols="2">
        <x-input 
            name="email" 
            type="email" 
            label="Email" 
            value="{{ $user->email }}" 
            required 
        />
        <x-input 
            name="phone" 
            type="tel" 
            label="Phone" 
            value="{{ $user->phone }}" 
        />
    </x-form-group>
    
    <x-form-group title="Preferences">
        <div class="form-group">
            <label>
                <input type="checkbox" name="newsletter" checked />
                Subscribe to newsletter
            </label>
        </div>
    </x-form-group>
    
    <div class="form-actions">
        <x-button variant="secondary" tag="a" href="/dashboard">Cancel</x-button>
        <x-button type="submit">Save Changes</x-button>
    </div>
</form>
```

---

## CSS-Only Components

These components use pure CSS classes (no Blade component wrapper):

### Checkbox & Radio

```blade
<div class="checkbox">
    <input type="checkbox" id="agree" name="agree" required />
    <label for="agree">I agree to the terms and conditions</label>
</div>

<div class="radio">
    <input type="radio" id="option1" name="choice" value="1" />
    <label for="option1">Option 1</label>
</div>

<div class="radio">
    <input type="radio" id="option2" name="choice" value="2" />
    <label for="option2">Option 2</label>
</div>
```

### Alerts & Status Messages

```blade
<!-- Form error -->
<span class="form-error">This field is required</span>

<!-- Form help -->
<span class="form-help">Enter your 6-digit verification code</span>

<!-- Form success -->
<span class="form-success">Changes saved successfully</span>

<!-- Help block -->
<div class="form-help-block">
    <strong>Password Requirements:</strong>
    Must be at least 8 characters with uppercase, lowercase, and numbers
</div>
```

### Breadcrumbs

```blade
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <span class="breadcrumb-item">
        <a href="/">Home</a>
    </span>
    <span class="breadcrumb-separator">›</span>
    <span class="breadcrumb-item">
        <a href="/admin">Admin</a>
    </span>
    <span class="breadcrumb-separator">›</span>
    <span class="breadcrumb-item is-current">
        Users
    </span>
</nav>
```

---

## Usage Patterns

### Form Validation Example

```blade
<form method="POST">
    @csrf
    
    <x-input 
        name="email" 
        type="email" 
        label="Email Address"
        value="{{ old('email') }}"
        error="{{ $errors->first('email') }}"
        required
    />
    
    <x-input 
        name="password" 
        type="password" 
        label="Password"
        error="{{ $errors->first('password') }}"
        required
    />
    
    <div class="form-actions">
        <x-button type="submit" variant="primary">Login</x-button>
    </div>
</form>
```

### Admin Table Example

```blade
<div class="card">
    <div class="card-header">
        <h3>Users</h3>
    </div>
    
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong></td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <x-badge variant="primary" size="sm">{{ ucfirst($user->role) }}</x-badge>
                    </td>
                    <td>
                        <x-button tag="a" href="/admin/users/{{ $user->id }}/edit" size="sm" variant="ghost">
                            <x-icon name="pencil" />
                            Edit
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="admin-table-empty">
                        <p>No users found.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

---

## Accessibility

All components follow WCAG 2.1 AA guidelines:

- ✅ Keyboard navigation support
- ✅ Focus indicators
- ✅ ARIA labels and descriptions
- ✅ Semantic HTML
- ✅ Color contrast (WCAG AA)
- ✅ Screen reader support

### Accessibility Checklist for Components

When creating new components:
- [ ] Labels clearly associated with inputs
- [ ] Focus states visible
- [ ] ARIA attributes where needed
- [ ] Color not the only indicator
- [ ] Keyboard accessible
- [ ] Screen reader tested

---

## Next Steps

See `resources/icons/README.md` for Heroicons setup and usage.

For theme customization, see `resources/css/tokens.css`.

For layout and page structure, refer to M03 (Admin Pilot) documentation.
