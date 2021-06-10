<pre>
<?php
exit;
// last processed users id 33941
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=windows-1251');

include_once 'utm_connect.php';

$result = mysqli_query($link, "SELECT id, tax_number FROM users");

$count = 0;
while ($row = mysqli_fetch_row($result)) {
    $modified = false;
    $id = $row[0];
    $str = $row[1];
//    echo $str . PHP_EOL;
    preg_match_all('/[0-9\- ]{6,}/', $str, $matches);

    foreach ($matches[0] as $match) {
        $phone = preg_replace('/[^0-9]+/', '', $match);
        if ($newValue = canReceiveSMS($phone)) {
//            $count++;
            $modified = true;
            $str = str_replace($match, $newValue, $str);
        }
    }

    if ($modified) {
        mysqli_query($link, "UPDATE users SET tax_number = '$str' WHERE id = $id");
        echo $str . PHP_EOL;
//        echo 'modified!' . PHP_EOL;
        // update users
    }
}

echo 'Mobile: ' . $count . PHP_EOL;

function canReceiveSMS($number) {
    $mask = '380xxxxxxxxx';
    $result = false;
    $len = strlen($number);

    if ($len > 5) {
        $result = $number;

        if ($len < 8) {
            if (isIT($number)) {
                $mask = ($len > 6) ? '38048xxxxxxx' : '380482xxxxxx';
            } else $result = false;
        } elseif ($len < 10) $result = false;
    }

    if ($result) return maskPhone($result, $mask);

    return false;
}

function str_pop(&$str) {
    $char = substr($str, -1);
    $str = substr($str, 0, -1);

    return $char;
}

function str_shift(&$str) {
    $char = substr($str, 0, 1);
    $str = substr($str, 1);

    return $char;
}

function maskPhone($number, $mask) {
    global $count;

    $len = strlen($mask);
    $mobile = (strlen($number) > 9) ? true : false;
    $result = '';

    for ($i = $len - 1; $i >= 0; $i--) {
        $result = (is_numeric($mask[$i]) ? $mask[$i] : str_pop($number)) . $result;
    }

    if ($mobile) $count++;

    return $mobile ? "[[$result]]" : "[$result]";
}

function fillRecursive($item) {
    $n = str_shift($item);
    if (!is_numeric($n)) return true;

    return array($n => fillRecursive($item));
}

function checkRecursive($mask, $item) {
    $n = str_shift($item);
    if (empty($mask[$n])) return false;
    if (is_array($mask[$n])) return checkRecursive($mask[$n], $item);

    return $mask[$n];
}

// InterTelecom
function isIT($number) {
    static $mask = array();
    $masks = array(
        '309���',
        '3905��',
        '399���',
        '743����',
        '787����',
        '798����',
        '799����',
        '794����',
        '795����',
        '700����',
        '701����',
        '702����',
        '703����',
        '770xxxx',
        '771xxxx',
        '772xxxx',
        '704xxxx',
        '706����',
        '709����',
        '736����',
        '7591���',
        '7592���',
        '7594���',
        '7596���',
        '7599���',
        '783����',
        '7886���',
        '7887���',
        '7888���',
        '7889���',
        '7890���',
        '7891���',
        '7892���',
        '7893���',
        '7894���',
        '7961���',
        '7962���',
        '7963���',
        '7964���',
        '7968���',
        '7969���',
        '3906���',
        '7880���',
        '7881���',
        '7882���',
        '7883���',
        '793����',
    );

    if (!$mask) {
        foreach ($masks as $item) {
            $len = strlen($item);
            $mask[$len] = array_replace_recursive(isset($mask[$len]) ? $mask[$len] : array(), fillRecursive($item));
        }
    }

    return checkRecursive($mask[strlen($number)], $number);
}