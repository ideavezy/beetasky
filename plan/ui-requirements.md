# UI Design Requirements

This document defines the UI design guidelines for the CRM system. All UI implementations must follow these principles to maintain visual consistency across all apps (marketing, portal, client).

## Design Philosophy

**"Less is More"** - Our design follows a minimalist approach with a dark, sophisticated aesthetic. Every element must have a purpose. If it doesn't serve the user, remove it.

### Core Principles

1. **Dark Theme First** - Dark backgrounds with high-contrast text
2. **Simplicity First** - Remove anything that doesn't add value
3. **Breathing Room** - Generous whitespace over cramped layouts
4. **Clarity Over Decoration** - Function drives form
5. **Quiet Confidence** - Let content speak, not chrome
6. **Intentional Design** - Every pixel has a purpose

### Tech Stack

- **CSS Framework**: Tailwind CSS + DaisyUI
- **Font**: Poppins (loaded locally)
- **Theme**: Dark mode default with optional light mode
- **Colors**: OKLCH color space for perceptual uniformity

We must make system work with all standard base on DaisyUI so we can use all classes DaisyUI has.

---

## Color Palette

### DaisyUI Theme (Dark Mode Default)

We use DaisyUI with a custom dark theme as the default. Colors are defined in OKLCH format for better color perception.

```
Background (Dark Theme - Default):
- base-100:    oklch(32% 0.015 252.42)   /* Main background - dark blue-gray */
- base-200:    oklch(29% 0.014 253.1)    /* Secondary - slightly darker */
- base-300:    oklch(26% 0.012 254.09)   /* Tertiary - darkest */

Text:
- base-content: oklch(97.807% 0.029 256.847)  /* Primary text - near white */

Primary (Golden/Amber - Main Actions):
- primary:         oklch(82% 0.189 84.429)
- primary-content: oklch(14% 0.005 285.823)

Secondary (Pink/Magenta):
- secondary:         oklch(65% 0.241 354.308)
- secondary-content: oklch(94% 0.028 342.258)

Accent (Teal/Cyan):
- accent:         oklch(77% 0.152 181.912)
- accent-content: oklch(38% 0.063 188.416)

Neutral:
- neutral:         oklch(14% 0.005 285.823)
- neutral-content: oklch(92% 0.004 286.32)

Status Colors:
- info:    oklch(74% 0.16 232.661)   /* Blue */
- success: oklch(76% 0.177 163.223) /* Green */
- warning: oklch(82% 0.189 84.429)  /* Amber (same as primary) */
- error:   oklch(71% 0.194 13.428)  /* Red */
```

### Light Theme (Optional)

```
Background (Light Theme):
- base-100: oklch(98% 0 240)    /* Main background - near white */
- base-200: oklch(100% 0 240)   /* Secondary - pure white */
- base-300: oklch(95% 0 240)    /* Tertiary - light gray */

Text:
- base-content: oklch(21% 0.006 285.885)  /* Primary text - near black */
```

### Color Rules

- **Dark mode is default** - All designs should prioritize dark theme
- **Use DaisyUI semantic classes** - `bg-base-100`, `text-primary`, `btn-secondary`, etc.
- **Primary for main actions** - Buttons, links, important elements
- **Status colors for meaning** - Success/error/warning/info for feedback only
- **High contrast maintained** - OKLCH ensures perceptual uniformity

---

## Typography

### Font Stack

```css
--font-sans: "Poppins", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
--font-mono: "JetBrains Mono", "SF Mono", "Fira Code", monospace;
```

### Available Font Weights (Poppins)

```
400 - Regular   (body text, labels)
500 - Medium    (emphasized text, buttons)
600 - SemiBold  (headings, important labels)
700 - Bold      (display text, hero headings)
900 - Black     (special display use only)
```

### Type Scale

```
Display:    48px / 1.1 line-height / -0.02em tracking / font-weight: 700
H1:         32px / 1.2 line-height / -0.02em tracking / font-weight: 600
H2:         24px / 1.3 line-height / -0.01em tracking / font-weight: 600
H3:         20px / 1.4 line-height / font-weight: 600
H4:         16px / 1.5 line-height / font-weight: 600
Body:       16px / 1.6 line-height / font-weight: 400
Small:      14px / 1.5 line-height / font-weight: 400
Caption:    12px / 1.4 line-height / font-weight: 400
```

### Typography Rules

- **Poppins is the primary font** - Already loaded locally in all apps
- **Maximum 2-3 font weights per page** - Usually 400, 500, and 600
- **No bold (700) for body text** - Reserve for display/hero only
- **Tight headings, relaxed body** - Negative letter-spacing for headings only
- **Left-align everything** - No centered text except hero sections
- **Short line lengths** - Maximum 65-75 characters per line

---

## Spacing System

Use a consistent 4px base unit with the following scale:

