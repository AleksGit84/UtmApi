<?php
$uid = isset($_POST["uid"]) ? $_POST["uid"] : $_GET["uid"];
$servise = isset($_POST["service"]) ? $_POST["service"] : $_GET["service"];
$action = isset($_POST["action"]) ? $_POST["action"] : $_GET["action"];
$user_ip = $_GET['ip'];


if($servise == 'onOff' && $uid) {
    $cmd = "php /var/www/scripts/onOff.php $uid $action";
    $var = exec($cmd);
}

if ( ($servise == "freezetariff") && ($action == 'freeze') ) {
    $cmd = "php /var/www/scripts/freezetariff.php $uid $user_ip FREEZE";
    $var = exec($cmd);
    //$go_url = "Location: /scripts/moreservice.php?uid=".$uid."&action=info&service=freezetariff";
    //header($go_url); exit;
}

if ( ($servise == "freezetariff") && ($action == 'unfreeze') ) {
    $cmd = "php /var/www/scripts/freezetariff.php $uid $user_ip UNFREEZE";
    $var = exec($cmd);
    if ($var == "ERROR CODE 5") {
         ?>
        <div class="form">
            <form action="" method="post">
                <p><span class="error">Ошибка!</span> Разморозить можно только замороженный счёт.</p>
                <p class="grey"><input type="button" value="Разморозить счет" id="submit" disabled /></p>
            </form>
        </div>
        <?php echo "</body></html>"; exit;
    }
//    else {
//        //$go_url = "Location: /scripts/moreservice.php?uid=".$uid."&action=info&service=freezetariff";
//        //header($go_url); exit;
//    }
}

//echo $Headers;

$coust_freeze = "1&nbsp;юнит";

$ArrayOfMonth[1]  = "января";
$ArrayOfMonth[2]  = "февраля";
$ArrayOfMonth[3]  = "марта";
$ArrayOfMonth[4]  = "апреля";
$ArrayOfMonth[5]  = "мая";
$ArrayOfMonth[6]  = "июня";
$ArrayOfMonth[7]  = "июля";
$ArrayOfMonth[8]  = "августа";
$ArrayOfMonth[9]  = "сентября";
$ArrayOfMonth[10] = "октября";
$ArrayOfMonth[11] = "ноября";
$ArrayOfMonth[12] = "декабря";



