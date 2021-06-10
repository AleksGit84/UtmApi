<?php
require '../vendor/init.php';

# Settings
$dbhost = '192.168.11.5';
$dbname = 'UTM';
$dbuser = 'svit';
$dbpasswd = 'mq3vdqebKDPNC9A8';
$uid = $argv[1];
$user_ip_address = $argv[2];
$action          = $argv[3];
$current_date = date("U");

$forbiden_tariffs = array('50205', '50210', '50225','60001','60050','60100','61000','301032','75050','70100','76030','76040','76050');


########
function writelog ($u_id,$service,$message) {
    global $dbhost;global $dbuser;global $dbpasswd;global $dbname;

//    $message = $_SERVER['REMOTE_'];
    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");
    $query = "INSERT INTO moreservice_log (uid,service,date,log) VALUES ('$u_id','$service',NOW(),'$message')";
    $result = mysql_query($query);
    mysql_close($link);
    return 0;
}


########
function find_uid ($uid,$u_ip) {
    global $dbhost;global $dbuser;global $dbpasswd;global $dbname;

    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");

    $query = "SELECT id FROM users WHERE id='$uid'";
    $result = mysql_query($query);
    if (!$result) { writelog('0','creditbutton',"Error 1000");echo "Error 1000"; exit; }
    While ($row = mysql_fetch_array($result, MYSQL_NUM)) {$uid = $row['0'];}

    mysql_close($link);

    if (!isset($uid)) { writelog('0','creditbutton',"Error 1001");echo "Error 1001"; exit (0);  }
    return $uid;
}


########
function get_user_atribut($u_id) {
    global $dbhost;global $dbuser; global $dbpasswd;global $dbname;

    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");

    $query = "SELECT ab_pstart, ab_pend, bill, tariff FROM users WHERE id=$u_id";
    $result = mysql_query($query);
    if (!$result) { writelog($u_id,'creditbutton',"Error 1101");echo "Error 1101"; exit (0); }

    while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
        $tmp_ab_pstart = $row[0];
        $tmp_ab_pend = $row[1];
        $tmp_bill = $row[2];
        $tmp_tariff = $row[3];
    }

    mysql_close($link);
    $tmp_array = array ($tmp_ab_pstart ,$tmp_ab_pend, $tmp_bill,$tmp_tariff );
    if (!isset($tmp_array)) { writelog($u_id,'creditbutton',"Error 1102");echo "Error 1102"; exit (0);  }
    return $tmp_array;
}



function get_info ($u_id,$u_ab_pstart,$u_ab_pend)
{
    global $dbhost;global $dbuser;global $dbpasswd;global $dbname;

    if (!isset($u_id) || !isset($u_ab_pstart) || !isset($u_ab_pend)) { writelog($u_id,'creditbutton',"Error 1701"); echo "Error 1701"; exit (0); }

    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");


    $query = "SELECT credit_left,last_activate FROM loan_internet WHERE uid = '$u_id' AND time_interval = '1D' AND ab_pstart = $u_ab_pstart AND ab_pend = $u_ab_pend ORDER BY id DESC LIMIT 1";
    $result = mysql_query($query);
    if (!$result) { writelog($u_id,'creditbutton',"Error 1200"); echo "Error 1200"; exit (0); }
    mysql_close($link);

### if 1 row, then analize point
    if(mysql_num_rows($result) == 1) {
        While ($row = mysql_fetch_array($result, MYSQL_NUM)) { $point = $row[0];$last_activate = $row[1];}
        if (($last_activate + 86400) > date("U")){
            return "$point ON $last_activate";
        }  else {
            return "$point OFF $last_activate";
        }
    }

    if(mysql_num_rows($result) == 0) {
        return '2 OFF';
    }
}

function activate_credit ($u_id,$u_ab_pstart,$u_ab_pend)
{
    global $dbhost;global $dbuser;global $dbpasswd;global $dbname;

    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");
    $u_id1000 = $u_id + 1000;

    $query = "SELECT credit_left, last_activate + 86400 AS date_end, UNIX_TIMESTAMP(), id FROM loan_internet WHERE uid = $u_id AND time_interval = '1D' AND ab_pstart = $u_ab_pstart AND ab_pend = $u_ab_pend";
    $result = mysql_query($query);
    if (!$result) { writelog($u_id,'creditbutton',"Error 1300"); echo "Error 1300"; exit (0); }
    while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
        $point = $row[0];
        $date_end = $row[1];
        $date_now = $row[2];
        $li_id = $row[3];
    }
    if(mysql_num_rows($result) == 0) {
        $query = "INSERT INTO loan_internet ( uid, ab_pstart, ab_pend, credit_left, last_activate) VALUES ($u_id, $u_ab_pstart, $u_ab_pend, 1, UNIX_TIMESTAMP())";
        $result = mysql_query($query);
        if (!$result) { writelog($u_id,'creditbutton',"Error 1301"); echo "Error 1301"; exit (0); }
        mysql_close($link);
        user_credit_on($u_id);

        return "OK CODE 1";
    }
    if(mysql_num_rows($result) == 1) {
        if (($point >= 1) && ($date_end < $date_now)) {
//          $query = "UPDATE loan_internet SET credit_left = credit_left - 1, last_activate = UNIX_TIMESTAMP() WHERE uid = $u_id AND ab_pstart = $u_ab_pstart AND ab_pend = $u_ab_pend";
            $query = "UPDATE loan_internet SET credit_left = credit_left - 1, last_activate = UNIX_TIMESTAMP() WHERE id = $li_id";
            $result = mysql_query($query);
            if (!$result) { writelog($u_id,'creditbutton',"Error 1302"); echo "Error 1302"; exit (0); }
            mysql_close($link);
            user_credit_on($u_id);




            return "OK CODE 1";
        }
        mysql_close($link);
        if ($date_end > $date_now) {
            return 'ERROR CODE 5';
        }
        if ($point < 1) {
            return 'ERROR CODE 6';
        }

    }
    return 'ERROR CODE 0';
}



