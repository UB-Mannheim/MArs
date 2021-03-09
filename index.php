<?php
/*
 * Seat booking for libraries
 *
 * (C) 2020 UB Mannheim
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Note: TODO comments mark missing and incomplete code.
 */
session_start();

require_once 'i12n.php';

// Read configuration
$scriptdir = dirname(__FILE__);
$localdir = '/local';
$confdir = $scriptdir . $localdir;
if (!is_dir($confdir)) {
    $localdir = '/local-template';
    $confdir = $scriptdir . $localdir;
}
require_once "$confdir/config.php";

// Command line parameter for staff mail (e.g. via cron)
global $argv;
if (isset($argv[1]) && $argv[1] == "mail-staff") {
    send_staff_mail();
    exit;
}

function alert($text)
{
    print("<script>alert('$text');</script>");
}

function trace($text)
{
    global $uid;
    if ($uid == TRACE_USER) {
        alert($text);
    }
}

// Get parameter from web (GET, POST) or command line (environment).
function get_parameter($name, $default = '')
{
    $parameter = $default;
    if (isset($_REQUEST[$name])) {
        // Value from GET or POST (web).
        $parameter = $_REQUEST[$name];
    } elseif (isset($_SERVER[$name])) {
        // Value from environment (command line).
        $parameter = $_SERVER[$name];
    }
    return htmlspecialchars($parameter);
}

// Get handle for database access.
function get_database()
{
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($error = $db->connect_errno) {
        die("Failed to connect to database, $error\n");
    }
    return $db;
}

// Dump all bookings.
function dump_database()
{
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("SELECT date,text,name FROM $table ORDER BY date,name");
    print("<table><tr><th>date</th><th>pl</th><th>uid</th></tr>");
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        $text = $reservation['text'];
        $uid = $reservation['name'];
        print("<tr><td>$date</td><td>$text</td><td>$uid</td></tr>");
    }
    print("</table>");
    $result->free();
    $db->close();
}

function mail_database($user)
{
    global $url_tstamp;
    if (!function_exists('sendmail')) {
        return;
    }
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("SELECT date,text FROM $table where name='" . $user['id'] . "' and used='0' ORDER BY date");
    $mailtext = "";
    $today = date('Y-m-d', time());
    if ($url_tstamp) {
        $today = date('Y-m-d', $url_tstamp);
    }
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        if ($date < $today) {
            // Don't show old bookings.
            continue;
        }
        // Convert date representation.
        $date = date('d.m.Y', strtotime($date));
        // Translate short into long location name.
        $text = AREAS[$reservation['text']]['name'];
        $mailtext .= "$date $text\n";
    }
    $result->free();
    $db->close();
    sendmail($user, $mailtext);
}

// Drop existing table with all bookings and create a new empty one.
function init_database()
{
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("DROP TABLE $table");
    $result = $db->query("CREATE TABLE `$table` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(16) NOT NULL,
        `member` BOOLEAN DEFAULT 1 NOT NULL,
        `text` VARCHAR(8) NOT NULL,
        `date` DATE NOT NULL,
        'used' TINYINT DEFAULT 0 NOT NULL,
        CONSTRAINT unique_reservation UNIQUE (date, name),
        PRIMARY KEY(`id`)
    )");
    $db->close();
}

// Add up to 10 random bookings for testing.
function preset_database()
{
    global $url_tstamp;
    $db = get_database();
    $table = DB_TABLE;
    $now = time();
    if ($url_tstamp) {
        $now = $url_tstamp;
    }
    for ($i = 0; $i < 10; $i++) {
        $uid = TEST_USERS[rand(0, count(TEST_USERS) - 1)];
        // TODO: Fixme. (What is meant to be fixed here?)
        $text = array_rand(AREAS);
        $date = date('Y-m-d', $now + 24 * 60 * 60 * rand(0 - MAX_AGE, 7));
        // TODO: Optionally update and insert external users, too.
        $result = $db->query("INSERT INTO $table (name, text, date) VALUES ('$uid','$text','$date')");
    }
    $db->close();
}

