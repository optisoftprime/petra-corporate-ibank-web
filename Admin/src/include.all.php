<?php
date_default_timezone_set('Africa/Lagos');
set_include_path('/var/www/html/classes/:/var/www/admin/classes/');
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

if (isset($_SESSION['uname']) && (trim($_SESSION['uname']) !='')) {
	class ClassAutoloader {
		public function __construct() {
			spl_autoload_register(array($this, 'loader'));
		}
		private function loader($className) {
			include 'class.'.$className . '.php';
		}
	}
	$autoloader = new ClassAutoloader();	
}else {
	header('location:index.php');	
}
?>