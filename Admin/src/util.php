<?php
include 'include.all.php';

$menu = new Menu();
$user = new UserAdmin($_SESSION['uname']);
$log = new Log($_SESSION['uname']);
$dbconnect = new dbConnect();
$forms = new FormGenerator();
$customer = new User();
$bankone = new BankOne();



function createAuditLogViewerSection()
{
	global $log;
	
	$log->logActivity('User accessed audit log viewer interface', 'System Administration'); 
	$retval = '<div class="module"><div class="module-title">Audit Log Viewer</div><div class="module-content">';
	
	// Filter section
	$retval .= '<div class="filter" align="center">
		<hr/>
		<div class="filter-wrapper" align="left">
			<div style="padding:5px;">
				<table cellpadding="5" cellspacing="0">
					<tr>
						<td><label>Start Date <input readonly="readonly" type="text" id="audit_start_date" name="audit_start_date" class="datepicker" value="' . date('Y-m-d', strtotime('-30 days')) . '" size="15"/></label></td>
						<td><label>End Date <input readonly="readonly" type="text" id="audit_end_date" name="audit_end_date" class="datepicker" value="' . date('Y-m-d') . '" size="15"/></label></td>
						<td><label>Category <select id="audit_category" name="audit_category"><option value="">All Categories</option>';
	
	// Get categories
	$categories = $log->getAuditLogCategories();
	if ($categories) {
		foreach ($categories as $cat) {
			$retval .= '<option value="' . htmlspecialchars($cat['category']) . '">' . htmlspecialchars($cat['category']) . '</option>';
		}
	}
	
	$retval .= '</select></label></td>
						<td><label>User <select id="audit_user" name="audit_user"><option value="">All Users</option>';
	
	// Get users
	$users = $log->getAuditLogUsers();
	if ($users) {
		foreach ($users as $user) {
			$retval .= '<option value="' . htmlspecialchars($user['usr']) . '">' . htmlspecialchars($user['usr']) . '</option>';
		}
	}
	
	$retval .= '</select></label></td>
						<td><input type="button" id="load-audit-logs" name="load-audit-logs" value="Load Logs" class="green-button"/></td>
					</tr>
				</table>
			</div>
		</div>
	</div>';
	
	// Stats section
	$retval .= '<div id="audit-stats" class="stats-section" style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 5px; display: none;">
		<div id="audit-stats-content"></div>
	</div>';
	
	// Table section
	$retval .= '<div id="audit-logs-content">
		<table id="audit-logs-table" class="listing stripe" width="100%" style="display: none;">
			<thead>
				<tr>
					<th>Date & Time</th>
					<th>User</th>
					<th>Activity</th>
					<th>Category</th>
					<th>IP Address</th>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
	</div>';
	
	$retval .= '</div></div>';
	return $retval;
}

function getAuditLogsData($startDate, $endDate, $category = null, $user = null) {
	global $log;
	
	$log->logActivity('Retrieved audit logs data for date range: ' . $startDate . ' to ' . $endDate, 'System Administration');
	
	$logs = $log->getAuditLogs($startDate, $endDate, $category, $user, 5000);
	$stats = $log->getAuditLogStats($startDate, $endDate);
	
	$response = array(
		'logs' => $logs ? $logs : array(),
		'stats' => $stats ? $stats : array(),
		'total' => $stats ? $stats['total_logs'] : 0
	);
	
	return json_encode($response);
}

function createAuditLogTable($data) {
	global $log;
	
	$log->logActivity('Generated audit log table', 'System Administration');
	
	// Decode the JSON data
	$tableData = json_decode($data, true);
	
	if (!$tableData || !is_array($tableData)) {
		return '<div style="color: red; text-align: center; padding: 20px;">Invalid data provided for table generation</div>';
	}
	
	// Create a FormGenerator instance to use createTblFromArray
	$formGen = new FormGenerator();
	
	// Use the createTblFromArray method to generate the table
	$tableHtml = $formGen->createTblFromArray($tableData, 'audit-logs-table');
	
	return $tableHtml;
}


function createDeleteUsersSection()
{
	global $log, $user;

	$log->logActivity('User Accessed the Delete User View', 'User Administration');

	$user_list = $user->getAllUsers();
	$retval = '<div class="module"><div class="module-title">Manage Users </div><div class="module-content"><div id="topup-application-content"><table width="100%" cellpadding="5" cellspacing="1" class="listing">';
	$retval .= '<thead><tr><th>FullName</th><th>UserID</th><th>Access Right</th><th>Delete ?</th></tr></thead>';
	$retval .= '<tbody>';
	if (is_array($user_list)) {
		foreach ($user_list as $users) {
			$retval .= '<tr>';
			$retval .= '<td>' . $users['Username'] . '</td>';
			$retval .= '<td>' . $users['UserID'] . '</td>';
			$retval .= '<td>' . $users['Role'] . '</td>';
			$retval .= '<td><a href="#" class="green-button-small"  title="reset-user" alt="' . $users['UserID'] . '">Reset User</a> &nbsp;<a href="#" class="red-button-small"  title="remove-user" alt="' . $users['UserID'] . '">Remove User</a></td>';
			$retval .= '</tr>';
		}
	}
	$retval .= '</tbody></table></div></div></div>';
	return $retval;
}

