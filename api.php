<?php

require_once("parse.php");

$NordPool = new ParseNordPool;
$NordPool->init();

if(isset($_GET['callback'])) {

    $data = $NordPool->returnCommaSeparatedRange();

    header("content-type: application/json");

    echo $_GET['callback']. '(['.implode(',',$data).'])';
}

if (isset($_POST['mode'])) {

    switch($_POST['mode']){
        case "get_prices" :
            echo json_encode($NordPool->getPrices());
            break;
        default:
            echo ":(";
    }
}

if (isset($_GET['cache_mode'])) {

    switch($_GET['mode']){
        case "clear":
            file_put_contents("cache.dat", "");
            break;
        default:
            echo ":(";
    }
}
