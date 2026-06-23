# local_heyday_courseplayer

Reusable ed2go-style master learner shell for Moodle + Adaptable.

Version 2026061411 updates:

- Header controls are learner-style only inside the content card.
- Back, bookmark, print/save, and full-screen controls are proportional to the content window.
- Print/save opens a compact dropdown with activity and section print options.
- Completion can auto-mark when the learner reaches the end of the rendered content body.
- Undo and Next Up remain clickable inside the player shell.
- Teacher editing shortcuts stay in the black topbar only.

Install the inner folder `heyday_courseplayer` to:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`

Then visit Site administration > Notifications and purge caches.


## 2026061412

- Reworked the master shell content card proportions to better match the ed2go reference.
- Moved Getting Started completion/Next Up into the shared master footer template.
- Getting Started and normal lesson pages now use the same completion, Undo, and Next Up controls.
- Completion is triggered by the shared view-to-complete logic after the learner reaches the end of the content body.
- Header icons remain proportional to the browser window and card width.


Version 2026061416 updates:

- Direct visits to /local/heyday_courseplayer/index.php?id=COURSEID now auto-open the next real Moodle activity from the native course structure.
- Explicit dashboard remains available with ?page=home.
- Native Moodle activity view URLs such as /mod/page/view.php?id=CMID are redirected to /local/heyday_courseplayer/index.php?id=COURSEID&page=lesson&cmid=CMID.
- No Moodle core or Adaptable theme files are edited.
- Add ?heydaynative=1 to a native /mod/.../view.php URL to bypass the player shell for troubleshooting.


## 2026061614 auto-refresh native Page activities

This build refreshes Moodle course structure for editors and collects visible non-shell Moodle sections, not only sections named `Lesson N`. Newly added, renamed, moved, or edited Moodle Page activities from the native Moodle + Adaptable course page now appear in the HeyDay player sidebar and open in the server-rendered shell via `cmid`.
