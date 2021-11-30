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
 * This file contains the definition for the library class for notifications submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package     assignfeedback_notifications
 * @category    lib
 * @copyright   Lukas Celinak, Edumood, Slovakia
 * @author      2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * library class for notifications feedback plugin extending submission plugin base class
 *
 * @package     assignfeedback_notifications
 * @category    assign_plugin
 * @copyright   Lukas Celinak, Edumood, Slovakia
 * @author      2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_notifications extends assign_feedback_plugin {

    /**
     * Get the name of the online text submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_notifications');
    }

    /**
     * Get the settings for notifications submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $DB;
        if ($this->assignment->has_instance()) {
            $settings=$this->get_config();
        }else{
            $settings=new stdClass();
        }

        $editoroptions=array(
            'subdirs'=>0,
            'maxbytes'=>0,
            'maxfiles'=>0,
            'changeformat'=>0,
            'context'=>null,
            'noclean'=>0,
            'trusttext'=>0,
            'enable_filemanagement' => false);

        $mform->addElement('duration', 'assignfeedback_notifications_after',get_string('notifiyafter_delay','assignfeedback_notifications'));
        $mform->addElement('text', 'assignfeedback_notifications_subject',get_string('subject','assignfeedback_notifications'));
        $mform->setType('assignfeedback_notifications_subject', PARAM_RAW);

        $mform->addElement('editor',
            'assignfeedback_notifications_after_message',
            get_string('notifiyafter_message','assignfeedback_notifications'),
            $editoroptions)->setValue( array('text' => property_exists($settings,'after_message')?$settings->after_message:null));

        $users=$DB->get_records('user');
        $usersselect=array();
        foreach ($users as $user){
            $usersselect[$user->id]=fullname($user);
        }

        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] =  sprintf("%02d", $i) ;
        }
        for ($i = 0; $i < 60; $i++) {
            $minutes[$i] ="   " .  sprintf("%02d", $i);
        }

        $timearray=array();
        $timearray[]=& $mform->createElement('select', 'assignfeedback_notifications_nextday_hours', '', $hours);
        $timearray[]=& $mform->createElement('select', 'assignfeedback_notifications_nextday_minutes', '', $minutes);
        $mform->addGroup( $timearray,'timeselect',get_string('notifynextday_time','assignfeedback_notifications') ,' ',false);
        $mform->addElement('editor',
            'assignfeedback_notifications_nextday_message',
            get_string('notifynextday_message','assignfeedback_notifications'),
            $editoroptions)->setValue( array('text' => property_exists($settings,'nextday_message')?$settings->nextday_message:null));
        $mform->addElement('editor',
            'assignfeedback_notifications_thirdday_message',
            get_string('notifynextday_message','assignfeedback_notifications'),
            $editoroptions)->setValue( array('text' => property_exists($settings,'thirdday_message')?$settings->thirdday_message:null));

        $mform->setType('assignfeedback_notifications_after', PARAM_INT);

        $messagecopyto[] = $mform->createElement('checkbox', 'assignfeedback_notifications_messagecopyto_enabled','', get_string('enable'));
        $messagecopyto[] = $mform->createElement('autocomplete', 'assignfeedback_notifications_messagecopyto', '',$usersselect);

        $mform->addGroup($messagecopyto, 'assignfeedback_notifications_messagecopyto_group',
                         get_string('messagecopyto','assignfeedback_notifications'),
                ' ', false);


        if ($this->assignment->has_instance()) {
            property_exists( $settings,'after') ?
                $mform->setDefault('assignfeedback_notifications_after', $settings->after)
                :null;
            property_exists( $settings,'nextday_hours')
                ? $mform->setDefault('assignfeedback_notifications_nextday_hours', $settings->nextday_hours)
                :null;
            property_exists( $settings,'nextday_minutes')
                ? $mform->setDefault('assignfeedback_notifications_nextday_minutes', $settings->nextday_minutes)
                :null;
            property_exists( $settings,'messagecopyto_enabled')
                ? $mform->setDefault('assignfeedback_notifications_messagecopyto_enabled', $settings->messagecopyto_enabled)
                :null;
            property_exists( $settings,'messagecopyto')
                ? $mform->setDefault('assignfeedback_notifications_messagecopyto', $settings->messagecopyto)
                :null;

            property_exists( $settings,'subject') ?
                $mform->setDefault('assignfeedback_notifications_subject', $settings->subject)
                :null;

        }

        $mform->hideIf('assignfeedback_notifications_messagecopyto',
            'assignfeedback_notifications_messagecopyto_enabled',
            'notchecked');

        $mform->hideIf('assignfeedback_notifications_after',
                       'assignfeedback_notifications_enabled',
                       'notchecked');
        $mform->hideIf('assignfeedback_notifications_subject',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('assignfeedback_notifications_after_message',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('timeselect',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('assignfeedback_notifications_nextday_message',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('assignfeedback_notifications_messagecopyto_enabled',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('assignfeedback_notifications_thirdday_message',
            'assignfeedback_notifications_enabled',
            'notchecked');
        $mform->hideIf('assignfeedback_notifications_messagecopyto',
            'assignfeedback_notifications_enabled',
            'notchecked');
    }

    /**
     * Save the settings for notifications submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        if (empty($data->assignfeedback_notifications_after)) {
            $after = 0;
        } else {
            $after = $data->assignfeedback_notifications_after;
        }

        if (empty($data->assignfeedback_notifications_messagecopyto) || empty($data->assignfeedback_notifications_messagecopyto_enabled)) {
            $messagecopyto = 0;
            $messagecopyto_enabled = 0;
        } else {
            $messagecopyto = $data->assignfeedback_notifications_messagecopyto;
            $messagecopyto_enabled = 1;
        }

        $this->set_config('after', $after);
        $this->set_config('subject', $data->assignfeedback_notifications_subject);
        $this->set_config('after_message', $data->assignfeedback_notifications_after_message['text']);
        $this->set_config('messagecopyto', $messagecopyto);
        $this->set_config('messagecopyto_enabled', $messagecopyto_enabled);
        $this->set_config('nextday_hours', $data->assignfeedback_notifications_nextday_hours);
        $this->set_config('nextday_minutes', $data->assignfeedback_notifications_nextday_minutes);
        $this->set_config('nextday_message', $data->assignfeedback_notifications_nextday_message['text']);
        $this->set_config('thirdday_message', $data->assignfeedback_notifications_thirdday_message['text']);
        return true;
    }

    /**
     * Upgrade the settings from the old assignment to the new plugin based one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment - the database for the old assignment instance
     * @param string $log record log events here
     * @return bool Was it a success?
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log) {
        // No settings to upgrade.
        return true;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }
}

