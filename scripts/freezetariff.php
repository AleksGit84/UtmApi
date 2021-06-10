<?php

# Settings
$dbHost = '192.168.11.5';
$dbName = 'UTM';
$dbUser = 'svit';
$dbPass = 'mq3vdqebKDPNC9A8';

$min_bill = 1;

$uid = $argv[1];
$user_ip_address = $argv[2];
$action = $argv[3];

// tariffs which can freeze (2 times in month for free - rest 1 unit fee)
$free_freeze_tariffs = array(60050, 60100, 61000, 75050, 70100, 76030, 76040, 76050);
// not freezing services
$rent_services = array(67, 56);
$rent_services_list = implode(', ', $rent_services);


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

/**
 * gets payment for tariff & extra services
 * @param $u_id
 */

function get_pay_unfreeze($u_id)
{
    $query = "SELECT login, tariff FROM users WHERE id = $u_id";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1000");
    $row = mysql_fetch_row($result);
    $login = $row[0];
    $t_id = $row[1];

    $query = "
    SELECT SUM(ps.price * psu.qnt) AS mon
    FROM products_services_users psu
    INNER JOIN products_services ps ON ps.id = psu.prod_code
    WHERE psu.uid = $u_id
    ";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1001");
    $row = mysql_fetch_row($result);
    $user_service_price = $row[0];

    $query = "
    SELECT SUM(ps.price * pst.qnt) AS money
    FROM products_services_tariffs pst
    INNER JOIN products_services ps ON ps.id = pst.prod_code
    WHERE pst.tariff_id = $t_id
    ";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1002");
    $row = mysql_fetch_row($result);
    $user_tariff_price = $row[0];

    $unit = $user_service_price + $user_tariff_price;
    $query = "UPDATE users SET bill = bill - $unit WHERE id = $u_id LIMIT 1";
    mysql_query($query) or write_log($u_id, "Error 1003");

    $comments = "Списание средства за оплату следующего учетного периода";
    $comments = cp2utf($comments,true);
    $query = "
    INSERT INTO bills_history
    (login, `date`, qnt, who, what, comments, currency_id, qnt_currency, real_pay_date, uid)
    VALUES
    ('$login', UNIX_TIMESTAMP(), '-$unit', 'freeze_tariff', 'payment', '$comments', 10, '-$unit', UNIX_TIMESTAMP(), $u_id)
    ";
    mysql_query($query) or write_log($u_id, "Error 1004");
}

/**
 * makes payment
 * @param $u_id
 * @param $unit
 * @param $comments
 */
function get_pay($u_id, $unit, $comments)
{
    $query = "SELECT login FROM users WHERE id = $u_id";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1100");
    $row = mysql_fetch_row($result);
    $login = $row[0];

    $query = "UPDATE users SET bill = bill - $unit WHERE id = $u_id LIMIT 1";
    mysql_query($query) or write_log($u_id, "Error 1101");

    $query = "
    INSERT INTO bills_history
    (login, `date`, qnt, who, what, comments, currency_id, qnt_currency, real_pay_date, uid)
    VALUES
    ('$login', UNIX_TIMESTAMP(), '-$unit', 'freeze_tariff', 'payment', '$comments', 10, '-$unit', UNIX_TIMESTAMP(), $u_id)
    ";
    mysql_query($query) or write_log($u_id, "Error 1104");
}

/**
 * save services for freezing users
 * except rent services
 * @param $u_id
 */
function save_services($u_id)
{
    global $rent_services_list;

    $query = "DELETE FROM freeze_products_services_users WHERE uid = $u_id";
    mysql_query($query) or write_log($u_id, "Error 1200");

    $query = "
    INSERT INTO freeze_products_services_users (prod_code, uid, qnt, `data`)
    SELECT prod_code, uid, qnt, NOW()
    FROM products_services_users
    WHERE uid = $u_id
    AND prod_code NOT IN ($rent_services_list)
    ";
    mysql_query($query) or write_log($u_id, "Error 1201");

    $query = "DELETE FROM products_services_users WHERE uid = $u_id AND prod_code NOT IN ($rent_services_list)";
    mysql_query($query) or write_log($u_id, "Error 1202");
}

/**
 * restore services from freezing
 * @param $u_id
 */
