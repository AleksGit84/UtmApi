<?php
require '../vendor/init.php';
require '../vendor/autoload.php';

Flight::route('/', function () {
    echo 'hello world!';
});

Flight::route('GET /rainbow', function () use ($db) {
    $month = isset($_GET['month']) ? $_GET['month'] : '';
    echo <<<FORM
<form action="" method="get">
<input type="month" name="month" value="$month">
<input type="submit">
</form>
FORM;

    if ($month) {
        $startDate = strtotime($month);
        $endDate = strtotime('+1 month', $startDate) - 1;
        $term = cp2utf('Радуж', true);

        $sql = <<<SQL
SELECT u.id, MAX(uh.change_date) last
FROM `users` u
INNER JOIN users_history uh ON u.id = uh.id
WHERE u.tariff = 40
AND uh.tariff != 40
AND u.`actual_address` LIKE '%$term%'
GROUP BY u.id
HAVING last > '{$startDate}'
AND last < '{$endDate}'

SQL;
//echo $sql;

        $many = $db->sql($sql)->many();

        if ($many) {
            echo '<table>';
            foreach ($many as $item) {
                $date = date('c', $item['last']);
                echo <<<TR
<tr>
    <td>{$item['id']}</td>
    <td>{$date}</td>
</tr>
TR;
            }
            echo '</table>';
        }
    }
});

