<?php
include 'includes/bootstrap.php';

try {
    switch ($_GET['action']) {
        case "login": {
            if(isset($data['email']) && trim($data['email']) !="" && isset($data['password']) && trim($data['email'])!= "") {
                $response = $user->login(trim($data['email']),trim($data['password']));
                if($response['success'] !== true) {
                    header("HTTP/1.1 422 Unprocessable Entity");
                    if($response['status'] == 'locked') {
                        // Pre-session: no key exists yet, plain JSON over TLS.
                        echo json_encode(array("description"=>"Profile Locked, Contact admin for support"));
                    }
                }else {
                    header("HTTP/1.1 200 OK");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "forgot_password": {

            if (isset($data['email']) && trim($data['email']) != "") {
                $response = $user->forgot_password(trim($data['email']));
                if (!$response) {
                    header("HTTP/1.1 422 Unprocessable Entity");
                } else {
                    header("HTTP/1.1 200 OK");
                }
            } else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }



        case "validateOTP": {
            if(isset($data['userId']) && trim($data['userId']) !="" && isset($data['otp_code']) && trim($data['otp_code'])!= "") {
                $response = $user->validateOTP(trim($data['userId']),trim($data['otp_code']));
                if(!$response) {
                    header("HTTP/1.1 422 Unprocessable Entity");
                }else {
                    header("HTTP/1.1 200 OK");
                    // $response is already a JSON string (User::validateOTP()) and this is
                    // the moment the client LEARNS its crypto_key/crypto_iv — it can't
                    // decrypt a response encrypted with a key it doesn't have yet, so this
                    // stays plain JSON, same as every other pre-session response.
                    echo $response;
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        default: {
            $logged_in_user = require_session();
            switch ($_GET['action']) {
                case "change_password": {
                    if(isset($data['current_password']) && trim($data['current_password']) !="" && isset($data['new_password']) && trim($data['new_password']) !="" ) {
                        $pattern = '/(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/';
                        if (preg_match($pattern,$data['new_password'])) {
                            if($user->changePassword($headers['X-Clientid'], $data['current_password'], $data['new_password'])) {
                                header("HTTP/1.1 200 OK");
                            }else {
                                header("HTTP/1.1 500 Internal Server Error");
                                $tx_echo = $secure->encrypt(json_encode(array("description"=>"Incorrect Password")));
                                echo $tx_echo;
                            }
                        }else {
                            header("HTTP/1.1 500 Internal Server Error");
                            $tx_echo = $secure->encrypt(json_encode(array("description"=>"Password not strong enough")));
                            echo $tx_echo;
                        }
                    }else {
                        header("HTTP/1.1 400 Bad Request");
                    }
                    break;
                }

                case "get_all_users": {
                    header("HTTP/1.1 200 OK");
                    $tx_echo = $secure->encrypt(json_encode($user->getAllUsersByCustomerID($logged_in_user['CustomerID'])));
                    echo $tx_echo;
                    break;
                }

                case "lock_account": {
                    if(isset($data['account']) && trim($data['account']) !="") {
                        header("HTTP/1.1 200 OK");
                        $tx_echo = $secure->encrypt(json_encode($user->lockUser($data['account'], $logged_in_user['CustomerID'])));
                        echo $tx_echo;
                    }else {
                        header("HTTP/1.1 400 Bad Request");
                    }
                    break;
                }

                case "create_otp": {
                    header("HTTP/1.1 200 OK");
                    $tx_echo = $secure->encrypt(json_encode($user->createOTP($headers['X-Clientid'])));
                    echo $tx_echo;
                    break;
                }

                case "get_user_details": {
                    header("HTTP/1.1 200 OK");
                    $tx_echo = $secure->encrypt(json_encode($user->getUserDetails($headers['X-Clientid'])));
                    echo $tx_echo;
                    break;
                }
                default: {
                    header("HTTP/1.1 400 Bad Request");
                    break;
                }
            }
        }
    }
} catch (Exception $ex) {
    error_log($ex);
}
