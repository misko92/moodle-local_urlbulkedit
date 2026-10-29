# Link bulk edit (local_urlbulkedit)

Edit the settings of every link (URL activity) in a course on one page, and add many links at once, instead of opening
each link's settings one at a time.

In a course, open **More → Bulk edit links**. Each link is one row of the table. Pick the columns you want with
**Columns** (remembered per user):

| Group | Settings |
|---|---|
| General | Name (renames everywhere); URL; visibility (needs `moodle/course:activityvisibility`) |
| Appearance | Display (automatic, embed, open, in pop-up, …: the display types the site allows for links, plus a link's current one); pop-up width and height (pop-ups only); display URL description (automatic, embed and frame only) |
| Completion | None / students mark it done / student must view; expected completion date. Locked when completion tracking is off in the course. |

- **Apply to selected**: tick links and set one setting on all of them. **All settings** copies one link's display
  and completion settings to the selected links.
- **Find & replace**: replace text in the URLs (or names) of the selected links, e.g. an old Google Drive folder ID.
  The changed cells are highlighted; nothing is saved until **Save changes**.
- **Filter** by keyword (name, section or address) and **Show changed links only**. Select all and the toolbars only
  affect the links shown; the filter is kept after saving.
- **Save changes** first shows every change (old → new, per link) and a warning when completion changes; nothing is
  saved until you confirm.
- Only the values you changed are saved, so the page never overwrites edits made elsewhere after you opened it.
- If any value is invalid, nothing is saved and the errors are shown in place.

Addresses are checked and tidied as the link settings form does (http:// is added when no scheme is given), and
`javascript:`, `data:` and `vbscript:` addresses are refused.

Changing a link's completion tracking resets students' completion of it and works it out again, as the link's
settings form does when completion is unlocked: ticks students made by hand are cleared, and earlier visits only count
if the link already required viewing (Moodle doesn't record views otherwise).

## Add links

**Bulk edit links → Add links** creates several links at once. Each row has a name, an address and a section
(subsections are listed under their section). **Paste a list of links** fills the table from lines such as
`Unit 1 notes | https://…`, `Unit 1 notes<TAB>https://…` (two spreadsheet columns) or `Unit 1 notes docs.google.com/…`.

All new links get the same display and completion settings: the site's defaults for links, or a copy of an existing
link's. **Preview** shows what will be created; **Create links** creates them with Moodle's own `create_module()` (the
same code as the "Add an activity" form), at the end of their sections, optionally hidden.

## Requirements

Moodle 5.2 or 5.3. Needs `moodle/course:manageactivities` (and `mod/url:addinstance` to add links).

## How it saves

Link settings are written to the `url` table directly rather than through `url_update_instance()`, which expects the
whole settings form and would otherwise drop the pop-up size and URL parameters. Names go through core's rename,
visibility through `set_coursemodule_visible()`, and completion is stored on the course module with its calendar event
updated, as the settings form does. The course cache is rebuilt and a `course_module_updated` event is logged for each
link.

## Licence

GNU GPL v3 or later.
