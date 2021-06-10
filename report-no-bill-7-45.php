<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css"
          integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <style>
table img {
    width: 21px;
    height: 21px;
}
    </style>
</head>
<body>

<div class="container">
<h2>Не пополнившиеся 7-45 дней</h2>
    <?php
    exit;
    header('Content-Type: text/html; charset=windows-1251');

    include_once 'utm_connect.php';
    include_once 'smsc_api.php';

    $dateStart = strtotime('-7 day midnight');
    $dateEnd = strtotime('-45 day midnight');

    $sql = "
SELECT FROM_UNIXTIME(sent_time, '%Y-%m-%d') `date`
FROM users u 
LEFT JOIN bills_history bh ON usc.user_id = bh.uid AND bh.date > $dateStart
WHERE 1
AND sent_time > $dateStart
GROUP BY `date`, `status`, user_id
ORDER BY `date`, `status` 
";
    //echo $sql;
    $result = mysqli_query($link, $sql);
    echo mysqli_error($link);

    function incArrayElement(&$element, $quantity = 1)
    {
        if (empty($element)) $element = $quantity;
        else $element += $quantity;
    }

    $rows = array();
    while ($row = mysqli_fetch_object($result)) {
        incArrayElement($rows[$row->date][$row->status]['user']);
        incArrayElement($rows[$row->date][$row->status]['sms'], $row->sms);

        $stateBill = 'not';
        if ($row->bill_time) {
            if ($row->bill_time < $row->sent_time + $period) {
                $stateBill = 'before';
            } else {
                $stateBill = 'after';
            }
        }
        incArrayElement($rows[$row->date][$row->status][$stateBill]);
    }

    echo "
<table class='table table-hover table-sm'>
<thead>
<tr>
<th>Дата</th>
<th>Статус</th>
<th>СМС</th>
<th>Клиенты</th>
<th>В течении 3-х</th>
<th>После 3-х</th>
<th>Не пополнились</th>
</tr>
</thead>
<tbody>
";
    $td = array();
    foreach ($rows as $date => $statuses) {
        $rowspan = is_array($statuses) ? count($statuses) : 1;
        $td[] = "<td rowspan='$rowspan'>$date</td>";

        foreach ($statuses as $status => $counter) {
            $td[] = "<th><img src='images/status_{$status}.png'></th>";
            $td[] = "<td>{$counter['sms']}</td>";
            $td[] = "<td>{$counter['user']}</td>";
            $counterBefore = $counter['before'] ?: 0;
            $td[] = "<td>$counterBefore</td>";
            $counterAfter = $counter['after'] ?: 0;
            $td[] = "<td>$counterAfter</td>";
            $counterNot = $counter['not'] ?: 0;
            $td[] = "<td>$counterNot</td>";

            $tr = implode(PHP_EOL, $td);

            $class = 'default';
            switch ($status) {
                case -1:
                    $class = 'warning';
                    break;
                case 0:
                    $class = 'info';
                    break;
                case 1:
                    $class = 'success';
                    break;
                case 3:
                    $class = 'active';
                    break;
                case 20:
                    $class = 'danger';
                    break;
            }
            echo "<tr class='table-$class'>$tr</tr>";
            $td = array();
        }
    }
    echo "
</tbody>
</table>
";
    ?>
    <br><br>
    <table class="table">
        <tr class="table-warning">
            <th><img src="images/status_-1.png"></th>
            <th>Ожидает отправки</th>
            <td>Если при отправке сообщения было задано время получения абонентом, то до этого времени сообщение будет
                находиться в данном статусе, в других случаях сообщение в этом статусе находится непродолжительное время
                перед отправкой на SMS-центр.
            </td>
        </tr>
        <tr class="table-info">
            <th><img src="images/status_0.png"></th>
            <th>Передано оператору</th>
            <td>Сообщение было передано на SMS-центр оператора для доставки.</td>
        </tr>
        <tr class="table-success">
            <th><img src="images/status_1.png"></th>
            <th>Доставлено</th>
            <td>Сообщение было успешно доставлено абоненту.</td>
        </tr>
        <tr class="table-active">
            <th><img src="images/status_3.png"></th>
            <th>Просрочено</th>
            <td>Возникает, если время "жизни" сообщения истекло, а оно так и не было доставлено получателю, например,
                если
                абонент не был доступен в течение определенного времени или в его телефоне был переполнен буфер
                сообщений.
            </td>
        </tr>
        <tr class="table-danger">
            <th><img src="images/status_20.png"></th>
            <th>Невозможно доставить</th>
            <td>Попытка доставить сообщение закончилась неудачно, это может быть вызвано разными причинами, например,
                абонент заблокирован, не существует, находится в роуминге без поддержки обмена SMS, или на его телефоне
                не
                поддерживается прием SMS-сообщений.
            </td>
        </tr>
    </table>

</div>

<!-- jQuery first, then Tether, then Bootstrap JS. -->
<script src="https://code.jquery.com/jquery-3.1.1.slim.min.js"
        integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n"
        crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js"
        integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb"
        crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"
        integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn"
        crossorigin="anonymous"></script>
</body>
</html>