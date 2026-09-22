<?php

class Transfer extends dbConnect {


    function getPendingTransfer($cod_cust, $txnLimit ) {
       $response = $this->get_data("Select * from Transfers where CustomerID=? and Status='Pending' and Amount <=? order by ID desc ",[$cod_cust, $txnLimit]);
        return is_array($response) ? $response : [] ;
    }

    function createTransfer($cod_cust, $beneficiary_details,$userId) {
        $batch_id=date("Ymdhms").uniqid();
        foreach($beneficiary_details as $beneficiary) { 
            $this->insert_data("insert into Transfers(DestBankCode, DestAccountNo, CustomerID, AccountTitle, Narration, Amount, Initiator, BatchReference, SourceAccount) values(?,?,?,?,?,?,?,?,?)",[$beneficiary['dest_bank_code'],$beneficiary['account_no'], $cod_cust, $beneficiary['account_name'], $beneficiary['narration'], $beneficiary['amount'],$userId, $batch_id,$beneficiary['source_account']]);
        }
        return $batch_id;
    }

    function getCurrentLimit($cod_cust) {
        $rs = $this->get_data("Select ifnull(sum(Amount),0) as Amount from Transfers where date(DateApproved)=date(now()) and Status='Successful' and CustomerID=?",[$cod_cust]);
        return $rs[0]['Amount'];
    }

    function authorize_transaction($trxn_id, $cod_cust,$userId) {
        foreach($trxn_id as $transaction) {
            $details = $this->getTransactionByTransactionID($transaction['ID']);
            $details = $details[0];
            if(is_array($details) && $details['Status'] =='Pending' && $details['CustomerID'] == $cod_cust) {
                $reference = uniqid();
                $this->updateTransactionStatus('Authorized',$details['ID'],$cod_cust,$userId,$reference);
            }
        }
    }

    function getTransactionByBatchID($batch_id) {
        return $this->get_data("Select a.ID, DateCreated, Amount, DestAccountNo, Initiator, Bank  from Transfers a, Banks b where DestBankCode=nsortCode and BatchReference=? order by a.ID desc ",[$batch_id]);
    }


    function getTransactionByTransactionID($trxn_id, $cod_cust="") {
        if($cod_cust !="") {
            return $this->get_data("Select * from Transfers where ID=? and CustomerID=?",[$trxn_id, $cod_cust ]);
        }else{
            return $this->get_data("Select * from Transfers where ID=?",[$trxn_id]);
        }
        
    }



    // $cod_cust scopes the UPDATE to the caller's own transfer. authorize_transfer's
    // caller (authorize_transaction()) already re-fetches the row and checks
    // CustomerID before reaching this method, but the check also lives here, in the
    // query itself, so nothing that calls this method can skip it by accident.
    function updateTransactionStatus($status,$trxn_id, $cod_cust, $userId, $sourceTransferReference) {
        return $this->insert_data("Update Transfers set Status=?,Authorizer=?, SourceDebitReference=?, DateApproved=now() where ID=? and CustomerID=? and Status='Pending'",[$status,$userId,$sourceTransferReference,$trxn_id,$cod_cust]);
    }

    function getAllTransactions($start_date, $end_date, $cod_cust = null) {
        if($cod_cust != null ) {
            return $this->get_data("Select * from Transfers where CustomerID=? and date(DateCreated) >=? and date(DateCreated) <=? order by ID desc  ",[$cod_cust,$start_date,$end_date]);
        }else {
            return $this->get_data("Select * from dbPetra.Transfers where date(DateCreated) >=? and date(DateCreated) <=? order by ID desc ",[$start_date,$end_date]);
        }

    }

    function addBeneficiary($cod_cust, $account_no, $dest_bank_code, $beneficiary_name, $userId) {
        $this->insert_data("Insert into Beneficiary(CustomerID, AccountNo, DestBankCode, BeneficiaryName, UserID ) values(?,?,?,?,?)",[$cod_cust,$account_no,$dest_bank_code, $beneficiary_name, $userId]);
    }

    function removeBeneficiary($beneficiary_id, $cod_cust, $userId) {
        $this->insert_data("Update Beneficiary set Status='Removed',DateRemoved=now() where CustomerID=? and UserID=? and ID=?",[$cod_cust, $userId, $beneficiary_id]);
    }

    function getBeneficiary($cod_cust, $userId) { 
        $response = $this->get_data("Select a.*, b.bank from Beneficiary a, Banks b where a.DestBankCode=b.nsortcode and a.UserId=? and a.CustomerID=?",[$userId,$cod_cust]);
        return $response;
    }
}


?>