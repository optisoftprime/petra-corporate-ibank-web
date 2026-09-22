<?php
include 'includes/bootstrap.php';

$dbconnect = new dbConnect();
$logged_in_user = require_session();

try {
    switch ($_GET['action']) {

        case "portfolio": {
            $bankone = new BankOne();
            $rs = $bankone->getInvestments($logged_in_user['CustomerID']);
            header("HTTP/1.1 200 Ok");
            $result['resultCode'] ="00";
            $result['resultDescription'] ="Sucessful";
            $result['data'] = $rs;
            $tx_echo =  $secure->encrypt(json_encode($result));
            echo $tx_echo;
            break;
        }
        default: {
            header("HTTP/1.1 400 Bad Request");
            break;
        }
    }
} catch (Exception $ex) {
    error_log($ex);
}
