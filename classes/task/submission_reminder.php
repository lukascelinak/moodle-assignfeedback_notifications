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
 * The assignsubmission_notification_send event class.
 *
 * @package    assignfeedback_notifications
 * @category   task
 * @copyright  Lukas Celinak, Edumood s.r.o., Slovakia
 * @author     2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_notifications\task;

defined('MOODLE_INTERNAL') || die();

use assignfeedback_notifications\event\notification_sent;

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * An example of a scheduled task.
 */
class submission_reminder extends \core\task\scheduled_task
{
    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('submission_reminder', 'assignfeedback_notifications');
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        global $DB, $CFG;
        $from = \core_user::get_support_user();
        $comparetextsql = $DB->sql_compare_text('value');
        $sqlsubmissions = "SELECT * FROM {assign_plugin_config}  
                         WHERE plugin = 'notifications' AND name = 'enabled'
                         AND {$comparetextsql} = :value";
        $notification_submissions = $DB->get_records_sql($sqlsubmissions, array('value' => 1));
        foreach ($notification_submissions as $submissionconfig) {
            $assignment = $DB->get_record('assign', array('id' => $submissionconfig->assignment));
            $course = $DB->get_record('course', array('id' => $assignment->course));
            $cm = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, $course);

            $notification = $assign->get_feedback_plugin_by_type('notifications');
            $settings = $notification->get_config();
            $footer = get_config('assignfeedback_notifications', 'footer');
            $bottomtext = get_config('assignfeedback_notifications', 'bottomtext');
            $url = new \moodle_url('/mod/assign/view.php', array('id' => $cm->id, 'action' => 'editsubmission'));
            $urlgrading = new \moodle_url('/mod/assign/view.php', array('id' => $cm->id, 'action' => 'grading'));
            $buttongrading= "<a href=\"{$urlgrading}\">" . get_string('showallsubmissions', 'assignfeedback_notifications') . "</a>";
            $buttonlink = "<a href=\"{$url}\" style=\"box-sizing: border-box;
                  background-color: #3498db;
                  border-color: #3498db;
                  color: #ffffff;
                  border: solid 1px #3498db;
                  border-radius: 5px;
                  box-sizing: border-box;
                  cursor: pointer;
                  display: inline-block;
                  font-size: 14px;
                  font-weight: bold;
                  margin: 0;
                  padding: 12px 25px;
                  text-decoration: none;
                  text-transform: capitalize\">" . get_string('addsubmission', 'assignfeedback_notifications') . "</a>";

            list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');

            // First notification after N minutes
            $sql = "SELECT u.* FROM {user} u 
                    LEFT JOIN {user_enrolments} ue ON ue.userid = u.id 
                    LEFT JOIN {enrol} e ON e.id = ue.enrolid 
                    LEFT JOIN {assign_submission} a ON ue.userid = a.userid AND a.assignment = :assigmentid 
                    LEFT JOIN {logstore_standard_log} l ON l.relateduserid = ue.userid 
                               AND l.component = \"assignfeedback_notifications\" 
                               AND l.other LIKE \"%after%\" AND l.objectid = :assigmentid1  
                     JOIN (
                            SELECT DISTINCT ra.userid
                            FROM {role_assignments} ra
                            WHERE ra.roleid IN ($CFG->gradebookroles)
                            AND ra.contextid {$relatedctxsql}
                       ) rainner ON rainner.userid = u.id 

                    WHERE e.courseid=:courseid AND ue.status = 0 AND l.id IS NULL AND a.id IS NULL AND UNIX_TIMESTAMP() > ue.timestart+:timeafter 
                          AND ue.timestart > UNIX_TIMESTAMP(CURDATE())";

            $nsuafterparams = ['assigmentid' => $assignment->id,
                'assigmentid1' => $assignment->id, 'timeafter' => $settings->after,
                'cmid' => $cm->id, 'courseid' => $assignment->course];
            $paramsafter = array_merge($nsuafterparams, $relatedctxparams);
            $nsuafter = $DB->get_records_sql($sql, $paramsafter);

