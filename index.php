<?php
/*
 * Sitzplatzreservierung für Bibliotheksbereiche
 * 2020-05-30 Prototyp begonnen /sw
 *
 * Datenbankschema:
 * uid  - user id
 * text - booking information
 * date - date for booking
 *
 * Note: TODO comments mark missing and incomplete code.
 */

// Read configuration for database access.
require_once('config.php');

// Get parameter from web (GET, POST) or command line (environment).
function get_parameter($name, $default='') {
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
function get_database() {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($error = $db->connect_errno) {
        die("Failed to connect to database, $error\n");
    }
    return $db;
}

// Dump all bookings.
function dump_database() {
    $db = get_database();
    $result = $db->query("SELECT date,text,name FROM reservierungen ORDER BY date,name");
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

function mail_database($uid) {
    if (!function_exists('sendmail')) {
        return;
    }
    $db = get_database();
    $result = $db->query("SELECT date,text FROM reservierungen where name='$uid' ORDER BY date");
    $mailtext = "Date       Location\n";
    $today = date('Y-m-d', time());
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        if ($date < $today) {
            // Don't show old bookings.
            continue;
        }
        $text = $reservation['text'];
        // Translate short into long location name.
        foreach (TEXTS as $location) {
            if ($text == $location[0]) {
                $text = $location[1];
                break;
            }
        }
        $mailtext .= "$date $text\n";
    }
    $result->free();
    $db->close();
    sendmail($uid, $mailtext);
}

// Drop existing table with all bookings and create a new empty one.
function init_database() {
    $db = get_database();
    $result = $db->query("DROP TABLE reservierungen");
    $result = $db->query("CREATE TABLE `reservierungen` (
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
function preset_database() {
    $db = get_database();
    $now = time();
    for ($i = 0; $i < 10; $i++) {
        $uid = TEST_USERS[rand(0, count(TEST_USERS) - 1)];
        $text = TEXTS[rand(0, count(TEXTS) - 2)][0];
        $date = date('Y-m-d', $now + 24 * 60 * 60 * rand(0 - MAX_AGE, 7));
        $result = $db->query("INSERT INTO reservierungen (name, text, date) VALUES ('$uid','$text','$date')");
    }
    $db->close();
}

// Add, modify or delete bookings in the database.
function update_database($uid, $date, $oldvalue, $value) {
    $db = get_database();
    $comment = "";
    $no_reservation = TEXTS[count(TEXTS) - 1][0];
    if ($value == $no_reservation) {
        // Delete booking.
        $result = $db->query("DELETE FROM reservierungen WHERE name='$uid' AND date='$date'");
        $success = $result ? 'bestätigt' : 'abgelehnt';
        $comment = DEBUG ? "gelöscht: $oldvalue, $success" : "Änderung $success";
    } else {
        // Limit bookings.
        $result = $db->query("SELECT COUNT(*) FROM reservierungen WHERE date='$date' AND text='$value'");
        $count = $result ? $result->fetch_row()[0] : 0;
        $limit = LIMIT[$value];
        if ($count > $limit) {
            $comment = "bereits voll: $value, maximal $limit, abgelehnt";
        } elseif ($oldvalue == $no_reservation) {
            // New bookings.
            $result = $db->query("INSERT INTO reservierungen (name, text, date) VALUES ('$uid','$value','$date')");
            $success = $result ? 'bestätigt' : 'abgelehnt';
            $comment = DEBUG ? "reserviert: $value, $success" : "Änderung $success";
        } else {
            // Modified booking.
            $result = $db->query("DELETE FROM reservierungen WHERE name='$uid' AND date='$date'");
            $result = $db->query("INSERT INTO reservierungen (name, text, date) VALUES ('$uid','$value','$date')");
            $success = $result ? 'bestätigt' : 'abgelehnt';
            $comment = DEBUG ? "aktualisiert: $oldvalue -> $value, $success" : "Änderung $success";
        }
    }
    $db->close();
    return $comment;
}

// Show stored bookings in a web form which allows modifications.
function show_database($uid, $lastuid) {
    $db = get_database();
    $result = $db->query("SELECT date, text FROM reservierungen WHERE name = '$uid' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    //echo 'row=' . htmlentities($row);
    $db->close();

    $now = time();
    // First day which can be booked.
    // Accept bookings for same day until 10:00.
    $start = $now + (24 - 10) * 60 * 60;
    $first = $start;
    $last = $now + 24 * 60 * 60 * MAX_DAYS;

    $today = date('Y-m-d', $now);

    print('<fieldset>');
    print('<legend>Buchungen / bookings</legend>');

    // Get the first reserved day from the booking list.
    $i = 0;
    $resday = '';
    if ($i < count($reservations)) {
        $resday = $reservations[$i]['date'];
        $time = strtotime($resday);
        if ($time < $first) {
            $first = $time;
            $day = date('Y-m-d', $time);
        }
    }

    for ($time = $first; $time < $last; $time += 24 * 60 * 60) {
        $disabled = '';
        $day = date('Y-m-d', $time);
        $text = 'no';
        if ($time < $start) {
            if ($day < $today && $day != $resday) {
                continue;
            }
            $disabled = ' disabled';
        }
        $label = date('d.m', $time);

        if ($i < count($reservations) && $day == $resday) {
            $text = $reservations[$i]['text'];
            $i++;
            if ($i < count($reservations)) {
                $resday = $reservations[$i]['date'];
            }
        }

        if ($day < $today) {
            // Skip old bookings.
            continue;
        }

        if ($uid == 'stweil') {
//~             print("day=$day\n<br/>");
        }
        // Skip days which cannot be booked.
        $weekday = date('D', $time);
        $closed = false;
        foreach (CLOSED as $condition) {
            $closed = ($weekday == $condition);
            if ($closed) {
                print("<label>$label</label>: Wochenende, geschlossen / week end, closed<br/>");
                break;
            }
            $closed = ($day == $condition);
            if ($closed) {
                print("<label>$label</label>: Feiertag, geschlossen / public holiday, closed<br/>");
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

        print("<label>$label</label>: ");
        foreach (TEXTS as $entry) {
            $value = $entry[0];
            $label = $entry[1];
            $id = "$value-$day";
            $checked = ($text == $value) ? ' checked' : '';
            print("<input type=\"radio\" name=\"$name\" id=\"$id\" value=\"$value\"$checked$disabled/>" .
                "<label for=\"$id\">$label</label>");
        }
        if ($comment != '') {
            $comment = " ($comment)";
        }
        print("$comment<br>\n");
    }
    print('</fieldset>');
}

// Get form values from input (normally POST) if available.
$task = get_parameter('task');
$email = get_parameter('email');
$userid = get_parameter('userid');
$lastuid = get_parameter('lastuid');
$password = get_parameter('password');
$md5 = get_parameter('md5');

// Is there a username with valid password?
$authorized = false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Mannheimer Reservierungssystem</title>
<link rel="stylesheet" type="text/css" href="mars.css" media="all">
</head>
<body>
<h2>♂ MA<small>RS</small> = MAnnheimer <small>Reservierungs-System TESTVERSION</small></h2>

<p>Hier können Sie Arbeitsplätze in den Bibliotheksbereichen TESTWEISE buchen. /<br/>
Here you can book seats in the library branches.</p>

<form id="reservation" method="post">

<fieldset>
<legend>Benutzerdaten / personal data</legend>
<label for="userid">Benutzerkennung </label><input id="userid" name="userid" placeholder="userid" required="required" value="<?=$userid?>"/>
<label for="password">Passwort </label><input id="password" name="password" placeholder="********" required="required" type="password" value="<?=$password?>"/>
<input id="lastuid" name="lastuid" type="hidden" value="<?=$userid?>"/>
<input id="md5" name="md5" type="hidden" value="<?=$md5?>"/>
</fieldset>

<?php

if ($userid != '') {
    $authorized = get_authorization($userid, $password);
}

// TODO: check role. Only staff may do this.
$master = ($authorized && $authorized == 'master');

if ($master && $task == 'dump') {
    print("<pre>\n");
    dump_database();
    print("<pre>\n");
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
    show_database($userid, $lastuid);
    if ($email != '') {
        // Send user bookings per e-mail.
        mail_database($userid);
    }
    ?>
    <p>Mit Klick auf „Eingaben absenden“ bestätigen Sie Ihren Buchungswunsch.
    Wenn gewünscht, senden wir Ihnen auch eine E-Mail mit Ihren vorgemerkten Buchungen.
    Wir speichern Ihre Angaben zur Buchung und löschen sie <?=MAX_AGE?> Tage nach dem jeweiligen Buchungsdatum.</p>
    <p>Achtung TESTVERSION! Hier buchen Sie nur zu Testzwecken!</p>
    <?php
} elseif ($userid != '') {
    if ($password == '') {
        print('<p>Bitte ergänzen Sie Ihre Anmeldedaten um Ihr Passwort.</p>');
    } else {
        print('<p>Die Anmeldedaten sind ungültig. Bitte prüfen Sie Benutzerkennung und Passwort.</p>');
    }
} else {
    ?>
    <p>Mit Klick auf „Eingaben absenden“ prüfen wir Ihre Anmeldedaten und zeigen Ihnen Ihre Buchung.
    Sie können dann bestehende Buchungen ändern und neue vormerken.</p>
    <?php
}
//<button type="reset">Eingaben zurücksetzen</button>
?>

<p>
<input type="checkbox" name="dsgvo" id="dsgvo" required="required" value="checked"/>
<label for="dsgvo">
Ich habe diesen Hinweis und die <a href="/datenschutzerklaerung/">Datenschutzerklärung</a> zur Kenntnis genommen /
I have read the above hint and the <a href="/en/privacy-policy/">privacy policy</a>
</label>
</p>

<button type="submit">Eingaben absenden</button>
<input type="checkbox" name="email" id="email" value="checked" <?=$email?>/>
<label for="email">E-Mail senden / send e-mail</label>

<?php
if ($master) {
?>
<p>
Admin-Funktionen:
<button>dump</button>
<button>test</button>
</p>
<?php
}
?>

</form>
</body>
</html>