// creditbutton block start
if ($servise == "creditbutton") {
    if ($action == "info") {
        $cmd = "php /var/www/scripts/creditbutton.php $uid $user_ip INFO";
        $var = exec($cmd, $output);
        $Status = explode(' ',$var);
        $PrintStatus[0] = "ноль активаций";
        $PrintStatus[1] = "одна активация";
        $PrintStatus[2] = "две активации";
        $Status[0]=$Status[0]*1;


        ?>

        <div class="form">
            <?php if ($var == "ERROR CODE 1") { ?>
                <form action="" method="post">
                    <p><span class="error">Услуга недоступна.</span> Напишите сообщение администратру, код ошибки №101.</p>
                    <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif ($var == "ERROR CODE 0") { ?>
                <form action="" method="post">
                    <p><span class="error">Услуга недоступна.</span> Напишите сообщение администратру, код ошибки №100.</p>
                    <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif ($var == "ERROR CODE 2") { ?>
                <form action="" method="post">
                    <p>На счету положительный баланс, включать кредит нет необходимости.</p>
                    <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif ($var == "ERROR CODE 3") { ?>
                <form action="" method="post">
                    <p><span class="error">Функция кредита недоступна для подневных пакетов.</span></p>
                    <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif ($var == "ERROR CODE 4") { ?>
                <form action="" method="post">
                    <p><span class="error">Функция кредита недоступна для замороженного счета.</span></p>
                    <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif (($Status[0] > 1) && ($Status[1]=="ON")) { ?>
                <form action="" method="post">
                    <p>Работа в интернете в кредит активирована до <span class="ok"><?=date("d.m.Y", $Status[2]+86400)?>, <?=date("H:i",$Status[2]+86400)?></span>, у вас есть <span class="ok"><?=$PrintStatus[$Status[0]]?></span>.</p>
                    <p class="green"><input type="button" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            <?php } elseif ( ($Status[0] == 2) && ($Status[1]=="OFF")) { ?>
                <form id="send-credit-form" method="post">
                    <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                    <input type="hidden" id="service" name="service" value="creditbutton">
                    <input type="hidden" id="action" name="action" value="on">
                    <p>У вас есть <span class="ok"><?=$PrintStatus[$Status[0]]?></span> до конца текущего учетного периода.</p>
                    <p class="grey"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" data-confirm="Подтвердите активацию кредита. \r\n\r\nУ вас есть <?=$PrintStatus[$Status[0]]?> до конца текущего учетного периода. \r\n\r\nКредит будет активирован на одни сутки."/></p>
                </form>
            <?php } elseif ( ($Status[0] == 1) && ($Status[1]=="OFF")) { ?>
                <form id="send-credit-form" method="post">
                    <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                    <input type="hidden" id="service" name="service" value="creditbutton">
                    <input type="hidden" id="action" name="action" value="on">
                    <p>У вас есть <span class="ok"><?=$PrintStatus[$Status[0]]?></span> до конца текущего учетного периода.</p>
                    <p class="grey"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" data-confirm="Подтвердите активацию кредита. \r\n\r\nУ вас есть <?=$PrintStatus[$Status[0]]?> до конца текущего учетного периода. \r\n\r\nКредит будет активирован на одни сутки."/></p>
                </form>
            <?php } elseif ( $Status[1]=="ON" ) { ?>
            <form id="send-credit-form" method="post">
                <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                <input type="hidden" id="service" name="service" value="creditbutton">
                <input type="hidden" id="action" name="action" value="on">
                <?php if ($Status[0] == 0) { ?>
                    <p><span class="error">Лимит активаций исчерпан.</span></p>
                <?php } else { ?>
                    <p>У вас есть <span class="ok"><?=$PrintStatus[$Status[0]]?></span> до конца текущего учетного периода.</p>
                <?php } ?>
                <!-- <p>Последняя активация была произведена <span class="ok"><?=date("d.m.Y", $Status[2])?></span> в <span class="ok"><?=date("H:i",$Status[2])?></span></p>-->
                <p>Работа в интернете в кредит активирована до <span class="ok"><?=date("d.m.Y", $Status[2]+86400)?>, <?=date("H:i",$Status[2]+86400)?></span>.</p>
                <p class="green"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                <?php } elseif ( ($Status[0] == 0) || ($Status[0] < 1) ) { ?>
                    <form action="" method="post">
                        <p><span class="error">Лимит активаций исчерпан.</span> Вы сможете активировать кредит в следующем учетном периоде.</p>
                        <p class="red"><input type="submit" name="mysubmit" value="Активировать кредит" id="submit" disabled /></p>
                    </form>
                <?php } ?>
        </div>
        <?php
    }

    if ($action == "on")
    {
        $cmd = "php /var/www/scripts/creditbutton.php $uid $user_ip ON";
        $var = exec($cmd);
        if ($var == "ERROR CODE 2") { ?>
            <div class="form">
                <form action="" method="post">
                    <p><span class="error1">На счету положительный баланс</span>, включать кредит нет необходимости.</p>
                    <p class="red"><input type="button" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        } elseif($var == "OK CODE 1") {
            $cmd = "php /var/www/scripts/creditbutton.php $uid $user_ip INFO";
            $var = exec($cmd);
            $Status = explode(' ',$var);
            $PrintStatus[0] = "ноль&nbsp;активаций";
            $PrintStatus[1] = "одна&nbsp;активация";
            $PrintStatus[2] = "две&nbsp;активации";
            $now = time() + 24*60*60;
            ?>
            <div class="form">
                <form action="" method="post">
                    <p>Кредит на работу в интернете активирован до <span class="ok"><?=date("d.m.Y, H:i", $now)?></span>, у вас есть <span class="ok"><?=$PrintStatus[$Status[0]]?></span>.</p>
                    <p class="green"><input type="button" value="Активировать кредит" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        } else {
            if($var == "Error 1001") {
                ?>
                <div class="form">
                    <form action="" method="post">
                        <p><span class="error">Сессия устарела.</span>Пожалуйста нажмите «Выйти», после чего авторизуйтесь снова.</p>
                        <p class="red"><input type="button" value="Активировать кредит" id="submit" disabled /></p>
                    </form>
                </div>
                <?php
            }
        }
    }

}

// freezetariff block start

if ($servise == "freezetariff")
{

    $PrintStatus[0] = "0&nbsp;бесплатных&nbsp;активаций";
    $PrintStatus[1] = "1&nbsp;бесплатная&nbsp;активация";
    $PrintStatus[2] = "2&nbsp;бесплатные&nbsp;активации";
    $PrintStatus[3] = "3&nbsp;бесплатные&nbsp;активации";
    $PrintStatus[4] = "4&nbsp;бесплатные&nbsp;активации";
    $PrintStatus[5] = "5&nbsp;бесплатных&nbsp;активаций";
    $PrintStatus[6] = "6&nbsp;бесплатных&nbsp;активаций";
    $PrintStatus[7] = "7&nbsp;бесплатных&nbsp;активаций";
    $PrintStatus[8] = "8&nbsp;бесплатных&nbsp;активаций";
    $PrintStatus[9] = "9&nbsp;бесплатных&nbsp;активаций";

    if ($action == "info")
    {
        $cmd = "php /var/www/scripts/freezetariff.php $uid $user_ip INFO";
        $var = exec($cmd);
        $Status = explode(' ',$var);
        if ($var == "ERROR CODE 3") {
            ?>
            <div class="form">
                <form action="" method="post">
                    <p>Для вашего тарифного плана услуга <span class="error">не доступна</span>. Ручная заморозка счета доступна для <a href="http://sohonet.ua/services/internet#play" target="_blank">подневных тарифных планов Play</a>.</p>
                    <p class="red"><input type="button" value="Заморозить счет" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        } elseif ($var == "ERROR CODE 7") {
            ?>
            <div class="form">
                <form action="" method="post">
                    <p>У вас <span class="error">не достаточно</span> средств на счету для активации услуги.</p>
                    <p class="red"><input type="button" value="Заморозить счет" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        }
        elseif (($Status[0] == 1) && ($Status[2] == 0)) {
            ?>
            <div class="form">
                <form action="" method="post">
                    <p>Ваш счет <span class="error">заблокирован</span>, для разморозки напишите сообщение администратору.</p>
                    <p class="red"><input type="button" value="Разморозить счет" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        }
        elseif (($Status[0] == 1) && ($Status[2] == 1)) {
            ?>
            <div class="form">
                <form id="send-freeze-form" method="post">
                    <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                    <input type="hidden" id="service" name="service" value="freezetariff">
                    <input type="hidden" id="action" name="action" value="unfreeze">
                    <p>Ваш счет <span class="error">заморожен</span>, нажмите на кнопку 'Разморозить счет' для его разморозки.</p>
                    <p class="green"><input type="submit" name="mysubmit" value="Разморозить счет" id="submit" data-confirm="Подтвердите разморозку счета. \r\n\r\nПосле разморозки со счета будут списаны средства за оплату следующего учетного периода в полном обьеме, согласно выбранного тарифного плана." /></p>
                </form>
            </div>
            <?php
        }
        elseif (($Status[0] == 0) && ($Status[1] >= 1)) {
            ?>
            <div class="form">
                <form id="send-freeze-form" method="post">
                    <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                    <input type="hidden" id="service" name="service" value="freezetariff">
                    <input type="hidden" id="action" name="action" value="freeze">
                    <p>У вас есть <span class="ok"><?=$PrintStatus[$Status[1]]?></span> до конца <span class="ok"><?=$ArrayOfMonth[date("n")];?></span>.</p>
                    <p class="green"><input type="submit" name="mysubmit" value="Заморозить счет" id="submit" data-confirm="Подтвердите заморозку счета. \r\n\r\nУ вас есть <?=$PrintStatus[$Status[1]]?> до конца <?=$ArrayOfMonth[date("m")];?>. \r\n\r\nСчет будет заморожен по окончанию текущего учетного периода. Отменить данное действие будет невозможно!" /></p>
                </form>
            </div>
            <?php
        }
        elseif (($Status[0] == 0) && ($Status[1] < 1)) {
            ?>
            <div class="form">
                <form id="send-freeze-form" method="post">
                    <input type="hidden" id="uid" name="uid" value="<?=$uid?>">
                    <input type="hidden" id="service" name="service" value="freezetariff">
                    <input type="hidden" id="action" name="action" value="freeze">
                    <p><span class="error">Активация платная</span>, стоимость активации&nbsp;&nbsp;<span class="ok"><?=$coust_freeze?></span>.</p>
                    <p class="grey"><input type="submit" name="mysubmit" value="Заморозить счет" id="submit" data-confirm="Подтвердите заморозку счета. \r\n\r\nСтоимость активации <?=$coust_freeze?>. \r\n\r\nСчет будет заморожен по окончанию текущего учетного периода. Отменить данное действие будет невозможно!" /></p>
                </form>
            </div>
            <?php
        }
        elseif ($Status[0] == 2) {
            ?>
            <div class="form">
                <form action="" method="post">
                    <!--<p><span class="error">Ошибка!</span> Разморозить можно только тарифный план <span class="error">&laquo;Замороженный доступ"</span>.</p>-->
                    <p>Счет будет заморожен по окончанию текущего учетного периода.</p>
                    <p class="green"><input type="button" value="Разморозить счет" id="submit" disabled /></p>
                </form>
            </div>
            <?php
        }
    }
}

if ($servise == "turbobutton")
{
    if ($action == "info")
    {
        $var = `/scripts/turbobutton $uid $user_ip INFO`;
        echo "\n";
        echo "$var";
        echo "\n";
    }

    if ($action == "on")
    {
        $var = `/scripts/creditbutton $uid $user_ip ON`;
        echo "\n";
        echo "$var";
        echo "\n";
    }

}

?>

</body>
</html>