            foreach ($nsuafter as $user) {
                $user->fullname=fullname($user);
                $subjectadm=get_string('message_subject',
                        'assignfeedback_notifications',$user);

                $htmlmail = $this->get_message_template($user,
                    $this->parse_variables($user,$settings->subject),
                    $buttonlink,
                    $this->parse_variables($user,$settings->after_message),
                    property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                    $this->parse_variables($user,$footer));
                $plainmail = strip_tags($htmlmail);

                if ($settings->messagecopyto_enabled == 1) {
                    $htmlmaill=$this->get_admin_message_template($user,
                        $subjectadm,
                        $buttongrading,
                        $this->parse_variables($user,$settings->after_message) ,
                        property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                        $this->parse_variables($user,$footer));

                    $plainmaill= strip_tags($htmlmaill);
                    $copyuser = $DB->get_record('user', array('id' => $settings->messagecopyto));
                    email_to_user($copyuser, $from, $subjectadm, $plainmaill, $htmlmaill);
                }
                email_to_user($user, $from,  $this->parse_variables($user,$settings->subject), $plainmail, $htmlmail);
                $event = \assignfeedback_notifications\event\notification_sent::create(array('objectid' => $assignment->id,
                    'context' => \context_module::instance($cm->id), 'relateduserid' => $user->id, 'other' => "after"));
                $event->trigger();
            }