function createTransactionReportSection()
{
	global $log;

	$log->logActivity('User Accessed the Transaction Report Section ', 'Reports');

	$retval = '<div class="module"><div class="module-title">Transaction Report</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>Start Date <input readonly="readonly" type="text" id="start_date" name="start_date" class="datepicker" value="' . date('Y-m-d', strtotime('-2 week')) . '" size="25"/></label></td>
							<td><label>End Date <input readonly="readonly" type="text" id="end_date" name="end_date" class="datepicker" value="' . date('Y-m-d') . '" size="25"/></label></td>
							<td><input type="button" id="get-transaction-report" name="get-transaction-report" value="Spool Report"/></td>
							
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function getTransactionReport($start_date, $end_date) {
	global $log, $user;

	$log->logActivity('User Spooled Transaction Report for date '. $start_date .' to ' . $end_date, 'Reports');
	$transfers = new Transfer();
	return $user->createTblFromArray($transfers->getAllTransactions($start_date, $end_date),'transaction_report');
}


function createLimitApprovalSection()
{
	global $log;

	$log->logActivity('User Accessed the Limit Approval Section ', 'Customer Administration');

	$retval = '<div class="module"><div class="module-title">Pending Limit Approval</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>Start Date <input readonly="readonly" type="text" id="start_date" name="start_date" class="datepicker" value="' . date('Y-m-d', strtotime('-2 week')) . '" size="25"/></label></td>
							<td><label>End Date <input readonly="readonly" type="text" id="end_date" name="end_date" class="datepicker" value="' . date('Y-m-d') . '" size="25"/></label></td>
							<td><input type="button" id="get-limit-approval" name="get-limit-approval" value="Spool Report"/></td>
							
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function getPendingLimitApproval($start_date, $end_date) {
	global $log, $bankone;

	$log->logActivity('User Spooled pending limit change requests from '. $start_date .' to ' . $end_date, 'Customer Administration');
	$requests = $log->get_data("Select * from Admin.UpdateLimits where Status='Pending' and date(DateCreated) >=? and date(DateCreated) <=?",[$start_date, $end_date]);
	$retval = '<table cellpadding="5" cellspacing="1" class="listing" width="100%">';
	$retval .= '<thead><tr><th>SN</th><th>Date Created</th><th>CustomerID</th><th>CustomerName</th><th>UserID</th><th>Current Single Transaction Limit</th><th>Current Daily Transaction Limit</th><th>Proposed Single Transaction Limit</th><th>Proposed Daily Transaction Limit</th><th>Inputer</th><th></th></tr></thead>';
	$retval .= '<tbody>';
	$counter = 1;
	if (is_array($requests)) {
		foreach ($requests as $request) {
			$bankone->getCustomerNameFromCustomerID($request['CustomerID']);
			$retval .= '<tr>';
			$retval .= '<td>'.$counter.'</td>';
			$retval .= '<td>' . $request['DateCreated'] . '</td>';
			$retval .= '<td>' . $request['CustomerID'] . '</td>';
			$retval .= '<td>' . $bankone->getCustomerNameFromCustomerID($request['CustomerID']) . '</td>';
			$retval .= '<td>' . $request['UserID'] . '</td>';
			$retval .= '<td align="right">' . number_format($request['OldDailyLimit'],2) . '</td>';
			$retval .= '<td align="right">' . number_format($request['OldSingleTxnLimit'],2). '</td>';
			$retval .= '<td align="right">' . number_format($request['ProposedSingleTxnLimit'],2) . '</td>';
			$retval .= '<td align="right">' . number_format($request['ProposedDailyLimit'],2) . '</td>';
			$retval .= '<td>' . $request['Inputer'] . '</td>';
			$retval .= '<td><a href="#" id="' . $request['id'] . '" class="green-button-small" alt="Approve">Approve</a>&nbsp;&nbsp;<a href="#" id="' . $request['id'] . '" class="red-button-small alt="Decline">Decline</a></td>';
			$retval .= '</tr>';
			$counter ++;
		}
	} 
	$retval .='</table>';
	return $retval;
}

function authorizeLimitChange($status, $rowId) {
	global $log;

	$log->logActivity('User '.$status.' Limit Change Request ID '.$rowId, 'Customer Administration');
	$requests = $log->insert_data("Update Admin.UpdateLimits set Status=?, DateApproved=now(), Authorizer=? where Status='Pending' and ID=?",[$status, $_SESSION['uname'], $rowId]);
}



function createAccountLinkSection()
{
	global $log;

	$log->logActivity('User Accessed the Account Link Approval Section ', 'Customer Administration');

	$retval = '<div class="module"><div class="module-title">Pending Account Link Approval</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>Start Date <input readonly="readonly" type="text" id="start_date" name="start_date" class="datepicker" value="' . date('Y-m-d', strtotime('-2 week')) . '" size="25"/></label></td>
							<td><label>End Date <input readonly="readonly" type="text" id="end_date" name="end_date" class="datepicker" value="' . date('Y-m-d') . '" size="25"/></label></td>
							<td><input type="button" id="get-link-approval" name="get-link-approval" value="Spool Report"/></td>
							
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function getPendingLinkApproval($start_date, $end_date) {
	global $log, $bankone;

	$log->logActivity('User Spooled pending account link from '. $start_date .' to ' . $end_date, 'Customer Administration');
	$requests = $log->get_data("Select * from dbPetra.LinkedCustomerID where Status='Pending' and date(DateCreated) >=? and date(DateCreated) <=?",[$start_date, $end_date]);
	$retval = '<table cellpadding="5" cellspacing="1" class="listing" width="100%">';
	$retval .= '<thead><tr><th>SN</th><th>Date Created</th><th>Main CustomerID</th><th>Main CustomerName</th><th>CustomerID</th><th>CustomerName</th><th>Inputer</th><th></th></tr></thead>';
	$retval .= '<tbody>';
	$counter = 1;
	if (is_array($requests)) {
		foreach ($requests as $request) {
			$bankone->getCustomerNameFromCustomerID($request['CustomerID']);
			$retval .= '<tr>';
			$retval .= '<td>'.$counter.'</td>';
			$retval .= '<td>' . $request['DateCreated'] . '</td>';
			$retval .= '<td>' . $request['MainCustomerID'] . '</td>';
			$retval .= '<td>' . $bankone->getCustomerNameFromCustomerID($request['MainCustomerID']) . '</td>';
			$retval .= '<td>' . $request['CustomerID'] . '</td>';
			$retval .= '<td>' . $bankone->getCustomerNameFromCustomerID($request['CustomerID']) . '</td>';
			$retval .= '<td>' . $request['Inputer'] . '</td>';
			$retval .= '<td><a href="#" id="' . $request['id'] . '" class="green-button-small" alt="Approved">Approve</a>&nbsp;&nbsp;<a href="#" id="' . $request['id'] . '" class="red-button-small alt="Declined">Decline</a></td>';
			$retval .= '</tr>';
			$counter ++;
		}
	} 
	$retval .='</table>';
	return $retval;
}

function linkAccount($status, $rowId) {
	global $log;

	$log->logActivity($status. ' Account Link Request ID  '.$rowId, 'Customer Administration');
	$log->insert_data("Update dbPetra.LinkedCustomerID set Status=?, Authorizer=?, DateApproved=now() where ID=?",[$status, $_SESSION['uname'], $rowId]);
}


function createAccessRightSection() {
	global $log;

	$log->logActivity('User Accessed Access Right Page ', 'System Adminstration');
	$roles = new Role();
	$rs_roles = $roles->getRoles();

	$retval = '<div class="module"><div class="module-title">Modifiy Access Rights</div><div class="module-content">';
	$retval.= '<div class="filter" align="center">
		<hr/>
		<div class="filter-wrapper" align="left">
			<div style="padding:5px;">
				<table cellpadding="5" cellspacing="0">
					<tr>
						<td><label>Role<br/><select id="roleID" name="roleID"><option value=""></option>';
						foreach($rs_roles as $role) {
							$retval .='<option value="'.$role['RoleID'].'">'.$role['Role'].'</option>';
						}
		 $retval .='</select></label></td>
						
						<td><input type="button" id="get-role-rights" name="get-role-rights" value="Load Rights"/></td>
					</tr>
				</table>
			</div>
		</div>
	</div>';
	$retval .='<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function getRoleRights($roleID)
{
	$roles = new Role();

	$access_rights =  $roles->getRightsByRoleID($roleID);
	$other_rights = $roles->getOtherRights($roleID);

	$retval = '<table cellpadding="5" cellspacing="1" class="listing">';
	$retval .= '<thead><tr><th>Module</th><th>Sub Module</th><th>Credit Account</th></tr></thead>';
	$retval .= '<tbody>';
	if (is_array($access_rights)) {
		foreach ($access_rights as $access_right) {
			$retval .= '<tr>';
			$retval .= '<td>' . $access_right['ModuleName'] . '</td>';
			$retval .= '<td>' . $access_right['Name'] . '</td>';
			$retval .= '<td><a href="#" id="' . $access_right['ID'] . '" class="red-button-small" title="' . $roleID . '">Revoke Right</a></td>';
			$retval .= '</tr>';
		}
	} 
	$retval .= '<tr>';
	$retval .= '<td><input type="hidden" id="role_id_val" name="role_id_val" value="' . $roleID . '"/></td>';
	$retval .= '<td><select id="new_right_ID" name="new_right_ID"><option value=""></option>';
	foreach ($other_rights as $role) {
		$retval .= '<option value="' . $role['ID'] . '">' . $role['ModuleName'] . ' &rarr; ' . $role['Name'] . '</option>';
	}
	$retval .= '</select></td>';
	$retval .= '<td><input type="button" id="add-role-rights" name="add-role-rights" value="Grant Access"/></td>';
	$retval .= '</tr>';
	return $retval;
}

function revokeRights($roleID, $serviceID) {
	global $user, $log;

	$log->logActivity('Revoked Access ServiceID '. $serviceID .' on RoleID '. $roleID, 'System Adminstration');
	$roles = new Role();
	$roles->revokeAccessRight($roleID, $serviceID); 
	return "Access Revoked";
}

function grantRights($roleID, $serviceID) {
	global $user, $log;

	$log->logActivity('Granted Access ServiceID '. $serviceID .' on RoleID '. $roleID, 'System Adminstration');
	$roles = new Role();
	$roles->grantAccessRight($roleID, $serviceID);
	return "Access Granted";
}


function createNewUserSection()
{
	global $log, $user;
	$log->logActivity('User Accessed the Create New User Section', 'Customer Adminstration');
	return $user->createFormsection();
}

function createNewUser($inputdata)
{
	global $log, $user;

	parse_str($inputdata, $data);
	$log->logActivity('User Created User '.$data['userID'] , 'System Adminstration');
	if (!$user->existingUser($data['userID']) && strlen(trim($data['userID'])) >=6 && trim($data['email']) != "") {
		$user->createUsers($data['userID'], $data['fullname'], $data['email'], $data['mobile'], $data['managerID'], $data['roleID']);
		return json_encode(array('message' => 'User successfully created'));
	} else {
		return json_encode(array('message' => 'UserID already exists, please try another userID'));
	}
}

function createChangePasswordForm() {
	global $log;

	$log->logActivity('User Accessed the Change Password Page' , 'System Adminstration');
	$retVal = '<div class="formMenuSection"><ul><li class="selected">Change Password</li></ul><br clear="all"/></div><form id="changePassword" method="post" name="changePassword" action="../src/submitVals.php"><div class="formContent">';
		$retVal.='<table cellpadding="5" cellspacing="0">';
			$retVal.='<tr><td colspan="2">Please fill the form below to change your password. Note that your password';
			$retVal .='<ol><li>Should be at least 8 characters long</li><li>Must have a numeric character</li><li>Must have an uppercase character</li></ol></td></tr>';
			$retVal.='<tr><td>Old Password</td><td><input type="password" class="required" name="oldpassword" id="oldpassword" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>New Password</td><td><input type="password" class="required"  name="password" id="password" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>Confirm Password</td><td><input type="password" class="required"  name="confirmpassword" id="confirmpassword" value="" size="35"/></td></tr>';
			$retVal.='<tr><td></td><td><input type="button" name="changePasswordSubmit" id="changePasswordSubmit" value="Change Password"/></td></tr>';
		$retVal.='</table>';
	$retVal .='</div></form>';
	return $retVal;
}

function createChangeAuthPasswordForm() {
	global $log;

	$log->logActivity('User Accessed the Change Authorization Password Page' , 'System Adminstration');
	$retVal = '<div class="formMenuSection"><ul><li class="selected">Change Authorization Password</li></ul><br clear="all"/></div><form id="changePassword" method="post" name="changeAuthPassword" action="../src/submitVals.php"><div class="formContent">';
		$retVal.='<table cellpadding="5" cellspacing="0">';
			$retVal.='<tr><td colspan="2">Please fill the form below to change your password. Note that your password';
			$retVal .='<ol><li>Should be at least 8 characters long</li><li>Must have a numeric character</li><li>Must have an uppercase character</li></ol></td></tr>';
			$retVal.='<tr><td>Old Password</td><td><input type="password" class="required" name="oldpassword" id="oldpassword" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>New Password</td><td><input type="password" class="required"  name="password" id="password" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>Confirm Password</td><td><input type="password" class="required"  name="confirmpassword" id="confirmpassword" value="" size="35"/></td></tr>';
			$retVal.='<tr><td></td><td><input type="button" name="changePasswordSubmit" id="changePasswordSubmit" value="Change Password"/></td></tr>';
		$retVal.='</table>';
	$retVal .='</div></form>';
	return $retVal;
}

function createNewCustomerSection() {
	global $log;

	$log->logActivity('User Accessed the Create New Customer Page' , 'Customer Adminstration');
	$retVal = '<div class="formMenuSection"><ul><li class="selected">Create New Customer</li></ul><br clear="all"/></div><form id="createNewCustomerForm"><div class="formContent">';
		$retVal.='<table cellpadding="5" cellspacing="0">';
			$retVal.='<tr><td>CustomerID</td><td><input type="text" name="customerID" id="customerID" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>CustomerName</td><td><input type="text" name="customerName" id="customerName" value="" readonly size="35"/></td></tr>';
			$retVal.='<tr><td>UserID</td><td><input type="text" name="userID" id="userID" value=""  size="35"/></td></tr>';
			$retVal.='<tr><td>Firstname</td><td><input type="text" name="Firstname" id="Firstname" value=""  size="35"/></td></tr>';
			$retVal.='<tr><td>LastName</td><td><input type="text" name="Lastname" id="Lastname" value=""  size="35"/></td></tr>';
			$retVal.='<tr><td>Mobile</td><td><input type="text" name="mobile" id="mobile" value=""  size="35"/></td></tr>';
			$retVal.='<tr><td>Email</td><td><input type="text" name="email" id="email" value=""  size="35"/></td></tr>';
			$retVal.='<tr><td>Single Transaction Limit </td><td><input type="text" name="txnLimit" id="singleTxnLimit" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>Daily Transaction Limit </td><td><input type="text" name="txnLimit" id="txnLimit" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>Role</td><td><select id="role" name="role"><option value="Viewer">Viewer</option><option value="Initiator">Initiator1</option><option value="I-Initiator">Initiator2</option><option value="Authorizer">Authorizer</option></select</td></tr>';
			$retVal.='<tr><td></td><td><input type="button" name="createNewCustomer" id="createNewCustomer" class="createNewCustomerForm" value="Create Customer"/></td></tr>';
		$retVal.='</table>';
	$retVal .='</div></form>';
	return $retVal;
}

function createNewCustomer($data) {
	global $customer, $log;

	parse_str($data, $payload);

	$log->logActivity('User created new Customer '.$payload['customerID'] .'---'. $payload['userID'] , 'Customer Adminstration');
	$response = $customer->createUser($payload);
	if($response) {
		return "Customer has been created and now waiting for Authorization";
	}else {
		return "Error creating account, email or mobile exists";
	}
}

function getChangeTxnLimitUI($userID) {
	global $customer;

	$response = $customer->getUserDetailsByRowID($userID );
	$retVal = '';
		$retVal.='<table cellpadding="5" cellspacing="0">';
			$retVal.='<tr><td>Single Transaction Limit </td><td><input type="text" name="singleTxnLimit" id="singleTxnLimit" value="'.$response['SingleTxnLimit'].'" size="35"/></td></tr>';
			$retVal.='<tr><td>Daily Transaction Limit </td><td><input type="text" name="dailyTxnLimit" id="dailyTxnLimit" value="'.$response['TxnLimit'].'" size="35"/></td></tr>';
			$retVal.='<tr><td><input type="hidden" id="userID" name="userID" value="' . $userID . '"/></td><td><input type="button" name="changeLimit" id="changeLimit"  value="Change Limit"/></td></tr>';
		$retVal.='</table>';
	return $retVal;
}

function getLinkedAccountUI($cod_cust) {
	global $log;

	$log->logActivity('Accessed Linked Customer Modal for Customer '.$cod_cust, 'Customer Adminstration');
		$retVal='<table cellpadding="5" cellspacing="0">';
			$retVal.='<tr><td>CustomerID</td><td><input type="text" name="linkedCustomerID" id="linkedCustomerID" value="" size="35"/></td></tr>';
			$retVal.='<tr><td>CustomerName</td><td><input type="text" name="linkedCustomerName" id="linkedCustomerName" value="" readonly  size="50"/></td></tr>';
			$retVal.='<tr><td><input type="hidden" id="mainCustomerID" name="mainCustomerID" value="' . $cod_cust . '"/></td><td><input type="button" name="linkCustomer" id="linkCustomer"  value="Add to Profile"/></td></tr>';
		$retVal.='</table>';
	return $retVal;
}

function updateTxnLimit($userID, $singleTxnLimit, $dailyTxnLimit) {
	global $customer;

	$customer->insert_data("insert into Admin.UpdateLimits(UserID, OldDailyLimit, OldSingleTxnLimit, ProposedDailyLimit, ProposedSingleTxnLimit, CustomerID, Inputer, DateCreated) Select UserID, TxnLimit, SingleTxnLimit, ?,?, CustomerID, ?, now() from dbPetra.Credentials where ID=?",[$dailyTxnLimit,$singleTxnLimit,$_SESSION['uname'],$userID]);
}


function createManageCustomerSection() {
	global $log;

	$log->logActivity('User accessed the manage customer page ', 'Customer Adminstration');
	$retval = '<div class="module"><div class="module-title">Manage Customer</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>CustomerID <input type="text" id="customerID" name="customerID" value="" size="35"/></label></td>
							<td><input type="button" id="get-customers" name="get-customers" value="LookUp Customer"/></td>
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function createAuthorizeAccountSetup() {
	global $log;

	$log->logActivity('User accessed the authorize customer page', 'Customer Adminstration');
	$retval = '<div class="module"><div class="module-title">Authorize Account Setup</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>CustomerID <input type="text" id="customerID" name="customerID" value="" size="35"/></label></td>
							<td><input type="button" id="get-customers" name="get-customers" value="LookUp Customer"/></td>
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}


function filterArray(array $array, callable $callback): array {
    return array_filter($array, $callback);
}

function createAuthorizePasswordReset() {
	global $log;

	$log->logActivity('User accessed the manage customer page', 'Customer Adminstration');
	$retval = '<div class="module"><div class="module-title">Authorize Password Reset</div><div class="module-content">';
	$retval .= '<div class="filter" align="center">
			<hr/>
			<div class="filter-wrapper" align="left">
				<div style="padding:5px;">
					<table cellpadding="5" cellspacing="0">
						<tr>
							<td><label>CustomerID <input type="text" id="customerID" name="customerID" value="" size="35"/></label></td>
							<td><input type="button" id="get-customers" name="get-customers" value="LookUp Customer"/></td>
						</tr>
					</table>
				</div>
			</div>
		</div>';
	$retval .= '<div id="topup-application-content"></div></div></div>';
	return $retval;
}

function getAllUsersByCustomerID($cod_cust, $status=null) {
	global $customer, $bankone;

	$users = $customer->getAllUsersByCustomerID($cod_cust);

	if ($status =='PendingReset' ) {
		$users = filterArray($users, function($account) {
			return $account['Status'] == 'PendingReset' ;
		});
	}

	if ($status =='New' ) {
		$users = filterArray($users, function($account) {
			return $account['Status'] == 'New';
		});
	}
	
	$retval ='<table class="listing" cellpadding="5" cellspacing="1" width="100%"><thead><tr><th>Firstname</th><th>Lastname</th><th>Email</th><th>Mobile</th><th>UserID</th><th>Daily Transaction Limit</th><th>Single Transaction Limit</th><th>Role</th><th>Status</th><th></th></tr></thead>';
	$retval .='<tbody>';
	if(is_array($users)) {
		foreach($users as $user) {
			$retval .='<tr>';
				$retval.='<td>'.$user['Firstname'].'</td>';
				$retval.='<td>'.$user['Lastname'].'</td>';
				$retval.='<td>'.$user['Email'].'</td>';
				$retval.='<td>'.$user['Mobile'].'</td>';
				$retval.='<td>'.$user['UserID'].'</td>';
				$retval.='<td align="right">'. number_format($user['TxnLimit'],2).'</td>'; 
				$retval.='<td align="right">'. number_format($user['SingleTxnLimit'],2).'</td>';
				$retval.='<td>'.$user['Role'].'</td>';
				$retval.='<td>'.$user['Status'].'</td>';
				$retval.='<td>';
					if ($user['Status'] == 'Active' || $user['Status'] == 'ChangePassword') { 
						$retval .='<span class="green-button-small" title="'.$user['ID'].'" alt="Reset">Reset</span> <span class="green-button-small" title="'.$user['ID'].'" alt="Lock">Lock</span> <span class="green-button-small" title="'.$user['ID'].'" alt="ChangeTxnLimit">Change Txn Limit</span>';
					}
	
					if ($user['Status'] == 'Locked') { 
						$retval .='<span class="green-button-small" title="'.$user['ID'].'" alt="Reset">Reset</span> <span class="green-button-small" title="'.$user['ID'].'" alt="Reactivate">Reactivate</span>';
					}
					
					/*
					if ($user['Status'] == 'New' && $status==null) { 
						$retval .='<span class="green-button-small" title="'.$user['ID'].'" alt="Delete">Delete</span>';
					}
					*/

					if ($user['Status'] == 'PendingReset' && $status=='PendingReset') { 
						$retval .='<span class="green-button-small" title="'.$user['ID'].'" alt="Authorize">Authorize</span><span class="red-button-small" title="'.$user['ID'].'" alt="Declined">Decline</span>';
					}
	
					if ($user['Status'] == 'New' && $status=='New') { 
						$retval .='<span class="green-button-small" title="'.$user['ID'].'" alt="Authorize">Authorize</span><span class="red-button-small" title="'.$user['ID'].'" alt="Declined">Decline</span>';
					}
				$retval .='</td>';
			$retval .='</tr>';
		}
	}else {
		$retval .='<tr><td colspan="7">No records</td></tr>';
	}
	$retval.='</tbody></table>';


	$linked_accounts = $customer->get_data("Select * from dbPetra.LinkedCustomerID where Status='Approved' and MainCustomerID=?",[$cod_cust]);
	$retval .='<br/><br/>Linked Accounts<br/><table class="listing" cellpadding="5" cellspacing="1" width="100%"><thead><tr><th>CustomerID</th><th>CustomerName</th><th>Status</th><th>DateCreated</th><th>Date Approved</th><th>Inputer</th><th>Authorizer</th><th></th></tr></thead>';
	$retval .='<tbody>';
	if(is_array($linked_accounts)) {
		foreach($linked_accounts as $account) {
			$retval .='<tr>';
				$retval.='<td>'.$account['CustomerID'].'</td>';
				$retval.='<td>'.$bankone->getCustomerNameFromCustomerID($account['CustomerID']).'</td>';
				$retval.='<td>'.$account['Status'].'</td>';
				$retval.='<td>'.$account['DateCreated'].'</td>';
				$retval.='<td>'.$account['DateApproved'].'</td>';
				$retval.='<td>'.$account['Inputer'].'</td>';
				$retval.='<td>'.$account['Authorizer'].'</td>';
				$retval.='<td>';
					if ($account['Status'] == 'Approved') { 
						$retval .='<span class="red-button-small" title="'.$account['id'].'" alt="UnlinkAccount">Unlink Account</span>';
					}
				$retval .='</td>';
			$retval .='</tr>';
		}
	}else {
		$retval .='<tr><td colspan="8"><span class="green-button-small" title="'.$cod_cust.'" alt="AddNew">Add New</span></td></tr>';
	}
	$retval.='</tbody></table>';
	
	return $retval;
}


function updateUserStatus($userID, $status) {
	global $customer, $log;

	$log->logActivity('User updated UserID '.$userID.' to Status '. $status, 'Customer Administration');

	switch ($status) {
		case "Authorize": {
			$details = $customer->getUserDetailsByRowID($userID);
			if($details['Status'] =='PendingReset') {
				$customer->approveResetUser($userID);
			}	
			if($details['Status'] =='New') {
				$customer->approveUser($userID);
			}
			break;
		}

		case "Delete": {
			$customer->insert_data("delete from  dbPetra.Credentials where ID=?",[$userID]);
			break;
		}

		case "Decline":{
			$customer->updateStatus('Declined',$userID);
			break;
		}

		case "Reactivate":{
			$customer->updateStatus('Active',$userID);
			break;
		}

		case "Lock":{
			$customer->updateStatus('Locked',$userID);
			break;
		}

		case "Delete":{
			$customer->updateStatus('Deleted',$userID);
			break;
		}

		case "Reset": {
			$customer->resetUser($userID);
			break;
		}
	}


}

function validateCustomerByID($cod_cust) {
	global $bankone, $log;

	$log->logActivity('User validated CustomerID '.$cod_cust,  'Customer Administration');

	$response = $bankone->getCustomerByID($cod_cust);
	return $response['CustomerName'];
}


function changePassword($oldpassword,$password) {
	global $log;

	$log->logActivity('User Changed Password ',  'Customer Administration');
	$authenticate = new Authenticate();
	$response = $authenticate->changePassword($_SESSION['uname'], $password, $oldpassword);//changePassword called from authenticate class
	return $response;
}


function validateUserID($userID) {
	global $log;

	if(is_array($log->get_data("Select * from dbPetra.Credentials where UserID=?",[$userID]))) {
		return json_encode([
			"success"=>false,
			"message"=>"UserID already exists, use another ID for user"
		]);
	}else {
		return json_encode([
			"success"=>true,
			"message"=>"UserID available"
		]);
	}
}




switch ($_POST['action']) {

	case "createNewCustomerSection": {
		echo createNewCustomerSection();
		break;
	}

	case "createNewCustomer": {
		echo createNewCustomer($_POST['formContent']);
		break;
	}

	case "validateCustomerByID": {
		echo validateCustomerByID($_POST['customerID']);
		break;
	}

	case "validateUserID": {
		echo validateUserID($_POST['userID']);
		break;
	}

	case "createManageCustomerSection": {
		echo createManageCustomerSection();
		break;
	}

	case "getAllUsersByCustomerID": {
		echo getAllUsersByCustomerID($_POST['customerID']);
		break;
	}

	case "getPendingSetup": {
		echo getAllUsersByCustomerID($_POST['customerID'],'New');
		break;
	}

	case "getPendingAuthorization": {
		echo getAllUsersByCustomerID($_POST['customerID'],'PendingReset');
		break;
	}

	case "updateUserStatus": {
		echo updateUserStatus($_POST['userID'], $_POST['status']);
		break;
	}

	case "createAuthorizePasswordReset": {
		echo createAuthorizePasswordReset();
		break;
	}

	case "createAuthorizeAccountSetup": {
		echo createAuthorizeAccountSetup();
		break;
	}
	
	case "createNewUserSection": {
		echo createNewUserSection();
		break;
	}
	
	case "createAccessRightSection": {
		echo createAccessRightSection();
		break;
	}

	case "getRoleRights": {
		echo getRoleRights($_POST['roleID']);
		break;
	}

	case "revokeAccessRights": {
		echo revokeRights($_POST['roleID'],$_POST['serviceID']);
		break;
	}

	case "grantAccessRights": {
		echo grantRights($_POST['roleID'],$_POST['serviceID']);
		break;
	}
	
	case "createTransactionReportSection": {
		echo createTransactionReportSection();
		break;
	}

	case "getTransactionReport": {
		echo getTransactionReport($_POST['start_date'], $_POST['end_date']);
		break;
	} 

	case "createChangePasswordForm": {
		echo createChangePasswordForm();
		break;
	}

	case "changePassword": {
		echo changePassword($_POST['oldPassword'], $_POST['Password']);
		break;
	}

	case "createDeleteUsersSection": {
		echo createDeleteUsersSection();
		break;
	}

	case "removeUser": {
		echo $user->deleteUser($_POST['userID']);
		break;
	}

	case "createNewUser": {
		echo createNewUser($_POST['formContent']);
		break;
	}

	case "createAuditLogViewerSection": {
		$log->logActivity('User accessed audit log viewer interface', 'System Administration');
		echo createAuditLogViewerSection();
		break;
	}

	case "getAuditLogsData": {
		$log->logActivity('Retrieved audit logs data', 'System Administration');
		echo getAuditLogsData($_POST['startDate'], $_POST['endDate'], $_POST['category'], $_POST['user']);
		break;
	}

	case "createAuditLogTable": {
		$log->logActivity('Generated audit log table', 'System Administration');
		echo createAuditLogTable($_POST['data']);
		break;
	}

	case "getChangeTxnLimitUI": {
		$log->logActivity('Accessed Change Txn Limit UI for User '.$_POST['userID'], 'Customer Administration');
		echo getChangeTxnLimitUI($_POST['userID']);
		break;
	}

	case "updateTxnLimit": {
		$log->logActivity('UpdateTxn Limit for UserID'.$_POST['userID'], 'Customer Administration');
		echo updateTxnLimit($_POST['userID'],$_POST['singleTxnLimit'],$_POST['dailyTxnLimit']);
		break;
	}

	case "createLimitApprovalSection": {
		echo createLimitApprovalSection();
		break;
	}

	case "getPendingLimitApproval": {
		echo getPendingLimitApproval($_POST['start_date'],$_POST['end_date']);
		break;
	}

	case "authorizeLimitChange": {
		echo authorizeLimitChange($_POST['status'],$_POST['rowId']);
		break;
	}

	case "getLinkedAccountUI": {
		echo getLinkedAccountUI($_POST['cod_cust']);
		break;
	}

	case "linkAccountRequest": {
		$log->logActivity('Link Account Request for MainCustomerID '.$_POST['customerID'].'to CustomerID '.$_POST['linkedCustomerID'], 'Customer Administration');
		$log->insert_data("insert into dbPetra.LinkedCustomerID(MainCustomerID, CustomerID, Inputer) values (?,?,?)",[$_POST['customerID'], $_POST['linkedCustomerID'], $_SESSION['uname']]);
		break;
	}

	case "createAccountLinkSection": {
		echo createAccountLinkSection();
		break;
	}

	case "getPendingLinkApproval": {
		echo getPendingLinkApproval($_POST['start_date'],$_POST['end_date']);
		break;
	}

	case "linkAccount": {
		echo linkAccount($_POST['status'],$_POST['rowId']);
		break;
	}

	case "unLinkAccount": {
		$log->logActivity('Unlink Account with RequestID '.$_POST['rowId'], 'Customer Administration');
		$log->insert_data("update dbPetra.LinkedCustomerID set Status='Unlinked', ModifiedBy=? where id=?",[$_SESSION['uname'], $_POST['rowId']]);
	}

}
