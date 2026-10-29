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
 * Create several links at once, each with its own name, address and section.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_urlbulkedit\local\creator;
use local_urlbulkedit\local\updater;

require(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/local/urlbulkedit/create.php', ['id' => $course->id]);
$backurl = new moodle_url('/local/urlbulkedit/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('create', 'local_urlbulkedit'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('bulkeditlinks', 'local_urlbulkedit'), $backurl);
$PAGE->navbar->add(get_string('create', 'local_urlbulkedit'), $url);

if (!creator::can_create($course)) {
    throw new required_capability_exception($context, 'mod/url:addinstance', 'nopermissions', '');
}
updater::require_libs();
$canshow = has_capability('moodle/course:activityvisibility', $context);
// Plain text: the template escapes it.
$models = [0 => get_string('createmodeldefaults', 'local_urlbulkedit')]
    + array_map(fn($entry) => $entry['cm']->get_formatted_name(['escape' => false]), updater::get_links($course));
$sections = creator::sections($course);

$modelid = 0;
$hidden = !$canshow;
$input = [];
$rows = [];
$errors = [];
$preview = false;
if ($data = data_submitted()) {
    require_sesskey();
    $modelid = clean_param($data->model ?? 0, PARAM_INT);
    if (!isset($models[$modelid])) {
        throw new moodle_exception('invalidrecord', 'error', '', 'url');
    }
    $hidden = !empty($data->hidden) || !$canshow;
    foreach ((array) ($data->r ?? []) as $i => $fields) {
        foreach (['name', 'externalurl', 'section'] as $field) {
            $input[clean_param($i, PARAM_INT)][$field] = clean_param(((array) $fields)[$field] ?? '', PARAM_RAW_TRIMMED);
        }
    }
    [$rows, $errors] = creator::parse_rows($course, $input);
    if (!$rows && !$errors) {
        $errors[-1] = get_string('errornorows', 'local_urlbulkedit');
    }
    $settings = creator::settings($course, $modelid);
    if (!$errors && !empty($data->createbutton)) {
        $cmids = creator::create($course, $settings, $rows, $hidden);
        redirect(
            $backurl,
            get_string('created', 'local_urlbulkedit', count($cmids)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $preview = !$errors;
}

// Start with a few empty rows in the first section with content, or section 0.
if (!$input) {
    $first = (string) array_key_first($sections);
    $input = array_fill(0, 5, ['name' => '', 'externalurl' => '', 'section' => $first]);
}

$templaterows = [];
foreach ($input as $i => $fields) {
    $templaterows[] = [
        'index' => $i,
        'name' => $fields['name'],
        'externalurl' => $fields['externalurl'],
        'nameerror' => $errors[$i]['name'] ?? null,
        'externalurlerror' => $errors[$i]['externalurl'] ?? null,
        'sectionerror' => $errors[$i]['section'] ?? null,
        'sections' => array_map(fn($num, $name) => [
            'value' => $num,
            'label' => $name,
            'selected' => (string) $num === $fields['section'],
        ], array_keys($sections), $sections),
    ];
}

$previewrows = [];
$previewsettings = '';
if ($preview) {
    foreach ($rows as $row) {
        $previewrows[] = [
            'name' => $row->name,
            'externalurl' => $row->externalurl,
            'section' => $sections[$row->section],
        ];
    }
    $displays = updater::display_labels();
    $completions = updater::choices('completion');
    $previewsettings = get_string('createsettings', 'local_urlbulkedit', (object) [
        'display' => $displays[$settings->display] ?? $settings->display,
        'completion' => $completions[$settings->completion] ?? $settings->completion,
        'visibility' => get_string($hidden ? 'visible_hide' : 'visible_show', 'local_urlbulkedit'),
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('create', 'local_urlbulkedit'));
echo html_writer::tag('p', get_string('create_desc', 'local_urlbulkedit'));
echo html_writer::link($backurl, get_string('backtobulkedit', 'local_urlbulkedit'), ['class' => 'd-inline-block mb-3']);
if ($errors) {
    echo $OUTPUT->notification($errors[-1] ?? get_string('fixerrors', 'local_urlbulkedit'), 'error');
}
echo $OUTPUT->render_from_template('local_urlbulkedit/create', [
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'models' => array_map(fn($id, $name) => [
        'value' => $id,
        'label' => $name,
        'selected' => $id === $modelid,
    ], array_keys($models), $models),
    'hidden' => $hidden,
    'canshow' => $canshow,
    'rows' => $templaterows,
    'preview' => $preview,
    'previewrows' => $previewrows,
    'previewsummary' => get_string('createpreview', 'local_urlbulkedit', count($previewrows)),
    'previewsettings' => $previewsettings,
]);
echo $OUTPUT->footer();