```
--space-1:   4px    (0.25rem)
--space-2:   8px    (0.5rem)
--space-3:   12px   (0.75rem)
--space-4:   16px   (1rem)
--space-5:   20px   (1.25rem)
--space-6:   24px   (1.5rem)
--space-8:   32px   (2rem)
--space-10:  40px   (2.5rem)
--space-12:  48px   (3rem)
--space-16:  64px   (4rem)
--space-20:  80px   (5rem)
```

### Spacing Rules

- **Generous padding** - When in doubt, add more space
- **Consistent gaps** - Use `gap` property, not margins between siblings
- **Section breathing** - Minimum 48px between major sections
- **Card padding** - Minimum 24px padding inside cards

---

## Layout Guidelines

### Grid System

- **12-column grid** for main layouts
- **Maximum content width**: 1280px
- **Minimum margins**: 16px (mobile), 24px (tablet), 32px (desktop)

### Layout Patterns

```
Sidebar Layout (Portal/Client):
┌─────────────────────────────────────────┐
│ ░░░░░░░░░░  │                           │
│ ░ Sidebar ░  │      Main Content         │
│ ░ 240-280px░ │                           │
│ ░░░░░░░░░░  │                           │
└─────────────────────────────────────────┘

Split Panel (AI View):
┌─────────────────────────────────────────┐
│        60-70%        │     30-40%        │
│   AI Presentation    │   Chat Panel      │
│      Panel           │                   │
└─────────────────────────────────────────┘
```

### Layout Rules

- **Align to grid** - No arbitrary positioning
- **Consistent gutters** - Same spacing between all columns
- **Responsive breakpoints**: 640px, 768px, 1024px, 1280px
- **Mobile-first** - Design for mobile, enhance for desktop

---

## Component Guidelines

### Buttons (DaisyUI)

Use DaisyUI button classes for consistency:

```
Primary Button (btn-primary):
- Background: var(--color-primary) - golden/amber
- Text: var(--color-primary-content) - dark
- Use for: Main actions, CTAs, submit buttons

Secondary Button (btn-secondary):
- Background: var(--color-secondary) - pink/magenta
- Text: var(--color-secondary-content)
- Use for: Alternative actions

Ghost Button (btn-ghost):
- Background: transparent
- Text: inherit
- Hover: subtle background highlight
- Use for: Tertiary actions, icon buttons

Outline Button (btn-outline):
- Background: transparent
- Border: 1px solid current color
- Use for: Secondary emphasis

Button Sizes:
- btn-xs: Extra small (icon buttons)
- btn-sm: Small (compact UI)
- btn: Default
- btn-lg: Large (hero CTAs)
```

### Cards

```
- Background: bg-base-200 or bg-base-300
- Border: border border-base-300 (subtle) or none
- Border-radius: rounded-box (0.5rem by default)
- Padding: Use padding="sm" prop (p-4 / 16px) for most cards
- IMPORTANT: Do NOT add nested <div className="p-4"> inside Card components
- See design-specification.md for detailed Card usage patterns
```

**Standard Card Pattern:**
```tsx
<Card className="bg-base-200 border border-base-300" padding="sm">
  {/* Content directly here - no wrapper div */}
</Card>
```

### Form Inputs (DaisyUI)

```
- Use: input, select, textarea classes
- Height: input-md (default) or input-lg for touch
- Border-radius: rounded-field (0.25rem)
- Focus: automatic focus ring from DaisyUI
- Variants: input-bordered, input-ghost
- Error state: input-error
```

### Tables

```
- Use: table class from DaisyUI
- Header: bg-base-300 or transparent
- Rows: hover:bg-base-200 for interactivity
- Borders: table-zebra for alternating or border-base-300
- Variants: table-zebra, table-pin-rows, table-pin-cols
```

---

## Icons

### Style Guidelines

- **Outline style only** - No filled icons
- **Stroke width**: 1.5px - 2px
- **Size scale**: 16px, 20px, 24px
- **Color**: Inherit from text color

### Recommended Icon Sets

1. Lucide Icons (primary)
2. Heroicons (alternative)

### Icon Rules

- **Meaningful icons only** - Don't add icons for decoration
- **Always with labels** - Icons alone are ambiguous
- **Consistent style** - Don't mix icon sets

---

## Animation & Motion

### Timing

```css
--duration-fast:   100ms
--duration-normal: 200ms
--duration-slow:   300ms

--ease-default:    cubic-bezier(0.4, 0, 0.2, 1)
--ease-in:         cubic-bezier(0.4, 0, 1, 1)
--ease-out:        cubic-bezier(0, 0, 0.2, 1)
```

### Available Animations

The following animations are defined in the global CSS:

```css
/* Fade in with slight upward movement */
.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out forwards;
}

/* Slide in from right edge */
.animate-slideInFromRight {
  animation: slideInFromRight 0.5s ease-out forwards;
}
```

### Utility Classes

```css
/* Gradient text effect (primary to accent) */
.text-gradient {
  background: linear-gradient(135deg, primary 0%, accent 100%);
}

/* Glow effect for primary color */
.glow-primary {
  box-shadow: 0 0 20px oklch(82% 0.189 84.429 / 0.3);
}
```

