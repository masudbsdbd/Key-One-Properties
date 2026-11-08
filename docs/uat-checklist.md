# Phase 1 UAT checklist

Tick each item on desktop and on a phone. Record the tester and date.

## Access and security

- [ ] Sign in with email; sign in with mobile in any format (01…, +880…, with dashes).
- [ ] Five wrong passwords lock sign-in for 5 minutes; the countdown shows and survives a page reload.
- [ ] The lock also applies to the same username from another network.
- [ ] Forgot password sends a request; admins see it in the bell and under Reset Requests.
- [ ] Admin resets the password; the user's other sessions end and they must change it at next sign-in.
- [ ] The forgot-password answer is the same for unknown accounts.
- [ ] Enable Google Authenticator from Profile; sign-in then asks for the code; a recovery code works once.
- [ ] Admin can require 2FA for a user and reset a user's 2FA.
- [ ] A deactivated user is signed out on their next click.
- [ ] Sign out of other devices works.
- [ ] URLs never show numeric ids (check projects, units, customers, images, documents).
- [ ] A user without permission gets "Access denied" for pages and actions they cannot use.

## Users, roles, departments

- [ ] Create, edit and delete a user without reloading the page.
- [ ] Create a role with selected permissions; assigning it changes what the user can open.
- [ ] A non-super-admin cannot grant permissions they do not have or edit their own role.
- [ ] Departments: create, add members by search, filter by status.
- [ ] Activity log shows before and after values and hides sensitive fields.
- [ ] Branding: change primary and sidebar colours, upload logo and favicon; the whole app updates.

## Projects

- [ ] Create a project; default stages appear; switch table and card views.
- [ ] Add, edit, reorder and delete stages (a stage with progress history cannot be deleted).
- [ ] Record progress (0–100, not in the future); overall progress = average of stages.
- [ ] Upload gallery images (JPG/PNG/WEBP ≤ 5 MB), set cover, change visibility, delete.
- [ ] Add team members; a member without "view all" sees only their projects.
- [ ] Archive and restore a project.

## Units

- [ ] Add a building with a floor range (basement and ground labels are correct).
- [ ] Create a unit with dependent Project → Building → Floor selects.
- [ ] Duplicate unit number in the same building is refused; allowed in another building.
- [ ] Bulk create: preview shows conflicts; create works only when the preview is clean; double-click creates nothing twice.
- [ ] Change price with a reason; price history shows old and new values.
- [ ] Change status (available / unavailable / maintenance); booked and sold cannot be chosen.
- [ ] Filters (project, building, floor, status, type), search, sort and pagination work without reload.

## Customers

- [ ] Create a customer; the mobile number is unique in any format.
- [ ] Matching another customer's alternate number shows a warning but saves.
- [ ] NID shows masked; "Show" reveals it only for users with the sensitive permission, and is logged.
- [ ] Add, edit, remove addresses; the primary address moves when the primary one is removed.
- [ ] Add and delete notes.
- [ ] Upload and download documents (PDF, images, Word, Excel ≤ 10 MB); other types are refused.
- [ ] Bookings and Payments tabs say "Coming in Phase 3".
- [ ] A sales user sees only customers they created; "view all" sees everyone.

## General

- [ ] Every list with more than 7 options in a dropdown is searchable.
- [ ] Global search (Ctrl + K) finds projects, units and customers the user may see.
- [ ] Error messages appear under the right field; buttons show a spinner and cannot be double-submitted.
- [ ] Sidebar collapse is remembered.

Sign-off: ____________________   Date: ____________
