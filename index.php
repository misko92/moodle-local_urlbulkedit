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
 * Edit the settings of all links in a course on one page.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_urlbulkedit\local\creator;
use local_urlbulkedit\local\updater;
use local_urlbulkedit\output\editor;

require(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$filter = optional_param('filter', '', PARAM_TEXT);
$url = new moodle_url('/local/urlbulkedit/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('bulkeditlinks', 'local_urlbulkedit'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('bulkeditlinks', 'local_urlbulkedit'), $url);

// Keep the keyword filter across the save redirect, but not in the page URL itself.
$returnurl = new moodle_url($url, $filter === '' ? [] : ['filter' => $filter]);
$links = updater::get_links($course);
$submitted = [];
$original = [];
$errors = [];

if ($data = data_submitted()) {
    require_sesskey();
    // Nested arrays: q[urlid][field] (new value) and o[urlid][field] (value originally shown).
    foreach (['q' => &$submitted, 'o' => &$original] as $key => &$target) {
        foreach ((array) ($data->$key ?? []) as $urlid => $fields) {
            $urlid = clean_param($urlid, PARAM_INT);
            foreach ((array) $fields as $field => $value) {
                if (in_array($field, updater::FIELDS, true)) {
                    $target[$urlid][$field] = clean_param($value, PARAM_RAW_TRIMMED);
                }
            }
        }
    }
    unset($target);

    [$changes, $errors] = updater::collect_changes($links, $submitted, $original, $course);
    if (!$errors) {
        if (!$changes) {
            redirect($returnurl, get_string('nochanges', 'local_urlbulkedit'), null, \core\output\notification::NOTIFY_INFO);
        }
        $count = updater::apply($course, $changes);
        redirect(
            $returnurl,
            get_string('changessaved', 'local_urlbulkedit', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkeditlinks', 'local_urlbulkedit'));
if (creator::can_create($course)) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/local/urlbulkedit/create.php', ['id' => $course->id]),
        get_string('create', 'local_urlbulkedit'),
        ['class' => 'btn btn-outline-primary']
    ), 'd-flex flex-wrap gap-2 mb-3');
}
if ($errors) {
    echo $OUTPUT->notification(get_string('fixerrors', 'local_urlbulkedit'), 'error');
}
echo $OUTPUT->render(new editor($course, $links, $submitted, $original, $errors, $filter));
echo $OUTPUT->footer();
