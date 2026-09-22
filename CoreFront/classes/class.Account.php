<?php

class Account extends dbConnect {
    private $bankone;

    function __construct()
    {
        $this->bankone = new BankOne();
    }

    function getAccounts($cod_cust) {
        $linked_accounts = $this->get_data("Select * from LinkedCustomerID where MainCustomerID=? and Status='Approved'",[$cod_cust]);
        if(is_array( $linked_accounts )) {
            $all_customer_id  = array_merge([$cod_cust], array_column($linked_accounts,'CustomerID'));
        }else {
            $all_customer_id = [$cod_cust];
        }
        $bankone = new BankOne();
        $ret_val = array();
        foreach ($all_customer_id  as $cod_cust) {
            $accounts = $bankone->getAccounts($cod_cust);
            
            if($accounts != null) {
                foreach ($accounts as $account) {
                    array_push($ret_val,array(
                        "COD_ACCT_NO"=> $account['NUBAN'],
                        "COD_ACCT_TITLE"=>$account['accountName'],
                        "COD_ACCT_TYPE"=>$account['accountType'],
                        "BAL_AVAILABLE"=>$account['availableBalance'],
                        "LEDGER_BAL"=>$account['ledgerBalance'],
                        "OD_LIMIT"=>0.00
                    ));
                }
            }
        }
        return $ret_val;
    }

    function getNubanAccounts($cod_cust) {
        $linked_accounts = $this->get_data("Select * from LinkedCustomerID where MainCustomerID=? and Status='Approved'",[$cod_cust]);
         if(is_array( $linked_accounts )) {
            $all_customer_id  = array_merge([$cod_cust], array_column($linked_accounts,'CustomerID'));
        }else {
            $all_customer_id = [$cod_cust];
        }
        $bankone = new BankOne();
        $ret_val = array();
        foreach ($all_customer_id  as $cod_cust) {
            $accounts = $bankone->getAccounts($cod_cust);
            
            if($accounts != null) {
                foreach ($accounts as $account) {
                    array_push($ret_val,$account['NUBAN']);
                }
            }
        }
        return $ret_val;
    }

    function getStatementPDF($cod_cust, $cod_acct_no, $start_date, $end_date)  {
        $rs = $this->get_data("insert into dbInternetBank.StatementRequest(CustomerID, AccountNo, StartDate, EndDate) values(?,?,?,?) ",[$cod_cust, $cod_acct_no, $start_date, $end_date]);
        return $rs;
    }

    function getStatement($cod_acct_no, $start_date, $end_date) {
        $bankone = new BankOne();
        $ret_val = array();
        $txns = $bankone->getStatement($cod_acct_no,$start_date,$end_date);
        if ($txns != null && is_array($txns)) {
            foreach ($txns as $txn) {
                array_push($ret_val, array(
                    "ID"=>$txn['Reference'],
                    "TransactionDate"=>$txn['CurrentDate'],
                    "ValueDate"=>$txn['TransactionDate'],
                    "Narration"=>$txn['Narration'],
                    "Debit"=>($txn['RecordType'] == "Debit" ?  doubleval($txn['Amount'])/100 : 0.00),
                    "Credit"=>($txn['RecordType'] == "Credit" ?  doubleval($txn['Amount'])/100: 0.00),
                    "Balance"=>doubleval($txn['Balance'])/100,
                ));
            }
        }
        return $ret_val;
    }

    function validateInternalAccount($cod_acct_no) {
        $bankone = new BankOne();
        $account = $bankone->getAccountSummary($cod_acct_no);
        return $account;
    }

    function validateExternalAccount($sort_code, $cod_acct_no) {
        $bankone = new BankOne();
        $response = $bankone->getValidateExternalAccount($sort_code, $cod_acct_no);
        if($response != null ) {
            return ['cod_acct_title' => $response['Name'], 'bvn' => $response['BVN']];
        }else {
            return null;
        }
    }
}

?>