function user_credit_on($u_id) {
    global $dbhost;global $dbuser;global $dbpasswd;global $dbname;

    $link = mysql_connect("$dbhost", "$dbuser", "$dbpasswd") or die("Could not connect");
    mysql_select_db("$dbname") or die("Could not select database");

    $query = "UPDATE users SET `block` = '0' WHERE id = {$u_id}";
    $result = mysql_query($query);
    if (!$result) { writelog($u_id,'creditbutton',"Error 1301"); echo "Error 1301"; exit (0); }


    $arrContextOptions = array(
        "ssl" => array(
            "verify_peer" => false,
               "verify_peer_name" => false,
           ),
       );

    $response = file_get_contents("https://ss.sohonet.ua/scripts/fwon.php?id={$u_id}&ip=NA",false, stream_context_create($arrContextOptions));
        $user['fw_on_response'] = var_export($response, true);
            if ($response == 'ok') {
                $query = "UPDATE users SET `fw_on` = '1' WHERE id = {$u_id}";
                $result = mysql_query($query);
                if (!$result) { writelog($u_id,'creditbutton',"Error 1301"); echo "Error 1301"; exit (0); }
            }


    mysql_close($link);

    return true;
//    }

    //return false;
}


############################################################################################
############################################################################################
############################################################################################


$user_id      = find_uid($uid,$user_ip_address);
$user_id1000  = $user_id + 1000;
$user_atribut = get_user_atribut($user_id);

$user_ab_pstart = $user_atribut[0];
$user_ab_pend = $user_atribut[1];
$user_bill = $user_atribut[2];
$user_tariff = $user_atribut[3];


if ($action == 'INFO' ) {
    $result = get_info($user_id,$user_ab_pstart,$user_ab_pend);
    if (!$result) {writelog($user_id,'creditbutton',"ERROR CODE 1");echo "ERROR CODE 1";exit (0);}
    writelog($user_id,'creditbutton',"$action $result");
    $info_arr=explode(" ", $result);

# Check date and tarif_period
### ERROR CODE 1 - error in tariff_period, some wrong with date
    if ( !($user_ab_pstart <= $current_date AND $current_date <= $user_ab_pend) ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 1'");
        echo "ERROR CODE 1";

        exit (0);
    }

# Check user bill state
### ERROR CODE 2 - where is money in user bill, no need to use this service
    if ( $user_bill > 0 ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 2'");
        echo "ERROR CODE 2";

        exit (0);
    }
# Check user tariff
### ERROR CODE 3 - user tariff  is forbiden for this service
    foreach ($forbiden_tariffs as $forbiden_tariff ) {
        if ( $user_tariff == $forbiden_tariff ) {
            writelog($user_id,'creditbutton',"$action 'ERROR CODE 3'");
            echo "ERROR CODE 3";

            exit (0);
        }
    }
# Check user tariff for frozen tariff
### ERROR CODE 4 - user have frozen tariff
    if ( $user_tariff == "40" ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 4'");
        echo "ERROR CODE 4";

        exit (0);
    }

    echo "$result";

    exit (0);
}

if ($action == 'ON' )
{

# Check date and tarif_period
### ERROR CODE 1 - error in tariff_period, some wrong with date
    if ( !($user_ab_pstart <= $current_date AND $current_date <= $user_ab_pend) ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 1'");
        echo "ERROR CODE 1";
        exit (0);
    }

# Check user bill state
### ERROR CODE 2 - where is money in user bill, no need to use this service
    if ( $user_bill > 0 ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 2'");
        echo "ERROR CODE 2";
        exit (0);
    }
# Check user tariff
### ERROR CODE 3 - user tariff  is forbiden for this service
    foreach ($forbiden_tariffs as $forbiden_tariff ) {
        if ( $user_tariff == $forbiden_tariff ) {
            writelog($user_id,'creditbutton',"$action 'ERROR CODE 3'");
            echo "ERROR CODE 3";
            exit (0);
        }
    }
# Check user tariff for frozen tariff
### ERROR CODE 4 - user have frozen tariff
    if ( $user_tariff == "40" ) {
        writelog($user_id,'creditbutton',"$action 'ERROR CODE 4'");
        echo "ERROR CODE 4";
        exit (0);
    }

    $result = activate_credit($user_id,$user_ab_pstart,$user_ab_pend);
    if (!$result) {echo "ERROR CODE 0";exit (0);}
    writelog($user_id,'creditbutton',"$action $result");
    echo "$result";
    exit (0);
}

### ERROR CODE 5 - service active, no need to activate
### ERROR CODE 6 - no credit point left
writelog('0','creditbutton',"ERROR CODE 0");echo "ERROR CODE 0";
?>
