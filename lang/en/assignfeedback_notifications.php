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

/**
 * Plugin strings are defined here.
 *
 * @package     assignfeedback_notifications
 * @category    string
 * @copyright  Lukas Celinak, Edumood s.r.o., Slovakia
 * @author     2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Submission reminder';
$string['enabled'] = 'Enabled';
$string['enabled_help'] = 'Submission notfications after user enrolment to course,
                           use it, when you need to have submitted something after user enrolment to course';
$string['notifiyafter'] = 'Send notificiation after enrolment';
$string['notifiyafter_delay'] = 'Delay for notification after enrolment';
$string['notifiyafter_message'] = 'Message of notification after enrolment';
$string['notifynextday'] = 'Send notificiation next day after enrolment';
$string['notifynextday_time'] = 'Time of next day and third day notification';
$string['notifynextday_message'] = 'Mesaage for next day notfication';
$string['submission_reminder'] = 'Submission reminder';
$string['event_notificationsended'] = 'Notification sended';
$string['messagecopyto'] = 'Send copy of all notifications to';
$string['addsubmission'] = 'Add submission now';
$string['showallsubmissions'] = 'Show all submissions';
$string['footer'] = 'Add notification footer';
$string['footer_help'] = 'Add footer for notifications sent trough submission reminder.';
$string['bottomtext'] = 'Add notification bottom text';
$string['bottomtext_help'] = 'Add text which is under the submit button.';
$string['subject'] = 'Messages subject';
$string['subject_help'] = 'Add subject for al lmessages sent from this assiment';
$string['message_subject'] = 'Submission reminder for {$a->fullname}';