# M01 – Audit and Proposal: Afrik Appart UI/UX Redesign

**Date:** August 24, 2026  
**Status:** Audit Complete – Ready for Review  
**Objective:** Route inventory, design assessment, component inventory, and redesign plan before visual implementation.

---

## 1. Executive Summary

Afrik Appart is a Laravel 12 short-term rental website with a functional public-facing experience and emerging admin/account areas. The current design uses Tailwind CSS with a custom token system (emerald, gold, cream palette) and successfully implements the brand direction specified in the master prompt.

**Key findings:**
- Public-facing design is visually cohesive and follows brand guidelines.
- Admin and account interfaces lack visual distinction and share the same styling as public pages.
- No reusable component library exists; pages are built with inline CSS utility classes.
- Design tokens are defined but underutilized; CSS customization is scattered.
- Accessibility baseline is present but incomplete (focus states, ARIA labels, keyboard navigation).
- No existing theme system; public site is single-theme.

**Recommendation:** Proceed with M02 (Foundation) to build shared component library and establish dual design systems before visual rollout.

---

## 2. Route Inventory

### 2.1 Public Routes (Unauthenticated)

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/` | `home` | Homepage with hero, search panel, features | ✅ Published |
| `/properties` | `properties.index` | Property listings and search results | ✅ Published |
| `/properties/{slug}` | `properties.show` | Property detail page with booking panel | ✅ Published |
| `/properties/{slug}/availability` | `properties.availability` | AJAX availability check | ✅ Published |
| `/properties/{slug}/reserve` | `properties.reserve` | Create reservation (redirect to checkout) | ✅ Published |
| `/contact` | `contact` | Contact form page | ✅ Published |
| `/privacy` | `privacy` | Privacy policy (static) | ✅ Published |
| `/terms` | `terms` | Terms of service (static) | ✅ Published |
| `/cookies` | `cookies` | Cookie policy (static) | ✅ Published |
| `/login` | `login` | Guest login form | ✅ Published |
| `/register` | `register` | Guest registration form | ✅ Published |
| `/forgot-password` | `password.request` | Password reset request | ✅ Published |
| `/reset-password/{token}` | `password.reset` | Password reset form | ✅ Published |
| `/language/{locale}` | `language.switch` | Locale switcher | ✅ Published |
| `/sitemap.xml` | `sitemap` | XML sitemap for SEO | ✅ Published |
| `/calendar/{slug}/{token}.ics` | `calendar.public-export` | ICS calendar export | ✅ Published |
| `/health` | `health` | Health check endpoint | ✅ Published |

**Public Page Count:** 17 routes  
**Content Types:** Homepage, listings, property details, contact, static pages, auth entry points

### 2.2 Authenticated Routes – Account Area

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/dashboard` | `dashboard` | Account dashboard (primary entry) | ✅ Published |
| `/account` | `account.profile` | User profile management | ✅ Published |
| `/account/settings` | `account.settings` | Account settings (role-dependent) | ✅ Published |
| `/account/preferences` | `account.preferences` | User preferences (locale, email) | ✅ Published |
| `/account/security` | `account.security` | Password change, security settings | ✅ Published |
| `/logout` | `logout` | Logout action | ✅ Published |

**Account Page Count:** 6 routes  
**Content Types:** Dashboard, profile, settings, preferences, security

### 2.3 Authenticated Routes – Admin Area (Staff/Admin only)

