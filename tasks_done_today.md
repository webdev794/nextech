# Tasks Done Today

- Verified and finished the seller pickup-address + per-product fee estimator work (migration, backend validation, Seller Center form, admin seller-detail view), then wired the seller's pickup address into the courier booking payload as the shipment's origin.
- Fixed a real routing bug: an order containing a seller's product could get assigned to a NexTech rider with no way to collect it from the seller — such orders now always go through the online courier instead.
- Built two-way seller↔admin messaging: sellers can now see and reply to admin messages from their status/dashboard page (previously write-only from admin), added a "Request changes" flow that reopens an application for the seller to edit and resubmit, and admin now sees the last message at a glance in the seller list/detail.
- Added a notification sound + mute toggle and live polling to the seller's chat, so a new admin message shows up and chimes without the seller needing to click Send (previously only the admin side updated dynamically).
- Reorganized admin Pages into menu-placement groups (Main menu, Main footer with its 4 sub-columns, Seller footer, Blog, Unassigned) via a new `menu_placements` field, with matching sidebar navigation and a multi-select "Placement" checklist on the page editor.
- Reworked the admin page editor to a WordPress-style layout: Save/Delete moved to a top-right bar, the page list hides while editing (with a "Back to pages" link), a prominent Title field, and a collapsible "Page settings" section (collapsed by default, arrow-only toggle on the right) separate from the Content section.
- Fixed several sidebar navigation bugs in admin Pages: clicking a category no longer leaves stale content on screen, clicking an open category/menu again now actually collapses it instead of staying stuck open.

