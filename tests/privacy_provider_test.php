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
 * logstore total course module viewed lang file
 *
 * @package    logstore_total_course_module_viewed
 * @author Céline Pervès <cperves@unistra.fr>
 * @author Matthieu Fuchs <matfuchs@unistra.fr>
 * @copyright Université de Strasbourg 2022 {@link http://unistra.fr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_total_course_module_viewed;

global $CFG;

use context_course;
use context_module;
use core\event\base;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use logstore_total_course_module_viewed\privacy\provider;
use mod_chat\event\course_module_viewed;
use PHPUnit\Framework\Constraint\TraversableContainsIdentical;
use PHPUnit\Framework\Constraint\Constraint;

require_once($CFG->libdir . '/tests/fixtures/events.php');

class privacy_provider_test extends provider_testcase {

    private $user1;
    private $user2;
    private $course;
    private $coursecontext;
    private $resource;
    private $resourcecontext;
    private $cmresource;
    private $chat;
    private $cmchat;
    private $chatcontext;

    protected function setUp() : void {
        parent::setUp();
        $this->setup_datas();
    }

    /**
     * test get_users_in_context function
     */
    public function test_get_users_in_context() {
        $this->setUser($this->user1);
        $event2 = $this->launch_module_viewed_events();
        $this->setUser($this->user2);
        $event2 = $this->launch_module_viewed_events();
        get_log_manager(true);
        $userlist =
            new userlist($this->coursecontext, 'logstore_total_course_module_viewed');
        provider::get_users_in_context($userlist);
        $users = $userlist->get_users();
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertTrue($user->id == $this->user1->id || $user->id == $this->user2->id);
        }
    }

    /**
     * Tets get_contexts_for_userid function.
     * Function that get the list of contexts that contain user information for the specified user.
     * @throws coding_exception
     */
    public function test_user_contextlist() {
        $this->setUser($this->user1);
        $event2 = $this->launch_module_viewed_events();
        get_log_manager(true);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(1, $contextlist->get_contexts());
        $this->assertEquals($this->coursecontext, $contextlist->get_contexts()[0]);
    }

    /**
     * Test export_all_data_for_user function.
     * funciton that export all data for a component for the specified user.
     * @throws coding_exception
     */
    public function test_export_user_data() {
        $this->setUser($this->user1);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            $this->user1,
            'logstore_total_course_module_viewed',
            [$this->coursecontext->id]
        );
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->coursecontext);
        $data = $writer->get_data([get_string('pluginname', 'logstore_total_course_module_viewed')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass'
, $data);
        $this->assertTrue(property_exists($data, 'logstore_totalcoursemodview_records'));
        $this->assertCount(1, $data->logstore_totalcoursemodview_records);
        foreach ($data->logstore_totalcoursemodview_records as $logstorecoursemoduleviewedrecord) {
            $this->assertEquals($this->user1->id, $logstorecoursemoduleviewedrecord->userid);
            $this->assertEquals($this->course->id, $logstorecoursemoduleviewedrecord->courseid);
        }
    }

    public function test_export_user_data_with_only_active_connections() {
        set_config('onlyactiveconnections', 1, 'logstore_total_course_module_viewed');
        $this->set_enrol_activation_state($this->user2->id, ENROL_USER_SUSPENDED);
        $this->setUser($this->user1);
        $this->launch_module_viewed_events();
        $this->setUser($this->user2);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            $this->user1,
            'logstore_total_course_module_viewed',
            [$this->coursecontext->id]
        );
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->coursecontext);
        $data = $writer->get_data([get_string('pluginname', 'logstore_total_course_module_viewed')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass'
, $data);
        $this->assertTrue(property_exists($data, 'logstore_totalcoursemodview_records'));
        $this->assertCount(1, $data->logstore_totalcoursemodview_records);
        foreach ($data->logstore_totalcoursemodview_records as $logstorecoursemoduleviewedrecord) {
            $this->assertEquals($this->user1->id, $logstorecoursemoduleviewedrecord->userid);
            $this->assertEquals($this->course->id, $logstorecoursemoduleviewedrecord->courseid);
        }
    }

    public function test_export_user_data_without_only_active_connections() {
        set_config('onlyactiveconnections', 0, 'logstore_total_course_module_viewed');
        $this->set_enrol_activation_state($this->user2->id, ENROL_USER_SUSPENDED);
        $this->setUser($this->user1);
        $this->launch_module_viewed_events();
        $this->setUser($this->user2);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            $this->user1,
            'logstore_total_course_module_viewed',
            [$this->coursecontext->id]
        );
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->coursecontext);
        $data = $writer->get_data([get_string('pluginname', 'logstore_total_course_module_viewed')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass'
, $data);
        $this->assertTrue(property_exists($data, 'logstore_totalcoursemodview_records'));
        $this->assertCount(1, $data->logstore_totalcoursemodview_records);
        foreach ($data->logstore_totalcoursemodview_records as $logstorecoursemoduleviewedrecord) {
            $this->assertEquals(
                $this->user1->id,
                $logstorecoursemoduleviewedrecord->userid
            );
            $this->assertEquals($this->course->id, $logstorecoursemoduleviewedrecord->courseid);
        }
    }


    /**
     * Test Add contexts that contain user information for the specified user.
     * @return void
     */
    public function test_add_contexts_for_userid() {
        $this->setUser($this->user1);
        $addedcontextlist = new contextlist();
        provider::add_contexts_for_userid($addedcontextlist, $this->user1->id);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(0, $contextlist);
        self::launch_module_viewed_events();
        get_log_manager(true);
        $addedcontextlist = new contextlist();
        provider::add_contexts_for_userid($addedcontextlist, $this->user1->id);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($this->coursecontext, $contextlist->get_contexts()[0]);
    }
    /**
     * Test add_userids_for_context function
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public function test_add_userids_for_context() {
        $userlist = new userlist($this->coursecontext,
            'logstore_total_course_module_viewed');
        $userids = $userlist->get_userids();
        $this->assertEmpty($userids);
        $this->setUser($this->user1);
        self::launch_module_viewed_events();
        $this->setUser($this->user2);
        self::launch_module_viewed_events();
        get_log_manager(true);
        provider::add_userids_for_context($userlist);
        get_log_manager(true);
        $userids = $userlist->get_userids();
        $this->assertCount(2, $userids);
        $this->assertContains((int)$this->user1->id, $userids);
        $this->assertContains((int)$this->user2->id, $userids);
    }

    /**
     * * Test delete_data_for_user function
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->setUser($this->user1);
        $this->launch_module_viewed_events();
        $this->setUser($this->user2);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $this->assertCount(2, $DB->get_records('logstore_totalcoursemodview'));
        $this->assertEquals(1, $DB->count_records('logstore_totalcoursemodview', array('userid' => $this->user1->id)));
        $this->assertEquals(1, $DB->count_records('logstore_totalcoursemodview', array('userid' => $this->user2->id)));
        provider::delete_data_for_user(
            new approved_contextlist(
                $this->user1, 'logstore_total_course_module_viewed', [$this->coursecontext->id]
            )
        );
        $this->assertFalse(
            $DB->record_exists('logstore_totalcoursemodview',
                array('userid' => $this->user1->id, 'courseid' => $this->course->id)
            )
        );
        $this->assertTrue(
            $DB->record_exists('logstore_totalcoursemodview',
                array('userid' => $this->user2->id, 'courseid' => $this->course->id)
            )
        );
        provider::delete_data_for_user(
            new approved_contextlist(
                $this->user2, 'logstore_total_course_module_viewed',
                [$this->coursecontext->id]
            )
        );
        $this->assertFalse($DB->record_exists('logstore_totalcoursemodview', array('userid' => $this->user2->id)));
        $this->assertEquals(0, $DB->count_records('logstore_totalcoursemodview', array('userid' => $this->user1->id)));
        $this->assertEquals(0, $DB->count_records('logstore_totalcoursemodview', array('userid' => $this->user2->id)));
    }

    /**
     * test delete_data_for_all_users_in_context function
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->setUser($this->user1->id);
        $this->launch_module_viewed_events();
        $this->setUser($this->user2->id);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $this->assertCount(2, $DB->get_records('logstore_totalcoursemodview'));
        provider::delete_data_for_all_users_in_context($this->coursecontext);
        $this->assertFalse($DB->record_exists('logstore_totalcoursemodview', array('courseid' => $this->course->id)));
        $this->assertCount(0, $DB->get_records('logstore_totalcoursemodview'));
    }

    /**
     * test delete_data_for_userlist function
     */
    public function test_delete_data_for_userlist() {
        global $DB;
        $this->setUser($this->user1->id);
        $this->launch_module_viewed_events();
        $this->setUser($this->user2->id);
        $this->launch_module_viewed_events();
        get_log_manager(true);
        $this->assertCount(2, $DB->get_records('logstore_totalcoursemodview'));
        $userlist = new approved_userlist(
            $this->coursecontext, 'logstore_total_course_module_viewed',
            array($this->user1->id, $this->user2->id)
        );
        provider::delete_data_for_userlist($userlist);
        $this->assertFalse($DB->record_exists('logstore_totalcoursemodview', array('courseid' => $this->course->id)));
        $this->assertCount(0, $DB->get_records('logstore_totalcoursemodview'));
    }

    /**
     * internal function to setu test datas
     * @throws coding_exception
     */
    private function setup_datas() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        $this->set_logstore();
        $this->setAdminUser();
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course->id, $studentrole->id);
        $this->resource = $this->getDataGenerator()->create_module('resource', array('course' => $this->course));
        $this->resourcecontext =  context_module::instance($this->resource->cmid);
        $this->cmresource = get_coursemodule_from_instance('resource', $this->resource->id);
        $this->chat = $this->getDataGenerator()->create_module('chat', array('course' => $this->course));
        $this->chatcontext =  context_module::instance($this->chat->cmid);
        $this->cmchat = get_coursemodule_from_instance('chat', $this->chat->id);
    }

    private function set_enrol_activation_state($userid, $status) {
        $courseinstances = enrol_get_instances($this->course->id, false);
        $manualinstance = null;
        foreach ($courseinstances as $courseinstance) {
            if ($courseinstance->enrol == 'manual') {
                $manualinstance = $courseinstance;
                break;
            }
        }
        $manualplugin = enrol_get_plugin('manual');
        $manualplugin->update_user_enrol($manualinstance, $userid, $status);
        get_log_manager(true);

    }

    /**
     * Set up logstore to test
     */
    private function set_logstore() {
        set_config('enabled_stores', '', 'tool_log');
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_total_course_module_viewed', 'tool_log');
        // Force reload.
        get_log_manager(true);
    }

    /**
     * @return base
     * @throws coding_exception
     */
    private function launch_module_viewed_events() {
        resource_view($this->resource, $this->course, $this->cmresource, $this->resourcecontext);
        $event = course_module_viewed::create(array('context' =>  context_module::instance($this->chat->cmid),
            'objectid' => $this->chat->id));
        $event->trigger();
    }

}

