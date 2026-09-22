<?php
include 'includes/bootstrap.php';

$account = new Account();
$logged_in_user = require_session();

try {
    switch ($_GET['action']) {
        case "get_accounts": {
            $rs = $account->getAccounts($logged_in_user['CustomerID']);
            if(is_array($rs)) {
                header("HTTP/1.1 200 OK");
                $tx_echo = $secure->encrypt(json_encode($rs));
                echo $tx_echo;
            }else {
                header("HTTP/1.1 500 Internal Server Error");
            }
            break;
        }

        case "freeze_account": {
            if(isset($data['cod_acct_no']) && strlen(trim($data['cod_acct_no'])) !="") {
                // Ownership check: without it, any logged-in customer could freeze ANY
                // account bank-wide. Same pattern already used a few cases down in
                // get_account_statement.
                $all_accounts  = $account->getAccounts($logged_in_user['CustomerID']);
                $owned_acct = false;
                foreach ($all_accounts as $acct) {
                    if($acct['COD_ACCT_NO'] == trim($data['cod_acct_no'])) {
                        $owned_acct = true;
                    }
                }
                if(!$owned_acct) {
                    header("HTTP/1.1 401 Unauthorized");
                    break;
                }
                $bankOne = new BankOne();
                $response = $bankOne->freezeAccount($data['cod_acct_no'], $data['reason']);
                if($response != null) {
                    header("HTTP/1.1 200 OK");

                }else {
                    header("HTTP/1.1 400 Bad Request");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "unfreeze_account": {
            if(isset($data['cod_acct_no']) && strlen(trim($data['cod_acct_no'])) !="") {
                // Ownership check — see the identical note in freeze_account above.
                $all_accounts  = $account->getAccounts($logged_in_user['CustomerID']);
                $owned_acct = false;
                foreach ($all_accounts as $acct) {
                    if($acct['COD_ACCT_NO'] == trim($data['cod_acct_no'])) {
                        $owned_acct = true;
                    }
                }
                if(!$owned_acct) {
                    header("HTTP/1.1 401 Unauthorized");
                    break;
                }
                $bankOne = new BankOne();
                $response = $bankOne->unFreezeAccount($data['cod_acct_no'], $data['reason']);
                if($response != null) {
                    header("HTTP/1.1 200 OK");

                }else {
                    header("HTTP/1.1 400 Bad Request");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "get_account_statement": {
            if(isset($data['cod_acct_no']) && strlen(trim($data['cod_acct_no'])) !="" && validateDate($data['start_date']) && validateDate($data['end_date'])) {
                $all_accounts  = $account->getAccounts($logged_in_user['CustomerID']);
                $owned_acct = false;
                foreach ($all_accounts as $acct) {
                    if($acct['COD_ACCT_NO'] == trim($data['cod_acct_no'])) {
                        $owned_acct = true;
                    }
                }
                if($owned_acct) {
                    header("HTTP/1.1 200 OK");
                    $tx_echo = $secure->encrypt(json_encode($account->getStatement($data['cod_acct_no'],$data['start_date'],$data['end_date'])));
                    echo $tx_echo;
                }else {
                    header("HTTP/1.1 401 Unauthorized");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "get_account_statement_pdf": {
            if(isset($data['cod_acct_no']) && strlen(trim($data['cod_acct_no'])) !="" && validateDate($data['start_date']) && validateDate($data['end_date'])) {
                $all_accounts  = $account->getAccounts($logged_in_user['CustomerID']);
                $owned_acct = false;
                foreach ($all_accounts as $acct) {
                    if($acct['COD_ACCT_NO'] == trim($data['cod_acct_no'])) {
                        $owned_acct = true;
                    }
                }
                if($owned_acct) {
                    header("HTTP/1.1 200 OK");
                    $account->getStatementPDF($logged_in_user['CustomerID'],$data['cod_acct_no'],$data['start_date'],$data['end_date']);
                }else {
                    header("HTTP/1.1 401 Unauthorized");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "validate_account": {
            if(isset($data['cod_acct_no']) && strlen(trim($data['cod_acct_no'])) == 10 && isset($data['account_type']) && ($data['account_type'] == "Intra" || $data['account_type'] == "Inter")) {
                switch($data['account_type']) {
                    case "Intra": {
                        $response = $account->validateInternalAccount($data['cod_acct_no']);
                        if(is_array($response)) {
                            header("HTTP/1.1 200 OK");
                            $tx_echo = $secure->encrypt(json_encode(array("cod_acct_title"=>$response['Message']['Name'])));
                            echo $tx_echo;
                        }
                        break;
                    }
                    case "Inter": {
                        $response  = $account->validateExternalAccount($data['sortcode'],$data['cod_acct_no']);
                        if($response  != null ) {
                            header("HTTP/1.1 200 OK");
                            $response =  $secure->encrypt(json_encode($response));
                            echo $response;
                        }else {
                            header("HTTP/1.1 500 Internal Server Error");
                            $response =  $secure->encrypt(json_encode($response));
                            echo $response;
                        }
                        break;

                    }
                }
            }
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
