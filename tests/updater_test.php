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

namespace local_urlbulkedit;

use local_urlbulkedit\local\updater;

/**
 * Tests for the updater.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_urlbulkedit\local\updater
 */
final class updater_test extends \advanced_testcase {
    /**
     * Course with completion on, a pop-up link and a new-window link, and an editing teacher, logged in.
     *
     * @return array [course, popup link, newwindow link, teacher]
     */
    private function setup_course(): array {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $urlgen = $gen->get_plugin_generator('mod_url');
        $popup = $urlgen->create_instance([
            'course' => $course->id,
            'name' => 'Notes',
            'externalurl' => 'https://docs.google.com/document/d/OLD/edit',
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'popupwidth' => 800,
            'popupheight' => 600,
        ]);
        $newwindow = $urlgen->create_instance([
            'course' => $course->id,
            'name' => 'Video',
            'externalurl' => 'https://youtube.com/watch?v=abc',
            'display' => RESOURCELIB_DISPLAY_NEW,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        return [$course, $popup, $newwindow, $teacher];
    }

    /**
     * Submitted form values equal to what is shown on page load.
     *
     * @param array $links
     * @return array
     */
    private function shown(array $links): array {
        $values = [];
        foreach ($links as $urlid => ['url' => $url]) {
            foreach (updater::FIELDS as $field) {
                $values[$urlid][$field] = updater::format_value($field, $url->$field);
            }
        }
        return $values;
    }

    public function test_get_links_reads_settings(): void {
        [$course, $popup, $newwindow] = $this->setup_course();
        $links = updater::get_links($course);
        $this->assertSame([(int) $popup->id, (int) $newwindow->id], array_keys($links));
        $url = $links[$popup->id]['url'];
        $this->assertEquals(RESOURCELIB_DISPLAY_POPUP, $url->display);
        $this->assertSame(800, $url->popupwidth);
        $this->assertSame(600, $url->popupheight);
        $this->assertSame('0', $url->completion);
        $this->assertSame('1', $links[$newwindow->id]['url']->completion);
    }

    public function test_only_changed_fields_are_saved(): void {
        [$course, $popup] = $this->setup_course();
        $links = updater::get_links($course);
        $shown = $this->shown($links);
        $submitted = $shown;
        $submitted[$popup->id]['externalurl'] = 'https://docs.google.com/document/d/NEW/edit';
        [$changes, $errors] = updater::collect_changes($links, $submitted, $shown, $course);
        $this->assertSame([], $errors);
        $this->assertSame([$popup->id => ['externalurl' => 'https://docs.google.com/document/d/NEW/edit']], $changes);
    }

    public function test_stale_page_does_not_overwrite(): void {
        global $DB;
        [$course, $popup] = $this->setup_course();
        $links = updater::get_links($course);
        $shown = $this->shown($links);
        // Someone renames the link after the page was loaded; the page sends the old name back unchanged.
        $DB->set_field('url', 'name', 'Renamed elsewhere', ['id' => $popup->id]);
        [$changes] = updater::collect_changes(updater::get_links($course), $shown, $shown, $course);
        $this->assertSame([], $changes);
    }

    public function test_url_validation(): void {
        $this->resetAfterTest();
        $this->assertSame('http://example.com/page', updater::parse_url('example.com/page'));
        $this->assertSame('https://example.com/a?b=c', updater::parse_url('https://example.com/a?b=c'));
        $this->assertNull(updater::parse_url(''));
        $this->assertNull(updater::parse_url('https://exa mple.com'));
        $this->assertNull(updater::parse_url('javascript://%0Aalert(1)'));
        $this->assertNull(updater::parse_url('data://text/html,hi'));
    }

    public function test_invalid_values_report_errors(): void {
        [$course, $popup, $newwindow] = $this->setup_course();
        $links = updater::get_links($course);
        $shown = $this->shown($links);
        $submitted = $shown;
        $submitted[$popup->id]['externalurl'] = 'javascript://%0Aalert(1)';
        $submitted[$popup->id]['name'] = '';
        // Pop-up size on a link that opens in a new window.
        $submitted[$newwindow->id]['popupwidth'] = '500';
        $submitted[$newwindow->id]['completion'] = '0';
        $submitted[$newwindow->id]['completionexpected'] = '2030-01-10T17:00';
        [, $errors] = updater::collect_changes($links, $submitted, $shown, $course);
        $this->assertArrayHasKey('externalurl', $errors[$popup->id]);
        $this->assertArrayHasKey('name', $errors[$popup->id]);
        $this->assertArrayHasKey('popupwidth', $errors[$newwindow->id]);
        $this->assertArrayHasKey('completionexpected', $errors[$newwindow->id]);
    }

    public function test_apply_url_and_display_keep_other_options(): void {
        global $DB;
        [$course, $popup, $newwindow] = $this->setup_course();
        $count = updater::apply($course, [
            $popup->id => ['externalurl' => 'https://example.com/new', 'name' => 'Notes v2'],
            $newwindow->id => ['display' => RESOURCELIB_DISPLAY_POPUP],
        ]);
        $this->assertSame(2, $count);

        $record = $DB->get_record('url', ['id' => $popup->id]);
        $this->assertSame('https://example.com/new', $record->externalurl);
        $this->assertSame('Notes v2', $record->name);
        // The pop-up size wasn't touched.
        $this->assertSame(['popupwidth' => 800, 'popupheight' => 600], unserialize_array($record->displayoptions));

        // A link switched to a pop-up gets the site's default size, and a cached onclick on the course page.
        $record = $DB->get_record('url', ['id' => $newwindow->id]);
        $this->assertEquals(RESOURCELIB_DISPLAY_POPUP, $record->display);
        $options = unserialize_array($record->displayoptions);
        $this->assertEquals(get_config('url', 'popupwidth'), $options['popupwidth']);
        $cm = get_fast_modinfo($course)->get_cm(get_coursemodule_from_instance('url', $newwindow->id)->id);
        $this->assertStringContainsString('window.open', $cm->onclick);
    }

    public function test_apply_popup_size_and_printintro(): void {
        global $DB;
        [$course, $popup] = $this->setup_course();
        updater::apply($course, [$popup->id => ['popupwidth' => 1000]]);
        $this->assertSame(
            ['popupwidth' => 1000, 'popupheight' => 600],
            unserialize_array($DB->get_field('url', 'displayoptions', ['id' => $popup->id]))
        );
        updater::apply($course, [$popup->id => ['display' => RESOURCELIB_DISPLAY_AUTO, 'printintro' => 1]]);
        $this->assertSame(
            ['printintro' => 1],
            unserialize_array($DB->get_field('url', 'displayoptions', ['id' => $popup->id]))
        );
    }

    public function test_apply_visibility(): void {
        [$course, $popup] = $this->setup_course();
        updater::apply($course, [$popup->id => ['visible' => 0]]);
        $cm = get_fast_modinfo($course)->get_instances_of('url')[$popup->id];
        $this->assertEquals(0, $cm->visible);
    }

    public function test_apply_completion_view_with_expected_date(): void {
        global $DB;
        [$course, $popup] = $this->setup_course();
        $expected = (int) (floor(time() / 60) * 60) + 5 * DAYSECS;
        updater::apply($course, [$popup->id => ['completion' => updater::COMPLETION_VIEW, 'completionexpected' => $expected]]);
        $cm = get_fast_modinfo($course)->get_instances_of('url')[$popup->id];
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm->completion);
        $this->assertEquals(COMPLETION_VIEW_REQUIRED, $cm->completionview);
        $this->assertEquals($expected, $cm->completionexpected);
        $this->assertTrue($DB->record_exists('event', [
            'modulename' => 'url',
            'instance' => $popup->id,
            'eventtype' => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED,
        ]));

        // Turning completion off clears the expected date and its calendar event.
        updater::apply($course, [$popup->id => ['completion' => '0']]);
        $cm = get_fast_modinfo($course)->get_instances_of('url')[$popup->id];
        $this->assertEquals(COMPLETION_TRACKING_NONE, $cm->completion);
        $this->assertEquals(0, $cm->completionexpected);
        $this->assertFalse($DB->record_exists('event', ['modulename' => 'url', 'instance' => $popup->id]));
    }

    public function test_changing_completion_keeps_views_and_clears_manual_ticks(): void {
        [$course, $popup, $newwindow, $teacher] = $this->setup_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        updater::apply($course, [$popup->id => ['completion' => updater::COMPLETION_VIEW]]);
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info(get_course($course->id));
        // The student views the "must view" link, and ticks the manual one by hand.
        $completion->set_module_viewed($modinfo->get_instances_of('url')[$popup->id], $student->id);
        $completion->update_state($modinfo->get_instances_of('url')[$newwindow->id], COMPLETION_COMPLETE, $student->id);

        // Must view -> manual: the view doesn't count as a tick.
        updater::apply($course, [$popup->id => ['completion' => (string) COMPLETION_TRACKING_MANUAL]]);
        $state = fn($urlid) => (new \completion_info(get_course($course->id)))
            ->get_data(get_fast_modinfo($course)->get_instances_of('url')[$urlid], false, $student->id)->completionstate;
        $this->assertEquals(COMPLETION_INCOMPLETE, $state($popup->id));
        // And back to must view: the recorded view still counts.
        updater::apply($course, [$popup->id => ['completion' => updater::COMPLETION_VIEW]]);
        $this->assertEquals(COMPLETION_COMPLETE, $state($popup->id));

        // Manual -> must view: the hand-made tick is cleared (no view was recorded while it was manual).
        $this->assertEquals(COMPLETION_COMPLETE, $state($newwindow->id));
        updater::apply($course, [$newwindow->id => ['completion' => updater::COMPLETION_VIEW]]);
        $this->assertEquals(COMPLETION_INCOMPLETE, $state($newwindow->id));
    }

    public function test_completion_locked_when_course_has_it_off(): void {
        [$course, $popup] = $this->setup_course();
        global $DB;
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $course->id]);
        $course = get_course($course->id);
        $links = updater::get_links($course);
        $shown = $this->shown($links);
        $submitted = $shown;
        $submitted[$popup->id]['completion'] = '1';
        [$changes, $errors] = updater::collect_changes($links, $submitted, $shown, $course);
        $this->assertSame([], $changes);
        $this->assertArrayHasKey('completion', $errors[$popup->id]);
    }
}
