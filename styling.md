# DESIGN SYSTEM — Blue & Clean UI

> Reusable visual styling reference for dashboards, admin panels, SaaS products, internal tools, and landing pages.
> Inspired by the supplied reference image. Use the image as visual guidance, not as a pixel-perfect template.

## 1. Design Direction

**Keywords:** clean, minimal, trustworthy, structured, calm, professional, approachable.

The interface should feel lightweight and organized. Use a strong blue identity for hierarchy and trust, pale-blue surfaces to separate sections, white cards for content, and green only for primary actions or positive states.

### Core principles
- **Clarity first:** users should understand page hierarchy and available actions quickly.
- **Restrained color:** use blue for identity and navigation, green for primary conversion actions, and neutral colors for most content.
- **Generous whitespace:** group related information and avoid crowding.
- **Card-based structure:** use subtle borders and modest corner radii to organize independent content.
- **Consistent hierarchy:** use a clear page title, section titles, supporting text, then content.
- **Responsive by default:** desktop layouts may use sidebars and multi-column grids; mobile layouts should collapse gracefully.
- **Data before decoration:** dashboards prioritize scanability, labels, values, and actions over marketing-style artwork.

## 2. Color Tokens

Use these as starting tokens. Adjust contrast where necessary to meet accessibility needs.

```css
:root {
  /* Brand / primary blue */
  --color-primary-950: #0B2F68;
  --color-primary-900: #0D3978;
  --color-primary-800: #104B91;
  --color-primary-700: #1762B2;
  --color-primary-600: #2378D1;
  --color-primary-500: #3D91E5;

  /* Pale blue surfaces from the reference */
  --color-blue-100: #EAF4FF;
  --color-blue-075: #F2F8FF;
  --color-blue-050: #F7FAFE;

  /* CTA / success green */
  --color-success-700: #147A38;
  --color-success-600: #19843E;
  --color-success-100: #E6F5EA;

  /* Neutral palette */
  --color-white: #FFFFFF;
  --color-neutral-950: #152235;
  --color-neutral-800: #344256;
  --color-neutral-700: #4C5B70;
  --color-neutral-600: #66758A;
  --color-neutral-500: #8390A2;
  --color-neutral-300: #D7E0EA;
  --color-neutral-200: #E6ECF2;
  --color-neutral-100: #F3F6F9;

  /* Semantic colors */
  --color-warning-700: #9A5B00;
  --color-warning-100: #FFF4D9;
  --color-danger-700: #B42318;
  --color-danger-100: #FDECEC;
  --color-info-700: #155AA8;
  --color-info-100: #E7F1FF;

  /* Text and surfaces */
  --color-text-primary: var(--color-neutral-950);
  --color-text-secondary: var(--color-neutral-600);
  --color-text-inverse: #FFFFFF;
  --color-page: #FFFFFF;
  --color-surface: #FFFFFF;
  --color-surface-muted: var(--color-blue-100);
  --color-border: var(--color-neutral-200);
  --color-border-strong: var(--color-neutral-300);
}
```

### Color usage
- **Primary blue:** page headings, links, selected navigation, icons, charts, and key interface accents.
- **Dark navy:** sidebar, footer, high-emphasis sections, or strong contrast areas.
- **Pale blue:** section backgrounds, filter panels, dashboard groupings, and selected states.
- **Green:** primary action buttons such as Save, Create, Confirm, Submit, and Register. Do not use green for every link or decorative element.
- **White:** cards, forms, tables, and the main working surface.
- **Neutrals:** body text, secondary labels, dividers, borders, and disabled controls.
- **Semantic colors:** reserve warning, danger, and information colors for meaningful status communication.

Do not use every color at full intensity on one screen. Most screens should be predominantly white and neutral, with blue accents and limited green.

## 3. Typography

Use a modern sans-serif family such as **Inter**, **Plus Jakarta Sans**, or **Roboto**. Prefer one family across the product. Use a clear weight contrast rather than many different font styles.

```css
:root {
  --font-family-sans: "Inter", "Plus Jakarta Sans", system-ui, -apple-system,
    BlinkMacSystemFont, "Segoe UI", sans-serif;

  --font-size-xs: 0.75rem;    /* 12px */
  --font-size-sm: 0.875rem;   /* 14px */
  --font-size-md: 1rem;       /* 16px */
  --font-size-lg: 1.125rem;   /* 18px */
  --font-size-xl: 1.25rem;    /* 20px */
  --font-size-2xl: 1.5rem;    /* 24px */
  --font-size-3xl: 1.875rem;  /* 30px */
  --font-size-4xl: 2.25rem;   /* 36px */

  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;

  --line-height-tight: 1.2;
  --line-height-heading: 1.3;
  --line-height-body: 1.6;
}
```