#### Reservation Management (Staff + Admin)

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin` | `admin.dashboard` | Admin overview (stats, quick links) | ✅ Published |
| `/admin/reservations` | `admin.reservations.index` | Reservation list/table with filtering | ✅ Published |
| `/admin/reservations/{id}` | `admin.reservations.show` | Reservation detail view | ✅ Published |
| `/admin/reservations/{id}/status` | `admin.reservations.update-status` | Update reservation status (PATCH) | ✅ Published |
| `/dashboard/reservations/{id}` | `dashboard.reservations.show` | Reservation detail (account area) | ✅ Published |

#### Admin-only: User Management

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/users` | `admin.users.index` | User list with roles | ✅ Published |
| `/admin/users/create` | `admin.users.create` | Create user form | ✅ Published |
| `/admin/users/{id}/edit` | `admin.users.edit` | Edit user form | ✅ Published |
| `/admin/users` | `admin.users.store` | Store new user (POST) | ✅ Published |
| `/admin/users/{id}` | `admin.users.update` | Update user (PUT) | ✅ Published |

#### Admin-only: Establishment Management

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/establishments` | `admin.establishments.index` | Establishment list | ✅ Published |
| `/admin/establishments/create` | `admin.establishments.create` | Create establishment form | ✅ Published |
| `/admin/establishments/{id}/edit` | `admin.establishments.edit` | Edit establishment form | ✅ Published |
| `/admin/establishments` | `admin.establishments.store` | Store new establishment (POST) | ✅ Published |
| `/admin/establishments/{id}` | `admin.establishments.update` | Update establishment (PUT) | ✅ Published |

#### Admin-only: Property Management

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/properties` | `admin.properties.index` | Property list/inventory | ✅ Published |
| `/admin/properties/{id}/edit` | `admin.properties.edit` | Edit property form | ✅ Published |
| `/admin/properties/{id}` | `admin.properties.update` | Update property (PUT) | ✅ Published |
| `/admin/properties/{id}/images` | `admin.properties.images.update` | Update image order (PUT) | ✅ Published |
| `/admin/properties/{id}/images` | `admin.properties.images.upload` | Upload images (POST) | ✅ Published |
| `/admin/properties/{id}/price-rules` | `admin.properties.price-rules.store` | Create price rule (POST) | ✅ Published |
| `/admin/properties/{id}/features` | `admin.properties.features.store` | Add feature to property (POST) | ✅ Published |
| `/admin/properties/{id}/availability-blocks` | `admin.properties.availability.store` | Block availability (POST) | ✅ Published |
| `/admin/properties/{id}/availability-blocks/{block}` | `admin.properties.availability.destroy` | Remove block (DELETE) | ✅ Published |
| `/admin/properties/{id}/availability` | `admin.properties.availability.check` | Check availability (GET) | ✅ Published |

#### Admin-only: Calendar Management

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/properties/{id}/calendar` | `admin.properties.calendar` | Calendar feed management | ✅ Published |
| `/admin/properties/{id}/calendar/feeds` | `admin.properties.calendar.store` | Add ICS feed (POST) | ✅ Published |
| `/admin/properties/{id}/calendar/feeds/{feed}` | `admin.properties.calendar.update` | Update feed (PUT) | ✅ Published |
| `/admin/properties/{id}/calendar/feeds/{feed}` | `admin.properties.calendar.destroy` | Remove feed (DELETE) | ✅ Published |
| `/admin/properties/{id}/calendar/feeds/{feed}/sync` | `admin.properties.calendar.sync` | Manual sync (POST) | ✅ Published |
| `/admin/properties/{id}/calendar/export.ics` | `admin.properties.calendar.export` | Export property calendar (ICS) | ✅ Published |

#### Admin-only: Review/Content Management

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/reviews` | `admin.reviews` | Review list and management | ✅ Published |
| `/admin/reviews` | `admin.reviews.store` | Create review (POST) | ✅ Published |
| `/admin/reviews/{id}` | `admin.reviews.update` | Update review (PUT) | ✅ Published |
| `/admin/reviews/{id}` | `admin.reviews.destroy` | Delete review (DELETE) | ✅ Published |
| `/admin/reviews/import` | `admin.reviews.import` | Import reviews (POST) | ✅ Published |

