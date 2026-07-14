<?php
/** db.php — Firebase client factory. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebaseRDB.php';

function getDB(): firebaseRDB {
    global $databaseURL;
    return new firebaseRDB($databaseURL);
}