### Motion Rules

- **Subtle transitions** - Barely noticeable is ideal
- **No bouncy animations** - Professional, not playful
- **Purpose-driven** - Animation should provide feedback, not entertainment
- **Respect reduced-motion** - Honor user preferences
- **Use existing utilities** - Prefer `.animate-fadeIn` and `.animate-slideInFromRight`

---

## Do's and Don'ts

### DO

- Use whitespace generously
- Keep interfaces clean and uncluttered
- Use consistent spacing throughout
- Let content be the focus
- Use subtle shadows and borders
- Maintain high contrast for text
- Design for accessibility first
- Use system fonts when possible
- Keep navigation simple and predictable

### DON'T

- Add decorative elements without purpose
- Use gradients (except very subtle ones)
- Use drop shadows heavier than 0.1 opacity
- Mix multiple accent colors
- Use text smaller than 12px
- Center-align body text
- Use all-caps for more than short labels
- Add hover effects to everything
- Use carousels or sliders
- Add unnecessary borders or dividers
- Use skeleton loaders for fast operations
- Over-animate interactions

---

## Page-Specific Guidelines

### Dashboard Pages

- **Key metrics first** - Most important numbers at top
- **Card-based layout** - Group related information
- **Minimal charts** - Simple line/bar charts, no 3D or fancy effects
- **Empty states** - Clean illustrations, not sad faces

### List/Table Pages

- **Search prominent** - Large search input at top
- **Filters collapsible** - Don't overwhelm with options
- **Bulk actions hidden** - Show only when items selected
- **Pagination simple** - "Previous / Next" over complex pagination

### Form Pages

- **Single column forms** - Easier to scan
- **Grouped fields** - Related inputs together
- **Inline validation** - Show errors immediately
- **Clear labels** - Above inputs, not floating

### Detail Pages

- **Hero section** - Key info and actions at top
- **Tabbed content** - For complex entities
- **Activity timeline** - Reverse chronological
- **Related items** - At bottom or sidebar

---

## Theme Configuration

### Dark Mode (Default)

Dark mode is the default theme. No additional configuration needed.

```html
<!-- Body automatically uses dark theme -->
<body class="bg-base-100 text-base-content">
```

### Light Mode (Optional)

To enable light mode, add `data-theme="light"` to the HTML element:

```html
<html data-theme="light">
```

### Theme Switching

DaisyUI handles theme switching automatically. Use the theme controller or:

```javascript
// Toggle theme
document.documentElement.setAttribute('data-theme', 'light');
document.documentElement.setAttribute('data-theme', 'dark');
```

---

## Reference: BeeMud Design Style

The BeeMud design language exemplifies minimalist design through:

1. **Dark, sophisticated backgrounds** - Professional and easy on the eyes
2. **Golden/amber accents** - Primary color used sparingly for emphasis
3. **Simple navigation** - Clear, unobtrusive menu
4. **Poppins typography** - Modern, friendly, highly readable
5. **Strategic color use** - Color only where it adds meaning
6. **Generous spacing** - Elements never feel cramped
7. **No visual noise** - Every element earns its place
8. **Professional tone** - Confident simplicity over flashy effects

When designing any component, ask: "Does this fit the BeeMud aesthetic?" If the answer is no, simplify until it does.

---

## AI Implementation Notes

When generating UI code, AI should:

1. **Default to dark theme** - Use `bg-base-100`, `bg-base-200`, `bg-base-300`
2. **Use DaisyUI components** - `btn`, `card`, `input`, `table`, etc.
3. **Use Tailwind + DaisyUI classes** - Consistent with project setup
4. **Follow spacing scale** - Use Tailwind spacing (p-4, gap-6, etc.)
5. **Use semantic color classes** - `text-primary`, `bg-base-200`, `btn-primary`
6. **Poppins is automatic** - No need to specify font, it's the default
7. **Avoid decorative elements** - No unnecessary gradients or embellishments
8. **Use existing animations** - `.animate-fadeIn`, `.animate-slideInFromRight`
9. **Consider empty states** - Design for zero data scenarios
10. **Mobile-first responsive** - Start small, scale up
11. **Accessibility always** - ARIA labels, keyboard navigation, contrast

### Quick Reference: Common Classes

```jsx
// Backgrounds
className="bg-base-100"      // Main background
className="bg-base-200"      // Card/section background
className="bg-base-300"      // Darker sections

// Text
className="text-base-content" // Primary text
className="text-primary"      // Accent text (golden)
className="text-secondary"    // Pink/magenta text

// Buttons
className="btn btn-primary"   // Primary action
className="btn btn-ghost"     // Subtle action
className="btn btn-outline"   // Outlined button

// Cards
className="card bg-base-200"
className="card-body"

// Inputs
className="input input-bordered"
className="select select-bordered"
```

The goal is interfaces that feel calm, professional, and effortless to use on a dark, sophisticated background.

