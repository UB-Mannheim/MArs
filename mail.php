<?php

require_once 'Swift/swift_required.php';

// Mail implementation using Swift Mailer.
// See documentation: https://swiftmailer.symfony.com/docs/introduction.html
function sendmail($uid, $text) {
    // TODO: Add nice intro using name and sex.
    // TODO: Optionally pass also pretty name.
    global $ldap;

    $to = $ldap['mailRoutingAddress'];
    if ($to == '') {
        $to = $ldap['mail'];
    }
    if ($to == '') {
        alert("Keine E-Mail-Adresse für $uid gefunden");
        return;
    }

    $text = "Diese Buchungen sind für die Benutzerkennung $uid vorgemerkt:\n\n$text";
    // TODO: URL für Produktivbetrieb aktualisieren.
    $text = "$text\nVerwalten Sie Ihre Buchungen hier: https://www.bib.uni-mannheim.de/reservation/?uid=$uid\n";
    $text = "$text\nMit freundlichen Grüssen,\nIhre Universitaetsbibliothek";

    // Anrede.
    $sn = $ldap['sn'];
    if ($ldap['rGender'] == 'M') {
        $text = "Sie haben über unser Buchungssystem eine E-Mail-Benachrichtigung angefordert.\n\n$text";
        $text = "Sehr geehrter Herr $sn,\n\n$text";
    } elseif ($ldap['rGender'] == 'W') {
        $text = "Sie haben über unser Buchungssystem eine E-Mail-Benachrichtigung angefordert.\n\n$text";
        $text = "Sehr geehrte Frau $sn,\n\n$text";
    }

    // Sendmail for transport.
    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    // TODO: Define from e-mail address.
    $from = ['stefan.weil@bib.uni-mannheim.de' => 'Stefan Weil'];

    // Create a message
    // TODO: Which subject?
    $message = (new Swift_Message('UB Mannheim - Sitzplatzbuchungen'))
        ->setFrom($from)
        // TODO: to wahlweise auch mit Name: [mail => name].
        ->setTo($to)
        ->setBody($text)
    ;

    // Send the message
    $result = $mailer->send($message);
}
