<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace assignfeedback_notifications\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The assignsubmission_notification_send event class.
 *
 * @package     assignfeedback_notifications
 * @category    event
 * @copyright  Lukas Celinak, Edumood s.r.o., Slovakia
 * @author     2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class notification_sent extends \core\event\base  {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'assign_submission';
        $this->data['crud'] = 'u'; // Usually we perform update db queries so 'u' its ok!
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_notificationsended', 'assignfeedback_notifications');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "Notification of type '{$this->other}' after course enrollment for submission of assigment was sent to user with id '{$this->relateduserid}' ";
    }
}