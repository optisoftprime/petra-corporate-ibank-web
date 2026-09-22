<?php

class BankOne extends dbConnect {
    // Resolved at runtime from the environment (see class.Env.php). No default:
    // an unset key simply fails the BankOne call, which is the safe failure mode.
    private static $apiKey;
    private static $instId;

    private static function apiKey() {
        if (self::$apiKey === null) {
            self::$apiKey = Env::get('BANKONE_API_KEY', '');
        }
        return self::$apiKey;
    }

    private static function instId() {
        if (self::$instId === null) {
            self::$instId = Env::get('BANKONE_INST_ID', '');
        }
        return self::$instId;
    }

	const BANKONE_CREATE_ACCOUNT ="https://api.mybankone.com/BankOneWebAPI/api/Account/CreateAccountQuick/2";
    const BANKONE_GET_CUSTOMER_DETAILS ="https://api.mybankone.com/BankOneWebAPI/api/Customer/GetByCustomerID/2";
    const BANKONE_GET_CUSTOMER_DETAILS_BY_ACCT_NO ="https://api.mybankone.com/BankOneWebAPI/api/Customer/GetByAccountNo2/2";
    const BANKONE_GET_ACCOUNT_DETAILS ="https://api.mybankone.com/BankOneWebAPI/api/Account/GetAccountsByCustomerId/2";
    const BANKONE_TRANSFER_LOCAL ="https://api.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/LocalFundsTransfer";
    const BANKONE_DR_TO_GL="https://api.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/Debit";
    const BANKONE_DR_FROM_GL="https://api.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/Credit";
    const BANKONE_TRANSFER_INTER ="https://api.mybankone.com/thirdpartyapiservice/apiservice/Transfer/InterBankTransfer";
    const BANKONE_VALIDATE_ACCT  ="https://api.mybankone.com/thirdpartyapiservice/apiservice/Transfer/NameEnquiry";
    const BANKONE_GETBANKS  ="https://api.mybankone.com/ThirdPartyAPIService/APIService/BillsPayment/GetCommercialBanks/";
    const BANKONE_VALIDATE_ACCT_INTRA  ="https://api.mybankone.com/thirdpartyapiservice/apiservice/Account/AccountEnquiry";
    //const BANKONE_GET_LOANS ="https://api.mybankone.com/BankOneWebAPI/api/LoanAccount/LoanAccountBalance2/2authToken=";
	const BANKONE_GET_LOANS_SCHEDULE ="https://api.mybankone.com/BankOneWebAPI/api/Loan/GetLoanRepaymentSchedule/2?authToken=";
	const BANKONE_GET_LOANS ="https://api.mybankone.com/BankOneWebAPI/api/Loan/GetLoansByCustomerId/2?authToken=";
    const BANKONE_GET_DEPOSITS ="https://api.mybankone.com/BankOneWebAPI/api/FixedDeposit/GetFixedDepositAccountByPhoneNumber/2";
    const BANKONE_VALIDATE_BVN ="https://api.mybankone.com/thirdpartyapiservice/apiservice/Account/BVN/GetBVNDetails";
    const BANKONE_FD="https://api.mybankone.com/BankOneWebAPI/api/FixedDeposit/GetFixedDepositAccountByLiquidationAccount/2?authToken=";
    const BANKONE_GET_ACCT_STATMENT ="https://api.mybankone.com/BankOneWebAPI/api/Account/GetTransactions/2";
    const BANKONE_PDF_STATEMENT = "https://api.mybankone.com/BankOneWebAPI/api/Account/GenerateAccountStatement2/2?authtoken=";
	const BANKONE_GET_ACCT_SUMMARY= "https://api.mybankone.com/BankOneWebAPI/api/Account/GetAccountSummary/2?authtoken=";
	const BANKONE_SEND_SMS = "https://api.mybankone.com/BankOneWebAPI/api/Messaging/SaveBulkSms/2?authtoken=";
	const BANKONE_FREEZE = "https://api.mybankone.com/thirdpartyapiservice/apiservice/Account/FreezeAccount";
	const BANKONE_UNFREEZE = "https://api.mybankone.com/thirdpartyapiservice/apiservice/Account/UnfreezeAccount";

	function getAccounts($cod_cust) {
		$url = $this::BANKONE_GET_ACCOUNT_DETAILS."?authToken=" . self::apiKey() ."&customerId=".$cod_cust;
		$response = $this->connectCURL($url, array(), "GET");
		error_log(json_encode($response));
		if($response != null && isset($response['Accounts'])) {
			return $response['Accounts'];
		}else {
			return null; 
		}
	}

