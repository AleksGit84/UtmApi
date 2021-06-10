<pre>
<?php
/**
 * Add new `users` group
 */

require '../vendor/init.php';

$users = $db->from('users')
//    ->limit(10)
    ->sortAsc('id')
    ->where("id > 35558 AND id NOT IN (35845, 35892)")
    ->select(array('id', 'bill', 'tariff'))
    ->many();

$usersUpdate = array();
foreach ($users as $user) {
    addUserToGroup($user['id']);

    if ($user['bill'] < -1 && in_array($user['tariff'], getDailyTariffs())) {
        $usersUpdate[] = $user['id'];
    }
}

echo $db->from('users')->where(array('id' => $usersUpdate))->update(array('bill' => -1))->execute();
