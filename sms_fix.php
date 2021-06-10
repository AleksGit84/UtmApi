<pre>
<?php
exit;
header('Content-Type: text/html; charset=windows-1251');

include_once 'utm_connect.php';

$sql = "
SELECT *
FROM users_sms_corolek
WHERE status = 20
";
$result = mysqli_query($link, $sql);

//if (mysqli_num_rows($result)) header("Refresh:3");

while ($row = mysqli_fetch_object($result)) {
    echo "USER ID: $row->user_id" . PHP_EOL;
    $date = date('Y.m.d', $row->sent_time);

    $sql = "
UPDATE users
SET 
tax_number = REPLACE(tax_number, '[[$row->phone]]', ''),
juridical_address = CONCAT('$date [$row->phone] Невозможно доставить SMS', '\r\n', juridical_address)
WHERE id = $row->user_id
";
echo $sql, PHP_EOL;
    try {
        if (!mysqli_query($link, $sql)) echo mysqli_error($link), PHP_EOL;
    } catch (Exception $e) {
        var_dump($e);
    }
}