	function getCustomerNameFromCustomerID($cod_cust) {
		$url = $this::BANKONE_GET_ACCOUNT_DETAILS."?authToken=" . self::apiKey() ."&customerId=".$cod_cust;
		$response = $this->connectCURL($url, array(), "GET");
		error_log(json_encode($response));
		return ( $response['name'] ?? 'Invalid CustomerID');
	}

	function validateAccountName($bankcode, $cod_acct_no) {
		$params = array();
       	$params['AccountNumber'] = $cod_acct_no;
       	$parans['BankCode'] = $bankcode;
		$params['Token'] = self::apiKey();

		$url = $this::BANKONE_VALIDATE_ACCT."?authToken=". self::apiKey() ;
		$response = $this->connectCURL($url, $params, "POST");
		if($response != null && isset($response['IsSuccessful']) && $response['IsSuccessful'] == true ) {
			return $response;
		}else {
			return false;
		} 
	}

	function getBanks() {
		$banks = self::connectCURL($this::BANKONE_GETBANKS . self::apiKey(),array(),"GET");
		$response_bank = array();
		foreach ($banks as $bank) {
			array_push($response_bank, array("Sortcode"=>$bank['Code'],"Bank"=>$bank['Name']));
		}

		usort($response_bank, function($a, $b) {
			return strcasecmp($a['Bank'], $b['Bank']);
		});
		return $response_bank;
	}

	function getLoans($cod_cust) {
		
		$loans = self::connectCURL($this::BANKONE_GET_LOANS .self::apiKey().'&institutionCode='. self::instId() . '&CustomerId='.$cod_cust .'&addStartAndEndDate=true',array(),'GET');
		$active_loans = array();
		if($loans['IsSuccessful']== true && is_array($loans['Message'])) {
			foreach ($loans['Message'] as $loan) {
				if($loan['RealLoanStatus'] == 'Active') {
					array_push($active_loans, array(
						"LoanAmount"=> $loan['LoanAmount'],
						"LoanAccountNo"=> $loan['Number'],
						"OutstandingBalance"=> $loan['BalanceInNaira'],
						"Interest"=> $loan['InterestRate'],
						"Tenure"=> sizeof($this->getLoanSchedule($loan['Number']))
						//"Repayment Amount"=> $loan['Total']
					));
				}
			}
		}
		return $active_loans;
	}


	function getLoanSchedule ($loanAccountNo) {
		
		$schedules = self::connectCURL($this::BANKONE_GET_LOANS_SCHEDULE .self::apiKey(). '&loanAccountNumber='.$loanAccountNo,array(),'GET');
		$loan_repayment_schedule = array();
		foreach ($schedules as $schedule) {
			
			array_push($loan_repayment_schedule, array(
				"DueDate"=> $schedule['PaymentDueDate'],
				//"Balance"=> $schedule[''],
				"Principal"=> $schedule['Principal'],
				"Interest"=> $schedule['Interest'],
				"Fee"=> $schedule['Fee'],
				"RepaymentAmount"=> $schedule['Total'],
				"Status"=> '',
			));
		}
		return $loan_repayment_schedule;
	}

	function getInvestments($cod_cust) {
		$accounts = $this->getAccounts($cod_cust);
		$active_investments  = array();
		foreach ($accounts as $account) {
			if($account['accountType'] =='SavingsOrCurrent') {
				$investments = $this->connectCURL($this::BANKONE_FD.self::apiKey()."&accountNumber=".$account['NUBAN'],array(), "GET");
				foreach($investments as $investment) {
					array_push($active_investments, array(
						'ID'=>$investment['AccountNumber'],
						'OpeningDate'=>$investment['InterestAccrualCommencementDate'],
						'Amount'=>$investment['Amount'],
						'Interest'=>$investment['ExpectedInterest'],
						'InterestRate'=>$investment['interestRate'],
						'Tenure'=>$investment['TenureInDays'],
						'MaturityDate'=>$investment['MaturationDate'],
						'Payment'=> ($investment['ApplyMonthlyInterest'] == true ? 'Monthly' : 'Capitalised')
					));
				}
			}
		}
		return $active_investments ;
	}

	function generateReference($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }



