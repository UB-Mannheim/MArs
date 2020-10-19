<?php

// Gather user information from your local source, e.g. an LDAP server
function get_user_info($uid) {
    global $ldap;
    $ldap = array();
    // curl your ldap server here

    // Map source info to MArs user
    $user = array();
    $user['id'] = $uid;
    $user['mail'] = $ldap['mail'];
    $user['surname'] = $ldap['sn'];
    $user['givenname'] = $ldap['givenName'];
    $user['gender'] = $ldap['rGender'];
    $user['is_member'] = in_array($ldap['gidNumber'], USERGROUPS) ? true : false;
    //// Add all available info to user
    //foreach ($ldap as $key => $value) {
    //    $user['ldap_'.$key] = $value;
    //}
    return $user;
}

// Get authorization from remote LDAP server.
function get_authorization($uid, $password) {
    global $user, $ldap;

    // Maybe check the uid for correct form here?
    // Adapt to your local needs!
    // if (!preg_match('/^[a-z_0-9]{0,8}$/', $uid) {
    //     return false;
    // }

    $authorized = false;
    $user = get_user_info($uid);

    if ($ldap['accountStatus'] == 'active') {
        if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
            return 'master';
        }
        // Use global $ldap here to do e.g. comparison of password hashes.
        // $authorized = ($ldap['pwhashfield'] == md5($password));
        // Pull requests for general examples are welcome!
    }

    return $authorized;
}