function restore_services($u_id)
{
    $query = "
    INSERT IGNORE INTO products_services_users (prod_code, uid, qnt)
    SELECT prod_code, uid, qnt
    FROM freeze_products_services_users
    WHERE uid = $u_id
    ";
    mysql_query($query) or write_log($u_id, "Error 1301");

    $query = "DELETE FROM freeze_products_services_users WHERE uid = $u_id";
    mysql_query($query) or write_log($u_id, "Error 1302");
}

/**
 * @param $u_sid
 * @param $u_ip
 * @return mixed user id or false
 */
function find_uid($uid, $u_ip)
{
    $query = "SELECT id FROM users WHERE id='$uid'";
    $result = mysql_query($query);
    if (!$result) write_log(0, "Error 1400");

    if ($row = mysql_fetch_row($result)) {
        return $row[0];
    } else write_log(0, "Error 1401");

    return false;
}

/**
 * freeze user
 * @param $u_id
 * @param $t_id
 * @param $point
 */
function freeze_tariff($u_id, $t_id, $point)
{
    // in favour of IGNORE
    //$query = "DELETE FROM freeze_users WHERE uid = $u_id";
    //mysql_query($query) or write_log($u_id, "Error 1502");

    save_services($u_id);

    $query = "INSERT IGNORE INTO freeze_users (uid, tariff, `date`) VALUES ($u_id, $t_id, NOW())";
    mysql_query($query) or write_log($u_id, "Error 1503");

    $query = "UPDATE users SET tariff_next = 40 WHERE id = $u_id LIMIT 1";
    mysql_query($query) or write_log($u_id, "Error 1504");

    if ($point == 1) {
        $query = "
        UPDATE freeze_users_free
        SET point_left = point_left - 1
        WHERE uid = $u_id
        AND `month` = MONTH(NOW())
        AND `year` = YEAR(NOW())
        LIMIT 1
        ";
        mysql_query($query) or write_log($u_id, "Error 1507");
    } elseif ($point == 2) {
        $query = "
        INSERT INTO freeze_users_free
        (uid, `month`, `year`, point_left, last_use)
        VALUES
        ($u_id, MONTH(NOW()), YEAR(NOW()), 1, NOW())
        ";
        mysql_query($query) or write_log($u_id, "Error 1508");
    } else {
        $txt = 'Заморозка счета';
        $txt = cp2utf($txt, true);
        get_pay($u_id, 1, $txt);
    }
}

/**
 * gets tariff id before freezing
 * @param $u_id
 * @return mixed
 */
function get_freeze_tariff($u_id)
{
    $query = "SELECT tariff FROM freeze_users WHERE uid = $u_id";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1601");
    $row = mysql_fetch_row($result);

    return $row[0];
}

/**
 * unfreeze user
 * @param $u_id
 */
function unfreeze_tariff($u_id)
{
    restore_services($u_id);

    $t_id = get_freeze_tariff($u_id);
    /*
        $query = "SELECT period_type FROM tariffs_current WHERE tid = $t_id";
        $result = mysql_query($query);
        if (!$result) write_log($u_id, "Error 1602");
        $row = mysql_fetch_row($result);
        $period_type = $row[0];*/

    /** end period +24 hours - option available only for daily tariffs */
    $ab_pend = '(UNIX_TIMESTAMP() + 86400)';
    /*    if ($period_type == 0) {
            $ab_pend = '(UNIX_TIMESTAMP(CURDATE() + INTERVAL 1 month))';
        } elseif ($period_type == 1) {
            $ab_pend = '(UNIX_TIMESTAMP() + 86400)';
        } elseif ($period_type == 90) {
            $ab_pend = '(UNIX_TIMESTAMP(CURDATE() + INTERVAL 90 day))';
        }*/

    $query = "
    UPDATE users
    SET tariff = $t_id, tariff_next = $t_id, ab_pstart = UNIX_TIMESTAMP(), ab_pend = $ab_pend, block = '0', fw_on = '1'
    WHERE id = '$u_id'
    LIMIT 1
    ";
    mysql_query($query) or write_log($u_id, "Error 1603");

    $query = "DELETE FROM freeze_users WHERE uid = $u_id";
    mysql_query($query) or write_log($u_id, "Error 1604");

    get_pay_unfreeze($u_id);
}

/**
 * gets users params
 * @param $u_id
 * @return array|bool
 */
function get_user_attr($u_id)
{
    $query = "SELECT bill, tariff, tariff_next FROM users WHERE id = $u_id";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1610");

    if ($row = mysql_fetch_row($result)) {
        return array($row[0], $row[1], $row[2]);
    } else write_log($u_id, "Error 1611");

    return false;
}