// Add, modify or delete bookings in the database.
function update_database($uid, $is_member, $date, $oldvalue, $value)
{
    global $url_tstamp;
    $db = get_database();
    $table = DB_TABLE;
    $comment = "";
    $commentType = 0;
    $no_reservation = 'no';
    $success_text = '<span class="success">' . __('Aktion erfolgreich') . '</span>';
    $failure_text = '<span class="failure">' . __('Aktion nicht erfolgreich') . '</span>';
    if ($value == $no_reservation) {
        echo("update in no_reservation<br />");
        // Delete booking.
        $result = $db->query("DELETE FROM $table WHERE name='$uid' AND date='$date'");
        $success = $result ? $success_text : $failure_text;
        $comment = DEBUG ? __('geloescht') . ": $oldvalue, $success" : $success;
        $commentType = 1;
    } elseif ($value == "cancel") {
        echo("update in cancel<br />");
        // Delete booking.
        $result = $db->query("UPDATE $table SET used='2' WHERE name='$uid' AND date='$date'");
        $success = $result ? $success_text : $failure_text;
        $comment = DEBUG ? __('storniert') . ": $oldvalue, $success" : $success;
        $commentType = 5;
    } else {
        echo("<br />" . __LINE__);
        // Limit bookings.
        $member = $is_member ? 1 : 0;
        $result = $db->query("SELECT COUNT(*) FROM $table WHERE date='$date' AND text='$value' AND member=$member");
        $count = $result ? $result->fetch_row()[0] : 0;
        $group = $is_member ? "member" : "extern";
        $limit = AREAS[$value]['limit'][$group];
        $today = date('Y-m-d', time());
        if ($url_tstamp) {
            $today = date('Y-m-d', $url_tstamp);
        }
        $result = $db->query("SELECT COUNT(*) FROM $table WHERE date>'$today' AND name='$uid'");
        $personal_bookings = $result ? $result->fetch_row()[0] : 999;
        if ($count >= $limit) {
            echo("<br />" . __LINE__ );
            $comment = '<span class="failure">' . __('Bibliotheksbereich ausgebucht') . '</span>';
            $commentType = 2;
        } elseif ($oldvalue == $no_reservation) {
            echo("<br />" . __LINE__ );
            // New bookings.
            if ($personal_bookings >= PERSONAL_LIMIT[$group]) {
                $comment = '<span class="failure">' . __('Persoenliches Buchungslimit erreicht') . '</span>';
                $commentType = 3;
            } else {
                echo("<br />" . __LINE__ );
                $result = $db->query("INSERT INTO $table (name, member, text, date) VALUES ('$uid',$member,'$value','$date')");
                $success = $result ? $success_text : $failure_text;
                $comment = DEBUG ? __('reserviert') . ": $value, $success" : $success;
                $commentType = 4;
            }
        } else {
            echo("<br />" . __LINE__ );
            // Modified booking.
            echo("<br />" . __LINE__ );
            if ($personal_bookings > PERSONAL_LIMIT[$group]) {
                echo("<br />" . __LINE__ );
                $comment = '<span class="failure">' . __('Persoenliches Buchungslimit erreicht') . '</span>';
                $commentType = 3;
            } else {
                echo("<br />" . __LINE__ );
                //$result = $db->query("DELETE FROM $table WHERE name='$uid' AND date='$date'");
                //$result = $db->query("INSERT INTO $table (name, member, text, date) VALUES ('$uid',$member,'$value','$date')");
                echo("update $table set text='$value', used='0' where name='$uid' and date='$date'");
                $result = $db->query("UPDATE $table set text='$value', used='0' WHERE name='$uid' AND date='$date'");
                $success = $result ? $success_text : $failure_text;
                $comment = DEBUG ? __('aktualisiert') . ": $oldvalue -> $value, $success" : $success;
                $commentType = 0;
                echo($success);
            }
        }
    }
    $db->close();
    return array($comment,$commentType);
}

