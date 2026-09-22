<?php

class UserAdmin extends FormGenerator {
	public $username = '';
	private $roleName ='';
	public $roleID ='';
	private $services ='';
	public $userid ='';
	public $mobile ='';
	public $email ='';
	public $pass_state='';
	
	function __construct($username) {
		$this->userid = $username;
		$role = new Role();
		$row = $this->get_data("Select a.Username,a.RoleID,Mobile,Email,b.Role,b.Services from Admin.Users a, Admin.Roles b where a.userID=? and a.RoleID = b.RoleID", [$username]);
		if (is_array($row)) {
			$this->username =$row[0]['Username'];
			$this->roleID = $row[0]['RoleID'];
			$this->roleName = $row[0]['Role'];
			$this->services = $role->getServices($this->roleID);
			$this->mobile = $row[0]['Mobile'];
			$this->email = $row[0]['Email'];
		}
	}

	/**
	 * Shared by createUsers() and resetPassword(). Uses random_int(), not
	 * array_rand() -- the latter is not cryptographically secure.
	 */
	private function generatePassword($length = 8) {
		$alphas = array_values(array_merge(range('A', 'Z'), range('a', 'z'), range(2, 9)));
		$password = "";
		for ($i = 0; $i < $length; $i++) {
			$password .= $alphas[random_int(0, count($alphas) - 1)];
		}
		return $password;
	}

	function createUsers($userid,$username, $email, $mobile, $managerID, $roleID) {
		$password = self::generatePassword();
		$authenticate = new Authenticate();

		$encrypted_password = $authenticate->generatePassword($password);
		$rs = $this->insert_data("insert into Admin.Users (UserID,Username,Password, RoleID, Mobile, Email, ManagerID) values (?,?,?,?,?,?,?) ", [$userid,$username,$encrypted_password,$roleID,$mobile,$email,$managerID]);
		
		$rs = $this->get_data("Select * from dbPetra.Contents where Type='CREATE_ADMIN_USER'");
		$mail_content = str_replace('[FirstName]',$username,$rs[0]['Content']);
		$mail_content = str_replace('[Username]',$userid,$mail_content);
		$mail_content = str_replace('[Password]',$password,$mail_content);

		$mailer = new Mailer();
        $mailer->AddAddress($email);
        $mailer->set('Subject','Petra Admin');
        $mailer->msgHTML($mail_content);
        $mailer->Send();
	}

	function modifyRoles($userid,$roleID) {
		$this->insert_data("Update Admin.Users set RoleID=? where UserID=? and RoleID != 1",[$roleID,$userid]);
		return true;
	}

	function deleteUser($userID) {
		$this->insert_data("delete from Admin.Users where UserID=? and RoleID !=1",[$userID]);
		// insert_data() only reports whether the DELETE executed without
		// throwing, not whether it actually removed a row -- a RoleID=1
		// account is deliberately protected by the guard above and would
		// still "succeed" (no exception, zero rows) by that measure.
		// existingUser() gives an honest answer instead.
		return self::existingUser($userID) ? "Unable to delete this account" : "Account has been successfully deleted";
	}


	function resetPassword($userID) {
		$password = self::generatePassword();
		$authenticate = new Authenticate();
		$encrypted_password = $authenticate->generatePassword($password);
		$this->insert_data("Update Admin.Users set Password=? where UserID=? and RoleID != 1", [$encrypted_password,$userID]);
		$user = $this->get_data("Select Email from Admin.Users where UserID=?",[$userID]);
		$template = $this->get_data("Select * from dbPetra.Contents where Type='RESET_ADMIN_USER'");
		$mail_content = str_replace('[FirstName]',$userID,$template[0]['Content']);
		$mail_content = str_replace('[Password]',$password,$mail_content);

		$mailer = new Mailer();
        $mailer->AddAddress($user[0]['Email']);
        $mailer->set('Subject','Petra Admin Reset');
        $mailer->msgHTML($mail_content);
        $mailer->Send();

		return 'Password Reset Successful';
	}
	
	function getServices() {
		$row = $this->get_data("select * from Admin.Category where id in (?) order by Name",[$this->services]);
		if (is_array($row)) {
			return $row;
		}
	}
	
	function getRoleName() {
		return $this->roleName;
	}
	
	function getUserRoleID () {
		return $this->roleID;
	}
	
	function existingUser($userID) {
		$rs = $this->get_data("select * from Admin.Users where UserID=?",[$userID]);
		if(is_array($rs)) {
			return true;
		}else {
			return false;
		}
	}

	function getAllUsers() {
		$rs = $this->get_data("Select * from Admin.Users a, Admin.Roles b where a.RoleID=b.RoleID");
		return $rs;
	}

	function createFormsection() {
		$index = false;
		$retVal = '<div class="formMenuSection"><ul>';
		$retVal .='<li title="Expense" class="selected">Create New User</li>';
		$retVal.='</ul><br clear="all"/></div><form id="createNewUser" method="post" name="createNewUser" action="../src/submitVals.php"><div class="formContent">'.self::generateSection().'</div></form>';
		return $retVal;
	}

	function generateSection() {
		$retval ='<div id="User"><table cellpadding="7" cellspacing="0" border="0" width="100%">';
		$rows = $this->get_data("select * from Admin.Forms where category='Users' order by id");
		foreach($rows as $row) {
			$retval.='<tr>';
			$retval.='<td width="30%">'.$row['field_long_name'].'</td>';
			$retval.='<td width="70%">'.self::createField($row['field_short_name'],@$sectionID, $row['field_type'], $row['field_range'], $row['required']).'</td>';
			$retval.='</tr>';
		}
		$retval.='<tr><td></td><td><input type="button" id="saveSubmit" name="saveSubmit" class="User" value="Create User"/></td></tr>';
		$retval.='</table></div>';
		return $retval;
	}
	
}