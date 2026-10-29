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

use local_urlbulkedit\local\creator;
use local_urlbulkedit\local\updater;

/**
 * Tests for creating links.
 *
 * @package    local_urlbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_urlbulkedit\local\creator
 */
final class creator_test extends \advanced_testcase {
    /**
     * Course with completion on, two sections and a model link, with an editing teacher logged in.
     *
     * @return array [course, model link]
     */
    private function setup_course(): array {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1, 'numsections' => 2]);
        $model = $gen->get_plugin_generator('mod_url')->create_instance([
            'course' => $course->id,
            'name' => 'Model',
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'popupwidth' => 900,
            'popupheight' => 700,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => COMPLETION_VIEW_REQUIRED,
        ]);
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        return [$course, $model];
    }

    public function test_parse_rows(): void {
        [$course] = $this->setup_course();
        [$rows, $errors] = creator::parse_rows($course, [
            0 => ['name' => 'Notes', 'externalurl' => 'docs.google.com/x', 'section' => '1'],
            1 => ['name' => '', 'externalurl' => '', 'section' => '1'],
            2 => ['name' => '', 'externalurl' => 'https://example.com', 'section' => '1'],
            3 => ['name' => 'Bad', 'externalurl' => 'not a url', 'section' => '99'],
        ]);
        $this->assertSame([0, 2, 3], array_keys($rows));
        $this->assertSame('http://docs.google.com/x', $rows[0]->externalurl);
        $this->assertArrayNotHasKey(0, $errors);
        $this->assertSame(['name'], array_keys($errors[2]));
        $this->assertSame(['externalurl', 'section'], array_keys($errors[3]));
    }

    public function test_create_with_model_settings(): void {
        global $DB;
        [$course, $model] = $this->setup_course();
        $settings = creator::settings($course, $model->id);
        [$rows] = creator::parse_rows($course, [
            ['name' => 'Unit 1', 'externalurl' => 'https://example.com/1', 'section' => '2'],
            ['name' => 'Unit 2', 'externalurl' => 'https://example.com/2', 'section' => '2'],
        ]);
        $cmids = creator::create($course, $settings, $rows, true);
        $this->assertCount(2, $cmids);

        $modinfo = get_fast_modinfo($course);
        $this->assertEquals($cmids, array_slice($modinfo->sections[2], -2));
        $cm = $modinfo->get_cm($cmids[0]);
        $this->assertSame('Unit 1', $cm->name);
        $this->assertEquals(0, $cm->visible);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm->completion);
        $this->assertEquals(COMPLETION_VIEW_REQUIRED, $cm->completionview);
        $url = $DB->get_record('url', ['id' => $cm->instance]);
        $this->assertSame('https://example.com/1', $url->externalurl);
        $this->assertEquals(RESOURCELIB_DISPLAY_POPUP, $url->display);
        $this->assertSame(['popupwidth' => 900, 'popupheight' => 700], unserialize_array($url->displayoptions));
    }

    public function test_create_with_site_defaults(): void {
        global $DB;
        [$course] = $this->setup_course();
        set_config('display', RESOURCELIB_DISPLAY_OPEN, 'url');
        $settings = creator::settings($course, 0);
        [$rows] = creator::parse_rows($course, [['name' => 'Plain', 'externalurl' => 'https://example.com', 'section' => '0']]);
        $cmids = creator::create($course, $settings, $rows, false);
        $cm = get_fast_modinfo($course)->get_cm($cmids[0]);
        $this->assertEquals(1, $cm->visible);
        $this->assertEquals(COMPLETION_TRACKING_NONE, $cm->completion);
        $this->assertEquals(RESOURCELIB_DISPLAY_OPEN, $DB->get_field('url', 'display', ['id' => $cm->instance]));
        // The new link shows up on the bulk edit page.
        $this->assertArrayHasKey($cm->instance, updater::get_links($course));
    }

    public function test_sections_include_subsections(): void {
        [$course] = $this->setup_course();
        if (!array_key_exists('subsection', \core\plugininfo\mod::get_enabled_plugins())) {
            $this->markTestSkipped('Subsections are not enabled.');
        }
        $sub = $this->getDataGenerator()->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Labs']);
        $subsection = get_fast_modinfo($course)->get_cm($sub->cmid)->get_delegated_section_info();
        $sections = creator::sections($course);
        // Listed right after its parent section.
        $keys = array_keys($sections);
        $this->assertSame($subsection->section, $keys[array_search(1, $keys) + 1]);
        $this->assertStringEndsWith('› Labs', $sections[$subsection->section]);

        [$rows] = creator::parse_rows($course, [
            ['name' => 'Lab sheet', 'externalurl' => 'https://example.com', 'section' => (string) $subsection->section],
        ]);
        $cmids = creator::create($course, creator::settings($course, 0), $rows, false);
        $this->assertEquals($subsection->section, get_fast_modinfo($course)->get_cm($cmids[0])->sectionnum);
    }
}
