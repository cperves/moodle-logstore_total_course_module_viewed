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
 * logstore total cours emodule viewed store test class
 *
 * @package    logstore_total_course_module_viewed
 * @author Céline Pervès <cperves@unistra.fr>
 * @author Matthieu Fuchs <matfuchs@unistra.fr>
 * @copyright Université de Strasbourg 2022 {@link http://unistra.fr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_total_course_module_viewed;

use advanced_testcase;
use context_module;
use context_system;
use core\session\manager;
use logstore_last_viewed_course_module\log\store;
use mod_chat\event\course_module_viewed;

class store_test extends advanced_testcase {
    /**
     * @var bool Determine if we disabled the GC, so it can be re-enabled in tearDown.
     */

    private $user1;
    private $course;
    private $resource;
    private $resourcecontext;
    private $cmresource;
    private $chat;
    private $chatcontext;
    private $cmchat;

    /**
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_logstore_enabling() {
        $this->setup_datas();
        // Test all plugins are disabled by this command.
        set_config('enabled_stores', '', 'tool_log');
        $manager = get_log_manager(true);
        $stores = $manager->get_readers();
        $this->assertCount(0, $stores);

        // Enable logging plugin.
        $this->set_log_store(false);

        $stores = $manager->get_readers();
        $this->assertCount(1, $stores);
        $this->assertEquals(array('logstore_total_course_module_viewed'), array_keys($stores));
        /** @var store $store */
        $store = $stores['logstore_total_course_module_viewed'];
        $this->assertInstanceOf('logstore_total_course_module_viewed\log\store', $store);
        $this->assertInstanceOf('tool_log\log\writer', $store);
        // This plugin is not Logging.
        $this->assertFalse($store->is_logging());
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_module_viewed(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $logs = $DB->get_records('logstore_totalcoursemodview', array(), 'id ASC');
        $this->assertCount(0, $logs);
        $this->setCurrentTimeStart();
        $this->setUser($this->user1->id);
        self::launch_module_viewed_events();
        get_log_manager(true);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('userid' => $this->user1->id, 'courseid' => $this->course->id), 'id ASC');
        $this->assertCount(1, $logs);
        $log = array_shift($logs);
        $this->assertEquals(2, $log->totalcoursemoduleviewed);
        $this->assertEquals(1, $log->totalcourseresourcesviewed);
        $this->assertEquals(1, $log->totalcourseactivitiesviewed);

        // Second time.
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('userid' => $this->user1->id, 'courseid' => $this->course->id), 'id ASC');
        $this->assertCount(1, $logs);
        $log = array_shift($logs);
        $this->assertEquals(4, $log->totalcoursemoduleviewed);
        $this->assertEquals(2, $log->totalcourseresourcesviewed);
        $this->assertEquals(2, $log->totalcourseactivitiesviewed);
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_module_viewed_loginas(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $logs = $DB->get_records('logstore_totalcoursemodview', array(), 'id ASC');
        $this->assertCount(0, $logs);
        $this->setCurrentTimeStart();
        $this->setAdminUser();
        manager::loginas($this->user1->id, context_system::instance());
        $this->assertTrue(manager::is_loggedinas());
        self::launch_module_viewed_events();
        get_log_manager(true);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('userid' => $this->user1->id, 'courseid' => $this->course->id), 'id ASC');
        $this->assertCount(0, $logs);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('userid' => get_admin()->id, 'courseid' => $this->course->id), 'id ASC');
        $this->assertCount(0, $logs);
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_deleted(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $this->setUser($this->user1);
        $logs = $DB->get_records('logstore_totalcoursemodview', array(), 'id ASC');
        $this->assertCount(0, $logs);
        self::launch_module_viewed_events();
        get_log_manager(true);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('courseid' => $this->course->id, 'userid' => $this->user1->id));
        $this->assertCount(1, $logs);
        ob_start();
        delete_course($this->course->id);
        get_log_manager(true);
        $logs = $DB->get_records('logstore_totalcoursemodview',
            array('courseid' => $this->course->id, 'userid' => $this->user1->id));
        $this->assertCount(0, $logs);
        ob_get_contents();
        ob_end_clean();
    }


    // Provider.
    public static function test_provider(): array {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @param $course1
     * @param $resource1
     * @param $course2
     * @param $resource2
     * @throws coding_exception
     */
    private function setup_datas() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        $this->setAdminUser();
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->resource = $this->getDataGenerator()->create_module('resource', array('course' => $this->course));
        $this->resourcecontext =  context_module::instance($this->resource->cmid);
        $this->cmresource = get_coursemodule_from_instance('resource', $this->resource->id);
        $this->chat = $this->getDataGenerator()->create_module('chat', array('course' => $this->course));
        $this->chatcontext =  context_module::instance($this->chat->cmid);
        $this->cmchat = get_coursemodule_from_instance('chat', $this->chat->id);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, $studentrole->id);
        get_log_manager(true);
    }

    private function set_log_store($jsonformat) {
        set_config('enabled_stores', '', 'tool_log');
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_total_course_module_viewed', 'tool_log');
        set_config('jsonformat', $jsonformat ? 1 : 0, 'logstore_total_course_module_viewed');
        set_config('module_connections', 0, 'logstore_total_course_module_viewed');
        // Force reload.
        get_log_manager(true);
    }

    private function launch_module_viewed_events() {
        resource_view($this->resource, $this->course, $this->cmresource, $this->resourcecontext);
        $event = course_module_viewed::create(array('context' =>  context_module::instance($this->chat->cmid),
            'objectid' => $this->chat->id));
        $event->trigger();
    }
}
