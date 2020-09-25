<?php

// MAGIC_PASSWORD is used as a master password if it is defined.
// The master password allows viewing and changing bookings of any user
// and can give access to additional functionality like reports.
define('MAGIC_PASSWORD', 'mysecretpassword');

// The global array `$ldap` is used to hold user specific information.
// That information can come from LDAP or other sources.
$ldap = array();

// The following functions should be adopted to the local requirements.

// Fill $ldap array with user specific information.
function get_ldap($uid, &$ldap) {
    // The following information is directly extracted from LDAP.
    $ldap = array();
    $ldap['sn'] = '';
    $ldap['givenName'] = '';
    $ldap['mail'] = '';
}

// Get authorization (for example from LDAP server).
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return 'master';
    }
    // For the demo we just allow any uid with a matching password.
    return $uid == $password;
}

// TODO: move somewhere more fitting
// Return full name of user for display in the web gui.
function get_usertext() {
    global $ldap, $master;
    $sn = $ldap['sn'];
    $givenName = $ldap['givenName'];
    $usertext = "$sn, $givenName";
    return $usertext;
}
