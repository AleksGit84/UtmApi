<?php

//if (!isAuthorized()) exit();

define('RATE', 6);

require 'sparrow.php';
require '../smsc_api.php';

$db = new Sparrow();

$db->setDb('mysql://svit:mq3vdqebKDPNC9A8@192.168.11.5/UTM');
//$db->stats_enabled = true;

function getUserField($id) {
    static $field;

    if (!$field) {
        $field = ((string)intval($id, 10) === $id) ? 'id' : 'login';
    }

    return $field;
}

/**
 * @return bool
 */
function isAuthorized() {
    return ($_SERVER["REMOTE_ADDR"] === '195.78.244.238')
        || in_array(substr($_SERVER["REMOTE_ADDR"], 0, 11), array('192.168.211', '192.168.11.'));

//    $headers = getallheaders();
//
//    return empty($headers['Authorization'])
//        ? false
//        : ($headers['Authorization'] === 'Bearer huO789y87GG89GgF78F87F8');
}

function parseIps($str) {
    return preg_split("/[\s]+/", $str, null, PREG_SPLIT_NO_EMPTY);
}

function getUser($id) {
    global $db;

    $field = getUserField($id);
    // get user info
    $user = $db
        ->from('users')
        ->where($field, $id)
        ->select(array(
            'id',
            'login',
            'bill',
            'credit',
            'block',
            'fw_on',
            'tariff',
            'ab_pend',
        ))
        ->one();

    return $user;
}

/**
 * internet ON if balance positive
 * @param $user
 * @return bool
 */
function userOn($user) {
    global $db;

    if (is_scalar($user)) {
        $user = getUser($user);
    }

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


function userHardOn($user) {
    global $db;

    if (is_scalar($user)) {
        $user = getUser($user);
    }

//    if ($user['bill'] > 0) {
        if ($user['block']) {
            $db->sql("UPDATE users SET `block` = '0' WHERE id = {$user['id']}")->execute()
            || exit('User unblock error');
        }
        if (!$user['fw_on']) {


            $db->sql("UPDATE users SET `fw_on` = '1' WHERE id = {$user['id']}")->execute()
                || exit('User update internet status error');


            $arrContextOptions=array(
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ),
            );


            $response = file_get_contents("https://ss.sohonet.ua/scripts/fwon.php?id={$user['id']}&ip=NA",false, stream_context_create($arrContextOptions));
            $user['fw_on_response'] = var_export($response, true);
            if ($response == 'ok') {
                $db->sql("UPDATE users SET `fw_on` = '1' WHERE id = {$user['id']}")->execute()
                || exit('User update internet status error');
            }
        }

        return true;
//    }

//    return false;
}


function userOff($id) {
    global $db;

    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );

    $db->sql("UPDATE users SET `fw_on` = '0' WHERE id = {$id}")->execute()
    || exit('User update internet status error');

    $response = file_get_contents("https://ss.sohonet.ua/scripts/fwon.php?id={$id}&ip=NA&delete",false, stream_context_create($arrContextOptions));


//    $response = file_get_contents("https://ss.sohonet.ua/scripts/fwon.php?id={$id}&ip=NA&delete");

    if ($response == 'ok') {
        $db->sql("UPDATE users SET `fw_on` = '0' WHERE id = {$id}")->execute()
        || exit('User update internet status error');
    }
}

/**
 * @param int|string $id login or ID
 * @param float $units
 * @param string $description
 * @param int $time
 */
function billUser($id, $units, $description, $time = null) {
    global $db;

    $field = getUserField($id);
    $description = empty($description) ? '' : cp2utf($description, true);
    $time = empty($time) ? time() : $time;

    // update balance
    $sql = "UPDATE users SET bill = bill + $units, bill_abs = bill_abs + $units WHERE $field = '$id'";
    $db->sql($sql)->execute() || exit('Update user balance error');

    $user = getUser($id);

    $sql = "
INSERT INTO bills_history
(login, real_pay_date, `date`, qnt_currency, who, what, uid, currency_id, comments, qnt)
VALUES
('{$user['login']}', $time, " . time() . ", $units, 'API Bill', 'payment', {$user['id']}, 10, '$description', $units)
        ";
    $db->sql($sql)->execute() || exit('Update payment history error');

    return $user;
}

