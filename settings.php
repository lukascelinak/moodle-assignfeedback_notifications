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
 * Plugin administration pages are defined here.
 *
 * @package     assignfeedback_notifications
 * @category    admin
 * @copyright   Lukas Celinak, Edumood s.r.o., Slovakia
 * @author      2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_confightmleditor('assignfeedback_notifications/bottomtext',
            new lang_string('bottomtext', 'assignfeedback_notifications'),
            new lang_string('bottomtext_help', 'assignfeedback_notifications'), ''));

        $settings->add(new admin_setting_confightmleditor('assignfeedback_notifications/footer',
            new lang_string('footer', 'assignfeedback_notifications'),
            new lang_string('footer_help', 'assignfeedback_notifications'), ''));

    }
}