// return transactions history
Flight::route('GET /history/@type(/@lastId)', function ($type, $lastId) use ($db) {
//    $table = 'history_' . $type;
    $table =  $type . '_bill_history';
    $lastId++;

    $result = $db->sql("
    SELECT t.*, u.id user_id 
    FROM $table t
    INNER JOIN users u ON t.login = u.login
    WHERE t.id >= $lastId
    ORDER BY t.id ASC 
    LIMIT 100
    ")->many();

//    foreach ($result as &$item) {
//        $item = (int)$item['id'];
//    }

    Flight::json($result);
});

// change history
Flight::route('GET /change-history/@user', function ($user) use ($db) {
    $history = $db->from('UTM_logs')
        ->limit(10)
        ->sortDesc('id')
        ->where("event_type = 'Change user' AND event_message LIKE '% uid={$user} %'")
        ->select(array('event_date `date`', 'event_type `type`', 'event_message `message`', 'event_user `agent`'))
        ->many();

    foreach ($history as &$item) {
        $item['date'] *= 1;
        $item['message'] = cp2utf($item['message']);
    }

    Flight::json($history);
});

// bill history 50
Flight::route('GET /bill-history/@user', function ($user) use ($db) {
    $history = $db->from('bills_history')
        ->limit(50)
        ->sortDesc('date')
        ->where("uid = $user")
        ->select(array('date', 'qnt `amount`', 'who', 'comments'))
        ->many();

    foreach ($history as &$item) {
        $item['amount'] *= RATE;
        $item['date'] *= 1;
        $item['comments'] = cp2utf($item['comments']);
    }

    Flight::json($history);
});

// bill history last
Flight::route('GET /last-api-bill-history/@user', function ($user) use ($db) {
    $history = $db->from('bills_history')
        ->limit(50)
        ->sortDesc('date')
        ->where("uid = $user and who in('API Bill', 'icard', 'Privat', 'EasyPay', '24nonostop') and qnt > 0")
        ->select(array('date', 'qnt `amount`', 'who', 'comments'))
        ->many();

    foreach ($history as &$item) {
        $item['amount'] *= RATE;
        $item['date'] *= 1;
        $item['comments'] = cp2utf($item['comments']);
    }

    Flight::json($history);
});

// balance history
Flight::route('GET /balance-history/@user', function ($user) use ($db) {
    $history = $db->from('balance_history')
        ->limit(50)
        ->sortDesc('id')
        ->where("uid = $user")
        ->select(array('balance_in `in`', 'balance_out `out`', 'IF(gm_in, gm_in, -gm_out) `amount`', 'date', 'gm_target `service`'))
        ->many();

    foreach ($history as &$item) {
        $item['in'] *= RATE;
        $item['out'] *= RATE;
        $item['amount'] *= RATE;
        $item['date'] *= 1;
        $item['service'] = getServiceName($item['service']);
    }

    Flight::json($history);
});

// return users IDs inactive more than 1 year
Flight::route('GET /users(/@lastId)', function ($lastId) use ($db) {
    $lastId++;
    $lastDate = strtotime('-1 year -1 day');

    $result = $db->sql("
    SELECT u.id, MAX(bh.date) num 
    FROM `users` u
    LEFT JOIN balance_history bh ON bh.uid = u.id
    WHERE u.id >= $lastId
    AND `block` = '1' 
    AND balance_in > 0
    AND `state` = 'active'
    AND  `reg_date` < {$lastDate}
    GROUP BY u.id
    HAVING num < {$lastDate}
    LIMIT 10
    ")->many();

    foreach ($result as &$item) {
        $item = (int)$item['id'];
    }

    Flight::json($result);
});

//// return users IDs inactive more than 1 year
//Flight::route('GET /users(/@lastId)', function ($lastId) use ($db) {
//    $lastId++;
//    $lastDate = strtotime('-1 year -1 day');
//    $result = array();
////    Flight::json($result);
////    exit;
//
//    while (!$result) {
//        $users = $db->from('users')
//            ->limit(30)
//            ->sortAsc('id')
//            ->where("id >= $lastId AND block = '1' AND state = 'active' AND reg_date < {$lastDate}")
//            ->select(array('id'))
//            ->many();
//
//        if (!$users) break;
//
//        foreach ($users as &$user) {
//            $date = $db->sql("SELECT MAX( bh.date )  `date`
//FROM balance_history bh
//WHERE uid = {$user['id']}
//AND balance_in > 0
//GROUP BY uid")->value('date');
//
//            $lastId = (int)$user['id'];
//            if (!$date || $date < $lastDate) {
//                $result[] = $lastId;
//            }
//        }
//    }
//
//    Flight::json($result);
//});

// WTF???
Flight::route('/user-ips', function () use ($db) {
    $users = $db->from('users')
        ->limit(10)
        ->where('ip')
        ->select(array('id', 'ip'))
        ->many();

    foreach ($users as $user) {
        echo "<br>User ID#{$user['id']} <br>";
        foreach (explode(' ', $user['ip']) as $ip) {
            if ($ip) {
                $response = file_get_contents("http://192.168.11.1/dhcp/api/static/{$ip}");
                $result = $response ? 'OK' : 'NOT';
                echo "IP {$ip} {$response} <br>";
            }
        }
    }
});

// get blade by IP address
Flight::route('/blade/@ip', function ($ip) {
    $blade = getBlade($ip);

    Flight::json($blade);
});

// get stats for debtors
Flight::route('/stat/debt', function () use ($db) {
    $whereInDaily = "(60050, 60100, 75050, 70100, 61000, 76030, 76040, 76050)";
    $whereInDrWeb = "(53, 30, 31, 82)";
    $whereInRealIp = "(9, 22, 52, 95)";
    $whereStudents = "(
#login LIKE 'ST-%' OR
ip LIKE '192.168.12.%' 
OR ip LIKE '192.168.120.%' 
OR ip LIKE '192.168.121.%' 
OR ip LIKE '192.168.15%'
OR ip LIKE '192.168.150.%' 
OR ip LIKE '192.168.151.%'
OR ip LIKE '192.168.152.%'
OR ip LIKE '192.168.39.%' 
OR ip LIKE '192.168.190.%' 
OR ip LIKE '192.168.191.%'
OR ip LIKE '192.168.180.%'
 )";

    $return = $db
        ->from('users')
        ->select(array(
            'COUNT(*) total',
//            "COUNT(IF(block = '1', 1, NULL)) blocked",
            "SUM(IF(bill < 0, -bill, 0)) debt",
            "COUNT(IF(bill < 0, 1, NULL)) minus",
            "COUNT(IF(bill < 0 AND tariff = 40, 1, NULL)) freeze",
            "COUNT(IF(bill < 0 AND $whereStudents, 1, NULL)) student",
            "COUNT(IF(credit > 0, 1, NULL)) credit",
        ))
//        ->sql();
        ->one();

    $return['debt'] = round($return['debt'] * RATE);

    $return['extra'] = $db
        ->from('users')
        ->where("bill < 0")
        ->join('products_services_users psu', array('psu.uid' => 'users.id'))
        ->select(array(
            "SUM(IF(psu.prod_code IN $whereInDrWeb, psu.qnt, 0)) drWeb",
            "SUM(IF(psu.prod_code IN $whereInRealIp, psu.qnt, 0)) realIp",
        ))
//        ->sql();
        ->one();

    $time = time();
    $day = 24 * 3600;
    $date14d = $time - 14 * $day;
    $date45d = $time - 45 * $day;
//    $date120d = $time - 120 * $day;

    $payments = $db
        ->sql("
SELECT MAX(bh.date) `date` 
FROM users 
INNER JOIN balance_history bh ON bh.uid = users.id AND `date` > $date45d AND bh.balance_in > 0
WHERE users.bill < 0
GROUP BY users.id
")
//        ->sql();
        ->many();

    $mayBe = $dead = $justNow = 0;
    foreach ($payments as $payment) {
        if ($payment['date'] < $date14d) $mayBe++;
        else $justNow++;
    }

    $return['payment'] = array(
        'justNow' => $justNow,
        'mayBe' => $mayBe,
    );

//    $return['payment'] = $payments;

    Flight::json($return);
});

// add payment to user account
Flight::route('POST /pay/@id', function ($id) use ($db) {
    $user = array();

    if (!empty($_POST['amount'])) {
        $units = $_POST['amount'] / RATE;
        $description = empty($_POST['description']) ? '' : $_POST['description'];
        $time = empty($_POST['time']) ? time() : $_POST['time'];

        $user = billUser($id, $units, $description, $time);
        userOn($user);
    }

    Flight::json($user);
});

// add dedicated IP service to user + takes all fees
Flight::route('POST /dedicated/@id', function ($id) use ($db) {
    $dailyFee = 0.13;

    // activation dedicated IP fee
    $user = billUser($id, -5, 'Активация Выделенного IP адреса');

    // add user service
    $tariffDetails = tariffDetails($user['tariff']);
    switch ($tariffDetails['period']) {
        case '1':
            $product = 52;
            break;
        case '90':
            $product = 22;
            break;
        case '365':
            $product = 95;
            break;
        default:
            $product = 9;
    }

    userAddProduct($user['id'], $product);
//    $data = array(
//        'prod_code' => $product,
//        'uid' => $user['id'],
//        'qnt' => 1,
//    );
//    $db->from('products_services_users')->insert($data)->execute();

    // calculate dedicated IP cost till end tariff period
    $timeDiff = $user['ab_pend'] - time();
    $daysCount = ceil($timeDiff / (3600 * 24));
    $units = $daysCount * $dailyFee;
    $user = billUser($id, -$units, 'Выделенный IP адрес до окончания учетного периода');

    userOn($user);

    Flight::json($user);
});

// remove dedicated IP service from user
Flight::route('DELETE /dedicated/@id', function ($id) use ($db) {

//    $result = $db->from('products_services_users')
//        ->where('uid', $id)
//        ->where('prod_code', array(52, 22, 95, 9))
//        ->delete()
//        ->execute();

    $result = userRemoveProducts($id, array(52, 22, 95, 9));

    Flight::json($result);
});

// remove user
Flight::route('DELETE /user/@id', function ($id) use ($db) {

    $result = $db->from('users')
        ->where('id', $id)
        ->delete()
        ->execute();

    Flight::json($result);
});

// change user IP
Flight::route('POST /ip/@id', function ($id) use ($db) {
    $field = getUserField($id);

    if ($field == 'login') {
        $id = $db
            ->from('users')
            ->where('login', $id)
            ->value('id');
    }

    $old = $_POST['old'];
    $new = $_POST['new'];

    $setIP = $old ? "REPLACE(`ip`, '$old', '$new')" : "CONCAT(`ip`, ' $new')";

    // internet OFF for old IP
    if ($old)
        userOff($id);

    // change user IP
    $db->sql("UPDATE users SET `ip` = $setIP WHERE `id` = '$id'")->execute();

    // internet ON for new IP - with payout action
    //file_get_contents("https://ss.soho.net.ua/scripts/fwon.php?id={$id}&ip=NA");

    Flight::json(true);
});

/**
 * tariff change to lower price = 30 UAH
 * from 40 (unfreeze) - 6 UAH
 * GBZ = 30 UAH
 *
 * tariff_next = <tariff>
 *
 * if tariff = 40
 *      ab_pend = time()
 *      block = 0
 */
// create/update users
Flight::route('POST /user(/@id)', function ($id) use ($db) {
    $result = null;
    $comment = empty($_POST['comment']) ? '' : mysql_real_escape_string(cp2utf($_POST['comment'], true)) . PHP_EOL;

    if ($id) {
        if (!empty($_POST['action'])) {
            $action = $_POST['action'];
            if ($action == 'off') {
                userOff($id);
            } elseif ($action == 'on') {
                userOn($id);
            } elseif($action == 'hardOn') {
                userHardOn($id);
            }
        } elseif (!empty($_POST['tariff'])) {
            $data = array(
                'tariff_next' => $_POST['tariff'],
            );

            if (!empty($_POST['ip']))
                $data['ip'] = $_POST['ip'];

            $user = getUser($id);
            if ($user['tariff'] < 100) {
                $data['block'] = '0';
                $data['ab_pend'] = time();

                if ($user['tariff'] == 40) {
                    billUser($user['id'], -1, 'Разморозка счёта');
                }
            }

            if ($_POST['tariff'] == 40) {
                billUser($user['id'], -1, 'Заморозка счета');
            } elseif (isNextTariffLower($user['tariff'], $_POST['tariff'], $user['id'])) {
                billUser($user['id'], -5, 'Переход на более дешевый пакет');
            }

            $result = $db->from('users')->where(array('id' => $user['id']))->update($data)->execute();

        } elseif (!empty($_POST['state'])) {
            $state = $_POST['state'];
            $result = $db->from('users')->where(array('id' => $id))->update(array('state' => $state))->execute();

            if ($state == 'inactive')
                userRemoveProducts($id);

        } elseif (!empty($_POST['email'])) {
            $email = $_POST['email'];
            $result = $db->from('users')->where(array('id' => $id))->update(array('email' => $email))->execute();
        }

        if ($comment) {
            $result = $db->sql("UPDATE users SET juridical_address = CONCAT('{$comment}', juridical_address) WHERE id = {$id}")->execute();
        }

    } else {
        $id = empty($_POST['id']) ? null : $_POST['id'];
        $login = empty($_POST['login']) ? null : $_POST['login'];

        $ip = empty($_POST['ip']) ? null : $_POST['ip'];
        $name = empty($_POST['name']) ? null : cp2utf($_POST['name'], true);
        $tariff = empty($_POST['tariff']) ? null : $_POST['tariff'];

        if ($name && $tariff) {
            $date = new DateTime();
            $periodStart = $date->getTimestamp();

            $tariffDetails = tariffDetails($tariff);
            switch ($tariffDetails['period']) {
                case '1':
                    $interval = 'P1D';
                    break;
                case '90':
                    $interval = 'P3M';
                    break;
                case '365':
                    $interval = 'P1Y';
                    break;
                default:
                    $interval = 'P1M';
            }

            $date->add(new DateInterval($interval));
            $periodEnd = $date->getTimestamp();

            $price = priceTariff($tariff);

            $data = array(
                'password' => '11nkwRIV7NHqA',
                'full_name' => $name,
//                'ip' => $ip,
                'bill' => -$price,
                'reg_date' => $periodStart,
                'ab_pstart' => $periodStart,
                'ab_pend' => $periodEnd,
                'tariff' => $tariff,
                'juridical_address' => $comment,
            );

            if ($ip)
                $data['ip'] = $ip;

            // new
            if ($id) {
                $data['id'] = $id;
                $data['login'] = $login;
            }

            $result = $db->from('users')
                ->insert($data)
                ->execute();

            // add extra services
            isNextTariffLower(null, $tariff, $id);

            if ($result && !$login) {
                $id = $db->insert_id;
                $login = 'ID' . $id;
                $db->from('users')
                    ->where(array('id' => $id))
                    ->update(array('login' => $login))
                    ->execute();
            }

            if ($result)
                addUserToGroup($id);

            $result = $id;
        }
    }

    Flight::json($result);
});

// get user details
Flight::route('GET /user/@id', function ($id) use ($db) {
    $user = array();
    $field = 'id';
    if (strlen($id) == 28) {
        $id = $db
            ->from('sessions')
            ->where('s_id', $id)
            ->where('is_live', 1)
            ->value('uid');
    } else
        $field = getUserField($id);

    if ($id) {
        $user = $db
            ->from('users')
            ->where($field, $id)
            ->select(array(
                'id',
                'login',
                'password',
                'full_name',
                'ip',
                'ip_type',
                'bill',
                'credit',
                'block',
                'fw_on',
                'reg_date',
                'email',
                'state',
                'ab_pstart',
                'ab_pend',
                'tariff',
                'tariff_next',
                'juridical_address',
                'actual_address',
                'tax_number',
            ))
            ->one();

        if ($user) {
            $user = cp2utf($user);
            $user['bill'] *= RATE;
            $user['ip'] = parseIps($user['ip']);

            if (isset($_GET['cost'])) {

                $tariffCost = priceTariff($user['tariff_next']) * RATE;

                if($tariffCost == 0) $tariffCost = priceTariff($user['tariff']) * RATE;

                $user['cost'] = array(
                    'tariff' => $tariffCost,
                    'extra' => priceExtra($user['id']) * RATE,
                );


            }

            if (isset($_GET['tariff'])) {
                $user['tariff'] = cp2utf(tariffDetails($user['tariff']));
            }

            if(isset($_GET['tariff-next'])) {
                $user['tariff-next'] = cp2utf(tariffDetails($user['tariff_next']));
            }

            if (isset($_GET['service'])) {
                $user['service'] = servicesExtra($user['id']);
            } else if(isset($_GET['servicePrice'])) {
                $user['service'] = servicesExtra($user['id']);

                if($user['service']) foreach ($user['service'] as $userService) {
                    $user['activeServices'][getServiceName($userService['prod_code'])] = getServicePrice($userService['prod_code']) * RATE;
                }

            } else {
                $user['service'] = servicesExtra($user['id']);

                if($user['service']) foreach ($user['service'] as $userService) {
                    $user['activeServices'][getServiceName($userService['prod_code'])] = $userService['qnt'];
                }

            }
        }
    }

    Flight::json($user);
});



Flight::route('GET /user-login/@login', function ($login) use ($db) {
    $user = array();
    $field = 'login';
//    if (strlen($id) == 28) {
//        $id = $db
//            ->from('sessions')
//            ->where('s_id', $id)
//            ->where('is_live', 1)
//            ->value('uid');
//    } else
//        $field = getUserField($id);

    if ($login) {
        $user = $db
            ->from('users')
            ->where($field, $login)
            ->select(array(
                'id',
                'login',
                'password',
                'full_name',
                'ip',
                'ip_type',
                'bill',
                'credit',
                'block',
                'fw_on',
                'reg_date',
                'email',
                'ab_pstart',
                'ab_pend',
                'tariff',
                'tariff_next',
                'juridical_address',
                'actual_address',
                'tax_number',
            ))
            ->one();

        if ($user) {
            $user = cp2utf($user);
            $user['bill'] *= RATE;
            $user['ip'] = parseIps($user['ip']);

            if (isset($_GET['cost'])) {
                $user['cost'] = array(
                    'tariff' => priceTariff($user['tariff_next']) * RATE,
                    'extra' => priceExtra($user['id']) * RATE,
                );
            }

            if (isset($_GET['tariff'])) {
                $user['tariff'] = cp2utf(tariffDetails($user['tariff']));
            }

            if(isset($_GET['tariff-next'])) {
                $user['tariff-next'] = cp2utf(tariffDetails($user['tariff_next']));
            }

            if (isset($_GET['service'])) {
                $user['service'] = servicesExtra($user['id']);
            } else if(isset($_GET['servicePrice'])) {
                $user['service'] = servicesExtra($user['id']);

                if($user['service']) foreach ($user['service'] as $userService) {
                    $user['activeServices'][getServiceName($userService['prod_code'])] = getServicePrice($userService['prod_code']) * RATE;
                }
            } else {
                $user['service'] = servicesExtra($user['id']);

                if($user['service']) foreach ($user['service'] as $userService) {
                    $user['activeServices'][getServiceName($userService['prod_code'])] = $userService['qnt'];
                }

            }
        }
    }

    Flight::json($user);
});



Flight::route('GET /user-services/@id', function ($id) use ($db) {
    $user['service'] = servicesExtra($id);

    if($user['service']) foreach ($user['service'] as $userService) {
        $user['activeServices'][getServiceName($userService['prod_code'])] = getServicePrice($userService['prod_code']);
    }

    if (isset($_GET['tariff'])) {
        $user['tariff'] = cp2utf(tariffDetails($user['tariff']));
    }

    Flight::json($user);
});


// get user details
Flight::route('GET /full-user-info/@id', function ($id) use ($db) {
    $user = array();
    $field = 'id';
    if (strlen($id) == 28) {
        $id = $db
            ->from('sessions')
            ->where('s_id', $id)
            ->where('is_live', 1)
            ->value('uid');
    } else
        $field = getUserField($id);

    if ($id) {
        $user = $db
            ->from('users')
            ->where($field, $id)
            ->select(array(
                'id',
                'login',
                'password',
                'full_name',
                'ip',
                'bill',
                'credit',
                'block',
                'fw_on',
                'reg_date',
                'email',
                'ab_pstart',
                'ab_pend',
                'tariff',
                'tariff_next',
                'juridical_address',
                'actual_address',
                'tax_number',
            ))
            ->one();

        if ($user) {
            $user = cp2utf($user);
            $user['bill'] *= RATE;
            $user['ip'] = parseIps($user['ip']);

            if (isset($_GET['cost'])) {
                $user['cost'] = array(
                    'tariff' => priceTariff($user['tariff_next']) * RATE,
                    'extra' => priceExtra($user['id']) * RATE,
                );
            }

            if (isset($_GET['tariff'])) {
                $user['tariff'] = cp2utf(tariffDetails($user['tariff']));
            }

            if(isset($_GET['tariff-next'])) {
                $user['tariff-next'] = cp2utf(tariffDetails($user['tariff_next']));
            }

//            if (isset($_GET['service'])) {
//                $user['service'] = servicesExtra($user['id']);
//            } else if(isset($_GET['servicePrice'])) {
//                $user['service'] = servicesExtra($user['id']);
//
//                if($user['service']) foreach ($user['service'] as $userService) {
//                    $user['activeServices'][getServiceName($userService['prod_code'])] = getServicePrice($userService['prod_code']) * RATE;
//                }
//            } else {
                $user['service'] = servicesUserFull($user['id']);

                if($user['service']) foreach ($user['service'] as $userService) {
                    $user['activeServices'][getServiceName($userService['prod_code'])] = $userService['qnt'];
                }

//            }
        }
    }

    Flight::json($user);
});



Flight::route('GET /expanded-debtor/@param', function ($param) use ($db) {

    $users = array();
    $client = array();

    $sql = <<<SQL
SELECT users.id,users.login,users.bill,users.reg_date,users.ab_pstart,users.ab_pend,users.tariff, MAX(bh.date) `date`
FROM users
INNER JOIN balance_history bh ON bh.uid = users.id and bh.balance_in > 0
WHERE users.bill < 0
GROUP BY users.id
SQL;

    $client = $db->sql($sql)->many();


    if($client) {
        foreach($client as $cl) {
            array_push( $users, cp2utf($cl));
        }
    }

    Flight::json($users);
});


Flight::route('POST /add-user-service/@id/@service', function($id, $service) use ($db) {

    userAddProduct($id, $service, $quantity = 1);

    Flight::json(true);

});

// bill history 1
Flight::route('GET /last-bill-history/@user', function ($user) use ($db) {
    $history = $db->from('balance_history')
        ->limit(10)
        ->sortDesc('date')
        ->where("uid = '".$user."' and gm_target in(122,123,124,125)")
        ->select('date')
        ->many();

    Flight::json($history);
});



Flight::route('GET /clients/@login', function ($login) use ($db) {
    $user = array();
    $field = 'login';

    if ($login) {
        $user = $db
            ->from('users')
            ->where($field, $login)
            ->select(array(
                'id',
                'login',
                'email',
            ))
            ->one();
    }

    Flight::json($user);
});


Flight::route('GET /info-from-billing/', function () use ($db) {

    $users = array();
    $client = array();

    $sql = <<<SQL
SELECT u.id as id,u.bill as bill,u.ab_pstart as ab_pstart,u.ab_pend as ab_pend,u.tariff as tariff,u.tariff_next as tariff_next, (SUM(IFNULL(ps.price, 0)) + IFNULL(ps2.price, 0)) as sum_bill
 FROM `users` u
LEFT JOIN `products_services_users` psu ON psu.uid = u.id
LEFT JOIN `products_services_tariffs` pst ON pst.tariff_id = u.tariff_next
LEFT JOIN `products_services` ps ON psu.prod_code = ps.id
LEFT JOIN  `products_services` ps2 ON pst.prod_code = ps2.id
WHERE u.block = "0" and u.tariff_next > 100 and u.bill > 0 and u.discount_eq = "0" and u.tariff_next <> 60002
GROUP BY u.id
SQL;

    $client = $db->sql($sql)->many();


    if($client) {
        foreach($client as $cl) {


            $user['service'] = servicesExtra($cl['id']);
            $privilege = false;

            if($user['service']) foreach ($user['service'] as $userService) {
                //$user['activeServices'][getServiceName($userService['prod_code'])] = $userService['qnt'];
                if(getServicePrice($userService['prod_code']) <= 0) {
                    $privilege = true;
                }
            }


            if(!$privilege) array_push( $users, cp2utf($cl));
        }
    }

    Flight::json($users);
});

Flight::route('GET /user-tariff-stat/', function () use ($db) {

    $tariffs = array();
    $users = array();

    $sql = <<<SQL
SELECT tc.name as name, tc.tid as tid, count(tariff) as tariff, COUNT(IF(bill >= 0, 1, NULL)) as plus, COUNT(IF(bill < 0, 1, NULL)) as minus, COUNT(IF(`tariff_next` = 40, 1, NULL)) as freeze, COUNT(IF(`state` = 'active', 1, NULL)) as active, COUNT(IF(`state` = 'inactive', 1, NULL)) as inactive
 FROM `users` u
INNER JOIN tariffs_current tc ON tc.tid = u.tariff
Group by u.tariff
SQL;

    $users = $db->sql($sql)->many();

    if($users) {
        foreach($users as $us) {
            array_push( $tariffs, cp2utf($us));
        }
    }


    Flight::json($tariffs);
});


Flight::route('GET /user-tariff-next-stat/', function () use ($db) {

    $tariffs = array();
    $users = array();

    $sql = <<<SQL
SELECT tc.name as name, u.tariff_next, count(tariff_next) as tariff, COUNT(IF(bill >= 0, 1, NULL)) as plus, COUNT(IF(bill < 0, 1, NULL)) as minus, COUNT(IF(`state` = 'active', 1, NULL)) as active, COUNT(IF(`state` = 'inactive', 1, NULL)) as inactive
 FROM `users` u
INNER JOIN tariffs_current tc ON tc.tid = u.tariff_next
Group by u.tariff_next 
SQL;

    $users = $db->sql($sql)->many();

    if($users) {
        foreach($users as $us) {
            array_push( $tariffs, cp2utf($us));
        }
    }


    Flight::json($tariffs);
});


Flight::route('GET /user-product-service/', function () use ($db) {

    $service = array();
    $users = array();

    $sql = <<<SQL
SELECT ps.prod_name as name,
 psu.prod_code as prod_code,
  SUM( psu.qnt ) as qnt,
 SUM( CASE WHEN u.bill >=0
THEN psu.qnt
ELSE NULL 
END ) AS plus,
 SUM( CASE WHEN u.bill <0
THEN psu.qnt
ELSE NULL 
END ) AS minus,
 SUM( CASE WHEN u.`tariff_next` =40
THEN psu.qnt
ELSE NULL 
END ) AS freeze,
 SUM( CASE WHEN u.`state` =  'active'
THEN psu.qnt
ELSE NULL 
END ) AS active,
 SUM( CASE WHEN u.`state` =  'inactive'
THEN psu.qnt
ELSE NULL 
END ) AS inactive
FROM  `users` u
INNER JOIN products_services_users psu ON psu.uid = u.id
INNER JOIN products_services ps ON ps.id = psu.prod_code
WHERE psu.prod_code <>126
AND psu.prod_code <>129
GROUP BY psu.prod_code
SQL;

    $users = $db->sql($sql)->many();

    if($users) {
        foreach($users as $us) {
            if(getServicePrice($us['prod_code'])) {
                $us['price'] = getServicePrice($us['prod_code']);
            } else {
                $us['price'] = 0;
            }
            array_push( $service, cp2utf($us));
        }
    }


    Flight::json($service);
});



Flight::route('GET /user-have-device/', function () use ($db) {

    $dateNow = date('Y-m-d');
    $nextDay = date('Y-m-d', strtotime($dateNow. ' + 1 days'));
    $users = array();
    $client = array();

    $sql = <<<SQL
SELECT u.* FROM `users` u
INNER JOIN `products_services_users` psu ON u.id = uid
 WHERE 
psu.prod_code in (79,110,80,73,85,74,87,88)
SQL;


    $client = $db->sql($sql)->many();


    if($client) {
        foreach($client as $cl) {

            if(date('Y-m-d', $cl['ab_pend']) == $nextDay) {
                array_push($users, cp2utf($cl));
            }

        }
    }

    Flight::json($users);
});


// remove dop tv service from user
Flight::route('GET /user-delete-dop-device/@id', function ($id) use ($db) {

    $result = false;

    if($id) $result = userRemoveArrayProducts($id);

    Flight::json($result);
});


// return users for history
Flight::route('GET /users-info-stat-debts/', function () use ($db) {

    $clients = array();
    $users = array();

    $sql = <<<SQL
    SELECT `id`,`login`,`bill`,`credit`,`tariff`,`tariff_next`, `block`, `state`, `ip` FROM `users`
SQL;
    $users = $db->sql($sql)->many();


    if($users) {
        foreach($users as $us) {
            array_push( $clients,$us);
        }
    }


    Flight::json($clients);
});



Flight::route('GET /check-card-status/@from/@to', function($from, $to) use ($db) {

    $countCard = 0;

    $sql = <<<SQL
   SELECT count(*) card FROM `icards` WHERE id >= $from and id <= $to and `card_status` = 1
SQL;
    $card = $db->sql($sql)->many();


    Flight::json($card);
});


//migrate-messages
Flight::route('GET /migrate-messages', function() use ($db) {

    $sql = <<<SQL
   SELECT * FROM `messages` where id > 208398 and id <= 208399
SQL;
    $messages = $db->sql($sql)->many();

    $array = array();

    if($messages) {
        foreach($messages as $m) {
            array_push($array, cp2utf($m));
        }
    }

    Flight::json($array);
});


// check card status
Flight::route('GET /card-status/@number/@pin', function ($number, $pin) use ($db) {
    $card = $db->from('icards')
        ->where("id = $number and card_serial = $pin")
        ->select(array('card_nominal', 'card_expire_date', 'card_status'))
        ->one();

    Flight::json($card);
});

//log-card-error
Flight::route('POST /log-card-error/', function () use ($db) {
    $result = false;

    if (!empty($_POST['message']) && !empty($_POST['login'])) {
        $data = array(
            'event_date' => strtotime(date('Y-m-d H:i')),
            'event_type' => 'icard',
            'event_message' => $_POST['message'],
            'event_user' => $_POST['login'],
        );

        $result = $db->from('UTM_logs')
            ->insert($data)
            ->execute();

    }

    Flight::json($result);
});



// add payment to user account
Flight::route('POST /card-activate/@id', function ($id) use ($db) {
    $result = false;

    if ($id && !empty($_POST['cardNumber']) && !empty($_POST['cardPin'])) {
        $result = cardBill($id, $_POST['cardNumber'], $_POST['cardPin']);
    }

    Flight::json($result);
});


// add payment to user account
Flight::route('POST /change-password/', function () use ($db) {
    $result = false;

    if(!empty($_POST['uid']) && !empty($_POST['login']) && !empty($_POST['newPassword']) && !empty($_POST['old_password'])) {

        $result = $db->from('users')->where(array('id' => $_POST['uid'], 'password' => $_POST['old_password'], 'login' => $_POST['login']))->update(array('password' => $_POST['newPassword']))->execute();

    }

    Flight::json($result);
});

Flight::route('POST /get-friends', function() use ($db) {
    $text = cp2utf('за абонента', true);
    $other = cp2utf('Начисление бонуса за привлеченного абонента', true);

    $sql = <<<SQL
   SELECT * FROM `bills_history` where comments like "%$text%" or comments like "%$other%" and qnt_currency > 0 and `date` > 1609498011
SQL;
    $messages = $db->sql($sql)->many();

    $array = array();

    if($messages) {
        foreach($messages as $m) {
            array_push($array, cp2utf($m));
        }
    }

    Flight::json($array);
});

Flight::route('GET /remove-year-service/@id', function ($id) use ($db) {

    $result = userRemoveProducts($id, 96);

    Flight::json($result);
});


Flight::route('GET /loan-internet/@uid', function($uid) use ($db) {

    $loan = $db->from('loan_internet')
        ->where("uid = $uid")
        ->sortDesc('id')
        ->one();

    Flight::json($loan);

});


Flight::route('POST /service-management/@id', function ($id) use ($db) {

    if ($id) {
        if (!empty($_POST['action'])) {
            $action = $_POST['action'];

            if ($action == 'internetOff') {
                userOff($id);
            }

            if ($action == 'internetOn') {
                userOn($id);
            }
        }
    }

    Flight::json(true);
});


Flight::route('GET /service-info/@uid', function ($uid) use ($db) {

    $result = array();
    if($uid) {

        $user = $db->from('users')
            ->where("id = $uid")
            ->one();

        if($user) {

            if(!empty($_GET['type']) && $_GET['type'] == 'credit') {

                $sql = <<<SQL
    SELECT credit_left,last_activate FROM loan_internet WHERE uid = '$uid' AND time_interval = '1D' AND ab_pstart = {$user['ab_pstart']} AND ab_pend = {$user['ab_pend']} ORDER BY id DESC LIMIT 1
SQL;
                $result = $db->sql($sql)->many();

            }



        }
    }

    Flight::json($result);

});


Flight::route('POST /activate-credit/@uid', function ($uid) use ($db) {

    $result = array();

    if ($uid) {
        $u_ab_pend = $_POST['ab_pend'];
        $u_ab_pstart = $_POST['ab_pstart'];

        $sql = <<<SQL
    SELECT credit_left, last_activate + 86400 AS date_end, UNIX_TIMESTAMP() as date_now, id FROM loan_internet WHERE uid = $uid AND time_interval = '1D' AND ab_pstart = $u_ab_pstart AND ab_pend = $u_ab_pend
SQL;
        $loan = $db->sql($sql)->one();

        if(!$loan) {

            $data = array(
                'uid' => $uid,
                'ab_pstart' => $u_ab_pstart,
                'ab_pend' => $u_ab_pend,
                'credit_left' => 1,
                'last_activate' => time()
            );

            $result = $db->from('loan_internet')
                            ->insert($data)
                            ->execute();


        } else if($loan) {

            if (($loan['credit_left'] >= 1) && ($loan['date_end'] < $loan['date_now'])) {

                $updateLoan = $db->from('loan_internet')
                                    ->where(array('id' => $loan['id']))
                                    ->update(array('last_activate' => time(), 'credit_left' => $loan['credit_left'] - 1))
                                    ->execute();

                $result = $updateLoan;

            }

            if($loan['date_end'] > $loan['date_now'])  $result = 'service active, no need to activate';

            if($loan['credit_left'] < 1) $result = 'no credit point left';


        }

    }

    Flight::json($result);
});



Flight::route('GET /recommend-pay', function () use ($db) {

    $users = $db->from('users')
        ->where("ab_pend >= {$_GET['dateStart']} and ab_pend <= {$_GET['dateEnd']} and tariff > 100 and tariff_next > 100")
        ->select(array('id'))
        ->many();


    Flight::json($users);
});


Flight::start();
