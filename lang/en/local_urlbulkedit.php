<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allsettings'] = 'All settings (except name, URL, visibility and expected date)';
$string['applytoselected'] = 'Apply to selected';
$string['backtobulkedit'] = 'Back to Bulk edit links';
$string['bulkeditlinks'] = 'Bulk edit links';
$string['changedonly'] = 'Show changed links only';
$string['changessaved'] = '{$a} link(s) updated.';
$string['columns'] = 'Columns';
$string['columnsdefault'] = 'Reset to default columns';
$string['completion'] = 'Completion';
$string['completion_hint'] = 'changing it resets students\' completion';
$string['completion_manual'] = 'Students mark it done';
$string['completion_none'] = 'None';
$string['completion_view'] = 'Student must view';
$string['completionexpected'] = 'Expected completion';
$string['confirmcompletion'] = 'Changing completion resets students\' completion of that link and works it out again, as the link\'s settings page does: ticks students made by hand are cleared, and earlier visits only count if the link already required viewing (Moodle doesn\'t record views otherwise).';
$string['confirmnone'] = '(none)';
$string['confirmsave'] = 'Save';
$string['confirmsummary'] = '{$a->settings} change(s) to {$a->links} link(s):';
$string['confirmtitle'] = 'Review changes';
$string['copyfrom'] = 'Same as {$a}';
$string['create'] = 'Add links';
$string['create_desc'] = 'Create several links at once. Give each one a name, a web address and a section; they all get the same display and completion settings. Leave a row empty to skip it.';
$string['createaddrow'] = 'Add row';
$string['createbutton'] = 'Create links';
$string['created'] = '{$a} link(s) created.';
$string['createhidden'] = 'Create them hidden from students';
$string['createmodel'] = 'Settings';
$string['createmodel_help'] = 'How the new links open and their completion tracking. Pick an existing link to copy its settings.';
$string['createmodeldefaults'] = 'Site defaults for links';
$string['createpaste'] = 'Paste a list of links';
$string['createpaste_example'] = 'Unit 1 notes | https://docs.google.com/…';
$string['createpaste_help'] = 'One link per line: the name, then the address, separated by a tab (as when copying two spreadsheet columns), by " | " or by a space. A line with only an address works too: then type its name in the table.';
$string['createpasteadd'] = 'Add to the table';
$string['createpreview'] = '{$a} link(s) will be created. Nothing has been created yet: check the list, then press Create links.';
$string['createremoverow'] = 'Remove row';
$string['createsettings'] = 'Opens: {$a->display} · Completion: {$a->completion} · {$a->visibility}';
$string['display'] = 'Display';
$string['editsettings'] = 'Edit all settings for {$a}';
$string['errorchoice'] = 'Invalid choice.';
$string['errorcompletionoff'] = 'Completion tracking is off in this course.';
$string['errorcreate'] = 'Could not create {$a}.';
$string['errordate'] = 'Invalid date.';
$string['errorexpectednone'] = 'An expected completion date needs completion tracking.';
$string['errorexternalurl'] = 'Enter a valid web address, e.g. https://example.com/page.';
$string['errorname'] = 'The name must not be empty, and at most 255 characters.';
$string['errornorows'] = 'Enter at least one link.';
$string['errorpopuponly'] = 'Only used when the link opens in a pop-up.';
$string['errorpopupsize'] = 'Must be a whole number of pixels, 1 to 9999.';
$string['errorprintintro'] = 'Not used with this display type.';
$string['errorvisibility'] = 'You are not allowed to show or hide this link.';
$string['externalurl'] = 'URL';
$string['field'] = 'Setting';
$string['filter'] = 'Filter';
$string['filtercount'] = 'Showing {$a->shown} of {$a->total} links';
$string['filterplaceholder'] = 'Name, section or address';
$string['find'] = 'Find';
$string['findempty'] = 'Type the text to find first.';
$string['fixerrors'] = 'Nothing was saved. Please fix the highlighted values and save again.';
$string['group_appearance'] = 'Appearance';
$string['group_completion'] = 'Completion';
$string['group_general'] = 'General';
$string['link'] = 'Link';
$string['lockedcompletionoff'] = 'Completion off';
$string['name'] = 'Name';
$string['nochanges'] = 'No changes to save.';
$string['nolinks'] = 'There are no links in this course that you can edit.';
$string['pluginname'] = 'Link bulk edit';
$string['popupheight'] = 'Pop-up height';
$string['popupheight_hint'] = 'pixels, pop-ups only';
$string['popupwidth'] = 'Pop-up width';
$string['popupwidth_hint'] = 'pixels, pop-ups only';
$string['preview'] = 'Preview';
$string['previewheading'] = 'Preview';
$string['printintro'] = 'Display URL description';
$string['printintro_hint'] = 'automatic, embed and frame only';
$string['privacy:metadata'] = 'The Link bulk edit plugin does not store any personal data.';
$string['replacedcount'] = 'Replaced in {$a} link(s). Nothing is saved until you press Save changes.';
$string['replacein'] = 'In';
$string['replacein_names'] = 'Names';
$string['replacein_urls'] = 'URLs';
$string['replaceinselected'] = 'Replace in selected';
$string['replacewith'] = 'Replace with';
$string['savechanges'] = 'Save changes';
$string['select'] = 'Select {$a}';
$string['selectall'] = 'Select all';
$string['selectfirst'] = 'Select at least one link first.';
$string['subsectionname'] = '{$a->parent} › {$a->name}';
$string['value'] = 'Value';
$string['visible'] = 'Visibility';
$string['visible_hide'] = 'Hidden';
$string['visible_show'] = 'Shown';
