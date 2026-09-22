<?php
include 'includes/bootstrap.php';

$account = new Account();
$transfer = new Transfer();
$logged_in_user = require_session();

function validateBeneficiary($beneficiary_details) {
    foreach($beneficiary_details as $detail) {
        if(floatval($detail['amount']) > 0 &&  strlen(trim($detail['account_no'])) == 10 && trim($detail['account_name']) !="") {

        }else {
            return false;
        }
    }
    return true;
}

try {
    switch ($_GET['action']) {
        case "create_transfer": {
            if($logged_in_user['Role']== "Initiator" && isset($data['auth_pin']) && $user->getTransactionPIN($headers['X-Clientid']) == trim($data['auth_pin'])) {
                $user->expireTxnPIN($headers['X-Clientid'],$data['auth_pin']);
                if(
                    isset($data['beneficiary_details']) && validateBeneficiary($data['beneficiary_details'])) {
                    $all_accounts  = $account->getNubanAccounts($logged_in_user['CustomerID']);
                    foreach ($data['beneficiary_details'] as $beneficiary) {
                        if(!in_array($beneficiary['source_account'],$all_accounts,true)) {
                            header("HTTP/1.1 401 Unauthorized");
                            exit;
                        }
                    }
                    //check max amount in txn
                    $max_amt_txn = max(array_column($data['beneficiary_details'], 'amount'));
                    if($max_amt_txn > $logged_in_user['SingleTxnLimit']) {
                        header("HTTP/1.1 400 Bad Request");
                        $tx_echo = $secure->encrypt(json_encode(array("description"=>"One or more transactions is above your single transaction limit")));
                        echo $tx_echo;
                        exit;
                    }

                    $tot_txn_Amount  = array_sum(array_map('floatval', array_column($data['beneficiary_details'], 'amount')));
                    $dailyTxnLimit = $transfer->getCurrentLimit($logged_in_user['CustomerID']);
                    if(($tot_txn_Amount + $dailyTxnLimit) > $logged_in_user['TxnLimit']) {
                        header("HTTP/1.1 400 Bad Request");
                        $tx_echo = $secure->encrypt(json_encode(array("description"=>"Total sum in batch is above your daily transaction limit")));
                        echo $tx_echo;
                        exit;
                    }

                    $batchID = $transfer->createTransfer($logged_in_user['CustomerID'],$data['beneficiary_details'],$headers['X-Clientid']);
                    $response = $transfer->getTransactionByBatchID($batchID);
                    $retval = '';
                    foreach($response as $trxn) {
                        $retval .='<tr>';
                            $retval .='<td>'.$trxn['ID'].'</td>';
                            $retval .='<td>'.$trxn['DateCreated'].'</td>';
                            $retval .='<td>'.$trxn['Bank'].'</td>';
                            $retval .='<td>'.$trxn['DestAccountNo'].'</td>';
                            $retval .='<td>'.number_format($trxn['Amount'],2).'</td>';
                            $retval .='<td>'.$trxn['Initiator'].'</td>';
                        $retval .='</tr>';
                    }
                    $rs = $transfer->get_data("Select Content from Contents where Type='CREATE_TRANSFER'");
                    $approvals = $transfer->get_data("Select * from Credentials where CustomerID=? and Role='Authorizer'",[$logged_in_user['CustomerID']]);

                    $mail_content = str_replace('[TRXNS]',$retval,$rs[0]['Content']);

                    $mailer = new Mailer();
                    if(is_array($approvals)) {
                        foreach ($approvals as $authorizer) {
                            $mailer->AddAddress($authorizer['Email']);
                        }
                    }
                    $mailer->set('Subject','Transactions waiting for your approval');
                    $mailer->msgHTML($mail_content);
                    $mailer->Send();
                    header("HTTP/1.1 200 OK");
                    $tx_echo = $secure->encrypt(json_encode(array("BatchID"=>$batchID)));
                    echo $tx_echo;
                }else{
                    header("HTTP/1.1 400 Bad Request");
                }
            }else {
                header("HTTP/1.1 422 Unprocessable Entity");
            }

            break;
        }

        case "authorize_transfer": {
            if($logged_in_user['Role']== "Authorizer" && isset($data['auth_pin']) && $user->getTransactionPIN($headers['X-Clientid']) == trim($data['auth_pin'])) {
                if(isset($data['trxn_id']) && is_array($data['trxn_id'])) {
                    //validate the transaction daily or single txn_limt
                    $totalTxn = 0.00 ;
                    foreach($data['trxn_id'] as $txn_id) {
                        $details = $transfer->getTransactionByTransactionID($txn_id['ID'], $logged_in_user['CustomerID']);
                        if($logged_in_user['SingleTxnLimit'] >= $details[0]['Amount']) {
                            $totalTxn +=$details[0]['Amount'];
                        }else {
                            header("HTTP/1.1 400 Bad Request");
                            $tx_echo = $secure->encrypt(json_encode(array("description"=>"One or more transactions is above your single transaction limit")));
                            echo $tx_echo;
                            exit;
                        }
                    }

                    if( $totalTxn  > $logged_in_user['TxnLimit']) {
                        header("HTTP/1.1 400 Bad Request");
                        $tx_echo = $secure->encrypt(json_encode(array("description"=>"One or more transactions is above your single transaction limit")));
                        echo $tx_echo;
                        exit;
                    }


                    $response = $transfer->authorize_transaction($data['trxn_id'],$logged_in_user['CustomerID'],$headers['X-Clientid']);
                    header("HTTP/1.1 200 OK");
                }else {
                    header("HTTP/1.1 400 Bad Request");
                }
            }else {
                header("HTTP/1.1 422 Unprocessable Entity");
            }
            break;
        }

        case "decline_transfer": {
            if($logged_in_user['Role'] =='Authorizer' && isset($data['trxn_id']) && is_array($data['trxn_id'])) {
                header("HTTP/1.1 200 OK");
                foreach($data['trxn_id'] as $txn_id) {
                    $transfer->updateTransactionStatus("Declined",$txn_id['ID'],$logged_in_user['CustomerID'],$headers['X-Clientid'],"");
                }
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "add_beneficiary": {
            if(isset($data['dest_bank_code']) && strlen(trim($data['dest_bank_code'])) >= 3 && isset($data['account_no']) && strlen(trim($data['account_no'])) == 10 && isset($data['account_name']) && trim($data['account_name']) != "") {
                header("HTTP/1.1 200 OK");
                $transfer->addBeneficiary($logged_in_user['CustomerID'],$data['account_no'], $data['dest_bank_code'],$data['account_name'],$headers['X-Clientid']);
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "get_beneficiary": {
            $response = $transfer->getBeneficiary($logged_in_user['CustomerID'],$headers['X-Clientid']);
            header("HTTP/1.1 200 OK");
            $tx_echo = $secure->encrypt(json_encode(is_array($response) ? $response : []));
            echo $tx_echo;
            break;
        }


        case "delete_beneficiary": {
            if(isset($data['beneficiary_id']) && intval($data['beneficiary_id']) > 0 ) {
                header("HTTP/1.1 200 OK");
                $transfer->removeBeneficiary($data['beneficiary_id'],$logged_in_user['CustomerID'], $headers['X-Clientid']);
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }


        case "get_pending_transfer": {
            if($logged_in_user['Role'] =='Authorizer') {
                header("HTTP/1.1 200 OK");
                $tx_echo = $secure->encrypt(json_encode($transfer->getPendingTransfer($logged_in_user['CustomerID'],$logged_in_user['TxnLimit'])));
                echo $tx_echo;
            }else {
                header("HTTP/1.1 401 Unauthorized");
            }
            break;
        }

        case "get_all_transactions": {
            if(isset($data['start_date']) && validateDate($data['start_date']) && isset($data['end_date']) && validateDate($data['end_date'])) {
                header("HTTP/1.1 200 OK");
                $tx_echo = $secure->encrypt(json_encode($transfer->getAllTransactions($data['start_date'],$data['end_date'],$logged_in_user['CustomerID'])));
                echo $tx_echo;
            }else {
                header("HTTP/1.1 400 Bad Request");
            }
            break;
        }

        case "generate_trxn_receipt": {

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
