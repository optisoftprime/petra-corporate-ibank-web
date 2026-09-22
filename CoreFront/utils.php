<?php
include 'includes/bootstrap.php';

$db = new dbConnect();

try {
    if (isset($headers['X-Authid-Token'], $headers['X-Clientid'])) {
        // $current_session was looked up by token alone (bootstrap.php); cross-check
        // its own UserID against the caller's claimed X-Clientid, same as require_session().
        if ($current_session && $current_session['UserID'] === $headers['X-Clientid']) {
            switch($_GET['action']) {
                case "get_banks": {
                    header("HTTP/1.1 200 OK");
                    $bankone = new BankOne();
                    $response = $secure->encrypt(json_encode($bankone->getBanks()));
                    echo $response;
                    break;
                }

                case "get_states": {
                    header("HTTP/1.1 200 OK");
                    $response = $secure->encrypt(json_encode($db->get_data("Select st_name from State order by st_name asc ")));
                    echo $response;
                    break;
                }

                case "validate_bvn": {
                    if(isset($data['bvn']) && strlen(trim($data['bvn'])) > 10 ) {
                        $response = $prembly->validateBVN($data['bvn']) ;
                        if($response != false ) {
                            header("HTTP/1.1 200 OK");
                            $response = $secure->encrypt(json_encode($response));
                            echo $response;
                        }else {
                            header("HTTP/1.1 500 Internal Server Error");
                            $response = $secure->encrypt(json_encode(array("description"=>"Error validating BVN")));
                            echo $response;
                        }
                    }else {
                        header("HTTP/1.1 400 Bad Request");
                        $response = $secure->encrypt(json_encode(array("description"=>"Invalid BVN ")));
                        echo $response;
                    }
                    break;
                }

                case "validate_reg_no": {
                    if(isset($data['rc_number']) && trim($data['rc_number']) != "" ) {
                        $response = $prembly->validateRCNumber($data['rc_number']) ;
                        if($response != false ) {
                            header("HTTP/1.1 200 OK");
                            $response = $secure->encrypt(json_encode($response));
                            echo $response;
                        }else {
                            header("HTTP/1.1 500 Internal Server Error");
                            $response = $secure->encrypt(json_encode(array("description"=>"Error RC No")));
                            echo $response;
                        }
                    }else {
                        header("HTTP/1.1 400 Bad Request");
                        $response = $secure->encrypt(json_encode(array("description"=>"Invalid RC No")));
                        echo $response;
                    }
                    break;
                }

                case "get_currency": {
                    header("HTTP/1.1 200 OK");
                    $response = $secure->encrypt(json_encode($db->get_data("Select * from Currency")));
                    echo $response;
                    break;
                }

                case "confirm_bank_account": {
                    if(isset($data['sortcode']) && strlen(trim($data['sortcode'])) == 6 && isset($data['account_no']) && strlen(trim($data['account_no'])) == 10) {
                        // account.php's validate_account (Inter case) calls the same
                        // getValidateExternalAccount() for the identical purpose.
                        $bankone = new BankOne();
                        $response = $bankone->getValidateExternalAccount($data['sortcode'], $data['account_no']);
                        if($response != null) {
                            header("HTTP/1.1 200 OK");
                            $tx_echo = $secure->encrypt(json_encode(array("cod_acct_title"=>$response['Name'], "bvn"=>$response['BVN'])));
                            echo $tx_echo;
                        }else {
                            header("HTTP/1.1 500 Internal Server Error");
                        }
                    }else {
                        header("HTTP/1.1 400 Bad Request");
                    }
                    break;
                }
                default:{
                    header("HTTP/1.1 400 Bad Request");
                }
            }
        }else {
            header("HTTP/1.1 401 Unauthorized");
        }
    }else {
        switch($_GET['action']) {
            case "validate_bvn": {
                if(isset($data['bvn']) && strlen(trim($data['bvn'])) > 10 ) {
                    $response = $prembly->validateBVN($data['bvn']) ;
                    if($response != false ) {
                        header("HTTP/1.1 200 OK");
                        // Pre-session: no key exists yet, plain JSON over TLS.
                        echo json_encode($response);
                    }else {
                        header("HTTP/1.1 500 Internal Server Error");
                    }
                }else {
                    header("HTTP/1.1 400 Bad Request");
                }
                break;
            }

            case "validate_reg_no": {
                if(isset($data['rc_number']) && trim($data['rc_number']) != "" ) {
                    $response = $prembly->validateRCNumber($data['rc_number']) ;
                    if($response != false ) {
                        header("HTTP/1.1 200 OK");
                        // Pre-session: no key exists yet, plain JSON over TLS.
                        echo json_encode($response);
                    }else {
                        header("HTTP/1.1 500 Internal Server Error");
                    }
                }else {
                    header("HTTP/1.1 400 Bad Request");
                }
                break;
            }
            default: {
                header("HTTP/1.1 401 Unauthorized");
            }
        }
    }
}catch(Exception $ex) {
	error_log($ex);
}
