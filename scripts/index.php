<?php
require '../vendor/init.php';
require '../vendor/autoload.php';




Flight::route('GET moreservice/@data', function ($data) {


//    $uid = $data['uid'];
//    $user_ip = $data['ip'];
//    $action = $data['action'];

//    $options = array(
//        'http' => array(
//            'method' => 'GET',
//            'content' => http_build_query($data))
//    );
//
//// Create a context stream with
//// the specified options
////    $stream = stream_context_create($options);
//
//
//    $file = file_get_contents('moreservice.php', false, $options);

    Flight::json($data);

});


Flight::start();
