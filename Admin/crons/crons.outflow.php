<?php
date_default_timezone_set('Africa/Lagos');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
set_include_path('/var/www/html/classes/:/var/www/admin/classes/');
class ClassAutoloader {
    public function __construct() {
        spl_autoload_register(array($this, 'loader'));
    }
    private function loader($className) {
        include 'class.'.$className . '.php';
    }
}
$autoloader = new ClassAutoloader();	
$bankone = new BankOne();
$txns = $bankone->get_data("Select * from Transfers where Status='Authorized' and Trials <=3  limit 10");
if(is_array($txns)){
    foreach ($txns as $txn) {
        $reference = $bankone->generateReference(12);
        $source_acct_title = $bankone->getAccount($txn['SourceAccount']);
        $bankone->insert_data("Update Transfers set Status='Processing', TransferReference=? , SourceAccountName=? , Trials = Trials + 1 where ID=?",[$reference,$source_acct_title['Name'] ,$txn['ID']]);
        $response = $bankone->transferFunds($txn['SourceAccount'],$source_acct_title['Name'],$txn['DestAccountNo'],$txn['DestBankCode'],$txn['Amount'],$txn['Narration'],$reference,'Inter');
        if(is_array($response) && $response['Status'] =='Successful' && $response['IsSuccessFul'] == true && $response['ResponseCode'] =='00' && $response['ResponseStatus'] =='Successful') {
            $bankone->insert_data("Update Transfers set Status='Successful', TxnResult='Successful', UniqueReference=?, SessionID=? where ID=?",[$response['UniqueIdentifier'], $response['SessionID'],$txn['ID']]);
        }else {
            $bankone->insert_data("Update Transfers set Status='Authorized' where ID=?",[$txn['ID']]);
        }
    }
}
?>