/**
 * get number of available points
 * @param $u_id
 * @return int
 */
function get_info_free_point($u_id)
{
    $query = "
    SELECT point_left
    FROM freeze_users_free
    WHERE uid = $u_id
    AND `month` = MONTH(NOW())
    AND `year` = YEAR(NOW())
    ";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1701");
    if (mysql_num_rows($result)) {
        $row = mysql_fetch_row($result);
        $point = $row[0];
        if ($point >= 1) return 1;
    } else return 2;

    return 0;
}

/**
 * return freeze method
 * @param $u_id
 * @return int 0 - system, 1 - manual by user
 */
function get_info_hand($u_id)
{
    $query = "SELECT uid FROM freeze_users WHERE uid = $u_id";
    $result = mysql_query($query);
    if (!$result) write_log($u_id, "Error 1800");

    if (mysql_num_rows($result)) {
        $hand_status = 1;
    } else {
        $hand_status = 0;
    }

    return $hand_status;
}

/**
 * logging in db
 * @param $u_id
 * @param $message
 * @param bool $exit
 */
function write_log($u_id, $message, $exit = true)
{
    //echo mysql_error();
    $query = "
    INSERT INTO moreservice_log (uid, service, `date`, log)
    VALUES ($u_id, 'freeze_tariff', NOW(), '$message')
    ";
    /** log to file if fails to DB */
    mysql_query($query) or logToFile($u_id, $message, mysql_error());

    // duplicate to file
    //logToFile($u_id, $message);
    if ($exit) exit($message);
}

/**
 * logging to file
 * @param int $u_id
 * @param string $message
 * @param string $error
 */
function logToFile($u_id, $message, $error = '') {
    $file = 'freeze_tariff.log';
    $data = date('Y-m-d H:i:s') . "  $u_id   $message    $error \n";

    //file_put_contents($file, $data, FILE_APPEND);

    $fp = fopen($file, 'a');
    fwrite($fp, $data);
    fclose($fp);

    /** if log to DB fails - exit */
    if ($error) exit('ERROR CODE 0');
}

#######################################################################################
#######################################################################################

$link = mysql_connect($dbHost, $dbUser, $dbPass) or die("Could not connect");
mysql_select_db($dbName) or die("Could not select database");

#######################################################################################
$user_id = find_uid($uid, $user_ip_address);
//$user_id1000 = $user_id + 1000;
$user_attr = get_user_attr($user_id);


if ($action == 'FREEZE') {
    if (in_array($user_attr[1], $free_freeze_tariffs)) {
        $free_point = get_info_free_point($user_id);
        if (($user_attr[0] <= $min_bill) && ($free_point == 0)) {
            write_log($user_id, 'ERROR CODE 7');
        }

        if (($user_attr[1] == 40 && $user_attr[2] == 40) || ($user_attr[2] == 40)) {
            write_log($user_id, 'ERROR CODE 4');
        }

        freeze_tariff($user_id, $user_attr[1], $free_point);

        write_log($user_id, 'OK CODE 1 FREEZE', false);
        exit('OK CODE 1');
    }
    write_log($user_id, 'ERROR CODE 3');
} elseif ($action == 'UNFREEZE') {
    if ($user_attr[1] != 40) {
        write_log($user_id, 'ERROR CODE 5');
    }

    unfreeze_tariff($user_id);

    write_log($user_id, 'OK CODE 1 UNFREEZE', false);
    exit('OK CODE 1');

} elseif ($action == 'INFO') {
    $tariff = ($user_attr[1] == 40) ? get_freeze_tariff($user_id) : $user_attr[1];

    if (in_array($tariff, $free_freeze_tariffs)) {
        $free_point = get_info_free_point($user_id);

        if ($user_attr[1] == 40 && $user_attr[2] == 40) {
            $status = 1;
        } elseif ($user_attr[2] == 40) {
            $status = 2;
        } else {
            $status = 0;
        }

        if (($user_attr[0] <= $min_bill) && ($status == 0) && ($free_point == 0)) {
            write_log($user_id, 'ERROR CODE 7');
        }

        $freeze_way = get_info_hand($user_id);
        $result = "$status $free_point $freeze_way";

        //write_log($user_id, "INFO $result", false);
        exit($result);
    }
    //write_log($user_id, 'ERROR CODE 3');
    exit('ERROR CODE 3');
}

write_log(0, "ERROR CODE 0");
//mysql_close($link);
?>
