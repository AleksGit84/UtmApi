<pre>
<?php
set_time_limit(0);
//exit;
header('Content-Type: text/html; charset=windows-1251');

include_once 'utm_connect.php';
include_once 'smsc_api.php';

//WHERE status IN (-1, 0)
//WHERE status = 20 AND error_code IS NULL
$sql = "
SELECT *
FROM users_sms_corolek
WHERE status IN (-1, 0)
ORDER BY status ASC 
LIMIT 50
";
$result = mysqli_query($link, $sql);

//if (mysqli_num_rows($result)) header("Refresh:1");

while ($row = mysqli_fetch_object($result)) {
    echo "SMS ID: $row->sms_id" . PHP_EOL;

    list($status, $time, $error) = get_status($row->sms_id, $row->phone);
    var_dump($error);
    $sql = "UPDATE users_sms_corolek SET status = '$status', sent_time = '$time', error_code = '$error' WHERE id = $row->id";
    try {
        if (!mysqli_query($link, $sql)) echo mysqli_error($link), PHP_EOL;
        if ($status == 20) {
            $date = date('Y.m.d', $time);

            $sql = "
UPDATE users
SET 
tax_number = REPLACE(tax_number, '[[$row->phone]]', ''),
juridical_address = CONCAT('$date [$row->phone] Невозможно доставить SMS', '\r\n', juridical_address)
WHERE id = $row->user_id
";
            if (!mysqli_query($link, $sql)) echo mysqli_error($link), PHP_EOL;
        }
    } catch (Exception $e) {
        var_dump($e);
    }
}
