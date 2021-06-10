<pre>
<?php
/**
 * Add payment history for action Bring friend
 */

require '../vendor/init.php';

$date = strtotime('2017-01-16');
for ($i = 0; $i < 10; $i++) {
//    $units = 20 / RATE;
    $units = 3.33;

    echo $db->from('bills_history')->insert(array(
        'login' => 'Universal',
        'date' => $date,
        'qnt' => $units,
        'who' => 'friend',
        'what' => 'payment',
        'comments' => cp2utf('Акция Приведи друга', true),
        'currency_id' => 10,
        'qnt_currency' => $units,
        'real_pay_date' => $date,
        'uid' => 3721,
    ))->execute();

    // +30 days
    $date += 2592000;
}