            $timeformated = "{$settings->nextday_hours}:{$settings->nextday_minutes}";
            //Second notficiation next day after enrolment at HH:MM
            $sqlnextday = "SELECT u.* FROM {user} u 
                         LEFT JOIN {user_enrolments} ue ON ue.userid = u.id 
                         LEFT JOIN {enrol} e ON e.id = ue.enrolid 
                         LEFT JOIN {assign_submission} a ON ue.userid = a.userid AND a.assignment = :assigmentid  
                         LEFT JOIN {logstore_standard_log} l ON l.relateduserid = ue.userid 
                                    AND l.component = \"assignfeedback_notifications\" 
                                    AND l.other LIKE \"%nextday%\" 
                                    AND l.objectid = :assigmentid1
                         JOIN (
                            SELECT DISTINCT ra.userid
                            FROM {role_assignments} ra
                            WHERE ra.roleid IN ($CFG->gradebookroles)
                            AND ra.contextid {$relatedctxsql}
                         ) rainner ON rainner.userid = u.id 
                         WHERE e.courseid=:courseid 
                               AND ue.status = 0 
                                AND l.id IS NULL AND a.id IS NULL
                                    AND UNIX_TIMESTAMP() 
                                        > UNIX_TIMESTAMP(
                                        DATE_ADD(
                                        CONCAT(
                                        FROM_UNIXTIME(ue.timestart, \"%Y-%c-%e\"),\" :time\"),INTERVAL +1 DAY)) 
                           AND ue.timestart > UNIX_TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL -1 DAY)) ";

            $nsunextdayparams = ['assigmentid' => $assignment->id, 'assigmentid1' => $assignment->id,
                'time' => $timeformated, 'cmid' => $cm->id, 'courseid' => $assignment->course];
            $paramsnext = array_merge($nsunextdayparams, $relatedctxparams);
            $nsunextday = $DB->get_records_sql($sqlnextday, $paramsnext);

            foreach ($nsunextday as $user) {
                $user->fullname=fullname($user);
                $subjectadm=get_string('message_subject',
                    'assignfeedback_notifications',$user);

                $htmlmail = $this->get_message_template($user,
                    $this->parse_variables($user,$settings->subject) ,
                    $buttonlink,
                    $this->parse_variables($user,$settings->nextday_message) ,
                    property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                    $footer);
                $plainmail = strip_tags($htmlmail);
                if ($settings->messagecopyto_enabled == 1) {
                    $htmlmaill=$this->get_admin_message_template($user,
                        $subjectadm,
                        $buttongrading,
                        $this->parse_variables($user,$settings->nextday_message),
                        property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                        $footer);

                    $plainmaill= strip_tags($htmlmaill);
                    $copyuser = $DB->get_record('user', array('id' => $settings->messagecopyto));
                    email_to_user($copyuser,
                        $from,
                        $subjectadm,
                        $plainmaill,
                        $htmlmaill);
                }

                email_to_user($user,
                    $from,
                    $this->parse_variables($user,$settings->subject),
                    $plainmail,
                    $htmlmail);
                $event = \assignfeedback_notifications\event\notification_sent::create(
                    array('objectid' => $assignment->id,
                        'context' => \context_module::instance($cm->id),
                        'relateduserid' => $user->id,
                        'other' => "nextday"));
                $event->trigger();
            }

            $sqlthirdday = "SELECT u.* FROM {user} u 
                         LEFT JOIN {user_enrolments} ue ON ue.userid = u.id 
                         LEFT JOIN {enrol} e ON e.id = ue.enrolid 
                         LEFT JOIN {assign_submission} a ON ue.userid = a.userid AND a.assignment = :assigmentid  
                         LEFT JOIN {logstore_standard_log} l ON l.relateduserid = ue.userid 
                                    AND l.component = \"assignfeedback_notifications\" 
                                    AND l.other LIKE \"%thirdday%\" 
                                    AND l.objectid = :assigmentid1
                         JOIN (
                            SELECT DISTINCT ra.userid
                            FROM {role_assignments} ra
                            WHERE ra.roleid IN ($CFG->gradebookroles)
                            AND ra.contextid {$relatedctxsql}
                         ) rainner ON rainner.userid = u.id 
                         WHERE e.courseid=:courseid 
                               AND ue.status = 0 
                                AND l.id IS NULL AND a.id IS NULL 
                                    AND UNIX_TIMESTAMP() 
                                        > UNIX_TIMESTAMP(
                                          DATE_ADD(CONCAT(FROM_UNIXTIME(ue.timestart, \"%Y-%c-%e\"),\" :time\"),INTERVAL +2 DAY)) 
                           AND ue.timestart > UNIX_TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL -2 DAY)) ";

            $nsunextdayparams = ['assigmentid' => $assignment->id, 'assigmentid1' => $assignment->id, 'time' => $timeformated, 'cmid' => $cm->id, 'courseid' => $assignment->course];
            $paramsafter = array_merge($nsunextdayparams, $relatedctxparams);
            $nsuthirdday = $DB->get_records_sql($sqlthirdday, $paramsafter);
            foreach ($nsuthirdday as $user) {
                $user->fullname=fullname($user);
                $subjectadm=get_string('message_subject',
                    'assignfeedback_notifications',$user);

                $htmlmail = $this->get_message_template($user,
                    $this->parse_variables($user,$settings->subject) ,
                    $buttonlink,
                    $this->parse_variables($user,$settings->thirdday_message) ,
                    property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                    $footer);
                $plainmail = strip_tags($htmlmail);
                if ($settings->messagecopyto_enabled == 1) {
                    $htmlmaill=$this->get_admin_message_template($user,
                        $subjectadm,
                        $buttongrading,
                        $this->parse_variables($user,$settings->thirdday_message) ,
                        property_exists($settings,'bottomtext')?$this->parse_variables($user,$settings->bottomtext):"",
                        $footer);
                    $plainmaill= strip_tags($htmlmaill);
                    $copyuser = $DB->get_record('user', array('id' => $settings->messagecopyto));
                    $subjectt=$subjectadm." ({$user->email})";
                    email_to_user($copyuser, $from, $subjectt, $plainmaill, $htmlmaill);
                }
                email_to_user($user, $from, $this->parse_variables($user,$settings->subject), $plainmail, $htmlmail);
                $event = \assignfeedback_notifications\event\notification_sent::create(
                    array('objectid' => $assignment->id,
                        'context' => \context_module::instance($cm->id),
                        'relateduserid' => $user->id, 'other' => "thirdday"));
                $event->trigger();
            }
        }
    }

    /**
     * @param obj $recipient
     * @param string $subject
     * @param string $toptext
     * @param string $bottomtext
     * @param string $buttonlink
     * @param string $footer
     * @return string
     */
    public function get_message_template($recipient, $subject = "", $buttonlink = "", $toptext = "", $bottomtext = "", $footer = "")
    {
        $htmlmail = "<!doctype html>
        <html>
          <head>
            <meta name=\"viewport\" content=\"width=device-width\" />
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
            <title>{$subject}</title>
            <style>
              /* -------------------------------------
                  GLOBAL RESETS
              ------------------------------------- */
              
              /*All the styling goes here*/
              
              img {
                border: none;
                -ms-interpolation-mode: bicubic;
                max-width: 100%; 
              }
        
              body {
                background-color: #f6f6f6;
                font-family: sans-serif;
                -webkit-font-smoothing: antialiased;
                font-size: 14px;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                -ms-text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%; 
              }
        
              table {
                border-collapse: separate;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
                width: 100%; }
                table td {
                  font-family: sans-serif;
                  font-size: 12px;
                  vertical-align: top; 
              }
        
              /* -------------------------------------
                  BODY & CONTAINER
              ------------------------------------- */
        
              .body {
                background-color: #f6f6f6;
                width: 100%; 
              }
        
              /* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
              .container {
                display: block;
                margin: 0 auto !important;
                /* makes it centered */
                max-width: 1024px;
                padding: 10px;
                width: 1024px; 
              }
        
              /* This should also be a block element, so that it will fill 100% of the .container */
              .content {
                box-sizing: border-box;
                display: block;
                margin: 0 auto;
                max-width: 1024px;
                padding: 10px; 
              }
        
              /* -------------------------------------
                  HEADER, FOOTER, MAIN
              ------------------------------------- */
              .main {
                background: #ffffff;
                border-radius: 3px;
                width: 100%; 
              }
        
              .wrapper {
                box-sizing: border-box;
                padding: 20px; 
              }
        
              .content-block {
                padding-bottom: 10px;
                padding-top: 10px;
              }
        
              .footer {
                clear: both;
                margin-top: 10px;
                text-align: center;
                width: 100%; 
              }
                .footer td,
                .footer p,
                .footer span,
                .footer a {
                  color: #999999;
                  font-size: 10px;
                  text-align: center; 
              }
        
              /* -------------------------------------
                  TYPOGRAPHY
              ------------------------------------- */
              h1,
              h2,
              h3,
              h4 {
                color: #000000;
                font-family: sans-serif;
                font-weight: 400;
                line-height: 1.4;
                margin: 0;
                margin-bottom: 30px; 
              }
        
              h1 {
                font-size: 35px;
                font-weight: 300;
                text-align: center;
                text-transform: capitalize; 
              }
        
              p,
              ul,
              ol {
                font-family: sans-serif;
                font-size: 14px;
                font-weight: normal;
                margin: 0;
                margin-bottom: 15px; 
              }
                p li,
                ul li,
                ol li {
                  list-style-position: inside;
                  margin-left: 5px; 
              }
        
              a {
                color: #3498db;
                text-decoration: underline; 
              }
        
              /* -------------------------------------
                  BUTTONS
              ------------------------------------- */
              .btn {
                box-sizing: border-box;
                width: 100%; }
                .btn > tbody > tr > td {
                  padding-bottom: 15px; }
                .btn table {
                  width: auto; 
              }
                .btn table td {
                  background-color: #ffffff;
                  border-radius: 5px;
                  text-align: center; 
              }
                .btn a {
                  background-color: #ffffff;
                  border: solid 1px #3498db;
                  border-radius: 5px;
                  box-sizing: border-box;
                  color: #3498db;
                  cursor: pointer;
                  display: inline-block;
                  font-size: 14px;
                  font-weight: bold;
                  margin: 0;
                  padding: 12px 25px;
                  text-decoration: none;
                  text-transform: capitalize; 
              }
        
              .btn-primary table td {
                background-color: #3498db; 
              }
        
              .btn-primary a {
                background-color: #3498db;
                border-color: #3498db;
                color: #ffffff; 
              }
        
              /* -------------------------------------
                  OTHER STYLES THAT MIGHT BE USEFUL
              ------------------------------------- */
              .last {
                margin-bottom: 0; 
              }
        
              .first {
                margin-top: 0; 
              }
        
              .align-center {
                text-align: center; 
              }
        
              .align-right {
                text-align: right; 
              }
        
              .align-left {
                text-align: left; 
              }
        
              .clear {
                clear: both; 
              }
        
              .mt0 {
                margin-top: 0; 
              }
        
              .mb0 {
                margin-bottom: 0; 
              }
        
              .preheader {
                color: transparent;
                display: none;
                height: 0;
                max-height: 0;
                max-width: 0;
                opacity: 0;
                overflow: hidden;
                mso-hide: all;
                visibility: hidden;
                width: 0; 
              }
        
              .powered-by a {
                text-decoration: none; 
              }
        
              hr {
                border: 0;
                border-bottom: 1px solid #f6f6f6;
                margin: 20px 0; 
              }
        
              /* -------------------------------------
                  RESPONSIVE AND MOBILE FRIENDLY STYLES
              ------------------------------------- */
              @media only screen and (max-width: 620px) {
                table[class=body] h1 {
                  font-size: 28px !important;
                  margin-bottom: 10px !important; 
                }
                table[class=body] p,
                table[class=body] ul,
                table[class=body] ol,
                table[class=body] td,
                table[class=body] span,
                table[class=body] a {
                  font-size: 16px !important; 
                }
                table[class=body] .wrapper,
                table[class=body] .article {
                  padding: 10px !important; 
                }
                table[class=body] .content {
                  padding: 0 !important; 
                }
                table[class=body] .container {
                  padding: 0 !important;
                  width: 100% !important; 
                }
                table[class=body] .main {
                  border-left-width: 0 !important;
                  border-radius: 0 !important;
                  border-right-width: 0 !important; 
                }
                table[class=body] .btn table {
                  width: 100% !important; 
                }
                table[class=body] .btn a {
                  width: 100% !important; 
                }
                table[class=body] .img-responsive {
                  height: auto !important;
                  max-width: 100% !important;
                  width: auto !important; 
                }
              }
        
              /* -------------------------------------
                  PRESERVE THESE STYLES IN THE HEAD
              ------------------------------------- */
              @media all {
                .ExternalClass {
                  width: 100%; 
                }
                .ExternalClass,
                .ExternalClass p,
                .ExternalClass span,
                .ExternalClass font,
                .ExternalClass td,
                .ExternalClass div {
                  line-height: 100%; 
                }
                .apple-link a {
                  color: inherit !important;
                  font-family: inherit !important;
                  font-size: inherit !important;
                  font-weight: inherit !important;
                  line-height: inherit !important;
                  text-decoration: none !important; 
                }
                #MessageViewBody a {
                  color: inherit;
                  text-decoration: none;
                  font-size: inherit;
                  font-family: inherit;
                  font-weight: inherit;
                  line-height: inherit;
                }
                .btn-primary table td:hover {
                  background-color: #34495e !important; 
                }
                .btn-primary a:hover {
                  background-color: #34495e !important;
                  border-color: #34495e !important; 
                } 
              }
        
            </style>
          </head>
          <body class=\"\">
            <span class=\"preheader\">{$subject}</span>
            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"body\">
              <tr>
                <td>&nbsp;</td>
                <td class=\"container\">
                  <div class=\"content\">
        
                    <!-- START CENTERED WHITE CONTAINER -->
                    <table role=\"presentation\" class=\"main\">
        
                      <!-- START MAIN CONTENT AREA -->
                      <tr>
                        <td class=\"wrapper\">
                          <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                            <tr>
                              <td>
                                {$toptext}
                                <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"btn btn-primary\">
                                  <tbody>
                                    <tr>
                                      <td align=\"left\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                                          <tbody>
                                            <tr>
                                              <td>{$buttonlink}</td>
                                            </tr>
                                          </tbody>
                                        </table>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                                {$bottomtext}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
        
                    <!-- END MAIN CONTENT AREA -->
                    </table>
                    <!-- END CENTERED WHITE CONTAINER -->
        
                    <!-- START FOOTER -->
                    <div class=\"footer\">
                      <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                        <tr>
                          <td class=\"content-block\">
                            <span class=\"apple-link\">{$footer}</span>
                          </td>
                        </tr>
                        <tr>
                          <td class=\"content-block powered-by\">
                          
                          </td>
                        </tr>
                      </table>
                    </div>
                    <!-- END FOOTER -->
        
                  </div>
                </td>
                <td>&nbsp;</td>
              </tr>
            </table>
          </body>
        </html>";
        return $htmlmail;
    }

