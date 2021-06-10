<pre>
<?php
set_time_limit(0);
//exit;
header('Content-Type: text/html; charset=windows-1251');

include_once 'utm_connect.php';
include_once 'smsc_api.php';
include_once 'library.php';

$daysNum = 3;

$period = time() + 60 * 60 * 24 * $daysNum;
$start = strtotime('midnight', $period);
$end = strtotime('tomorrow', $period);

$sql = "
SELECT u.id, u.tariff, u.bill, (ps.price * pst.qnt) AS price_tariff, SUM(pse.price * psu.qnt) AS price_extra, u.tax_number
FROM users u
INNER JOIN products_services_tariffs pst ON pst.tariff_id = u.tariff AND u.tariff IN (70010, 70025, 70005, 70001, 75090, 71090, 60365) 
LEFT JOIN products_services ps ON pst.prod_code = ps.id

LEFT JOIN products_services_users psu ON psu.uid = u.id
LEFT JOIN products_services pse ON psu.prod_code = pse.id

WHERE $start <= ab_pend
AND ab_pend < $end
AND `block` = '0'
AND tax_number LIKE '%[[%]]%'
GROUP BY u.id
HAVING u.bill < (price_tariff + price_extra)
";
$result = mysqli_query($link, $sql);
echo mysqli_error($link);

//if (mysqli_num_rows($result)) header("Refresh:1");

//echo $sql;
//exit;
$countPhones = 0;
while ($row = mysqli_fetch_object($result)) {
//    echo PHP_EOL;
//    echo "Price tariff: $row->price_tariff", PHP_EOL;
//    echo "Price extra: $row->price_extra", PHP_EOL;
//    echo "Balance: $row->bill", PHP_EOL;

    $amount = 6 * ceil($row->price_tariff + $row->price_extra - $row->bill);

    preg_match_all('#\[\[([0-9]+)\]\]#', $row->tax_number, $matches);
    foreach ($matches[1] as $phone) {
        $countPhones++;
        $phone = '+' . $phone;

        $text = "Uvazhaemyj abonent ID{$row->id}. Napominaem, cherez 3 dnja okonchitsja oplachennyj period uslug interneta. Rekomenduem zaranee popolnit' schet na summu ot {$amount} grn";
        sendSms($row->id, $phone, $text, date('dmy') . '1100');

        $text = "Popolnenie vaucherami bez komissii. Adres prodazhi: Nevskogo 57 — Magazin SOHO; Zhukova 47 — ostanovka Aviazavod; Glushko 16 — knizhnyj rynok Magazin Shkoljarik";
        sendSms($row->id, $phone, $text, date('dmy') . '1200');
    }
}

$totalUsers = mysqli_num_rows($result) . PHP_EOL;
echo "Total users: $totalUsers" . PHP_EOL;

echo "Total phones: $countPhones" . PHP_EOL;

$totalAmount = $countPhones * .248;
echo "Total sms amount: $totalAmount UAH" . PHP_EOL;



//    $text = "Uvazhaemyj abonent ID $row->user_id! Informiruem Vas o zadolzhennosti po oplate uslug Internet za 2016 v razmere $balance grn. Îplatitå vo izbezhanie proceduri vzyskanija";
//    $text = "Vnimanie! Personal'naja akcija dlja abonenta ID$row->user_id! Popolnite schet i poluchite mesjac v podarok. Obratites' k menedzheru za spisaniem dolga! Tel. 0487432535";
//    $text = "Yvazhaemiy abonent! Provodyatsya avariyniye raboti po vosstanovleniyu uslug Internet i IPTV po vashemu adresu. Vremya otsutstviya svyazi budet kompensirovano.";

