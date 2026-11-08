# Projects module

## Access

- `projects.view_all`: every project.
- `projects.view`: only projects where the user is a team member (`project_user`).
- Every other project permission (`update`, `manage_stages`, `update_progress`,
  `manage_media`, `manage_members`, `archive`) also requires that the project is visible
  to the user. Checks run in `ProjectPolicy` on every endpoint, including AJAX partials,
  gallery image URLs and the project Select2 source.
- The creator is added as a member (role "Owner") so they keep access.

## Overall progress

Overall progress is the simple average of all stage `progress_percent` values; every
stage has equal weight. It is calculated on read (`Project::overallProgress()`), never stored.

Recording progress (`ProjectService::recordProgress`):

1. Locks the stage row, saves an immutable `project_progress_updates` row with the previous
   and new percentage, report date, summary and visibility.
2. Sets the stage percentage. 100% marks the stage completed and stamps `actual_end_at`;
   any value above 0 moves a not-started stage to in progress and stamps `actual_start_at`.
3. Writes a `progress_recorded` activity entry with the project id.

Progress updates cannot be edited or deleted. A correction is a new update.

## Investor visibility (future portal)

`project_progress_updates.visibility` and `project_media.visibility` are `internal` or
`investor`. The Phase 4 investor portal must only read rows marked `investor`.

## Files

Gallery images are stored on the private `local` disk under `projects/{public_id}/` with
random file names and are streamed through an authorised route. Allowed: JPG, PNG, WEBP up
to `UPLOAD_IMAGE_MAX_KB` (default 5 MB).