    /**
     * @param obj $recipient notification recipient
     * @param string $subject
     * @param string $toptext
     * @param string $bottomtext
     * @param string $buttonlink
     * @param string $footer
     * @return string
     */
    public function get_admin_message_template($recipient, $subject = "", $buttonlink = "", $toptext = "", $bottomtext = "", $footer = "") {
        $recipientname=fullname($recipient);
        $htmlmail = "<!doctype html>
        <html>
          <head>
            <meta name=\"viewport\" content=\"width=device-width\" />
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
            <title>{$subject}</title>
            <style>
              /* -------------------------------------
                  GLOBAL RESETS
              ------------------------------------- */
              
              /*All the styling goes here*/
              
              img {
                border: none;
                -ms-interpolation-mode: bicubic;
                max-width: 100%; 
              }
        
              body {
                background-color: #f6f6f6;
                font-family: sans-serif;
                -webkit-font-smoothing: antialiased;
                font-size: 14px;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                -ms-text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%; 
              }
        
              table {
                border-collapse: separate;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
                width: 100%; }
                table td {
                  font-family: sans-serif;
                  font-size: 12px;
                  vertical-align: top; 
              }
        
              /* -------------------------------------
                  BODY & CONTAINER
              ------------------------------------- */
        
              .body {
                background-color: #f6f6f6;
                width: 100%; 
              }
        
              /* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
              .container {
                display: block;
                margin: 0 auto !important;
                /* makes it centered */
                max-width: 1024px;
                padding: 10px;
                width: 1024px; 
              }
        
