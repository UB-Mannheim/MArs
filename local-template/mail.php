<?php

require_once 'Swift/swift_required.php';

// Mail implementation using Swift Mailer.
// See documentation: https://swiftmailer.symfony.com/docs/introduction.html
function sendmail($uid, $text) {
    $text = "Diese Reservierungen sind für die Benutzerkennung $uid vorgemerkt:\n\nDatum      Bibliotheksbereich\n" . $text;

    // Sendmail for transport.
    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    $from = ['user@example.org' => 'Demo Sender'];
    $to = $ldap['mail'];

    // Create a message
    $message = (new Swift_Message('Sitzplatzreservierung'))
        ->setFrom($from)
        ->setTo($to)
        ->setBody($text)
    ;

    // Send the message
    $result = $mailer->send($message);
}

function send_staff_mail() {
    $today = date('Y-m-d');
    $db = get_database();
    $table = DB_TABLE;

    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');
    $mailer = new Swift_Mailer($transport);

    $from = ['user@example.org' => 'Demo Sender'];
    $to = 'resu@example.org';
    $subject = "Reservierungen für $today";
    $text = "Sehr geehrte Damen und Herren,\n\n";
    $text .= "anbei finden Sie die heutigen Sitzplatzreservierungen.\n\n";
    $text .= "Mit freundlichen Grüßen,\nIhre Universitätsbibliothek";

    $message = (new Swift_Message($subject))
        ->setFrom($from)
        ->setTo($to)
        ->setBody($text)
    ;

    foreach (AREAS as $location => $values) {
        $longname = $values['name'];
        $result = $db->query("SELECT name FROM $table WHERE date = '$today' AND text = '$location'");
        $reservations = $result->fetch_all();
        $result->free();
        $report = "Tagesliste für $longname am $today\n\n";

        if (count($reservations) > 0) {
            $names = array();
            foreach ($reservations as $row) {
                get_ldap($row[0], $ldap);
                $givenName = $ldap['givenName'];
                $sn = $ldap['sn'];
                $fullname = "$sn, $givenName";
                $names[] = $fullname;
            }
            sort($names);
            foreach ($names as $nr => $name) {
                $report .= ++$nr . " - " . $name . "\n";
            }
        } else {
            $report .= "Keine Buchungen vorhanden.\n";
        }

        $message->attach(
            Swift_Attachment::newInstance($report, "$today\_$location.txt", "plain/text")
        );
    }
    $db->close();
    $result = $mailer->send($message);
}
