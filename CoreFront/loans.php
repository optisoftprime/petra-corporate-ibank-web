<?php
include 'includes/bootstrap.php';

$dbconnect = new dbConnect();
$logged_in_user = require_session();

try {
    switch ($_GET['action']) {
        case "get_repayment_schedule": {
            if(isset($data['loanID']) && intval($data['loanID']) > 0 ) {
                $bankone = new BankOne();
                // Ownership check: loanID here is BankOne's LoanAccountNo. Without this
                // check, any authenticated session could pull any customer's repayment
                // schedule by guessing one. Must be one of the caller's own active loans.
                $owned_loan = false;
                foreach ($bankone->getLoans($logged_in_user['CustomerID']) as $loan) {
                    if ($loan['LoanAccountNo'] == trim($data['loanID'])) {
                        $owned_loan = true;
                        break;
                    }
                }
                if (!$owned_loan) {
                    header("HTTP/1.1 401 Unauthorized");
                    break;
                }
                $schedule = $bankone ->getLoanSchedule($data['loanID']);

                header("HTTP/1.1 200 Ok");
                    $result['resultCode'] ="00";
                    $result['resultDescription'] ="Sucessful";
                    $result['data'] = $schedule;
                    $tx_echo =  $secure->encrypt(json_encode($result));
                    echo $tx_echo;
            }else {
                header("HTTP/1.1 400 Bad Request");
                $response = array("error"=>"Invalid LoanID");
                $tx_echo =  $secure->encrypt(json_encode($response));
                echo $tx_echo;
            }
            break;
        }

        case "get_loan_details": {
            if(isset($data['loanID']) && intval($data['loanID']) > 0) {
                // Ownership check folded into both queries below (CustomerID=?). Without it,
                // any authenticated session could pull any customer's full loan detail
                // (amount, interest, balance, disbursement date) by guessing a LoanID.
                $rs = $dbconnect->get_data("Select * from dbCredits.Loans where LoanID=? and Status=4 and CustomerID=?",[$data['loanID'], $logged_in_user['CustomerID']]);

                if(is_array($rs)) {
                    $rs = $dbconnect->get_data("Select CustomerID,LoanID,Loan_Amount, Loan_Tenure, Interest, Loan_Type, Monthly_Repayments,(Select ifnull(sum(principal),0) from dbCredits.Statements where Status=1 and LoanID=a.LoanID) as AmountPaid, (Select ifnull(sum(principal),0) from dbCredits.Statements where Status in (0,-1) and LoanID=a.LoanID) as AmountLeft, if (Status=4,'Active','Non-Active') as Status, DateDisbursed, if(Status=5,(Select Date_Liquidated from dbCredits.LiquidatedLoans where LoanID=a.LoanID),'') as DateLiquidated from dbCredits.Loans a where LoanID=? and CustomerID=?",[$data['loanID'], $logged_in_user['CustomerID']]);

                    header("HTTP/1.1 200 Ok");
                    $result['resultCode'] ="00";
                    $result['resultDescription'] ="Sucessful";
                    $result['data'] = $rs;
                    $tx_echo =  $secure->encrypt(json_encode($result));
                    echo $tx_echo;
                }else {
                    header("HTTP/1.1 400 Bad Request");
                    $response = array("error"=>"Invalid LoanID");
                    $tx_echo =  $secure->encrypt(json_encode($response));
                    echo $tx_echo;
                }

            }else {
                header("HTTP/1.1 400 Bad Request");
                $response = array("error"=>"Invalid LoanID");
                $tx_echo =  $secure->encrypt(json_encode($response));
                echo $tx_echo;
            }
            break;
        }

        case "portfolio": {
            $bankone = new BankOne();
            $loans = $bankone->getLoans($logged_in_user['CustomerID']);
            header("HTTP/1.1 200 Ok");
            $result['resultCode'] ="00";
            $result['resultDescription'] ="Sucessful";
            $result['data'] = $loans;
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
