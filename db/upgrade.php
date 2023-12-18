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
 * logstore total_course_module_viewed
 *
 * @package    logstore_total_course_module_viewed
 * @author Céline Pervès <cperves@unistra.fr>
 * @author Matthieu Fuchs <matfuchs@unistra.fr>
 * @copyright Université de Strasbourg 2022 {@link http://unistra.fr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_logstore_total_course_module_viewed_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2022031408) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('logstore_totalcoursemodview');
        if (!$dbman->field_exists('logstore_totalcoursemodview', 'totalcourseresourcesviewed')) {
            $field = new xmldb_field('totalcourseresourcesviewed', XMLDB_TYPE_INTEGER, '10', null, false, false, 0);
            $dbman->add_field($table, $field);
        }
        if (!$dbman->field_exists('logstore_totalcoursemodview', 'totalcourseactivitiesviewed')) {
            $field = new xmldb_field('totalcourseactivitiesviewed', XMLDB_TYPE_INTEGER, '10', null, false, false, 0);
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022031408, 'logstore', 'logstore_total_course_module_viewed');
    }
    return true;
}