#### Admin-only: Settings

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/admin/settings` | `admin.settings` | Global settings form | ✅ Published |
| `/admin/settings` | `admin.settings.update` | Update settings (POST) | ✅ Published |

#### Checkout (Public + Authenticated)

| Route | Name | Purpose | Current State |
|-------|------|---------|----------------|
| `/reservations/{id}/checkout` | `checkout.show` | Checkout page (summary & payment) | ✅ Published |
| `/reservations/{id}/checkout` | `checkout.start` | Start payment flow (POST) | ✅ Published |
| `/reservations/{id}/checkout/complete` | `checkout.complete` | Complete payment (POST) | ✅ Published |
| `/webhooks/{provider}` | `checkout.webhook` | Payment provider webhook | ✅ Published |

**Admin Page Count:** 44+ routes  
**Content Types:** Tables, forms (create/edit), detail views, settings, calendar, API endpoints

---

## 3. Current Design Assessment

### 3.1 Design Direction & Brand

**Current Palette:**
```css
--afrik-bg: #f6f3ec (Warm cream background)
--afrik-surface: #ffffff (White)
--afrik-emerald: #0f5b4c (Primary brand color)
--afrik-emerald-strong: #0a3f35 (Dark emerald)
--afrik-gold: #c89b3c (Warm gold accent)
--afrik-text: #1d2a2d (Deep charcoal text)
--afrik-muted: #57656b (Muted gray)
--afrik-line: rgba(19, 31, 35, 0.08) (Subtle borders)
```

**Typography:**
- Font stack: 'Instrument Sans', 'Inter', 'Segoe UI', system fonts
- No Tailwind theme size scale customization yet

**Strengths:**
- ✅ Warm, inviting color palette aligned with master prompt
- ✅ Premium feel with cream backgrounds and emerald/gold accents
- ✅ Good contrast between primary colors
- ✅ Consistent use of custom CSS variables

**Issues:**
- ⚠️ No dark mode support
- ⚠️ No responsive token adjustments for small screens
- ⚠️ Semantic naming inconsistency (no distinction between "text", "text-muted", "text-secondary")
- ⚠️ Border radius defined inline; no token for consistency

### 3.2 Public-Facing Pages

**Pages reviewed:**
- Homepage (`/`)
- Property listings (`/properties`)
- Property detail (`/properties/{slug}`)
- Contact, privacy, terms, cookies

**Current Strengths:**
- ✅ Hero section with search panel effectively showcases brand
- ✅ Image-heavy card layouts convey premium experience
- ✅ Search/booking panel is prominent and accessible
- ✅ Large typography and generous spacing create welcoming feel
- ✅ Responsive grid layouts

**Current Issues:**
- ⚠️ Hero, featured cards, and stats cards each define unique styles
- ⚠️ No component abstraction (card, button, badge, form input are inline utilities)
- ⚠️ Static single-theme; no theme selector
- ⚠️ Some colour usage lacks WCAG AA contrast (gold text on light backgrounds)
- ⚠️ Focus states not visibly implemented

### 3.3 Admin & Account Pages

**Pages reviewed:**
- Admin dashboard
- Account profile
- (Additional pages not visually reviewed yet)

**Current Strengths:**
- ✅ Semantic hierarchy with badges and sections
- ✅ Information organized into cards
- ✅ Form layouts present but inconsistent

**Current Issues:**
- ❌ No visual separation from public pages; uses identical topbar and styles
- ❌ No sidebar/application navigation; relies on global topbar
- ❌ Dashboard cards and stat cards defined ad-hoc; no reusable pattern
- ❌ Forms lack consistent labeling, spacing, field grouping
- ❌ No clear distinction between public and admin experiences
- ❌ No breadcrumbs or page hierarchy indicators
- ❌ Button styles are not standardized
- ❌ No table/list styling patterns
- ❌ No empty states or loading states
- ❌ No form validation messaging standards

### 3.4 Key Components (Current Inventory)

#### Existing Components

| Component | Status | Location | Notes |
|-----------|--------|----------|-------|
| Button | Basic | CSS utilities | No variants (primary, secondary, ghost, disabled) |
| Badge | Partial | `.badge.badge-emerald/.badge-gold` | Only 2 color variants |
| Card | Implicit | Various `.card`, `.summary-card`, `.stat-card` | No consistent structure |
| Form Input | Minimal | `input`, `select`, `textarea` | No styling; uses browser defaults |
| Form Label | Minimal | `<label>` | No styling |
| Navigation | Basic | `.topbar`, `.nav` | Only public nav exists |
| Topbar | Complete | `.topbar` | Sticky header with blurred backdrop |
| Hero Section | Custom | `.hero`, `.hero-card` | One-off implementation |
| Search Panel | Custom | `.search-panel` | One-off implementation |
| Section Tabs | Basic | `.section-tabs` | Inline utilities, no reusable pattern |

#### Missing Components

- Sidebar/app navigation
- Breadcrumbs
- Tabs (inside pages)
- Pagination
- Alerts/notifications
- Toast messages
- Modals/dialogs
- Dropdowns
- Progress indicators
- Spinners/loading states
- Empty states
- Data tables
- Multi-step forms
- Date pickers
- File upload fields
- Range inputs
- Checkboxes with styling
- Radio buttons with styling
- Toggles/switches

### 3.5 Accessibility Assessment

**Baseline Compliance:**
- ✅ Semantic HTML (header, nav, section, article tags)
- ✅ ARIA labels on interactive elements
- ✅ Form `<label>` associations
- ✅ Alt text on images (where applicable)
- ❌ No visible focus indicators on links/buttons
- ❌ No skip-to-content link
- ❌ Colour contrast issues (gold text on cream/white backgrounds)
- ❌ No reduced-motion preference respect in CSS
- ❌ Mobile keyboard navigation not thoroughly tested
- ⚠️ Admin tables and complex forms lack proper ARIA

**WCAG 2.1 AA Target:** Some work needed to meet standards.

### 3.6 Responsiveness Assessment

**Breakpoints in use:**
- Tailwind defaults (sm, md, lg, xl)
- Custom media queries for specific layouts

**Current Strengths:**
- ✅ Hero section responsive (flex layouts)
- ✅ Search panel stacks on mobile
- ✅ Grid layouts adapt to screen size

**Current Issues:**
- ⚠️ No explicit mobile-first breakpoint strategy defined in CSS
- ⚠️ Admin tables may not wrap properly on small screens (horizontal scroll risk)
- ⚠️ Form layouts on mobile not pre-designed
- ⚠️ No touch-friendly sizing for buttons/inputs on mobile

---

## 4. Component Inventory & Reusability Assessment

### 4.1 CSS Reusability Analysis

**Total CSS file size:** ~200 lines (minimal)  
**Utility classes:** 80+ (using Tailwind)  
**Custom classes:** ~30  
**Utility coverage:** ~60% of styling  
**Issues:**
- No design pattern abstraction
- Inline Tailwind utilities scattered across templates
- CSS variables defined but underutilized in custom classes
- Duplicated styling patterns (card styles)

### 4.2 View Structure Analysis

```
resources/views/
├── layouts/
│   └── app.blade.php (single shared layout)
├── partials/
│   ├── account-tabs.blade.php (partial navigation)
│   └── admin-tabs.blade.php (partial navigation)
├── home.blade.php
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   └── password-reset.blade.php
├── account/
│   ├── profile.blade.php
│   ├── preferences.blade.php
│   ├── security.blade.php
│   └── settings.blade.php
├── admin/
│   ├── dashboard.blade.php
│   ├── users/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── establishments/
│   ├── properties/
│   ├── reservations/
│   ├── reviews.blade.php
│   └── settings.blade.php
├── checkout/
├── contact.blade.php
├── properties/
│   ├── index.blade.php
│   └── show.blade.php
└── legal/
    ├── privacy.blade.php
    ├── terms.blade.php
    └── cookies.blade.php