              /* This should also be a block element, so that it will fill 100% of the .container */
              .content {
                box-sizing: border-box;
                display: block;
                margin: 0 auto;
                max-width: 1024px;
                padding: 10px; 
              }
        
              /* -------------------------------------
                  HEADER, FOOTER, MAIN
              ------------------------------------- */
              .main {
                background: #ffffff;
                border-radius: 3px;
                width: 100%; 
              }
        
              .wrapper {
                box-sizing: border-box;
                padding: 20px; 
              }
        
              .content-block {
                padding-bottom: 10px;
                padding-top: 10px;
              }
        
              .footer {
                clear: both;
                margin-top: 10px;
                text-align: center;
                width: 100%; 
              }
                .footer td,
                .footer p,
                .footer span,
                .footer a {
                  color: #999999;
                  font-size: 10px;
                  text-align: center; 
              }
        
              /* -------------------------------------
                  TYPOGRAPHY
              ------------------------------------- */
              h1,
              h2,
              h3,
              h4 {
                color: #000000;
                font-family: sans-serif;
                font-weight: 400;
                line-height: 1.4;
                margin: 0;
                margin-bottom: 30px; 
              }
        
              h1 {
                font-size: 35px;
                font-weight: 300;
                text-align: center;
                text-transform: capitalize; 
              }
        
