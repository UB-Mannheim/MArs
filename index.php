<?php
/*
 * Sitzplatzreservierung für Bibliotheksbereiche
 * 2020-05-30 Prototyp begonnen /sw
 *
 * Datenbankschema:
 * email - e-mail address
 * ecum  - ecUM number
 * text  - reservation information
 * date  - date for reservation
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

// Dump all reservations.
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

function mail_database($uid, $email) {
    if (!function_exists('sendmail')) {
        return;
    }
    // TODO: Add nice intro using name and sex.
    $db = get_database();
    $result = $db->query("SELECT date,text FROM reservierungen where name='$uid' ORDER BY date");
    $mailtext = "date       location\n";
    while ($reservation = $result->fetch_assoc()) {
        $date = $reservation['date'];
        $text = $reservation['text'];
        $mailtext .= "$date $text\n";
    }
    $result->free();
    $db->close();
    // TODO: Optionally pass also pretty name.
    sendmail($uid, $email, $mailtext);
}

// Drop existing table with all reservations and create a new empty one.
function init_database() {
    $db = get_database();
    $result = $db->query("DROP TABLE reservierungen");
    $result = $db->query("CREATE TABLE `reservierungen` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(16) NOT NULL,
        `email` VARCHAR(64) NOT NULL,
        `ecum` VARCHAR(16) NOT NULL,
        `text` VARCHAR(8) NOT NULL,
        `date` DATE NOT NULL,
        CONSTRAINT unique_reservation UNIQUE (date, name),
        PRIMARY KEY(`id`)
    )");
    $db->close();
}

// Add up to 10 random reservations for testing.
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

// Add, modify or delete reservations in the database.
function update_database($uid, $date, $oldvalue, $value) {
    $db = get_database();
    $comment = "";
    $no_reservation = TEXTS[count(TEXTS) - 1][0];
    if ($value == $no_reservation) {
        // Delete reservation.
        $result = $db->query("DELETE FROM reservierungen WHERE name='$uid' AND date='$date'");
        $success = $result ? 'bestätigt' : 'abgelehnt';
        $comment = DEBUG ? "gelöscht: $oldvalue, $success" : "Änderung $success";
    } else {
        // Limit reservations.
        $result = $db->query("SELECT COUNT(*) FROM reservierungen WHERE date='$date' AND text='$value'");
        $count = $result ? $result->fetch_row()[0] : 0;
        $limit = LIMIT[$value];
        if ($count > $limit) {
            $comment = "bereits voll: $value, maximal $limit, abgelehnt";
        } elseif ($oldvalue == $no_reservation) {
            // New reservation.
            $result = $db->query("INSERT INTO reservierungen (name, text, date) VALUES ('$uid','$value','$date')");
            $success = $result ? 'bestätigt' : 'abgelehnt';
            $comment = DEBUG ? "reserviert: $value, $success" : "Änderung $success";
        } else {
            // Modified reservation.
            $result = $db->query("DELETE FROM reservierungen WHERE name='$uid' AND date='$date'");
            $result = $db->query("INSERT INTO reservierungen (name, text, date) VALUES ('$uid','$value','$date')");
            $success = $result ? 'bestätigt' : 'abgelehnt';
            $comment = DEBUG ? "aktualisiert: $oldvalue -> $value, $success" : "Änderung $success";
        }
    }
    $db->close();
    return $comment;
}

// Show stored reservations in a web form which allows modifications.
function show_database($uid, $lastuid) {
    $db = get_database();
    $result = $db->query("SELECT date, text FROM reservierungen WHERE name = '$uid' ORDER BY date");
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    //echo 'row=' . htmlentities($row);
    $db->close();

    $now = time();
    // First day which can be reserved.
    // Accept reservations for same day until 10:00.
    $start = $now + (24 - 10) * 60 * 60;
    $first = $start;
    $last = $now + 24 * 60 * 60 * MAX_DAYS;

    print('<fieldset>');
    print('<legend>Reservierungen</legend>');

    // Get the first reserved day from the reservation list.
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
            if ($day != $resday) {
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

        // Skip days which cannot be reserved.
        $weekday = date('D', $time);
        $closed = false;
        foreach (CLOSED as $condition) {
            $closed = ($weekday == $condition);
            if ($closed) {
                print("<label>$label</label>: $weekday, geschlossen<br/>");
                break;
            }
            $closed = ($day == $condition);
            if ($closed) {
                print("<label>$label</label>: Feiertag, geschlossen<br/>");
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
$ecum = get_parameter('ecum');
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
<?php
if (DEBUG) {
?>
<h2>Sitzplatzreservierung</h2>
<h2>miriam = Mannheim Individual Reservations / Information Assisting Mananger</h2>
<h2><strong>MA</strong><em>RS</em> = MAnnheimer <em>Reservierungs-System</em></h2>
<?php
}
?>
<h2>MA<small>RS</small> = MAnnheimer <small>Reservierungs-System</small></h2>

<p>Hier können Sie Arbeitsplätze in den Bibliotheksbereichen reservieren.</p>

<form id="reservation" method="post">

<fieldset>
<legend>Benutzerdaten</legend>
<label for="email">E-Mail </label><input id="email" name="email" placeholder="name@uni-mannheim.de" type="email" size="30" value="<?=$email?>"/>
<label for="ecum">ecUM </label><input id="ecum" name="ecum" placeholder="1234567890" type="number" value="<?=$ecum?>"/><br/>
<label for="userid">Benutzerkennung </label><input id="userid" name="userid" placeholder="userid" required="required" value="<?=$userid?>"/>
<label for="password">Passwort </label><input id="password" name="password" placeholder="********" type="password" value="<?=$password?>"/>
<input id="lastuid" name="lastuid" type="hidden" value="<?=$userid?>"/>
<input id="md5" name="md5" type="hidden" value="<?=$md5?>"/>
</fieldset>

<?php

if ($userid != '') {
    $authorized = get_authorization($userid, $password);
}

if ($authorized && $task == 'dump') {
    // TODO: check role. Only staff may do this.
    print("<pre>\n");
    dump_database();
    print("<pre>\n");
} elseif ($authorized && $task == 'init') {
    // TODO: check role. Only staff may do this.
    init_database();
    print('<pre>');
    dump_database();
    print('</pre>');
} elseif ($authorized && $task == 'phpinfo') {
    phpinfo();
} elseif ($authorized && $task == 'preset') {
    // TODO: check role. Only staff may do this.
    preset_database();
    print('<pre>');
    dump_database();
    print('</pre>');
} elseif ($authorized) {
    show_database($userid, $lastuid);
    if ($email != '') {
        // Send user reservations per e-mail.
        mail_database($userid, $email);
    }
    ?>
    <p>Mit Klick auf „Eingaben absenden“ bestätigen Sie Ihren Reservierungswunsch.
    Wenn Sie eine E-Mail-Adresse angegeben haben, schicken wir Ihnen eine Bestätigung per E-Mail.
    Wir speichern Ihre Angaben zur Reservierung und löschen sie <?=MAX_AGE?> Tage nach dem jeweiligen Reservierungsdatum.</p>
    <?php
} elseif ($userid != '') {
    if ($password == '') {
        print('<p>Bitte ergänzen Sie Ihre Anmeldedaten um Ihr Passwort.</p>');
    } else {
        print('<p>Die Anmeldedaten sind ungültig. Bitte prüfen Sie Benutzerkennung und Passwort.</p>');
    }
} else {
    ?>
    <p>Mit Klick auf „Eingaben absenden“ prüfen wir Ihre Anmeldedaten und zeigen Ihnen Ihre Reservierung.
    Sie können dann bestehende Reservierungen ändern und neue vormerken.</p>
    <?php
}
//<button type="reset">Eingaben zurücksetzen</button>
?>

<button type="submit">Eingaben absenden</button>

</form>
</body>
</html>