### Type hierarchy
- **Page title:** 28–32px desktop, 24–28px mobile; bold; dark navy or primary blue.
- **Section title:** 20–24px; semibold or bold.
- **Card title:** 16–18px; semibold.
- **Body text:** 14–16px; regular; line-height around 1.5–1.6.
- **Labels and metadata:** 12–13px; medium; use neutral secondary text.
- **KPI value:** 28–36px; bold; use primary blue for emphasis.
- Avoid tiny text for essential instructions. Keep low-emphasis metadata readable.

## 4. Spacing System

Use a 4px base unit. Keep spacing consistent and use larger gaps to separate distinct groups.

```css
:root {
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
  --space-10: 40px;
  --space-12: 48px;
  --space-16: 64px;
  --space-20: 80px;
}
```

### Spacing rules
- Input label to input: 6–8px.
- Between related form controls: 16–20px.
- Card internal padding: 20–24px desktop; 16px mobile.
- Between cards: 16–24px.
- Between page sections: 32–48px for dashboards; 56–80px for marketing pages.
- Page horizontal padding: 32px desktop, 24px tablet, 16px mobile.
- Avoid arbitrary one-off spacing values unless the layout requires it.

## 5. Layout and Grid

### General
- Use a centered content container with a sensible maximum width.
- Dashboard content should prioritize usable width rather than narrow marketing-page columns.
- Align page titles, filters, cards, tables, and section headings to a shared grid.
- Use consistent gaps between columns and rows.

```css
:root {
  --content-max-width: 1440px;
  --page-padding-desktop: 32px;
  --page-padding-tablet: 24px;
  --page-padding-mobile: 16px;
  --grid-gap: 24px;
  --grid-gap-compact: 16px;
}
```

### Dashboard layout guidance
- **App shell:** fixed or sticky sidebar on desktop, top bar for global actions, and a flexible main content region.
- **Sidebar width:** approximately 240–264px expanded; 72–80px collapsed if collapse behavior is needed.
- **Top bar:** 64–72px high.
- **Main content:** page title and actions first, followed by filters, KPI cards, charts, tables, or task lists.
- **KPI grid:** 4 columns on wide desktop, 2 on tablet, 1–2 on mobile depending on content.
- **Data tables:** full available width; horizontal overflow only when necessary, with a clear mobile fallback.
- **Forms:** one or two columns depending on task complexity; use one column on narrow screens.
- **Dialogs:** keep focused and compact; do not place an entire page inside a modal.

### Marketing / landing page guidance
- Use a centered hero with clear headline, supporting copy, and one primary CTA.
- Pale-blue backgrounds may separate key sections.
- Keep text widths comfortable; avoid long line lengths.
- Use image-led sections only when imagery supports the message.
- Use dark navy for high-contrast closing CTA or footer areas.

## 6. Surfaces, Borders, Radius, and Shadows

The reference uses clean white cards, subtle boundaries, and restrained shadows. Avoid heavy glassmorphism, excessive gradients, and thick outlines.

```css
:root {
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 14px;
  --radius-xl: 18px;
  --radius-pill: 999px;

  --shadow-sm: 0 1px 3px rgba(11, 47, 104, 0.06);
  --shadow-md: 0 4px 12px rgba(11, 47, 104, 0.08);
  --shadow-lg: 0 12px 28px rgba(11, 47, 104, 0.12);
}
```

### Component treatment
- **Cards:** white background, 1px neutral border, 10–14px radius, subtle shadow only when elevation is useful.
- **Inputs:** white background, 1px neutral border, 8–10px radius.
- **Buttons:** 8–10px radius; solid fills for primary actions.
- **Pills / badges:** pill radius; pale semantic background; compact but readable.
- **Panels:** pale blue background when they need separation from the main white canvas.
- Use shadows to indicate elevation, not as decoration on every component.

## 7. Buttons and Actions

### Primary button
Use green for the main commit action, matching the reference CTA. Keep the label short and action-oriented.

```css
.button-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 40px;
  padding: 10px 16px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: var(--color-success-600);
  color: var(--color-text-inverse);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: background 160ms ease, box-shadow 160ms ease,
    transform 160ms ease;
}

.button-primary:hover {
  background: var(--color-success-700);
}

.button-primary:focus-visible {
  outline: 3px solid rgba(35, 120, 209, 0.3);
  outline-offset: 2px;
}
```

