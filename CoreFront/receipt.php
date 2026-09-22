<?php
	set_include_path('/var/www/html/classes/:/var/www/html/classes/tcpdf/');
    date_default_timezone_set('Africa/Lagos');
	include 'includes/include.all.php';

	// Requires the same session every other endpoint does, plus ownership (the
	// transfer's SourceAccount must belong to the caller), matching account.php's
	// pattern.
	$user = new User();
	$account = new Account();
	$dbconnect = new dbConnect();

	$headers = apache_request_headers();
	if (!isset($headers['X-Authid-Token'], $headers['X-Clientid'])) {
		header("HTTP/1.1 401 Unauthorized");
		exit;
	}
	$session = $user->getSession($headers['X-Authid-Token']);
	if (!is_array($session) || $session['UserID'] !== $headers['X-Clientid']) {
		header("HTTP/1.1 401 Unauthorized");
		exit;
	}
	$logged_in_user = $user->getUserDetails($headers['X-Clientid']);

	$rs=$dbconnect->get_data("Select * from Transfers a, Banks b where TransferReference=? and a.DestBankCode=b.nsortcode",[$_GET['PaymentReference']]);
	if(!is_array($rs)) {
		exit;
	}else {
		$row = $rs[0];
	}

	$owned_acct = false;
	foreach ($account->getAccounts($logged_in_user['CustomerID']) as $acct) {
		if ($acct['COD_ACCT_NO'] == trim($row['SourceAccount'])) {
			$owned_acct = true;
			break;
		}
	}
	if (!$owned_acct) {
		header("HTTP/1.1 401 Unauthorized");
		exit;
	}

	$nairaConverter = new ToWords('naira', 'kobo');

	$data ='<!DOCTYPE html>
<html>
<head>
<style>
	*{color: #248332; font-size:10.5pt}
	td {

		border-bottom:1px dashed #248332;
}
</style>
</head>
<body>
<br><br><br><br><br>
<table cellspacing ="1" cellpadding="15" width="100%">
<tr>
<td><strong>Transaction Date: </strong><br/>'.$row['DateCreated'].'</td>
</tr>
<tr>
<td><strong>Reference Number:</strong><br/>'.$row['TransferReference'].'</td>
</tr>
<tr>
<td><strong>SessionID:</strong><br/>'.$row['SessionID'].'</td>
</tr>
<tr>
<td><strong>Sender:</strong><br/>'.$row['SourceAccountName'] .' ('.$row['SourceAccount'] .')'.'</td>
</tr>
<tr>
<td><strong>Transaction Amount:</strong><br/> N'.number_format($row['Amount'],2).'</td>
</tr>
<tr>
<td><strong>Amount in Words</strong>:<br/>'.ucwords(trim($nairaConverter->convert($row['Amount']))).' Only </td>
</tr>
<tr>
<td><strong>Receiver</strong><br/>'.$row['AccountTitle'].'</td>
</tr>
<tr>
<td><strong>Account Number:</strong><br/>'.$row['DestAccountNo'].'</td>
</tr>
<tr>
<td><strong>Receiving Bank:</strong><br/>'.$row['bank'].'</td>
</tr>
<tr>
<td><strong>Remarks:</strong><br/>'.$row['Narration'].'</td>
</tr>
</table></body>
</html>';


	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
	$pdf->SetHeaderMargin(0);
	$pdf->SetFooterMargin(0);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);

	$pdf->setLanguageArray($l);
	$pdf->setFontSubsetting(false);

	$pdf->AddPage();
	$pdf->SetMargins(0, 0, 0);
   	$pdf->Image('bg_receipt.jpg', 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
	$pdf->setPageMark();
	$pdf->SetAutoPageBreak(false, 0);
	$pdf->setImageScale(2);
	$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
	$pdf->writeHTML($data,true, false, true, false, '');
	$pdf->lastPage();
	$filename = $row['TransferReference'].".pdf";
	$pdf->Output($filename,'I');


?>
