<?php

require_once 'Swift/swift_required.php';

// Mail implementation using Swift Mailer.
// See documentation: https://swiftmailer.symfony.com/docs/introduction.html
function sendmail($uid, $email, $text) {
    $text = "Diese Reservierungen sind für die Benutzerkennung $uid vorgemerkt:\n\n" . $text;

    // Sendmail for transport.
    $transport = new Swift_SendmailTransport('/usr/sbin/sendmail -bs');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    $from = ['user@example.org' => 'Demo Sender'];

    // Create a message
    $message = (new Swift_Message('Sitzplatzreservierung'))
        ->setFrom($from)
        ->setTo($email)
        ->setBody($text)
    ;

    // Send the message
    $result = $mailer->send($message);
}