              p,
              ul,
              ol {
                font-family: sans-serif;
                font-size: 14px;
                font-weight: normal;
                margin: 0;
                margin-bottom: 15px; 
              }
                p li,
                ul li,
                ol li {
                  list-style-position: inside;
                  margin-left: 5px; 
              }
        
              a {
                color: #3498db;
                text-decoration: underline; 
              }
        
              /* -------------------------------------
                  BUTTONS
              ------------------------------------- */
               
              .btn {
                box-sizing: border-box;
                width: 100%; }
                .btn > tbody > tr > td {
                  padding-bottom: 15px; }
                .btn table {
                  width: auto; 
              }
                .btn table td {
                  background-color: #ffffff;
                  border-radius: 5px;
                  text-align: center; 
              }
                .btn a {
                  background-color: #ffffff;
                  border: solid 1px #3498db;
                  border-radius: 5px;
                  box-sizing: border-box;
                  color: #3498db;
                  cursor: pointer;
                  display: inline-block;
                  font-size: 14px;
                  font-weight: bold;
                  margin: 0;
                  padding: 12px 25px;
                  text-decoration: none;
                  text-transform: capitalize; 
              }
        
              .btn-primary table td {
                background-color: #3498db; 
              }
        
              .btn-primary a {
                background-color: #3498db;
                border-color: #3498db;
                color: #ffffff; 
              }
        
              /* -------------------------------------
                  OTHER STYLES THAT MIGHT BE USEFUL
              ------------------------------------- */
              .last {
                margin-bottom: 0; 
              }
        
              .first {
                margin-top: 0; 
              }
        
              .align-center {
                text-align: center; 
              }
        
              .align-right {
                text-align: right; 
              }
        
              .align-left {
                text-align: left; 
              }
        
              .clear {
                clear: both; 
              }
        
              .mt0 {
                margin-top: 0; 
              }
        
              .mb0 {
                margin-bottom: 0; 
              }
        
              .preheader {
                color: transparent;
                display: none;
                height: 0;
                max-height: 0;
                max-width: 0;
                opacity: 0;
                overflow: hidden;
                mso-hide: all;
                visibility: hidden;
                width: 0; 
              }
        
