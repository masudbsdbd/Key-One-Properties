# Phase 1 ERD

```mermaid
erDiagram
    users ||--o{ model_has_roles : has
    roles ||--o{ model_has_roles : grants
    roles ||--o{ role_has_permissions : has
    permissions ||--o{ role_has_permissions : in
    users }o--o{ departments : department_user
    users }o--o{ projects : project_user
    users ||--o{ projects : creates

    projects ||--o{ project_stages : has
    projects ||--o{ project_progress_updates : has
    project_stages ||--o{ project_progress_updates : reports
    projects ||--o{ project_media : gallery
    projects ||--o| project_media : cover

    projects ||--o{ buildings : has
    buildings ||--o{ floors : has
    floors ||--o{ flats : has
    buildings ||--o{ flats : has
    projects ||--o{ flats : scopes
    flats ||--o{ flat_price_history : prices
    flats ||--o{ flat_status_history : statuses
    flats ||--o{ flat_media : images

    customers ||--o{ customer_addresses : has
    customers ||--o{ customer_notes : has
    customers ||--o{ customer_documents : has
    users ||--o{ customers : creates

    projects ||--o{ financial_events : references
    flats ||--o{ financial_events : references
    customers ||--o{ financial_events : references
    users ||--o{ approval_requests : requests
```

Notes:

- Every table exposed to the browser has a random 16-character `public_id`; internal `id`
  values never leave the server.
- `flats.project_id` is denormalised for scoping and always derived from the floor's building.
- There is no customer–flat link in Phase 1; bookings in Phase 3 will create it.
- `activity_log.project_id` lets a project's history be listed in one indexed query.
- `settings` (key/value) holds branding.
