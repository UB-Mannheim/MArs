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

// Read configuration for database access.
require_once 'config.php';

function alert($text)
{
    print("<script>alert('$text');</script>");
}

function trace($text)
{
    global $uid;
    if ($uid == 'stweil') {
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
    return $parameter;
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
    print("date       pl uid\n");
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        $text = $reservation['text'];
        $uid = $reservation['name'];
        print("$date $text $uid\n");
    }
    $result->free();
    $db->close();
}

function mail_database($uid)
{
    if (!function_exists('sendmail')) {
        return;
    }
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("SELECT date,text FROM $table where name='$uid' ORDER BY date");
    $mailtext = "Datum      Bibliotheksbereich\n";
    $today = date('Y-m-d', time());
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        if ($date < $today) {
            // Don't show old bookings.
            continue;
        }
        // Convert date representation.
        $date = date('d.m.Y', strtotime($date));
        // Translate short into long location name.
        $text = TEXTS[$reservation['text']];
        $mailtext .= "$date $text\n";
    }
    $result->free();
    $db->close();
    sendmail($uid, $mailtext);
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
        `text` VARCHAR(8) NOT NULL,
        `date` DATE NOT NULL,
        CONSTRAINT unique_reservation UNIQUE (date, name),
        PRIMARY KEY(`id`)
    )");
    $db->close();
}

// Add up to 10 random bookings for testing.
function preset_database()
{
    $db = get_database();
    $table = DB_TABLE;
    $now = time();
    for ($i = 0; $i < 10; $i++) {
        $uid = TEST_USERS[rand(0, count(TEST_USERS) - 1)];
        // TODO: Fixme.
        $text = TEXTS[rand(0, count(TEXTS) - 2)][0];
        $date = date('Y-m-d', $now + 24 * 60 * 60 * rand(0 - MAX_AGE, 7));
        $result = $db->query("INSERT INTO $table (name, text, date) VALUES ('$uid','$text','$date')");
    }
    $db->close();
}

// Add, modify or delete bookings in the database.
function update_database($uid, $date, $oldvalue, $value)
{
    $db = get_database();
    $table = DB_TABLE;
    $comment = "";
    $no_reservation = 'no';
    $success_text = '<span class="success">Aktion erfolgreich</span>';
    $failure_text = '<span class="failure">Aktion nicht erfolgreich</span>';
    if ($value == $no_reservation) {
        // Delete booking.
        $result = $db->query("DELETE FROM $table WHERE name='$uid' AND date='$date'");
        $success = $result ? $success_text : $failure_text;
        $comment = DEBUG ? "gelöscht: $oldvalue, $success" : $success;
    } else {
        // Limit bookings.
        $result = $db->query("SELECT COUNT(*) FROM $table WHERE date='$date' AND text='$value'");
        $count = $result ? $result->fetch_row()[0] : 0;
        $limit = LIMIT[$value];
        $today = date('Y-m-d', time());
        $result = $db->query("SELECT COUNT(*) FROM $table WHERE date>='$today' AND name='$uid'");
        $personal_bookings = $result ? $result->fetch_row()[0] : 999;
        if ($count >= $limit) {
            $comment = '<span class="failure">Bibliotheksbereich ausgebucht</span>';
        } elseif ($oldvalue == $no_reservation) {
            // New bookings.
            if ($personal_bookings >= PERSONAL_LIMIT) {
                $comment = '<span class="failure">Persönliches Buchungslimit erreicht</span>';
            } else {
                $result = $db->query("INSERT INTO $table (name, text, date) VALUES ('$uid','$value','$date')");
                $success = $result ? $success_text : $failure_text;
                $comment = DEBUG ? "reserviert: $value, $success" : $success;
            }
        } else {
            // Modified booking.
            if ($personal_bookings > PERSONAL_LIMIT) {
                $comment = '<span class="failure">Persönliches Buchungslimit erreicht</span>';
            } else {
                $result = $db->query("DELETE FROM $table WHERE name='$uid' AND date='$date'");
                $result = $db->query("INSERT INTO $table (name, text, date) VALUES ('$uid','$value','$date')");
                $success = $result ? $success_text : $failure_text;
                $comment = DEBUG ? "aktualisiert: $oldvalue -> $value, $success" : $success;
            }
        }
    }
    $db->close();
    return $comment;
}

