<?php

// Settings for seat reservation.
//
// Copy this template to config.php and update it to your local needs.

// Optionally enable debug output.
define('DEBUG', false);

// Database host.
define('DB_HOST', 'localhost');

// Name of database.
define('DB_NAME', 'sitzplatzreservierung');

// Name of database user.
define('DB_USER', 'sitzplatzreservierung');

// Password of database user.
define('DB_PASS', 'mysecret');

// Delete reservations older than $MAX_AGE days.
define('MAX_AGE', 14);
// Allow reservations for the next MAX_DAYS days.
define('MAX_DAYS', 14);

// Short and long texts for reservations.
define('TEXTS', [
    ['a3', 'Bibliotheksbereich A3'],
    ['eh', 'Bibliotheksbereich Ehrenhof'],
    ['no', 'keine Reservierung']
]);

define('LIMIT', [
    'a3' => 50,
    'eh' => 70,
]);

// Test users.
define('TEST_USERS', [
    'testuser1',
    'testuser2',
    'testuser2',
]);

// MAGIC_PASSWORD is used as a master password if it is defined.
define('MAGIC_PASSWORD', 'mysecretpassword');

define('CLOSED', [
    'Sat', 'Sun',
    '2020-05-31',
    '2020-06-01',
    '2020-06-11',
]);

// Get authorization from remote LDAP server.
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return true;
    }
    return $uid == $password;
}

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