### Button variants
- **Primary:** green, for the main action on a screen.
- **Secondary:** white with a neutral border and dark text.
- **Tertiary / ghost:** transparent with blue text; for low-emphasis actions.
- **Destructive:** semantic red; use only for destructive operations.
- **Link:** blue text; use for navigation or inline secondary actions.

Rules:
- Prefer one primary button per clear action group.
- Keep button heights consistent: 40px standard, 44–48px for prominent/mobile actions.
- Include hover, focus-visible, active, disabled, and loading states.
- Never communicate state through color alone; include text or an icon where appropriate.

## 8. Navigation

### Sidebar
- Use dark navy or white with a subtle right border; choose one treatment and keep it consistent.
- Brand area at the top with a compact logo or wordmark.
- Navigation items should have clear labels and consistent icons.
- Active item: pale-blue background and primary-blue text/icon, or a restrained dark-blue selected state.
- Use 40–44px minimum row height.
- Group long navigation lists using short section labels.
- Place account/profile and utility actions in a predictable location.

### Top bar
- Keep it visually quiet.
- Include breadcrumb or page context when helpful.
- Put search, notifications, help, and profile actions in consistent positions.
- Separate global navigation from page-specific actions.

## 9. Cards and KPI Components

### Standard card
- White background.
- 1px neutral border.
- 12px radius.
- 20–24px padding.
- Clear title, optional description, content, and optional footer action.

### KPI / metric card
- Show a short label, a prominent value, and optionally a trend or comparison.
- Use primary blue for the main number.
- Keep supporting metadata small but legible.
- Do not overuse icons, gradients, or decorative illustrations.
- Use a consistent card height across one row when possible.

### Information card
- Use a pale-blue icon container or small blue accent.
- Title first, concise description next, optional action last.
- Avoid dense paragraphs inside compact cards.

## 10. Forms and Inputs

- Always pair controls with visible labels.
- Placeholder text is an example, not a replacement for a label.
- Use helper text for format requirements or important context.
- Place validation feedback next to the relevant control.
- Use red for errors, green only for confirmed success, and blue for neutral guidance.
- Inputs should have a clear focus ring and adequate contrast.
- Standard control height: 40–44px; mobile controls may be 44–48px.
- Use sensible input types, autocomplete, and inline validation where appropriate.
- For long forms, group related fields under clear section headings.

## 11. Tables, Filters, and Data-Dense Screens

- Use clear column headings with medium or semibold weight.
- Align text left; align numbers and currency consistently.
- Use subtle row dividers rather than heavy gridlines.
- Row height should usually be 48–56px.
- Use a restrained pale-blue background for selected or hovered rows.
- Place search, filters, sorting, and export actions in a predictable toolbar.
- Keep pagination and result counts together.
- Provide explicit empty, loading, error, and no-results states.
- Avoid reducing text size excessively to fit too many columns; allow horizontal scroll or offer a compact mobile card layout.

## 12. Charts and Data Visualization

- Use primary and secondary blues for data series.
- Use green for positive performance only when that meaning is clear.
- Use semantic warning/red colors only when the data calls for them.
- Keep chart backgrounds white; use subtle gridlines and readable labels.
- Avoid 3D charts and decorative effects.
- Include units, dates, legends, and meaningful tooltips.
- Do not rely only on color to distinguish series; use labels, shapes, or line styles when necessary.

## 13. Icons and Imagery

- Use one consistent icon family, preferably simple outline icons.
- Common sizes: 16px inline, 20px standard, 24px for prominent actions.
- Use primary blue for selected or meaningful icons; neutral gray for secondary actions.
- Keep icons aligned with text and avoid mixing filled and outlined styles without a reason.
- Use photography and illustrations sparingly in dashboards.
- In marketing pages, use imagery that supports the content, with a pale-blue or soft neutral environment.
- Do not stretch, crop, or distort logos and product imagery.

## 14. Motion and Interaction

- Use subtle transitions, typically 140–220ms.
- Animate hover, focus, menu opening, modal entry, and small state changes.
- Prefer opacity and transform over layout-shifting animations.
- Avoid scroll-jacking, excessive parallax, and distracting motion in data-heavy interfaces.
- Respect `prefers-reduced-motion`.
- Motion should communicate state or hierarchy, not delay work.

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

## 15. Responsive Behavior

### Breakpoint suggestions
```css
:root {
  --breakpoint-sm: 640px;
  --breakpoint-md: 768px;
  --breakpoint-lg: 1024px;
  --breakpoint-xl: 1280px;
}
```