// Show stored bookings in a web form which allows modifications.
function show_database($uid, $lastuid)
{
    $db = get_database();
    $table = DB_TABLE;
    $result = $db->query("SELECT date, text FROM $table WHERE name = '$uid' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    //echo 'row=' . htmlentities($row);
    $db->close();

    // Get current time.
    $now = time();

    // First day which can be booked (time rounded to start of day).
    // Accept bookings for same day until 10:00.
    $start = $now + (24 - 10) * 60 * 60;
    $start = strtotime(date('Y-m-d', $start));

    // Round current time to start of day.
    $now = strtotime(date('Y-m-d', $now));

    // Last day which may be booked (time rounded to start of day).
    $last = $now + 24 * 60 * 60 * MAX_DAYS;

    // First day which will be shown.
    $first = $now;

    print('<fieldset>');
    print('<legend>Buchungen / bookings</legend>');

    // Get the first reserved day from the booking list.
    $i = 0;

    for ($time = $first; $time < $last; $time += 24 * 60 * 60) {
        $disabled = '';
        $day = date('Y-m-d', $time);
        $text = 'no';
        if ($time < $start) {
            $disabled = ' disabled';
        }

        $label = date('d.m.', $time);
        $label = "<label class=\"day\">$label</label>";

        $resday = '';
        while ($i < count($reservations) && ($resday = $reservations[$i]['date']) < $day) {
            $i++;
        }

        if ($i < count($reservations)) {
            $text = $reservations[$i]['text'];
        }
        if ($resday != $day) {
            $text = 'no';
        }

        // Skip days which cannot be booked.
        $weekday = date('D', $time);
        $label = "<label><span class=\"weekday\">$weekday</span> $label</label>";
        $closed = false;
        foreach (CLOSED as $condition) {
            $closed = ($weekday == $condition);
            if ($closed) {
                print("<div class=\"closed\">$label geschlossen / closed</div>");
                break;
            }
            $closed = ($day == $condition);
            if ($closed) {
                print("<div class=\"closed\">$label geschlossen / closed</div>");
                break;
            }
        }
        if ($closed) {
            continue;
        }

        $name = "choice-$day";
        $requested = get_parameter($name, 'no');
        $comment = '';
        if ($time < $start) {
            $comment = DEBUG ? 'nicht änderbar' : '';
        } elseif ($uid != $lastuid) {
            // Initial call with the current user id.
            $comment = DEBUG ? 'änderbar' : '';
        } elseif ($text == $requested) {
            $comment = DEBUG ? 'unverändert' : '';
        } else {
            $comment = update_database($uid, $day, $text, $requested);
            $text = $requested;
        }

        $line = '';
        foreach (TEXTS as $value => $longname) {
            $id = "$value-$day";
            $checked = ($text == $value) ? ' checked' : '';
            $line .= "<input type=\"radio\" name=\"$name\" id=\"$id\" value=\"$value\"$checked$disabled/>" .
                "<label class=\"$value\" for=\"$id\">$longname</label>";
        }
        if ($comment != '') {
            $comment = " $comment";
        }
        print("<div class=\"open\">$label$line$comment</div>\n");
    }
    print('</fieldset>');
}

// Daily report for a location.
function day_report($location = false)
{
    $now = time();
    $today = date('Y-m-d', $now);

    $db = get_database();
    $table = DB_TABLE;

    if (!$location) {
        // Summary of daily bookings per location.
        $result = $db->query("SELECT date, text, COUNT(text) AS n FROM seatbookings GROUP BY date, text");
        $reservations = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        $db->close();
        print("<h2>MARS Buchungsübersicht</h2>\n");
        print("<pre>\n");
        print("Datum      Bibliotheksbereich                  Buchungen\n");
        foreach ($reservations as $booking) {
            $date = $booking['date'];
            $location = $booking['text'];
            $count = $booking['n'];
            $longname = TEXTS[$location];
            printf("%s %-36s%9d\n", $date, $longname, $count);
        }
        print("</pre>\n");
        return;
    }

    $result = $db->query("SELECT date, text, name FROM $table WHERE date = '$today' AND text = '$location' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $db->close();

    $date = $today;
    $longname = TEXTS[$location];
    print("<h2>MARS Tagesbericht $date $longname</h2>\n");
    print("<pre>\n");
    print("Nr. Datum      Bibliotheksbereich                  Kennung   Name\n");

    // Get all full names from LDAP and sort them.
    $names = array();
    foreach ($reservations as $booking) {
        $name = $booking['name'];
        get_ldap($name, $ldap);
        $givenName = $ldap['givenName'];
        $sn = $ldap['sn'];
        $fullname = "$sn, $givenName";
        $names[$fullname] = $name;
    }
    ksort($names);

    // Generate report.
    $nr = 0;
    foreach ($names as $fullname => $name) {
        $nr++;
        printf("%3d %s %-36s%-10s%s\n", $nr, $date, $longname, $name, $fullname);
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

if (!preg_match('/^[a-z_0-9]{0,8}$/', $uid)) {
    // uid is longer than 8 characters or contains invalid characters.
    alert("Ungültige Universitätskennung");
    $uid = '';
}

// Is there a username with valid password?
$authorized = false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>UB Sitzplatzbuchung</title>
<link rel="stylesheet" type="text/css" href="mars.css" media="all">
</head>
<body>
<?php
if ($uid != '') {
    $authorized = get_authorization($uid, $password);
}

if ($uid == '' || $task == '') {
    $pw = htmlspecialchars($password);
    ?>
<form id="reservation" method="post">

<fieldset class="personaldata">
<legend>Benutzerdaten / personal data</legend>
<label class="uid" for="uid">Universitätskennung:*</label>
<input class="uid" id="uid" name="uid" placeholder="user id" maxlength="8"
  pattern="^([a-z_0-9]{0,8})$" required="required" value="<?=$uid?>"/>
<label class="password" for="password">Passwort:*</label><input id="password" name="password" placeholder="********" required="required" type="password" value="<?=$pw?>"/>
<input id="lastuid" name="lastuid" type="hidden" value="<?=$authorized ? $uid : ''?>"/>
</fieldset>
    <?php
}

if ($authorized && $task == '') {
    ?>
<button class="logout" type="button"><a class="logout" href=".">Abmelden / Logout</a></button>
    <?php
}

// Should admin commands be allowed?
$master = ($authorized === 'master');

if ($master && $task == 'dump') {
    print("<pre>\n");
    dump_database();
    print("<pre>\n");
} elseif ($master && $task == 'a3-report') {
    day_report('a3');
} elseif ($master && $task == 'eh-report') {
    day_report('eh');
} elseif ($master && $task == 'day-report') {
    day_report();
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
} elseif ($authorized) {
    // Show some information for the current uid.
    $usertext = get_usertext();
    print("<p>$usertext</p>");
    print("<h3>Meine Sitzplatzbuchungen / My seat bookings</h3>");
    // Show all bookings.
    show_database($uid, $lastuid);
    if ($email != '') {
        // Send user bookings per e-mail.
        mail_database($uid);
    }
} elseif ($uid != '') {
    if ($password == '') {
        print('<p>Bitte ergänzen Sie Ihre Anmeldedaten um Ihr Passwort.</p>');
    } else {
        print('<p>Die Anmeldedaten sind ungültig. Bitte prüfen Sie Universitätskennung und Passwort.</p>');
    }
} else {
    ?>
    <p>Die <a href="/datenschutzerklaerung/" target="_blank">Informationen zum Datenschutz</a> wurden mir zur Verfügung gestellt.<br/>
    The <a href="/en/privacy-policy/" target="_blank">privacy information</a> was provided to me.</p>
    <?php
}
//<button type="reset">Eingaben zurücksetzen</button>

if ($uid == '' || $task == '') {
    if ($authorized) {
        ?>
<button class="submit" type="submit">Eingaben absenden</button>
<br/>
<input type="checkbox" name="email" id="email" value="checked" <?=$email?>/>
<label for="email">Informieren Sie mich bitte per E-Mail über meine aktuellen Sitzplatzbuchungen.
Please inform me by e-mail about my current bookings.</label>
        <?php
    } else {
        ?>
<button class="submit" type="submit">Anmelden</button>
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
<li><a href="./?task=a3-report" target="_blank">Tagesplanung A3</a>
<li><a href="./?task=eh-report" target="_blank">Tagesplanung Schloss Ehrenhof</a>
</ul>
</p>
        <?php
    }
    ?>

</form>

    <?=HINTS?>
    <?php
}
?>

<script>
    // Fix height of iframe.
    let iFrame = window.parent.document.getElementById("seatbooking");
    if (iFrame) {
        let iFrameDocument = iFrame.contentWindow.document;
        iFrame.height = iFrame.contentWindow.document.body.scrollHeight + 20;
    }
</script>

</body>
</html>
