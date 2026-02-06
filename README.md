# Semitexa Core Frontend

Server-side rendering for Semitexa using Twig: layouts, slots, and HTML response handling.

## Installation

```bash
composer require semitexa/module-core-frontend
```

## What's inside

- **LayoutRenderer** — Renders response content into a layout template (e.g. one-column, two-column)
- **Twig** — Twig integration; templates under `Application/View/templates/` in your modules
- **Layout slots** — `#[AsLayoutSlot]` for blocks (header, footer, etc.)
- **HtmlResponse** — Response type for HTML pages

Use this package when you build HTML pages (not just JSON API). See **semitexa/docs** (e.g. AI_REFERENCE, RECOMMENDED_STACK) and [core/docs/ADDING_ROUTES.md](../semitexa-core/docs/ADDING_ROUTES.md) for the “Responses: JSON and HTML pages” section.
