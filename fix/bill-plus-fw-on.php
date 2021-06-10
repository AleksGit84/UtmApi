<?php
require '/var/www/vendor/sparrow.php';
$db = new Sparrow();
$db->setDb('mysql://svit:mq3vdqebKDPNC9A8@192.168.11.5/UTM');

$users = $db
    ->from('users')
    ->where("fw_on = '0' AND tariff != 40 AND bill > 0 AND ip !=  ''")
    ->limit(100)
    ->many();

foreach ($users as $user) {
    //echo $user['id'] . ' ' . date('Y-m-d H:i') . PHP_EOL;

    if($user['id']) {
        $bill = $db
            ->from('bills_history')
            ->where("uid = {$user['id']}")
            ->sortDesc('date')
            ->limit(1)
            ->one();


        if($bill) {
            if(date('Y-m-d', $bill['date']) == date('Y-m-d')) {
                echo $user['id'] . ' ' . date('Y-m-d H:i') . PHP_EOL;
                userOn($user);
            }
        }

        //userOn($user);
    }
}


function userOn($user) {
    global $db;

    if ($user['bill'] > 0) {
        if ($user['block']) {
            $db->sql("UPDATE users SET `block` = '0' WHERE id = {$user['id']}")->execute()
            || exit('User unblock error');
        }

        if (!$user['fw_on']) {

            $arrContextOptions=array(
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ),
            );

            $db->sql("UPDATE users SET `fw_on` = '1' WHERE id = {$user['id']}")->execute() || exit('User update internet status error');

            $response = file_get_contents("https://ss.sohonet.ua/scripts/fwon.php?id={$user['id']}&ip=NA",false, stream_context_create($arrContextOptions));
            $user['fw_on_response'] = var_export($response, true);
            if ($response == 'ok') {
                $db->sql("UPDATE users SET `fw_on` = '1' WHERE id = {$user['id']}")->execute()
                || exit('User update internet status error');
            }
        }

        return true;
    }

    return false;
}