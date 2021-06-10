<?php
if (!in_array(substr($_SERVER["REMOTE_ADDR"], 0, 11), array('192.168.211', '192.168.11.'))) exit;

$link = mysqli_connect('192.168.11.5', 'vvolf', 'jOwcKh0Rxe', 'UTM');
//mysqli_query($link, "SET NAMES 'cp1251'");
echo mysqli_error($link), PHP_EOL;

