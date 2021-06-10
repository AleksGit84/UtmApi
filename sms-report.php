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
        .table td, .table th { border-top: solid 1px grey; text-align: center; }
    </style>
</head>
<body>

<div class="container">

    <?php
    //exit;
    header('Content-Type: text/html; charset=windows-1251');

    include_once 'utm_connect.php';
    include_once 'smsc_api.php';

    $dateStart = strtotime('-7 day midnight');
    //$dateEnd = strtotime('tomorrow midnight');
    $period = 60 * 60 * 24 * 3;

    $sql = "
SELECT uid, MIN(date) bill_time
FROM bills_history
WHERE 1
AND date > $dateStart
GROUP BY `uid`
";
    //echo $sql;
    $result = mysqli_query($link, $sql);
    echo mysqli_error($link);

    $userBill = array();
    while ($row = mysqli_fetch_object($result)) {
        $userBill[$row->uid] = $row->bill_time;
    }

    $sql = "
SELECT FROM_UNIXTIME(sent_time, '%Y-%m-%d') `date`
, `status`, user_id
, MIN(sent_time) sent_time
, COUNT(DISTINCT phone) phones
, SUM(IF(`status` = 1, sms_cost, 0)) cost
, SUM(IF(`status` = 1, 1, 0)) sms
FROM users_sms_corolek usc 
WHERE 1
AND sent_time > $dateStart
GROUP BY `date`, `status`, user_id
ORDER BY `date` DESC, `status` 
";
    //echo $sql;
    $result = mysqli_query($link, $sql);
    echo mysqli_error($link);

    function incArrayElement(&$element, $quantity = 1)
    {
        if (empty($element)) $element = $quantity;
        else $element += $quantity;
    }

    $rows = $total = array();
    while ($row = mysqli_fetch_object($result)) {
        incArrayElement($rows[$row->date][$row->status]['user']);
        incArrayElement($rows[$row->date][$row->status]['sms'], $row->phones);

        incArrayElement($total[$row->date]['user']);
        incArrayElement($total[$row->date]['sms'], $row->sms);
        incArrayElement($total[$row->date]['cost'], $row->cost);

        $stateBill = 'not';
        if (isset($userBill[$row->user_id])) {
            if ($userBill[$row->user_id] < $row->sent_time + $period) {
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
<th>Дата / Клиенты [SMS-OK]</th>
<th>Статус</th>
<th>Клиенты <small>[tel]</small></th>
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
        $price = round($total[$date]['cost'], 2);
        $td[] = "<td rowspan='$rowspan'>{$date}
<br>Клиенты: <strong>{$total[$date]['user']}</strong> <small>[{$total[$date]['sms']}]
<br>Затраты на отправку: {$price} грн</small></td>";

        foreach ($statuses as $status => $counter) {
            $td[] = "<th><img src='images/status_{$status}.png'></th>";
            $td[] = "<td>{$counter['user']} <small>[{$counter['sms']}]</small></td>";
            $counterBefore = $counter['before'] ?: 0;
            $td[] = "<td>$counterBefore</td>";
            $counterAfter = $counter['after'] ?: 0;
            $td[] = "<td>$counterAfter</td>";
            $counterNot = $counter['not'] ?: 0;
            $td[] = "<th>$counterNot</th>";

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
    <br>
    <h4 class="text-center"><a href="https://ss.soho.net.ua/sv/report-gone.php?year=2017&type=other">Не пополнившиеся более 7-ми дней</a></h4>
    <br>
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