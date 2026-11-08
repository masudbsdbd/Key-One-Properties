# UI components

Design tokens live in `resources/css/tokens.css`. The brand colour (`--brand-primary`)
and sidebar colour (`--brand-sidebar`) are set at runtime from Settings > Branding;
every accent shade derives from them. Component classes live in `resources/css/components.css`.

Local showcase: `/dev/components` (only when `APP_ENV=local`).

## Blade components

| Component | Key props |
| --- | --- |
| `<x-layouts.app>` | `title` |
| `<x-layouts.guest>` | `title` |
| `<x-page-header>` | `title`, `subtitle`, `breadcrumbs` (label => url), slot `actions` |
| `<x-card>` | `title`, `padded`, slot `actions` |
| `<x-stat-card>` | `label`, `value`, `icon`, `tone`, `href`, slot `meta` |
| `<x-button>` | `variant` (primary, secondary, outline, ghost, danger, danger-soft), `size`, `icon`, `href`, `type` |
| `<x-badge>` | `tone` (neutral, accent, success, warning, danger, info, violet, orange) |
| `<x-avatar>` | `name`, `size` |
| `<x-icon>` | `name`, `size` |
| `<x-modal>` / `<x-drawer>` | `id`, `title`; open with `data-modal-open="id"` |
| `<x-empty-state>` | `icon`, `title`, `description`, slot = actions |
| `<x-data-table>` | `url`, `columns`, `sort`, `dir`, `search`, slots `filters`, `toolbar` |
| `<x-form.input>` | `name`, `label`, `type`, `value`, `required`, `hint`, `icon` |
| `<x-form.select>` | `name`, `label`, `options`, `selected`, `url` (remote), `multiple`, `depends-on`, `depends-param` |
| `<x-form.textarea>`, `<x-form.checkbox>`, `<x-form.file>`, `<x-form.actions>` | |

## JavaScript contracts

- `form[data-ajax-form]`: AJAX submit, pending button, inline 422 errors, toast,
  closes the parent modal, fires `ajax:success` with the response body.
- `[data-ajax-table]`: server-rendered rows. Endpoint returns
  `Ajax::table($html, $paginator)`; query params `search`, `sort`, `dir`, `page`, filters.
- `[data-row-action="delete|post"]` inside a table: `data-url`, optional `data-confirm`.
- `select[data-select]`: Select2. Static lists get search above 7 options.
  `data-select-url` uses remote search returning `Ajax::options()`.
- `App.http`, `App.toast`, `App.modal`, `App.confirm` are available globally.

## Server responses

Use `App\Support\Ajax`: `success($message, $data, $meta)`, `error($message, $status)`,
`table($html, $paginator)`, `options($results, $more)`. IDs sent to the browser are
always `public_id` values.