function priceTariff($tariff) {
    global $db;

    return $db->sql("
SELECT (ps.price * pst.qnt) AS tariff
FROM products_services_tariffs pst  
LEFT JOIN products_services ps ON pst.prod_code = ps.id
WHERE pst.tariff_id = $tariff
")->value('tariff');
}

function priceExtra($user) {
    global $db;

    return $db->sql("
SELECT SUM(pse.price * psu.qnt) AS extra
FROM products_services_users psu
LEFT JOIN products_services pse ON psu.prod_code = pse.id
WHERE psu.uid = $user
")->value('extra');
}

function servicesExtra($user) {
    global $db;

    $rows = $db->sql("
SELECT prod_code, qnt
FROM products_services_users psu
WHERE psu.uid = $user
AND psu.qnt > 0
")->many();

//    $result = array();
//    foreach ($rows as $row) {
//        $result[] = (int)$row['prod_code'];
//    }

    return $rows;
}

function servicesUserFull($user) {
    global $db;

    $rows = $db->sql("
SELECT prod_code, qnt
FROM products_services_users psu
WHERE psu.uid = $user
")->many();

    return $rows;
}


function tariffDetails($tariff) {
    global $db;

    return $db->sql("
SELECT tid id, `name`, IF(period_type, period_type, 30) period
FROM tariffs_current  
WHERE tid = $tariff
")->one();
}

function getServiceName($id) {
    global $db;
    static $services;

    if (!$services) {
        $result = $db->sql("
        SELECT id, prod_name `name`
        FROM products_services  
        ")->many();

        foreach ($result as $item) {
            $services[(int)$item['id']] = cp2utf($item['name']);
        }
    }

    return isset($services[$id]) ? $services[$id] : null;
}

function getServicePrice($id) {
    global $db;
    static $services;

    if (!$services) {
        $result = $db->sql("
        SELECT id, price, prod_name `name`
        FROM products_services  
        ")->many();

        foreach ($result as $item) {
            $services[(int)$item['id']] = cp2utf($item['price']);
        }
    }

    return isset($services[$id]) ? $services[$id] : null;
}

function cp2utf($value, $revers = false) {
    $convert = function ($value) use ($revers) {
        $in = 'CP1251';
        $out = 'UTF-8';

        if ($revers) {
            $tmp = $in;
            $in = $out;
            $out = $tmp;
        }

        return iconv($in, $out, $value);
    };

    if (is_scalar($value)) return $convert($value);

    foreach ($value as &$field) {
        if ($field) $field = $convert($field);
    }

    return $value;
}

function sendSms($user, $phone, $text, $date) {
    global $db;

    $len = strlen($text);
    echo $phone . PHP_EOL;
    echo $text . PHP_EOL;
    echo "Message length: $len symbols" . PHP_EOL;

    $response = send_sms($phone, $text, 1, $date, 0, 0, 'SOHONET.UA');
    var_dump($response);
    if (!empty($response[2])) {
        list($sms_id, $sms_cnt, $cost) = $response;
        $sql = "
INSERT INTO users_phone_sms
SET
id = $sms_id,
user_id = $user,
phone = $phone,
sms_cost = $cost
";
        $db->sql($sql)->execute();
    }
}

function getBlade($ip)
{
    $octets = explode('.', $ip);

    $blades = ($octets[1] == 212)
        ? array(
            '195.78.244.70' => array(192, 193, 194, 195), // 9
            '195.78.244.71' => array(196, 197, 198, 199), // 10
        )
        : array(
            '192.168.11.21' => array(20, 21, 22, 14, 16, 17, 24, 25, 26, 27, 29, 125, 11), // 1
            '192.168.11.22' => array(6, 7, 9, 32, 70, 71, 72, 73, 74, 75, 77, 193, 86, 87, 88, 89, 197, 198, 200), // 2
            '192.168.11.23' => array(4, 18, 19, 40, 41, 42, 43, 44, 45, 90, 93, 94, 187, 188), // 3
            '192.168.11.24' => array(3, 30, 31, 35, 10, 100, 101, 102, 103, 131), // 4
            '192.168.11.25' => array(46, 47, 48, 49, 5, 55, 56, 80, 81, 82, 91, 92, 181, 185, 192), // 5
            '192.168.11.26' => array(8, 13, 28, 38, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 138, 199, 221, 223, 228), // 6
            '192.168.11.27' => array(160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171), // 7
            '192.168.11.28' => array(12, 120, 121, 15, 150, 151, 152, 39, 180, 190, 191), // 8
            '192.168.11.41' => array(194, 195, 215, 222, 224, 225, 226, 227, 229, 230, 231, 232, 235, 236), // 11
            '192.168.11.42' => array(23, 33, 34, 36, 78, 79, 95, 96, 97, 98, 99, 182), // 12
            '192.168.11.43' => array(50, 51, 52, 53, 54, 57, 58, 59, 104, 105, 124, 153, 154), // 13
            '192.168.11.44' => array(172, 173, 174, 175, 176, 177, 178, 179, 240, 241, 242, 243, 244, 245, 246, 247), // 14
        );

    foreach ($blades as $blade => $values) {
        if (in_array($octets[2], $values)) return $blade;
    }

    return false;
}

function checkIp($ip)
{
    return preg_match('#(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)#', $ip);
}

function isNextTariffLower($current, $next, $userId)
{
    if ($current == $next)
        return false;

    $current = intval($current);
    $next = intval($next);
    $withActivation = Array(60365, 60367);

    if (in_array($next, $withActivation)) {
        // add activate product service 5U
        userAddProduct($userId, 96);

        // GBZ is free ever
        return false;
    } elseif (in_array($current, $withActivation)) {
        // remove activate product service 5U
        userRemoveProducts($userId, 96);
    }

    if (!$current)
        return false;

    $current = tariffDetails($current);
    $currentPrice = ($current['period'] < 30 ? 30 : 1) * priceTariff($current['id']);

    $next = tariffDetails($next);
    $nextPrice = ($next['period'] < 30 ? 30 : 1) * priceTariff($next['id']);

    return $currentPrice > $nextPrice;
}

function userAddProduct($userId, $productId, $quantity = 1)
{
    global $db;

    $data = array(
        'prod_code' => $productId,
        'uid' => $userId,
        'qnt' => $quantity,
    );

    return $db->from('products_services_users')->insert($data)->execute();
}

function userRemoveProducts($userId, $productId = null)
{
    global $db;

    $st = $db->from('products_services_users')
        ->where('uid', $userId);

    if ($productId)
        $st->where("AND prod_code = '$productId'");
    else
        $st->where('AND prod_code NOT IN ( 45, 56, 66, 67, 83, 84, 109 )'); // rent services

    return $st->delete()->execute();
}


function userRemoveArrayProducts($userId)
{
    global $db;

    $st = $db->from('products_services_users')
        ->where('uid = "'.$userId.'" AND prod_code in (79,110,80,73,85,74,87,88)');

    return $st->delete()->execute();
}

/**
 * @return array daily tariffs ID's
 */
function getDailyTariffs()
{
    global $db;
    static $tariffs = array();

    if (!$tariffs) {
        $rows = $db->from('tariffs_current')->where(array('period_type' => 1))->select('tid')->many();
        foreach ($rows as $row) {
            $tariffs[] = $row['tid'];
        }
    }

    return $tariffs;
}

function addUserToGroup($userId, $groupId = 1060)
{
    global $db;

    $data = array(
        'group_id' => $groupId,
        'tag' => 'user_id',
        'value' => $userId,
    );

    return $db->from('groups')->insert($data)->execute();
}



function cardBill($id, $cardNumber, $cardPin) {
    global $db;

    $field = getUserField($id);
    $time = time();

    $card = $db
        ->from('icards')
        ->where("id = $cardNumber and card_serial = $cardPin and card_status = 1")
        ->one();


    if($card) {
        $units = $card['card_nominal'];
        // update balance
        $sql = "UPDATE users SET bill = bill + $units, bill_abs = bill_abs + $units WHERE $field = '$id'";
        $db->sql($sql)->execute() || exit('Update user balance error');

        $user = getUser($id);

        $sql = "
INSERT INTO bills_history
(login, real_pay_date, `date`, qnt_currency, who, what, uid, currency_id, comments, qnt)
VALUES
('{$user['login']}', $time, $time, $units, 'icard', $cardNumber, {$user['id']}, 10, 'With rate: 1', $units)
        ";
        $db->sql($sql)->execute() || exit('Update payment history error');

        $sql = "UPDATE icards SET card_status = 0 WHERE id = $cardNumber and card_serial = $cardPin and card_status = 1";
        $db->sql($sql)->execute() || exit('Update card status error');


        userOn($user);

        return true;

    } else {

        return false;
    }


}

function getRemoteAddr()
{
    return $_SERVER;
}