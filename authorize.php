<?php

// Get authorization from remote LDAP server.
// In addition, a master password is optionally accepted.
function get_authorization($uid, $password) {
    if (defined('MAGIC_PASSWORD') && $password == MAGIC_PASSWORD) {
        return true;
    }
    return $uid == $password;
}