	function transferFunds($cod_acct_from, $cod_acct_from_title, $cod_acct_to, $cod_dest_bank, $amount, $narration, $reference, $type) {
		if($type =='Inter') {
			$response = $this->getValidateExternalAccount($cod_dest_bank, $cod_acct_to);
			if(is_array($response)) {
				$cod_dest_acct_kyc = $response['KYC'];
				$cod_dest_acct_bvn = $response['BVN'];
				$cod_dest_acct_enq_ref = $response['SessionID'];
				$cod_dest_acct_title = $response['Name'];
				$params = array(
					"Amount" => $amount * 100,
					"AppzoneAccount"=> "",
					"PayerAccountNumber"=>$cod_acct_from,
					"Payer"=> $cod_acct_from_title,
					"ReceiverBankCode"=> $cod_dest_bank,
					"ReceiverAccountNumber"=>$cod_acct_to,
					"ReceiverName"=> $cod_dest_acct_title,
					"ReceiverKYC"=> $cod_dest_acct_kyc,
					"ReceiverBVN"=> $cod_dest_acct_bvn,
					"TransactionReference"=> $reference,
					"Narration"=> $narration,
					"NIPSessionID"=> $cod_dest_acct_enq_ref,
					"Token"=> self::apiKey(),
				);
				$response = $this->connectCURL($this::BANKONE_TRANSFER_INTER .'?authToken='.self::apiKey(), $params,"POST");
				return $response;
			}
		}else {

		}
	}


	function sendSMS($mobile, $text) {
		$params = array();
		array_push($params,array("AccountNumber"=>"0000000000","To"=>"0". substr($mobile,-10),"Body"=>$text, "ReferenceNo"=>rand(1111111111,99999999999)));
		return self::connectCURL($this::BANKONE_SEND_SMS . self::apiKey(),$params,"POST");
	}

	function getStatement($cod_acct_no, $start_date, $end_date) {
		$response = $this->connectCURL($this::BANKONE_GET_ACCT_STATMENT."?authToken=". self::apiKey(). "&accountNumber=".$cod_acct_no."&fromDate=".$start_date ."&toDate=".$end_date,array(), "GET");
		if($response != null && isset($response['IsSuccessful']) && $response['IsSuccessful'] == true ) {
			return $response['Message'];
		} 
	}

	function getCustomerByID ($cod_cust) {
		$response = $this->connectCURL($this::BANKONE_GET_CUSTOMER_DETAILS . "?authToken=".self::apiKey()."&CustomerID=".$cod_cust, array(), "GET");
		$customerDetails = array();
		if($response != null && is_array($response)) {
			$customerDetails['CustomerName'] = $response['Name'];
			return $customerDetails; 
		}else {
			return null;
		}
    }

	function getAccount($cod_acct_no) {
		$response = $this->connectCURL($this::BANKONE_VALIDATE_ACCT_INTRA, array("AccountNo" =>$cod_acct_no,"AuthenticationCode" => self::apiKey()), "POST");
		return $response;
	}

	function getValidateExternalAccount($sort_code, $cod_acct_no) {
		$params = array(
			'Token'=>self::apiKey(),
			'AccountNumber'=>$cod_acct_no,
			'BankCode'=>$sort_code
		);
		$response = $this->connectCURL($this::BANKONE_VALIDATE_ACCT,$params, "POST");
		if(is_array($response) && isset($response['IsSuccessful']) && $response['IsSuccessful'] == true ) {
			return $response;
		}else {
			return null;
		}
	}

	function freezeAccount($account, $reason) {
		$params = array(
			'AuthenticationCode'=>self::apiKey(),
			'AccountNo'=>$account,
			'ReferenceID'=>uniqid(),
			'Reason'=>$reason
		);
		$response = $this->connectCURL($this::BANKONE_FREEZE,$params, "POST");
		if(is_array($response) && isset($response['RequestStatus']) && $response['RequestStatus'] == true ) {
			return $response;
		}else {
			return null;
		}
	}

	function unFreezeAccount($account, $reason) {
		$params = array(
			'AuthenticationCode'=>self::apiKey(),
			'AccountNo'=>$account,
			'ReferenceID'=>uniqid(),
			'Reason'=>$reason
		);
		$response = $this->connectCURL($this::BANKONE_UNFREEZE,$params, "POST");
		if(is_array($response) && isset($response['RequestStatus']) && $response['RequestStatus'] == true ) {
			return $response;
		}else {
			return null;
		}
	}

	function getAccountSummary($cod_acct_no) {
		$response = $this->connectCURL($this::BANKONE_GET_ACCT_SUMMARY . self::apiKey() . '&accountNumber='. $cod_acct_no  .'&institutionCode=' . self::instId(),array(), "GET");
		return $response;
	}

    function connectCURL($url, $params, $type){
		$curl_post_data = json_encode($params);
		error_log($curl_post_data);
        error_log($url);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		if ($type == "POST") {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
			curl_setopt($curl, CURLOPT_POST, true);
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$result = curl_exec($curl);

		error_log($result); 
		$result = json_decode($result, true);
		return $result;
	}
}


?>