              .powered-by a {
                text-decoration: none; 
              }
        
              hr {
                border: 0;
                border-bottom: 1px solid #f6f6f6;
                margin: 20px 0; 
              }
        
              /* -------------------------------------
                  RESPONSIVE AND MOBILE FRIENDLY STYLES
              ------------------------------------- */
              @media only screen and (max-width: 620px) {
                table[class=body] h1 {
                  font-size: 28px !important;
                  margin-bottom: 10px !important; 
                }
                table[class=body] p,
                table[class=body] ul,
                table[class=body] ol,
                table[class=body] td,
                table[class=body] span,
                table[class=body] a {
                  font-size: 16px !important; 
                }
                table[class=body] .wrapper,
                table[class=body] .article {
                  padding: 10px !important; 
                }
                table[class=body] .content {
                  padding: 0 !important; 
                }
                table[class=body] .container {
                  padding: 0 !important;
                  width: 100% !important; 
                }
                table[class=body] .main {
                  border-left-width: 0 !important;
                  border-radius: 0 !important;
                  border-right-width: 0 !important; 
                }
                table[class=body] .btn table {
                  width: 100% !important; 
                }
                table[class=body] .btn a {
                  width: 100% !important; 
                }
                table[class=body] .img-responsive {
                  height: auto !important;
                  max-width: 100% !important;
                  width: auto !important; 
                }
              }
        
              /* -------------------------------------
                  PRESERVE THESE STYLES IN THE HEAD
              ------------------------------------- */
              @media all {
                .ExternalClass {
                  width: 100%; 
                }
                .ExternalClass,
                .ExternalClass p,
                .ExternalClass span,
                .ExternalClass font,
                .ExternalClass td,
                .ExternalClass div {
                  line-height: 100%; 
                }
                .apple-link a {
                  color: inherit !important;
                  font-family: inherit !important;
                  font-size: inherit !important;
                  font-weight: inherit !important;
                  line-height: inherit !important;
                  text-decoration: none !important; 
                }
                #MessageViewBody a {
                  color: inherit;
                  text-decoration: none;
                  font-size: inherit;
                  font-family: inherit;
                  font-weight: inherit;
                  line-height: inherit;
                }
                .btn-primary table td:hover {
                  background-color: #34495e !important; 
                }
                .btn-primary a:hover {
                  background-color: #34495e !important;
                  border-color: #34495e !important; 
                } 
              }
        
            </style>
          </head>
          <body class=\"\">
            <span class=\"preheader\">{$subject}</span>
            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"body\">
              <tr>
                <td></td>
                <td class=\"container\">
                  <div class=\"content\">
        
                    <!-- START CENTERED WHITE CONTAINER -->
                    <table role=\"presentation\" class=\"main\">
        
                      <!-- START MAIN CONTENT AREA -->
                      <tr>
                        <td class=\"wrapper\">
                          <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                            <tr>
                              <td>
                              <h1>Copy of reminder notification sended to {$recipientname} email {$recipient->email}</h1>
                                {$toptext}
                                <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"btn btn-primary\">
                                  <tbody>
                                    <tr>
                                      <td align=\"left\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                                          <tbody>
                                            <tr>
                                              <td>{$buttonlink}</td>
                                            </tr>
                                          </tbody>
                                        </table>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                                {$bottomtext}
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
        
                    <!-- END MAIN CONTENT AREA -->
                    </table>
                    <!-- END CENTERED WHITE CONTAINER -->
        
                    <!-- START FOOTER -->
                    <div class=\"footer\">
                      <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
                        <tr>
                          <td class=\"content-block\">
                            <span class=\"apple-link\">{$footer}</span>
                          </td>
                        </tr>
                        <tr>
                          <td class=\"content-block powered-by\">
                          
                          </td>
                        </tr>
                      </table>
                    </div>
                    <!-- END FOOTER -->
        
                  </div>
                </td>
                <td>&nbsp;</td>
              </tr>
            </table>
          </body>
        </html>";
        return $htmlmail;
    }

    public function parse_variables($user,$text){
        foreach ($user as $key => $value) {
            if (strpos($text, $key)) {
                $text = str_replace("{" . $key . "}", $value, $text);
            }
        }
        return $text;
    }
}

