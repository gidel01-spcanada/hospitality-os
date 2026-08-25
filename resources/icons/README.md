# Heroicons Setup Guide

This directory contains SVG icons from **Heroicons**, an open-source icon set by Tailwind Labs.

## Icon Setup

### Option 1: Automatic Download (Recommended)

Run the following command to download all Heroicons (24x24 solid) SVG files:

```bash
# Create temporary directory for download
mkdir -p /tmp/heroicons

# Download Heroicons repository
curl -L https://github.com/tailwindlabs/heroicons/archive/refs/heads/main.zip -o /tmp/heroicons.zip
unzip /tmp/heroicons.zip -d /tmp/heroicons

# Copy all solid 24px icons to this directory
cp /tmp/heroicons/heroicons-main/src/24/solid/* resources/icons/

# Clean up
rm -rf /tmp/heroicons /tmp/heroicons.zip
```

### Option 2: Manual Download

1. Visit https://heroicons.com/
2. Download each icon you need as SVG (24x24 Solid recommended)
3. Place SVG files in `resources/icons/`
4. Name files with kebab-case (e.g., `check-circle.svg`, `user-plus.svg`)

### Option 3: NPM Package

```bash
# Install Heroicons NPM package
npm install @heroicons/react

# Then use in Blade templates via JavaScript components (advanced)
```

## Icon Files Included

Currently included sample icons:
- `check.svg` – Checkmark icon
- `x-mark.svg` – Close/cancel icon
- `plus.svg` – Add/create icon

## Icons Needed for M02-M07

Core icons used in forms, tables, and admin UX:

**CRUD Actions:**
- `check-circle.svg` – Success
- `x-circle.svg` – Error
- `exclamation-circle.svg` – Warning
- `information-circle.svg` – Info
- `plus.svg` – Add/create
- `minus.svg` – Remove
- `pencil.svg` (or `pencil-square.svg`) – Edit
- `trash.svg` – Delete
- `archive.svg` – Archive

**Navigation:**
- `chevron-down.svg` – Dropdown/collapse
- `chevron-left.svg` – Back/previous
- `chevron-right.svg` – Next/forward
- `bars-3.svg` (or `menu.svg`) – Mobile menu
- `home.svg` – Home
- `square-3-stack-3d.svg` (or `window.svg`) – Dashboard

**Entities & Common:**
- `users.svg` – Users/team
- `user.svg` – Single user
- `building-office.svg` (or `building.svg`) – Organization
- `calendar-days.svg` (or `calendar.svg`) – Dates
- `clock.svg` – Time
- `envelope.svg` – Email
- `phone.svg` – Phone
- `map-pin.svg` – Location

**Settings & Admin:**
- `cog-6-tooth.svg` (or `cog.svg`) – Settings
- `sliders-horizontal.svg` (or `adjustments.svg`) – Filters
- `magnifying-glass.svg` (or `search.svg`) – Search
- `list-bullet.svg` (or `list.svg`) – Lists
- `square-2-stack.svg` – Duplicate/copy
- `arrow-top-right-on-square.svg` – External link
- `eye.svg` – View/show
- `eye-slash.svg` – Hide

**Interactive:**
- `bell.svg` – Notifications
- `heart.svg` – Favorite/like
- `star.svg` – Star/rating
- `bookmark.svg` – Bookmark/save
- `share.svg` – Share
- `download.svg` – Download
- `upload.svg` (or `arrow-up-tray.svg`) – Upload

## Using Icons in Templates

### In Blade Components

```blade
<!-- Using the icon component -->
<x-icon name="check" size="md" color="success" />

<!-- With text -->
<button>
    <x-icon name="plus" size="sm" />
    <span>Add Item</span>
</button>

<!-- In buttons -->
<x-button variant="primary">
    <x-icon name="check" />
    Save Changes
</x-button>
```

### Icon Component Props

- `name` (string) – Icon file name without `.svg` (required)
- `size` (string) – Icon size: `xs`, `sm`, `md`, `lg`, `xl`, `2xl` (default: `md`)
- `color` (string) – Color class: `primary`, `success`, `error`, `warning`, `info`, `muted`, `disabled`
- Any other HTML attributes (class, data-*, aria-*, etc.)

### Styling Icons

```blade
<!-- Using Tailwind classes -->
<x-icon name="check" class="text-emerald-600 hover:text-emerald-700" />

<!-- Using color prop -->
<x-icon name="check" color="success" />

<!-- Animated icon -->
<x-icon name="check" class="animate-spin" />
```

## Icon Naming Convention

Use **kebab-case** for icon file names:
- ✅ `check-circle.svg`
- ✅ `user-plus.svg`
- ❌ `checkCircle.svg`
- ❌ `Check_Circle.svg`

When referencing in templates, use the same kebab-case name (without `.svg`):

```blade
<x-icon name="check-circle" />  <!-- Loads check-circle.svg -->
```

## Icon Accessibility

All icons should include text or aria-labels:

```blade
{{-- Good: Icon with text --}}
<button>
    <x-icon name="trash" />
    <span>Delete Item</span>
</button>

{{-- Good: Icon with aria-label --}}
<button aria-label="Delete this item">
    <x-icon name="trash" />
</button>

{{-- Avoid: Icon-only without context --}}
<button title="Delete">
    <x-icon name="trash" />
</button>
```

## Reference

- **Heroicons Repository:** https://github.com/tailwindlabs/heroicons
- **Heroicons Website:** https://heroicons.com/
- **License:** MIT (free for commercial use)
- **Version:** v2.0+
- **Format:** SVG 24x24 (solid variant)

## Troubleshooting

**Icon not displaying:**
1. Check file name matches icon name (kebab-case)
2. Verify SVG file is in `resources/icons/`
3. Check browser console for include path errors
4. Ensure SVG has valid `<path>` elements

**Icon styling not working:**
1. Check if icon class uses `fill="currentColor"`
2. Try adding explicit color class: `<x-icon name="check" class="text-emerald-600" />`
3. Inspect element to see if CSS is loading

## Next Steps (M03+)

- [ ] Download full Heroicons set
- [ ] Test all icon sizes and colors
- [ ] Add custom brand icons if needed
- [ ] Document icon usage in component library
- [ ] Create icon showcase/demo page
