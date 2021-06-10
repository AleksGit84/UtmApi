<?php
set_time_limit(0);
//error_reporting(E_ALL);

require '../vendor/autoload.php';
require '../vendor/init.php';


Flight::route('/check', function () use ($db) {
    $sql = "
    SELECT *
    FROM users_phone_sms
    WHERE status IN (-1, 0)
    ORDER BY status ASC 
    LIMIT 50
    ";
    $phones = $db->sql($sql)->many();
    if ($db->num_rows) header("Refresh:15");

    echo '<pre>';
    foreach ($phones as $sms) {
        echo "SMS ID: {$sms['id']}" . PHP_EOL;
        $response = get_status($sms['id'], $sms['phone']);
        var_dump($response);
        if (!isset($response[2])) continue;

        list($status, $time, $error) = $response;
        $sql = "UPDATE users_phone_sms SET status = '$status', sent_time = '$time', error_code = '$error' WHERE id = {$sms['id']}";
        try {
            $db->sql($sql)->execute();
            if ($status == 20) {
                $date = date('Y.m.d', $time);

                $sql = "
                UPDATE users
                SET 
                tax_number = REPLACE(tax_number, '[[{$sms['phone']}]]', ''),
                juridical_address = CONCAT('$date [{$sms['phone']}] Невозможно доставить SMS', '\r\n', juridical_address)
                WHERE id = {$sms['user_id']}
                ";
                $db->sql($sql)->execute();
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
});



Flight::route('/send/@year/@month', function ($year, $month) use ($db) {
    if (!in_array($year, array(2015, 2016)) || ($month > 12 || $month < 1)) return;

    $users = $db->sql("
SELECT ug.*, u.tax_number phones
FROM users_gone ug
INNER JOIN users u ON u.id = ug.id
WHERE `year` = $year
AND `month` = $month
AND u.tax_number LIKE '%[[%]]%'
        ")->many();

    $totalUsers = $db->num_rows;

    $month++;
    if ($month > 12) {
        $month = 1;
        $year++;
    }
    header('refresh: 30; url=' . Flight::request()->base . "/send/$year/$month/");

    $countPhones = 0;
    echo '<pre>';
    foreach ($users as $user) {
        $count = $db->from('users_phone_sms')->where('user_id', $user['id'])->count();
        var_dump($count);
        if ($count) continue;

        preg_match_all('#\[\[([0-9]+)\]\]#', $user['phones'], $matches);
        foreach ($matches[1] as $phone) {
            $countPhones++;
            $phone = '+' . $phone;

            $text = "Uv. klient ID{$user['id']}, my razrabotali novyj tarif Lojal'nyj dlja vernuvshihsja abonentov - 100Mb Interneta + TV - vsego za 96 grn/mes. Podrobnosti po t.0487432535";
            sendSms($user['id'], $phone, $text, date('dmy') . '1300');
        }
    }

    echo "Total users: $totalUsers" . PHP_EOL;
    echo "Total phones: $countPhones" . PHP_EOL;

    $totalAmount = $countPhones * .248;
    echo "Total sms amount: $totalAmount UAH" . PHP_EOL;
//    var_dump($db->getStats());
});

Flight::start();