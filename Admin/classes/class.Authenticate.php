<?php
if(session_status() == PHP_SESSION_NONE){
    //session has not started
    session_start();
}
include_once 'class.dbConnect.php';
include_once 'class.SMTP.php';
include_once 'class.Mailer.php';

class Authenticate extends dbConnect {
	private $roles;
	
	function generatePassword($password) {
		return password_hash($password, PASSWORD_BCRYPT);
	}
	
	function changePassword($username,$password,$oldpassword) {

		if (!self::validatePassword($password)) {
			return [
				"response" => false, 
				"message"=> "Invalid Password Format, <ul><li>Should be at least 8 characters long</li><li>Must have a numeric character</li><li>Must have an uppercase character</li></ul>"
			];
		}

		if(trim($password) == trim($oldpassword)) {
			return [
				"response" => false, 
				"message"=> "Invalid Password, you can't reuse your last password"
			];
		}

		$response = self::authenticateUsers($username, $oldpassword);
		if ($response['login'])  {
			$this->insert_data("update Admin.Users set Password=?, LPasswordCDate=now(), LastLoginDate= now() where UserID=?",[self::generatePassword($password),$username]); 
			return [
				"response" => true, 
				"message"=> "Password has been successfully changed"
			];
		}else {
			return [
				"response" => false, 
				"message"=> "Invalid Password"
			];
		}
	}

	function resetPassword($username) {
		
		$rs = $this->get_data("Select * from Admin.Users where UserID=?",[$username]);
		if(is_array($rs)) {
			$password = self::getToken(10);
			$this->insert_data("Update Admin.Users set Password=?, LastLoginDate=null ,Secret=null where UserID=?",[self::generatePassword($password),$username]);

			$template = $this->get_data("Select Content from dbPetra.Contents where Type='RESET_ADMIN_USER'");
            $mail_content = str_replace('[Password]',$password,$template[0]['Content']);
            $mail_content = str_replace('[FirstName]',$rs[0]['Username'],$mail_content);
            
            $mailer = new Mailer();
            $mailer->AddAddress($rs[0]['Email']);
            $mailer->set('Subject','Petra Admin Password Reset');
            $mailer->msgHTML($mail_content);
		}
		

	}
	
	function changeAuthPassword($username,$password,$oldpassword) {
		if (self::authenticateAuthUsers($username, $oldpassword))  {
			if (self::validatePassword($password)) {
				$this->insert_data("update Admin.Users set Auth_Password=?, LAuth_PasswordCDate=now() where UserID=?",[self::generatePassword($password),$username]);

				return 'Authorization Password has been changed successfully';
			}else {
				return 'Invalid Password Format';
			}
		}else {
			return 'Invalid Password';
		}
	}

	function validatePassword($password) {
		if (strlen($password) > 7 && strlen($password) < 21 && preg_match('`[A-Z]`',$password) && preg_match('`[a-z]`',$password) && preg_match('`[0-9]`',$password)){
	      return true;
	    }else{
	        return false;
	    }
	}

	function createValidSession($username) {
		$session_token = self::getToken(20);
		$this->insert_data("Update Admin.Users set LastAuthenticatedToken=?, LastLoginDate=now() where UserID=?",[$session_token,$username]);
		$_SESSION['token'] = $session_token;
		$_SESSION['uname'] = strtolower($username);
	}


	function updateSecret($username, $secret) {
		$this->insert_data("Update Admin.Users set Secret=? where UserID=?",[$secret,strtolower($username)]);
		
	}
	


	function authenticateUsers($username,$password) {
		$rs = $this->get_data("select Password, LastLoginDate, Secret, datediff(now(), LPasswordCDate) as Age from Admin.Users where UserID=?",[strtolower($username)]);
		if (is_array($rs)) {
			$dbPassword = $rs[0]['Password'];
			if (password_verify($password,$rs[0]['Password'])) {
				if($rs[0]['LastLoginDate'] =="" or $rs[0]['Age'] > 90 ) {
					return array("login"=>true,"status"=>"change_password_ui");
				}

				if($rs[0]['Secret'] =="") {
					return array("login"=>true,"status"=>"reg_2fa_ui");
				}

				return array("login"=>true,"status"=>"enter_2fa_ui","secret"=>$rs[0]['Secret']);
			}else {
				return array("login"=>false);
			}
		}else {
			return array("login"=>false);
		}
	}

	function getToken($length){
		 // Cryptographically secure (random_int(), not rand()) -- used for
		 // session tokens and password-reset codes.
		 $token = "";
		 $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		 $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
		 $codeAlphabet.= "0123456789";
		 $max = strlen($codeAlphabet);

		 for ($i=0; $i < $length; $i++) {
		  $token .= $codeAlphabet[random_int(0, $max-1)];
		 }
		 return $token;
	 }

	function authenticateAuthUsers($username,$password) {
		$rs = $this->get_data("select * from Admin.Users where UserID=?",[strtolower($username)]);
		if (is_array($rs)) {
			if (password_verify($password,$rs[0]['Auth_Password'])) {
				return true;
			}else {
				return false;
			}
		}else {
			return false;
		}
	}

	function validateTokenID($username,$tokenID) {
		$rs= $this->get_data("select * from Admin.Users where UserID=?",[strtolower($username)]);
		if(is_array($rs)) {
			// hash_equals() instead of == -- a plain string comparison on a
			// security token is a timing side-channel, however small.
			if(hash_equals((string)$rs[0]['LastAuthenticatedToken'], (string)$tokenID)) {
				return true;
			}else {
				return false;
			}
		}else {
			return false;
		}
	}

}

?>