- **Mobile (<640px):** single-column content, compact page header, stacked actions, sidebar becomes a drawer or bottom navigation where appropriate.
- **Tablet (640–1023px):** reduce grid columns, allow sidebar collapse, preserve comfortable card padding.
- **Desktop (1024px+):** full app shell, multi-column KPI cards, charts and tables side by side where useful.
- Never let important actions become inaccessible at small widths.
- Use responsive tables thoughtfully; do not simply shrink every column.

## 16. Accessibility and Usability

- Maintain readable contrast, aiming for WCAG AA contrast for normal text.
- Provide visible keyboard focus.
- Ensure every interactive element is keyboard accessible.
- Use semantic headings in order.
- Give icon-only controls accessible names.
- Provide labels and clear errors for form fields.
- Do not use color alone to represent status.
- Support reduced motion.
- Use comfortable hit targets, ideally around 44×44px for frequent touch actions.

## 17. States Every Component Should Consider

Where relevant, design and implement:
- Default
- Hover
- Focus-visible
- Active / pressed
- Selected
- Disabled
- Loading
- Success
- Warning
- Error
- Empty state
- No-results state

A dashboard is not complete if it only shows the ideal populated state.

## 18. Recommended Dashboard Page Pattern

Use this as a flexible starting point, not a mandatory template:

1. **App shell:** sidebar + top bar.
2. **Page header:** breadcrumb if useful, title, short description, primary action.
3. **Toolbar:** search, filters, date range, and secondary actions.
4. **Overview:** KPI cards or summary strip.
5. **Main content:** chart, table, task list, or workflow-specific panel.
6. **Supporting content:** recent activity, alerts, or related data.
7. **Feedback:** toasts, inline messages, loading states, empty states, and errors.

Prioritize the user's most frequent task. Do not force every dashboard to include every section.

## 19. Do / Don't

### Do
- Use white surfaces, blue accents, pale-blue section backgrounds, and restrained green CTAs.
- Keep spacing, radius, typography, and control heights consistent.
- Establish a clear hierarchy between page title, section title, card title, and body.
- Use compact, scannable information architecture.
- Provide responsive and accessible states.
- Keep dashboards functional and data-focused.

### Don't
- Do not copy the reference landing page's narrow centered layout into every dashboard.
- Do not use green as the dominant brand color; reserve it for primary actions and positive states.
- Do not add gradients, shadows, borders, and rounded corners to every element simultaneously.
- Do not use low-contrast tiny text for key information.
- Do not overcrowd a screen with too many cards or charts.
- Do not use decorative animation that slows down routine tasks.
- Do not invent a different design language for each page.

<!-- ## 20. Reusable Prompt for AI Design / Vibe Coding

Paste this section into a future project prompt and add the product-specific requirements below it:

> Follow `DESIGN_SYSTEM.md` as the source of truth for visual styling. Use a clean, minimal, professional interface inspired by the supplied blue-and-white reference: primary/navy blue for hierarchy and navigation, pale-blue surfaces for section grouping, white cards with subtle borders, restrained shadows, consistent rounded corners, readable sans-serif typography, generous but efficient spacing, and green for the main commit action.
>
> Apply the system consistently across every page, modal, table, form, chart, empty state, and responsive breakpoint. For dashboards, prioritize scanability, data hierarchy, and task completion over marketing-style hero layouts. Use shared design tokens and reusable components rather than hardcoding slightly different styles per page. Include hover, focus, active, disabled, loading, success, error, and empty states wherever relevant. Maintain accessible contrast, keyboard focus, and responsive behavior.
>
> Treat the reference image as styling inspiration, not as a source of product content or a fixed layout. Follow the project requirements for information architecture and functionality, and do not invent features that are not in the product brief. -->

<!-- ## 21. Project-Specific Overrides

Add project-specific decisions here without rewriting the core system.

- **Product / project name:** `[PROJECT_NAME]`
- **Product type:** `[ADMIN_DASHBOARD / SAAS / INTERNAL_TOOL / LANDING_PAGE / OTHER]`
- **Primary users:** `[TARGET_USERS]`
- **Primary user tasks:** `[KEY_TASKS]`
- **Required pages:** `[PAGE_LIST]`
- **Brand/logo assets:** `[ASSET_PATHS]`
- **Special constraints:** `[CONSTRAINTS]` -->

<!-- If a project requirement conflicts with this styling guide, follow the product's functional and accessibility requirements first. Record deliberate visual exceptions in this section. -->
