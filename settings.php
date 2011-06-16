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
pl * plagiarism.php - allows the admin to configure plagiarism stuff
 *
 * @package   plagiarism_turnitin
 * @author    Dan Marsden <dan@danmarsden.com>
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once(dirname(dirname(__FILE__)) . '/../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/plagiarismlib.php');
    require_once($CFG->dirroot.'/plagiarism/urkund/lib.php');
    require_once($CFG->dirroot.'/plagiarism/urkund/urkund_form.php');

    require_login();
    admin_externalpage_setup('plagiarismurkund');

    $context = get_context_instance(CONTEXT_SYSTEM);

    require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

    $mform = new urkund_setup_form();
    $plagiarismplugin = new plagiarism_plugin_urkund();

    if ($mform->is_cancelled()) {
        redirect('');
    }

    echo $OUTPUT->header();
    $currenttab='urkundsettings';
    require_once('urkund_tabs.php');
    if (($data = $mform->get_data()) && confirm_sesskey()) {
        if (!isset($data->urkund_use)) {
            $data->urkund_use = 0;
        }
        foreach ($data as $field=>$value) {
            if (strpos($field, 'urkund')===0) {
                if ($configfield = $DB->get_record('config_plugins', array('name'=>$field, 'plugin'=>'plagiarism'))) {
                    $configfield->value = $value;
                    if (! $DB->update_record('config_plugins', $configfield)) {
                        error("errorupdating");
                    }
                } else {
                    $configfield = new stdClass();
                    $configfield->value = $value;
                    $configfield->plugin = 'plagiarism';
                    $configfield->name = $field;
                    if (! $DB->insert_record('config_plugins', $configfield)) {
                        error("errorinserting");
                    }
                }
            }
        }
        //now check to see if username/password is correct. - this check could probably be improved further.
        $curloptions = array(CURLOPT_HTTPAUTH =>CURLAUTH_BASIC, CURLOPT_USERPWD=>$data->urkund_username.":".$data->urkund_password);
        $file = download_file_content($data->urkund_api.'/rest/submissions',null, null, true, 300, 20, false, NULL, false, $curloptions);
        if ($file->status == '401') {
            //disable turnitin as this config isn't correct.
            $rec = $DB->get_record('config_plugins', array('name'=>'urkund_use', 'plugin'=>'plagiarism'));
            $rec->value = 0;
            $DB->update_record('config_plugins', $rec);
            echo $OUTPUT->notification(get_string('savedconfigfailed', 'plagiarism_urkund'));
        } else {
            echo $OUTPUT->notification(get_string('savedconfigsuccess', 'plagiarism_urkund'), 'notifysuccess');
        }
    }
    $plagiarismsettings = (array)get_config('plagiarism');
    $mform->set_data($plagiarismsettings);
    
    echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
    $mform->display();
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
