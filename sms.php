<pre>
<?php
exit;
header('Content-Type: text/html; charset=windows-1251');
include_once 'utm_connect.php';
//include_once 'smsc_api.php';

//list($sms_id, $sms_cnt, $cost, $balance) = send_sms($phone, $text, 1, '3101171600', 0, 0, 'SOHONET');
//$result = send_sms($phone, $text, 1, '3101171600', 0, 0, 'SOHONET');
//$result = get_status(25, $phone);
//var_dump($result);
//
//exit;

$sql = "
SELECT u.id, u.bill, u.tax_number as phones
FROM users AS u
WHERE u.ip LIKE '192.168.31.%'
OR u.ip LIKE '192.168.131.%'
OR u.ip LIKE '192.168.40.%'
OR u.ip LIKE '192.168.4.%'
OR u.ip LIKE '192.168.41.%'
OR u.ip LIKE '192.168.42.%'
OR u.ip LIKE '192.168.43.%'
OR u.ip LIKE '192.168.44.%'
";
//$sql = "
//SELECT u.id, u.bill, u.tax_number as phones, MAX( bs.real_pay_date ) AS pay_date
//FROM users AS u
//INNER JOIN bills_history AS bs ON u.login = bs.login
//WHERE u.block = '1'
//AND u.bill < 0
//AND u.login NOT LIKE 'st-%'
//AND u.tax_number LIKE '%[[%]]%'
//GROUP BY u.login
//HAVING pay_date >1451606400
//AND pay_date <1483228800
//ORDER BY u.bill ASC
//";
$result = mysqli_query($link, $sql);

$countPhones = 0;
while ($row = mysqli_fetch_object($result)) {
    $balance = floor($row->bill * 6);
    if (!$balance) continue;
    preg_match_all('#\[\[([0-9]+)\]\]#', $row->phones, $matches);
    foreach ($matches[1] as $match) {
        $countPhones++;
        $sql = "INSERT INTO users_sms_corolek (user_id, phone) VALUES ($row->id, $match)";
        try {
            if (!mysqli_query($link, $sql)) echo mysqli_error($link), PHP_EOL;
        } catch (Exception $e) {
            var_dump($e);
        }
    }
}

$totalUsers = mysqli_num_rows($result) . PHP_EOL;
echo "Total users: $totalUsers" . PHP_EOL;

echo "Total phones: $countPhones" . PHP_EOL;

$totalAmount = $countPhones * .248;
echo "Total sms amount: $totalAmount UAH" . PHP_EOL;