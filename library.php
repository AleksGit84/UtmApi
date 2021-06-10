<?php

function sendSms($user, $phone, $text, $date) {
    global $link;

    $len = strlen($text);
    echo $phone . PHP_EOL;
    echo $text . PHP_EOL;
    echo "Message length: $len symbols" . PHP_EOL;

    list($sms_id, $sms_cnt, $cost, $balance) = send_sms($phone, $text, 1, $date, 0, 0, 'SOHONET.UA');
    var_dump(array($sms_id, $sms_cnt, $cost, $balance));
    if ($sms_cnt > 0 && $sms_id) {
        $sql = "
INSERT INTO users_sms_corolek
SET
user_id = $user,
phone = $phone,
sms_id = $sms_id,
sms_cost = $cost
";
        try {
            if (!mysqli_query($link, $sql)) echo mysqli_error($link), PHP_EOL;
        } catch (Exception $e) {
            var_dump($e);
        }
    }
}