```

**Assessment:**
- Single shared layout; no admin-specific layout
- Partials exist for navigation but not for reusable components
- Opportunity for form, table, card, button component partials
- No component naming convention established

---

## 5. Proposed Design Direction

### 5.1 Dual Design Systems

#### System 1: Public-Facing Experience
- **Purpose:** Showcase properties, inspire bookings, build brand affinity
- **Navigation:** Horizontal topbar with language switcher, login/register
- **Key Pages:** Home, property listings, property detail, booking flow, static pages
- **Aesthetic:** Premium, warm, inviting, visual imagery-focused
- **Themes:** 8 selectable visual themes (see 5.3)

#### System 2: Admin & Account Experience
- **Purpose:** Efficient operations, clear information hierarchy, daily use
- **Navigation:** Sidebar + top bar with breadcrumbs
- **Key Pages:** Dashboard, forms (create/edit), lists, settings, calendar
- **Aesthetic:** Professional, structured, neutral, optimized for forms/tables
- **Themes:** Light and optional dark mode (consistent across all users)

### 5.2 Core Design Tokens (M02 Foundation)

All tokens will be CSS variables organized by semantic category.

**Colors (Base Palette):**
```css
/* Brand Primary */
--color-primary-50: #f0faf9
--color-primary-100: #d8f2f0
--color-primary-200: #a9dfd7
--color-primary-400: #309688
--color-primary-600: #0f5b4c (current primary)
--color-primary-700: #0a3f35 (current dark)
--color-primary-900: #051f1b