// Show stored bookings in a web form which allows modifications.
function show_database($uid, $lastuid, $is_member)
{
    // Because of the output of the possible bookings at the beginning,
    // no direct print but intermediate storage and output at the end
    $aPrint = array();

    if ($_SESSION['language'] === 'de') {
        $weekdays = array('So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa');
    } else {
        $weekdays = array('Sun', 'Mon', 'Tue', 'Med', 'Thu', 'Fri', 'Sat');
    }

    global $url_tstamp, $user;
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("SELECT date, text, used FROM $table WHERE name = '$uid' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    //echo 'row=' . htmlentities($row);
    $db->close();

    // Get current time.
    $now = time();
    if ($url_tstamp) {
        $now = $url_tstamp;
    }

    // First day which can be booked (time rounded to start of day).
    // Accept bookings for current day until HH:MM.
    $deadline    = explode(':', DAILY_DEADLINE);
    $deadminutes = ($deadline[0] * 60.0 + $deadline[1] * 1.0);
    // add (1 day - deadline) to now() and round to next or current day
    $start = $now + (((24 * 60) - $deadminutes) * 60);
    $start = strtotime(date('Y-m-d', $start));

    // Round current time to start of day.
    $now = strtotime(date('Y-m-d', $now));

    // Last day which may be booked (time rounded to start of day).
    $last = $now + 24 * 60 * 60 * MAX_DAYS;

    // First day which will be shown.
    $first = $now;

    $aPrint[] = '<fieldset>';
    //print('<legend>' . __('Buchungen') . '</legend>');
    $aPrint[] = '<table id="reservations">';
    // Print Headline for table
    $aPrint[] = '<tr><th></th>';
    $nNrLongnames = 0;
    foreach (AREAS as $area => $values) {
        $aPrint[] = '<th>' . $values['name'] . '</th>';
        $nNrLongnames++;
    }
    $nNrLongnames++;
    $aPrint[] = '<th>&nbsp;</th></tr>';

    // Get the first reserved day from the booking list.
    $i = 0;

    for ($time = $first; $time < $last; $time = strtotime("+1 day", $time)) {
        //$disabled = false;
        $is_today = $false;
        $CommentClass= '';
        $day = date('Y-m-d', $time);
        $text = 'no';
        $used = '';
        // am gleichen Tag kein Reservieren mehr möglich
        echo("<br />time: " . $time . " start: " . $start );
        if ($time < $start) {
            //$disabled = true;
            $is_today = true;
        }

        $label = date('d.m.', $time);
        $label = "<span class=\"day\">$label</span>";

        $resday = '';
        while ($i < count($reservations) && ($resday = $reservations[$i]['date']) < $day) {
            $i++;
        }
        if ($i < count($reservations)) {
            $text = $reservations[$i]['text'];
            $used = $reservations[$i]['used'];
            //print_r($reservations);
        }
        if ($resday != $day) {
            $text = 'no';
        }

        $languageClass = 'de';
        if ($_SESSION['language'] === 'en') {
            $languageClass = 'en';
        }

        // Skip days which cannot be booked.
        $weekday = $weekdays[date('w', $time)];
        $label = "<label><span class=\"weekday\">$weekday</span> $label</label>";
        $closed = false;
        foreach (CLOSED as $condition) {
            $line = '';
            $closed = ($weekday == $condition);
            if ($closed) {
                //print("<div class=\"closed\">$label geschlossen / closed</div>");
                $aPrint[] = '<tr class="closed><td class="label">' . $label . '</td>';
                $name = "choise-$day";
                $line = '';
                $languageClass = 'closed-day-de';
                if ($_SESSION['language'] === 'en') {
                    $languageClass = 'closed-day-en';
                }
                foreach (AREAS as $area => $values) {
                    $id = "$area-$day";
                    $cTitle = __('geschlossen') . ': ' . $values['name'];
                    //print('<td><span class="closed-day ' . $languageClass . '">&nbsp;</span></td>');
                    $line .= '<td class="dateradio ' . $area . ' closed-day ' . $languageClass . '" title=' . "'" . $cTitle . ': ' . date('d.m.', $time) . "'>" .
                             "<input class=\"closed-day-input\" type=\"checkbox\" name=\"$name\" id=\"$id\" value=\"$area\" disabled />" .
                             "</td>";
                };
                $aPrint[] = $line . '<td class="feedback"></td></tr>';
                break;
            }
            $closed = ($day == $condition);
            if ($closed) {
                //print("<div class=\"closed\">$label geschlossen / closed</div>");
                $aPrint[] = "<tr class=\"closed\"><td class=\"label\">$label</td>";
                $name = "choice-$day";
                $line = '';
                $languageClass = 'closed-day-de';
                if ($_SESSION['language'] === 'en') {
                    $languageClass = 'closed-day-en';
                }
                foreach (AREAS as $area => $values) {
                    $id = "$area-$day";
                    $cTitle = __('geschlossen') . ': ' . $values['name'];
                    //print('<td><span class="closed-day ' . $languageClass . '">&nbsp;</span></td>');

                    $line .= '<td class="dateradio ' . $area . ' closed-day ' . $languageClass . '" title=' . "'" . $cTitle . ': ' . date('d.m.', $time) . "'>" .
                             "<input class=\"closed-day-input\" type=\"checkbox\" name=\"$name\" id=\"$id\" value=\"$area\" disabled />" .
                             "</td>";
                };
                $aPrint[] = $line . '<td class="feedback"></td></tr>';
                break;
            }
        }
        if ($closed) {
            continue;
        }

        $name = "choice-$day";
        echo("<br />" . $name );
        $requested = get_parameter($name, '');
        echo("<br />'". $requested . '"');
        if ($requested != '') {
        } else {
            echo("<br />" . __LINE__ );
            $requested = get_parameter("cancel-choice-$day", '');
        };
        $comment = '';
        $commentType = 0;

        if ($uid != $lastuid) {
            // Initial call with the current user id.
            $comment = DEBUG ? __('aenderbar') : '';
        } elseif ($text == $requested) {
            $comment = DEBUG ? __('unveraendert') : '';
        } elseif ($used == '2' && $requested == 'cancel') {
            $comment = DEBUG ? __('unveraendert') : '';
        } else {
            // TODO: get new DB values here or do it in some other way, where displaying and updating the db are independent from each other...
            echo("<br />update_database(" . $uid . ", " . $is_member . ", " . $day . ", " . $text . ", " . $requested . ")<br/>");
            $aComment = update_database($uid, $is_member, $day, $text, $requested);
            $comment = $aComment[0];
            $commentType = $aComment[1];
            $text = $requested == 'cancel' ? $text : $requested;
            $used = $requested == 'cancel' ? '2' : '0';
        }

        $languageClass = 'open-day-de';
        if ($_SESSION['language'] === 'en') {
            $languageClass = 'open-day-en';
        }

        $line = '';
        echo("<br /" . __LINE__ . " used: " . $used . "<br />");
        echo(__LINE__ . " requested: " . $requested . "<br/>");
        echo(__LINE__ . " is_today: " . ($is_today == True ? 'j' : 'n') . "<br />");

        if ($used == '1') {
            /*
            $line = AREAS[$text]['name'].': Buchung wahrgenommen';
            $line .= "<input type=\"hidden\" name=\"$name\" id=\"$text-$day\" value=\"$text\" checked/>";
            */
            // für wahrgenommene Buchung am aktuellen Tag
            foreach (AREAS as $area => $values) {
                $id = "$area-$day";
                $checked = ($text == $area) ? ' checked' : '';
                $checkedClass = '';
                if ($disabled) {
                    $disabled_html = ' disabled';
                } else {
                    $disabled_html = '';
                }

                if (($commentType != 3) and ($commentType != 2)) {
                    $checkedClass = ($text == $area) ? ' checked ' : ' ';
                } else {
                    $checkedClass = ($text == $area) ? ' checkedError errorType-' . $commentType . ' ' : ' ';
                }
                $checkedClassInput = ($text == $area) ? ' class="checked-input ' . __LINE__ . '" ' : ' ';

                $cTitle = $values['name'];
                $cTitle = __("Keine Reservierungen fuer den laufenden Tag moeglich");

                $lineHide = '';
                // unterscheiden ob ein normaler Eintrag oder ein aktiver Eintrag der gecanceld werden soll
                $value=$area;
                if ($area == $text) {
                    $value = "wahrgenommen";
                    $disabled_html = ' disabled';
                    $cTitle = __("Buchung wahrgenommen");
                    $checkedClass = ' wahrgenommen ';
                    $checkedClassInput = ' class="wahrgenommen" ';
                    $id = "wahrgenommen-$area-$day";
                    $lineHide = "<input type=\"hidden\" name=\"$name\" id=\"$text-$day\" value=\"$text\" checked/>";
                };

                $line .= '<td class="dateradio ' . $area . $checkedClass . $disabled_html . ' ' . $languageClass . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                         '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $value . '"' . $checked . $disabled_html . ' onclick="onlyOne(this, ' . "'" . $name . "')" . '" ' . $checkedClassInput . '/>' .
                         $lineHide .
                         '</td>';
            }
            // für wahrgenommene Buchung am aktuellen Tag Ende



        } elseif ($requested == 'cancel' || $used == '2') {
            /*
            $line = '<del class="cancelled">'.AREAS[$text]['name'].'</del> <ins class="cancelled">Buchung storniert</ins>';
            $line .= "<input type=\"hidden\" name=\"$name\" id=\"cancel-$day\" value=\"cancel\"/>";
            */

            // für stornierte Buchung am aktuellen Tag
            // Die sind schon storniert und können nicht nochmal storniert werden
            // Was kann mit ihnen getan werden
            // Was soll gezeigt werden
            // bei Dennis wird nichts angezeigt
            // Wozu speichere ich es dann?
            foreach (AREAS as $area => $values) {
                $id = "$area-$day";
                $checked = ($text == $area) ? ' checked' : '';
                $checkedClass = '';
                //if ($disabled) {
                //    $disabled_html = ' disabled';
                //} else {
                //    $disabled_html = '';
                //}

                if (($commentType != 3) and ($commentType != 2)) {
                    $checkedClass = ($text == $area) ? ' checked ' : ' ';
                } else {
                    $checkedClass = ($text == $area) ? ' checkedError errorType-' . $commentType . ' ' : ' ';
                }
                $checkedClassInput = ($text == $area) ? ' class="checked-input ' . __LINE__ . '" ' : ' ';

                $cTitle = $values['name'];
                //$cTitle = __("Keine Reservierungen fuer den laufenden Tag moeglich");

                $lineHide = '';
                // unterscheiden ob ein normaler Eintrag oder ein aktiver Eintrag der gecanceld werden soll
                $value=$area;
                if ($area == $text) {
                    $value = "cancel";
                    //$disabled_html = ' disabled ';
                    //$cTitle = __("Buchung storniert");
                    $checkedClass = ' checked-canceled ';
                    $checkedClassInput = ' class="checked-input-canceled ' . __LINE__ . '" ';
                    //$id = "cancel-$area-$day";
                    //$id = "cancel-$day";
                    //$lineHide = "<input type=\"hidden\" name=\"$name\" id=\"cancel-$day\" value=\"cancel\"/>";
                    $checked = '';
                };

                $line .= '<td class="dateradio ' . $area . $checkedClass . $disabled_html . ' ' . $languageClass . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                         '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $value . '"' . $checked . $disabled_html . ' onclick="onlyOne(this, ' . "'" . $name . "')" . '" ' . $checkedClassInput . '/>' .
                         $lineHide .
                         '</td>';
            }
            // für stornierte Buchung am aktuellen Tag Ende


        } elseif ($is_today && $used != '' && $requested != 'no') {
            //if ($used == '0') {
                /*
                $area = AREAS[$text];
                $line = "<input type=\"radio\" name=\"$name\" id=\"$text-$day\" value=\"$text\" checked/>" .
                        "<label class=\"$text\" for=\"$text-$day\">" . $area['name'] . "</label>";
                $line .= "<input type=\"radio\" name=\"$name\" id=\"cancel-$day\" value=\"cancel\"/>" .
                        "<label class=\"cancel\" for=\"cancel-$day\">Buchung stornieren</label>";
                */
                // Option um Buchung am aktuellen Tag stornieren zu können
                foreach (AREAS as $area => $values) {
                    $id = "$area-$day";
                    $checked = ($text == $area && $used != '2') ? ' checked' : '';
                    $disabled_html = '';
                    $checkedClass = '';
                    //if ($disabled) {
                    //    $disabled_html = ' disabled';
                    //} else {
                    //    $disabled_html = '';
                    //}
                    if (($commentType != 3) and ($commentType != 2)) {
                        $checkedClass = ($text == $area) ? ' checked ' : ' ';
                    } else {
                        $checkedClass = ($text == $area) ? ' checkedError errorType-' . $commentType . ' ' : ' ';
                    }
                    $checkedClassInput = ($text == $area) ? ' class="checked-input ' . __LINE__ . ' used-' . $used . '" ' : ' ';

                    $cTitle = $values['name'];
                    //$cTitle = __("Keine Reservierungen fuer den laufenden Tag moeglich");
                    $lineHide = '';

                    // unterscheiden ob ein normaler Eintrag oder ein aktiver Eintrag der gecanceld werden soll
                    $value=$area;
                    if ($area == $text) {
                        $value = "cancel";
                        //$disabled_html = '';
                        $cTitle = __("Buchung stornieren");
                        $id = "cancel-$area-$day";
                        //$id = "cancel-$day";
                        //choise-cancel-$day
                        $lineHide = "<input type=\"hidden\" name=\"cancel-$name\" id=\"cancel-$day\" value=\"cancel\"/>";
                    };

                    $line .= '<td class="dateradio ' . $area . $checkedClass . $disabled_html . ' ' . $languageClass . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                             '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $value . '"' . $checked . $disabled_html . ' onclick="onlyOne(this, ' . "'" . $name . "')" . '" ' . $checkedClassInput . '/>' .
                             $lineHide .
                             '</td>';
                }
                // Option um Buchung am aktuellen Tag stornieren zu können Ende

            //} else {
            /*
                // Keine Reservierungen für den laufenden Tag möglich.
                // wenn kein eintrag in Datenbank vorhanden ist
                foreach (AREAS as $area => $values) {
                    //$line = "Keine Reservierungen für den laufenden Tag möglich.";

                    $id = "$area-$day";
                    $checked = ($text == $area) ? ' checked' : '';
                    $checkedClass = '';
                    if ($disabled) {
                        $disabled_html = ' disabled';
                    } else {
                        $disabled_html = '';
                    }
                    if (($commentType != 3) and ($commentType != 2)) {
                        $checkedClass = ($text == $area) ? ' checked ' : ' ';
                    } else {
                        $checkedClass = ($text == $area) ? ' checkedError errorType-' . $commentType . ' ' : ' ';
                    }
                    $checkedClassInput = ($text == $area) ? ' class="checked-input" ' : ' ';

                    $cTitle = $values['name'];
                    $cTitle = __("Keine Reservierungen fuer den laufenden Tag moeglich");

                    $line .= '<td class="dateradio ' . $area . $checkedClass . $disabled_html . ' ' . $languageClass . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                             '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $area . '"' . $checked . $disabled_html . ' onclick="onlyOne(this, ' . "'" . $name . "')" . '" ' . $checkedClassInput . '/>' .
                             '</td>';
                }
            } */


        } else {


            // bisheriges Verhalten
            foreach (AREAS as $area => $values) {
                $id = "$area-$day";
                $checked = ($text == $area) ? ' checked' : '';
                if (($commentType != 3) and ($commentType != 2)) {
                    $checkedClass = ($text == $area) ? ' checked ' : ' ';
                } else {
                    $checkedClass = ($text == $area) ? ' checkedError errorType-' . $commentType . ' ' : ' ';
                }
                $checkedClassInput = ($text == $area) ? ' class="checked-input ' . __LINE__ . '" ' : ' ';

                $cTitle = $values['name'];
                if ($disabled) {
                    $cTitle = __('Keine Aenderung mehr moeglich');
                    $disabled_html = ' disabled';
                } else {
                    $disabled_html = '';
                }

                $line .= '<td class="dateradio ' . $area . $checkedClass . $disabled_html . ' ' . $languageClass . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                         '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $area . '"' . $checked . $disabled_html . ' onclick="onlyOne(this, ' . "'" . $name . "')" . '" ' . $checkedClassInput . '/>' .
                         '</td>';
            }

            /*
            $id = "no-$day";
            $checked = ($text == 'no') ? ' checked' : '';
            $cTitle = 'Keine Buchung';
            $line .= '<td class="dateradio noBooking ' . $disabled . '" title="' . $cTitle . ': ' . date('d.m.', $time) . '">' .
                     '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="no"' . $checked . '/>' .
                     '</td>';
                // "<label class=\"no\" for=\"$id\">Keine Buchung</label>";
            */
            if ($comment != '') {
                $comment = " $comment";
                $CommentClass = 'comment';
            }
        }
        $aPrint[] = '<tr class="open"><td class="buchbar label' . $CommentClass . '">' . $label . '</td>'. $line . '<td class="feedback">' . $comment . '</td></tr>' . "\n";
    }
    $aPrint[] = '</table>';
    $aPrint[] = '</fieldset>';

    // Print number of possible Bookings
    print_number_possible_bookings( $uid );

    // print Arrayelements
    for($i=0; $i < count($aPrint); $i++) {
        echo $aPrint[$i] . "\n";
    }

}

function print_number_possible_bookings( $uid )
{
    global $user;
    $now = time();
    $db = get_database();
    $table = DB_TABLE;
    $today = date('Y-m-d', $now);
    $result = $db->query("SELECT COUNT(*) FROM $table WHERE date>'$today' AND name='$uid'");
    $personal_bookings = $result ? $result->fetch_row()[0] : 999;
    $db->close();
    $group = $user['is_member'] ? "member" : "extern";
    $open_bookings = PERSONAL_LIMIT[$group] - $personal_bookings;
    if ($open_bookings < 0) {
        $open_bookings = 0;
    }
    if ($open_bookings > 0) {
        print('<p>' . sprintf(__("Noch %s von %s Buchungen moeglich"), $open_bookings, PERSONAL_LIMIT[$group]) . '.</p>');
    } else {
        print('<p>' . __("Keine Buchungen mehr moeglich") . '.</p>');
    };
}

// Daily report for a location.
function day_report($location = false)
{
    global $url_tstamp;
    $now = time();
    if ($url_tstamp) {
        $now = $url_tstamp;
    }
    $today = date('Y-m-d', $now);

    $db = get_database();
    $table = DB_TABLE;

    if (!$location) {
        // Summary of daily bookings per location.
        $result = $db->query("SELECT date, text, SUM(member) AS internal, SUM(NOT member) AS external FROM seatbookings GROUP BY date, text");
        $reservations = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        $db->close();

        print("<h2>MARS Buchungsübersicht</h2>\n");
        print('<table class="buchungsuebersicht"><tr><th>Datum</th><th>Bibliotheksbereich</th><th>Gesamt</th><th>Mitglieder</th><th>Externe</th></tr>');
        foreach ($reservations as $booking) {
            $date = $booking['date'];
            $location = $booking['text'];
            $count_ext = $booking['external'];
            $count_member = $booking['internal'];
            $count_total = $count_ext + $count_member;
            $longname = AREAS[$location]['name'];
            print("<tr><td>$date</td><td>$longname</td><td>$count_total</td><td>$count_member</td><td>$count_ext</td></tr>");
        }
        print("</table>");
        return;
    }

    $result = $db->query("SELECT date, member, text, name FROM $table WHERE date = '$today' AND text = '$location' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $db->close();

    $date = $today;
    $longname = AREAS[$location]['name'];
    print("<h2>MARS Tagesbericht $date $longname</h2>\n");
    print("<table><tr><th>Nr.</th><th>Datum</th><th>Bibliotheksbereich</th><th>Uni-ID</th><th>Name</th></tr>");

    $nr = 0;

    print("<tr><td>members</td></tr>");

    // Get all full names and sort them.
    $names = array();
    foreach ($reservations as $booking) {
        if (!$booking['member']) continue;
        $name = $booking['name'];
        $visitor = get_user_info($name);
        if (!$visitor) {
            // user does not seem to exist
            $fullname = "[Unbekannter Nutzer]";
        } else {
            $fullname = $visitor['surname'] . ", " . $visitor['givenname'];
        }
        $names[$fullname] = $name;
    }
    ksort($names);

    // Generate report.
    foreach ($names as $fullname => $name) {
        $nr++;
        print("<tr><td>$nr</td><td>$date</td><td>$longname</td><td>$name</td><td>$fullname</td></tr>");
    }

    print("<tr><td>external users</td></tr>");

    // Get all full names and sort them.
    $names = array();
    foreach ($reservations as $booking) {
        if ($booking['member']) continue;
        $name = $booking['name'];
        $visitor = get_user_info($name);
        if (!$visitor) {
            // user does not seem to exist
            $fullname = "[Unbekannter Nutzer]";
        } else {
            $fullname = $visitor['surname'] . ", " . $visitor['givenname'];
        }
        $names[$fullname] = $name;
    }
    ksort($names);

    // Generate report.
    foreach ($names as $fullname => $name) {
        $nr++;
        print("<tr><td>$nr</td><td>$date</td><td>$longname</td><td>$name</td><td>$fullname</td></tr>");
    }
    print("</pre>\n");
}

// Get form values from input (normally POST) if available.
$task = get_parameter('task');
// TODO: $email eventuell entfernen.
$email = get_parameter('email');
$uid = get_parameter('uid');
$lastuid = get_parameter('lastuid');
$password = get_parameter('password');

// Is there a username with valid password?
$authorized = false;

// Initiate user. User info is gathered in auth.php
global $user;
$user = array(
    'id' => '',
    'surname' => '',
    'givenname' => '',
    'gender' => '',
    'mail' => '',
    'is_member' => '',
    //'fullname' => '',
    //'usergroup' => '',
    //'groups' => array(),
);


?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?>">
<head>
<title><?php echo( __('UB Sitzplatzbuchung')) ?></title>
<meta charset="utf-8">
<link rel="stylesheet" type="text/css" href="mars.css" media="all">
<script src="mars.js?202009251414"></script>
</head>
<body>

<?php
if ($uid != '') {
    $authorized = get_authorization($uid, htmlspecialchars_decode($password));
}

if ($uid == '' || $task == '') {
    ?>
<form id="reservation" method="post"  class="powermail_form powermail_form_reservation nolabel">

<div class="powermail_fieldwrap powermail_fieldwrap_type_headline nolabel">
    <div class="powermail_field">
        <h4><?php echo __('Benutzerdaten') ?></h4>
    </div>
</div>

<div id="userid" class="powermail_fieldwrap powermail_fieldwrap_type_input">
    <label for="uid" class="uid powermail_label" title="Universitätskennung">Uni-ID<span class="mandatory">*</span></label>
    <div class="powermail_field">
        <input id="uid"
            class="uid powermail_input"
            name="uid"
            placeholder="<?php echo __('Pflichtfeld') ?>"
            maxlength="8"
            pattern="^([a-z_0-9]{0,8})$"
            required="required"
            type="text"
            value="<?=$uid?>"/>
    </div>
</div>

<div id="userpw" class="powermail_fieldwrap powermail_fieldwrap_type_input">
    <label for="password" class="password powermail_label"><?php echo __('Passwort') ?><span class="mandatory">*</span></label>
    <div class="powermail_field">
        <input id="password"
            name="password"
            placeholder="<?php echo __('Pflichtfeld') ?>"
            required="required"
            type="password"
            value="<?=$password?>"/>
    </div>
</div>
<input id="lastuid" name="lastuid" type="hidden" value="<?=$authorized ? $uid : ''?>"/>

    <?php
}

if ($authorized && $task == '') {
    // <button class="logout" type="button"><a class="logout" href=".">Abmelden / Logout</a></button>
    ?>
<div class="powermail_fieldwrap powermail_fieldwrap_type_submit powermail_fieldwrap_logout nolabel">
    <!--<label for="logout" class="powermail_label leer"></label>-->
    <!-- <?php echo(__LINE__); ?> -->
    <!--<input id="L" name="L" type="hidden" value="<?php echo ($_SESSION['language'] === 'de' ? '0' : '1' ) ?>"/>-->
    <div class="powermail_field">
        <button class="logout" type="button"><a class="button powermail_submit btn btn-primary" href="."><?php echo __('Abmelden') ?></a></button>
        <!--<input id="logout" name="logout" class="powermail_submit btn btn-primary" value="<?php echo __('Abmelden') ?>" type="submit">-->
        <!-- <?php echo(__LINE__); ?> -->
    </div>
</div>
    <?php
}

// Should admin commands be allowed?
$master = ($authorized === 'master');

// Use fake timestamp for testing.
// strtotime understands different datetime formats, but use of ISO 8601 is recommended.
$url_tstamp;
if ($master) {
    $url_tstamp = strtotime(get_parameter('date'));
}


if ($master && $task == 'dump') {
    dump_database();
} elseif ($master && $task == 'init') {
    init_database();
    print('<pre>');
    dump_database();
    print('</pre>');
} elseif ($master && $task == 'phpinfo') {
    phpinfo();
} elseif ($master && $task == 'preset') {
    preset_database();
    print('<pre>');
    dump_database();
    print('</pre>');
} elseif ($master && preg_match('/-report$/', $task)) {
    $param = explode('-', $task);
    if (count($param) == 2) {
        $task_area = $param[0];
        if (array_key_exists($task_area, AREAS)) {
            day_report($task_area);
        } elseif ($task_area == "day") {
            day_report();
        }
    }
} elseif ($authorized) {
    // Show some information for the current uid.
    $usertext = $user['surname'] . ", " . $user['givenname'];
    if (TEST || $master) {
        foreach ($user as $key => $value) {
            $usertext .= "<br/>$key: $value";
        }
    }
    print("<p>" . __('Sie sind angemeldet als') . " $usertext</p>");
    print("<h3>" . __('Meine Sitzplatzbuchungen') . "</h3>");

    // Show all bookings.
    show_database($uid, $lastuid, $user['is_member']);
    if ($email != '') {
        // Send user bookings per e-mail.
        mail_database($user);
    }
} elseif ($uid != '') {
    if ($password == '') {
        print('<p>' . __('Bitte ergaenzen Sie Ihre Anmeldedaten um Ihr Passwort') . '.</p>');
    } else {
        print('<p>' . __('Die Anmeldedaten sind ungueltig. Bitte pruefen Sie Uni-ID und Passwort') . '.</p>');
    }
} else {
    ?>
<div class="powermail_fieldwrap powermail_fieldwrap_type_html powermail_fieldwrap_datenschutz">
    <div class="powermail_field ">
        <div class="powermail_field">
        <div id="conditional-display-1218" class="conditional-display" data-cond-field="" data-cond-value="">
<?php if ($_SESSION['language'] === 'de') { ?>
            <p>Die <a href="https://www.uni-mannheim.de/datenschutzerklaerung/datenschutzinformationen-der-universitaetsbibliothek/" target="_blank">Informationen zum Datenschutz</a> wurden mir zur Verfügung gestellt.</p>
<?php } else { ?>
            <p>The <a href="https://www.uni-mannheim.de/en/data-protection-declaration/data-protection-declaration-of-the-university-library/" target="_blank">privacy information</a> was provided to me.</p>
<?php } ?>
        </div>
    </div>
</div>
    <?php
}
//<button type="reset">Eingaben zurücksetzen</button>

if ($uid == '' || $task == '') {
    if ($authorized) {
        // <button class="submit" type="submit">Eingaben absenden</button>
        ?>
<input type="checkbox" name="email" id="email" value="checked" <?=$email?>/>
<label for="email"><?php echo __('Informieren Sie mich bitte per E-Mail ueber meine aktuellen Sitzplatzbuchungen') ?>.</label>

<div class="powermail_fieldwrap powermail_fieldwrap_type_submit powermail_fieldwrap_absenden nolabel">
    <label for="absenden" class="powermail_label leer"></label>
    <div class="powermail_field">
        <input id="submit" name="submit" class="powermail_submit btn btn-primary" value="<?php echo __('Eingaben absenden') ?>" type="submit">
        <!-- <?php echo(__LINE__); ?> -->
    </div>
</div>
        <?php
    } else {
        // <button class="submit" type="submit">Anmelden</button>
        ?>
<div class="powermail_fieldwrap powermail_fieldwrap_type_submit powermail_fieldwrap_anmelden">
    <div class="powermail_field">
        <input id="L" name="L" type="hidden" value="<?php echo ($_SESSION['language'] === 'de' ? '0' : '1' ) ?>"/>
        <input id="login" name="login" class="powermail_submit" type="submit" value="<?php echo __('Anmelden') ?>">
        <!-- <?php echo(__LINE__); ?> -->
    </div>
</div>
        <?php
    }
    ?>

    <?php
    if ($master) {
        ?>
<h3>Admin-Funktionen</h3>
<p>
<ul>
<li><a href="./?task=dump" target="_blank">Alle Buchungen ausgeben</a>
<li><a href="./?task=day-report" target="_blank">Buchungsübersicht</a>
<?php
foreach (AREAS as $key => $values) {
    print("<li><a href='./?task=$key-report' target='_blank'>Tagesplanung " . $values['name'] . "</a>");
}
?>
</ul>
</p>
        <?php
    }
    ?>

</form>

    <?=HINTS?>
    <?php
}

// Include JS
if (file_exists("$confdir/local.js")) {
    print("<script type='text/javascript' src='.$localdir/local.js'></script>");
}
?>

</body>
</html>
