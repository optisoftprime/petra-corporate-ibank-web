<?php
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$inactive_timeout = 600;
	if (isset($_SESSION['last_activity'])) {
		$inactive_time = time() - $_SESSION['last_activity'];
		if ($inactive_time >= $inactive_timeout) {
			session_unset();
			session_destroy();
			session_start();
		}
	}
	$_SESSION['last_activity'] = time();
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
	$authenticate = new Authenticate();
	$totp = new TOTPManager();

	

	function login($username, $password) {
		global $authenticate, $totp;

		$response = $authenticate->authenticateUsers($username, $password);
		if ($response['login']) {
			unset($_SESSION['message']);
			$_SESSION['status'] = $response['status'];
			$_SESSION['temp_name'] = $username;
			if($response['status'] =="reg_2fa_ui") {
				$enrollment = $totp->enroll($username, 'Petra Admin');
				$_SESSION['secret'] = $enrollment['secret'];
				$_SESSION['qr_code_url'] = $enrollment['qr_code_url'] ;
			}

			if($response['status'] =="enter_2fa_ui") {
				$_SESSION['secret'] = $response['secret']; 
			}
		}else {
			$_SESSION['message'] = "Invalid Password";
		}
	}


	function changepasword($username, $currentpassword, $newpassword) {
		global $authenticate;

		$response = $authenticate->changePassword($username,$newpassword,$currentpassword);
		if($response["response"]) {
			login($username, $newpassword);
		}else {
			$_SESSION['message']= $response["message"]; 
		}
	}

	function resetPassword($userID) {
		global $authenticate;

		$authenticate->resetPassword($userID);
		$_SESSION['message']= "See your email to proceed with the password reset";
	}


	function register2FA($username, $secret, $otpcode) {
		global $authenticate, $totp;

		if($totp->validateCode($otpcode, $secret)) {
			$authenticate->updateSecret($username, $secret);
			$authenticate->createValidSession($_SESSION['temp_name']);
			return true;
		}else {
			return false;
		}

	}

	function validateOTP($secret, $otpcode) {
		global $authenticate, $totp;

		if($totp->validateCode($otpcode, $secret)) {
			$authenticate->createValidSession($_SESSION['temp_name']);
			return true;
		}else {
			return false;
		}

	}



	$validate = false; 
	switch($_POST['action']) {
		case "login": {
			$response = login($_POST['loginame'], $_POST['password']);
			break;
		}

		case "changepassword": {
			changepasword($_SESSION['temp_name'], $_POST['currentpassword'], $_POST['newpassword']);
			break;
		}

		case "register2fa": {
			if(register2FA($_SESSION['temp_name'], $_SESSION['secret'], $_POST['otpcode'])) {
				$_SESSION['uname'] = $_SESSION['temp_name'];
				unset($_SESSION['temp_name']);
				unset($_SESSION['message']);
				unset($_SESSION['secret']);
				unset($_SESSION['status']);
				$validate = true;
			}
			break;
		}

		case "forgot_password": {
			resetPassword($_POST['userID']);
			break;
		}
		
		case "validateOTP": {
			if(validateOTP($_SESSION['secret'], $_POST['otpcode'])) {
				$_SESSION['uname'] = $_SESSION['temp_name'];
				unset($_SESSION['temp_name']);
				unset($_SESSION['message']);
				unset($_SESSION['status']);
				unset($_SESSION['secret']);
				header('location:../views/main.php');
				$validate = true;
			}
			break;
		}
	}

	if($validate) {
		header('location:../views/main.php');
	}else {
		header('location:../views/index.php');
	}
	


?>