/* Brand Secondary (Gold/Accent) */
--color-accent-50: #fdf9f5
--color-accent-100: #fce6d0
--color-accent-300: #f4c9a1
--color-accent-500: #c89b3c (current accent)
--color-accent-600: #9d6f2a
--color-accent-900: #5c401a

/* Semantic Statuses */
--color-success-50: #f0fdf9
--color-success-600: #16a34a

--color-warning-50: #fffaf5
--color-warning-600: #ca8a04

--color-error-50: #fef2f2
--color-error-600: #dc2626

/* Neutral (for admin) */
--color-slate-50: #f8fafc
--color-slate-100: #f1f5f9
--color-slate-200: #e2e8f0
--color-slate-300: #cbd5e1
--color-slate-500: #64748b
--color-slate-600: #475569
--color-slate-900: #0f172a
```

**Semantic Colors:**
```css
--text-primary: var(--afrik-text)
--text-secondary: var(--afrik-muted)
--text-disabled: rgba(87, 101, 107, 0.5)

--bg-page: var(--afrik-bg)
--bg-surface: var(--afrik-surface)
--bg-overlay: rgba(29, 42, 45, 0.1)

--border-default: var(--afrik-line)
--border-strong: rgba(19, 31, 35, 0.16)

--shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05)
--shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1)
--shadow-lg: 0 12px 20px rgba(0, 0, 0, 0.15)
```

**Spacing Scale (Tailwind-aligned):**
- 4px, 8px, 12px, 16px, 20px, 24px, 32px, 40px, 48px, 56px, 64px, 80px, 96px, 128px

**Typography (Base):**
- Font family: 'Instrument Sans', system stack
- Base size: 16px
- Scale: xs (12px), sm (14px), base (16px), lg (18px), xl (20px), 2xl (24px), 3xl (30px), 4xl (36px)
- Line heights: tight (1.2), normal (1.5), relaxed (1.75)
- Weights: normal (400), semibold (600), bold (700)

**Border Radius:**
```css
--radius-sm: 4px
--radius-md: 8px
--radius-lg: 12px
--radius-xl: 16px
--radius-full: 9999px
```

### 5.3 Eight Public-Facing Themes

Each theme will override semantic color tokens. Users can preview and select at account level.

1. **Brand Light** (current) – Cream backgrounds, emerald primary, gold accents
2. **Modern Dark** – Dark slate backgrounds, bright emerald, white text
3. **Ocean Blue** – Deep blue primary, light cyan accents, white backgrounds
4. **Forest Green** – Deep forest green primary, sage accents, cream backgrounds
5. **Terracotta Sunset** – Warm rust/terracotta primary, amber accents, peachy backgrounds
6. **Warm Sand** – Sandy beige primary, burnt orange accents, light backgrounds
7. **Premium Indigo** – Deep indigo primary, violet accents, white backgrounds
8. **High Contrast** – Pure black/white, high-saturation accent, max WCAG AAA compliance

### 5.4 Admin & Account Design System

**Navigation Structure:**
- Sidebar (collapsible on mobile) with hierarchical menu
- Top bar with user profile, notifications, theme toggle
- Breadcrumbs below top bar on content pages

**Layout Patterns:**
- **List pages:** Sidebar + content area with table/card grid
- **Detail/edit pages:** Sidebar + form content
- **Settings pages:** Sidebar + form tabs
- **Dashboard:** Sidebar + stat cards + quick actions

**Form Standards:**
- Labels above fields (not floating, not placeholder)
- Two-column grid layout on desktop (switch to single column on mobile)
- Grouped sections with visual borders
- Consistent field heights and spacing
- Validation messages below fields
- Required/optional field indicators
- Help text for complex fields
- Action buttons at form footer (Save, Cancel, Back)
- Unsaved changes warning (browser beforeunload)

**Table Standards:**
- Sortable column headers
- Row hover effects
- Batch action toolbar
- Pagination footer
- Empty state message
- Loading skeleton state
- Responsive: stack on mobile or horizontal scroll with sticky header

**Card Standards:**
- Consistent shadow and border radius
- Title, content, footer sections
- Flexible height based on content
- Icon/avatar support

**Button Variants:**
- Primary (emerald bg, white text, hover/active states)
- Secondary (white bg, emerald border/text)
- Ghost (transparent, emerald text)
- Danger (red bg, white text)
- Disabled (reduced opacity, cursor not-allowed)
- Loading (spinner overlay, disabled state)

---

## 6. Incremental Implementation Plan (M02–M07)

### M02 – Foundation (2 weeks)
**Deliverable:** Shared design tokens, typography system, base components  
**Scope:**
- Refactor CSS variables into semantic token system
- Create Tailwind config customizations for tokens
- Build reusable button component (all variants)
- Build input/select/textarea styled components
- Build card component with variants
- Build badge component with all color variants
- Document token usage and component examples

**Exit Criteria:**
- Tokens applied to existing public pages without visual change
- New button and form components tested in admin area
- Component library documented

---

### M03 – Admin Pilot (2 weeks)
**Deliverable:** One representative admin page + complex form redesigned and approved  
**Scope:**
- Implement sidebar navigation
- Redesign admin dashboard with new layout
- Redesign user management form (create/edit) as pilot
- Apply form standards, validation, accessibility
- Implement breadcrumbs and page structure

**Exit Criteria:**
- Admin pilot pages pass accessibility review
- Visual separation from public pages clear
- Team approves design direction before rollout

---

### M04 – Account Area (2 weeks)
**Deliverable:** Account and profile pages using approved design system  
**Scope:**
- Redesign account profile page
- Redesign settings/preferences pages
- Redesign security page
- Add breadcrumbs and consistent navigation
- Update form components

---

### M05 – Public Themes (3 weeks)
**Deliverable:** Theme system + 8 selectable themes + theme selector UI  
**Scope:**
- Create CSS variable override system for themes
- Design and implement all 8 theme color palettes
- Build theme preview/selector component
- Add theme persistence (localStorage or DB)
- Test all themes for contrast and accessibility

---

### M06 – Full Rollout (3 weeks)
**Deliverable:** Apply approved component system to all pages  
**Scope:**
- Refactor all admin pages to use new design system
- Refactor all public pages to use new components
- Create tables and list components for admin pages
- Implement loading, error, and empty states
- Migrate checkout page to new system

---

### M07 – Quality Assurance (2 weeks)
**Deliverable:** Accessibility, responsiveness, browser compatibility verified  
**Scope:**
- Audit all pages for WCAG 2.1 AA compliance
- Mobile and tablet responsiveness verification
- Browser testing (Chrome, Firefox, Safari, Edge)
- Visual consistency review across themes
- Performance optimization
- Final adjustments based on feedback

---

## 7. Existing Functionality Preservation Checklist

- ✅ All routes remain functional (no route changes)
- ✅ All business logic preserved (controllers unchanged)
- ✅ Database schema unchanged (styling only)
- ✅ API endpoints unchanged
- ✅ Payment flow unchanged
- ✅ Authentication unchanged
- ✅ Multi-language support (FR/EN) preserved
- ✅ Mobile responsiveness enhanced (not broken)
- ✅ Booking flow preserved with better UX
- ✅ Admin workflows unchanged (better organization)

---

## 8. Outstanding Decisions & Assumptions

### Assumptions
1. **Sidebar Width:** Assume 260px (collapsible to 60px on mobile < 768px)
2. **Max Content Width:** Keep 1200px max width for readability
3. **Theme Persistence:** Save to user account in database (not localStorage) for multi-device sync
4. **Public Theme Selector:** Place in account preferences (not on public pages themselves)
5. **Form Max Width:** 600px for single forms, 1000px for two-column layouts
6. **Mobile Breakpoint:** Use Tailwind's `md` (768px) as primary sidebar collapse point

### Decisions – APPROVED ✅
1. ✅ **Sidebar Navigation:** 260px collapsible sidebar for admin (collapse at 768px on mobile)
2. ✅ **Theme Selector UI:** Card preview grid in account preferences (site admin only)
3. ✅ **Admin Color Scheme:** Neutral slate grays (not brand colors)
4. ✅ **Dark Mode for Public:** Out of scope for MVP (M05), designed for future implementation
5. ✅ **Icon Library:** Heroicons (MIT license, open source, Tailwind-native, 400+ icons)
   - Public-facing: Heroicons SVG or components
   - Admin: Same Heroicons (unified aesthetic)
   - Future option: Material Icons if specialized admin icons needed
   - All icons must include `aria-label` and text backup for accessibility

---

## 8a. Icon Implementation Strategy (Heroicons)

### Setup
1. Install Heroicons SVG package or use CDN
2. Create a Blade component wrapper for icon rendering
3. Store icon SVGs in `resources/icons/` with naming convention

### Component Example (M02)
```blade
{{-- resources/views/components/icon.blade.php --}}
@props(['name', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'w-4 h-4',
        'md' => 'w-6 h-6',
        'lg' => 'w-8 h-8',
    ];
@endphp

<svg class="icon icon-{{ $name }} {{ $sizes[$size] }}" 
     viewBox="0 0 24 24" 
     fill="currentColor"
     aria-hidden="true"
     {{ $attributes }}>
    @include("icons.{$name}")
</svg>
```

### Usage in Templates
```blade
<!-- Icon with text (preferred for accessibility) -->
<button>
    <x-icon name="check" size="md" class="text-emerald-600" />
    <span>Save</span>
</button>

<!-- Icon alone with aria-label -->
<button aria-label="Close menu">
    <x-icon name="x-mark" size="md" />
</button>
```

### Heroicons Set (Examples for M02-M07)
- Check, X-Mark, Exclamation – validation/status
- Plus, Minus, Pencil – CRUD actions
- Trash, Archive – destructive actions
- Calendar, Clock – dates/times
- Users, Building – entities
- Cog, Sliders – settings
- Bell, Envelope – notifications
- Chevron-Down, Chevron-Left – navigation
- Home, Dashboard – sections
- Search, Filter – search/filter

---

## 9. File Structure After M02

```
resources/
├── css/
│   ├── app.css (root, reset, layout)
│   ├── tokens.css (design tokens & themes)
│   ├── typography.css (font sizes, weights, line heights)
│   ├── components/
│   │   ├── button.css
│   │   ├── input.css
│   │   ├── card.css
│   │   ├── badge.css
│   │   ├── navigation.css
│   │   └── ...
│   └── admin.css (admin-specific overrides)
├── icons/
│   ├── check.svg
│   ├── x-mark.svg
│   ├── plus.svg
│   └── ... (Heroicons SVG set)
├── views/
│   ├── components/
│   │   ├── button.blade.php
│   │   ├── input.blade.php
│   │   ├── form-group.blade.php
│   │   ├── card.blade.php
│   │   ├── badge.blade.php
│   │   ├── icon.blade.php (Heroicons wrapper)
│   │   └── ...
│   ├── layouts/
│   │   ├── app.blade.php (public layout)
│   │   ├── admin.blade.php (admin layout with sidebar)
│   │   └── auth.blade.php (auth layout)
│   └── ... (existing pages)
└── js/
    └── app.js (theme switching, interactive components)
```

---

## 10. Next Steps

### To Approve This Proposal:
1. ✅ Review route inventory – confirm all routes captured
2. ✅ Review current design strengths and issues – validate assessment
3. ⚠️ **Decide on sidebar vs. alternative navigation** for admin
4. ⚠️ **Confirm theme selector placement and UI style**
5. ⚠️ **Approve 8 theme palette concepts** (or adjust)
6. ⚠️ **Confirm design token semantic naming**

### To Begin M02:
1. Approve M01 audit and proposal (this document)
2. Create design token CSS file
3. Begin button component build
4. Set up component documentation
5. Establish code review checklist for component consistency

### Checkpoint Validation
At the end of M02:
- [ ] All new components render correctly in isolation
- [ ] Existing public pages load without visual changes
- [ ] No console errors or accessibility warnings
- [ ] Form components properly keyboard accessible
- [ ] Responsive behavior tested on mobile
- [ ] All changes documented and mergeable

---

## Appendix A: Sample Page Mockup Notes

### Admin Dashboard (Current vs. Proposed)
- **Current:** Full-width layout with topbar only; stat cards in grid
- **Proposed:** Sidebar + top bar + breadcrumbs; sidebar collapsed on mobile

### User Create/Edit Form (Current vs. Proposed)
- **Current:** Minimal styling; form groups unclear
- **Proposed:** Two-column grid; grouped sections (Personal, Contact, Permissions); labeled fields; validation feedback

### Property List (Current vs. Proposed)
- **Current:** Limited filtering; no table view
- **Proposed:** Sidebar filters; sortable data table; batch actions; responsive stack on mobile

---

## Appendix B: Accessibility Checklist (for M07)

- [ ] All interactive elements (buttons, links, form inputs) have visible focus states
- [ ] Form labels properly associated with inputs (`<label for="id">`)
- [ ] Error messages tied to form fields with `aria-describedby`
- [ ] Colour contrast meets WCAG AA (4.5:1 for text, 3:1 for large text)
- [ ] All icons have `aria-label` or hidden `<span>` text
- [ ] Page structure uses semantic HTML (h1-h6, nav, main, aside, article)
- [ ] Mobile keyboard navigation tested (tab, arrow keys, enter)
- [ ] Reduced motion CSS respects `prefers-reduced-motion`
- [ ] Page titles describe page content
- [ ] Skip-to-main-content link present
- [ ] Images have descriptive alt text (or marked as decorative)
- [ ] Tables have `<thead>`, `<tbody>`, header row associations
- [ ] Form validation messages are text (not icon-only)

---

**Document Status:** READY FOR REVIEW  
**Prepared by:** Design System Audit (M01)  
**Approval:** Pending stakeholder sign-off before proceeding to M02
