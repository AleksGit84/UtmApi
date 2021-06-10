<?php
require '../vendor/init.php';


$uid = $argv[1];
$action = $argv[2];


if($uid) {
    if($action == 'on') userOn($uid);
    if($action == 'off') userOff($uid);
}

