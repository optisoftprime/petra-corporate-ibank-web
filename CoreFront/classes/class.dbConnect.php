<?php

class dbConnect {

	// Credentials are resolved at runtime from the environment (see class.Env.php),
	// never hardcoded. DB_NAME/DB_HOST/DB_PORT default to the values this codebase
	// always used locally; DB_USER/DB_PASSWORD have no default — an unset password
	// simply fails to connect, which is the safe failure mode.
	private static $user;
	private static $pass;
	private static $database;
	private static $server;
	private static $port;

	const options = [
		\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_EMULATE_PREPARES   => false,
	];

	private static function loadConfig() {
		if (self::$database !== null) {
			return;
		}
		self::$user     = Env::get('DB_USER', 'root');
		self::$pass     = Env::get('DB_PASSWORD', '');
		self::$database = Env::get('DB_NAME', 'dbPetra');
		self::$server   = Env::get('DB_HOST', 'localhost');
		self::$port     = Env::get('DB_PORT', '3306');
	}

	public $dbh;

	function get_data($qry,$params=null){ 
		error_log($qry);
		if($this->dbh == null ) {
			self::connect();
		}
		$stmt = $this->dbh->prepare($qry);
		if (!$params == NULL ) {
			$stmt->execute($params);
		}else {
			$stmt->execute();
		}
		$rs = $stmt->fetchAll(); 
		if (sizeof($rs) > 0 ) {
			return $rs; 
		}else {
			return false;
		}
	}

	function get_data_allow_empty($qry){ 
		error_log($qry);
		$stmt = $this->dbh->prepare($qry);
		$stmt->execute();
		return $stmt->fetchAll(); 
		
	}


	function __construct() {
		self::connect();
	}

    function insert_data($qry,$params,$id=false, $log = false){
		if(!$log) {
			error_log($qry);
		}
		try {
			$stmt = $this->dbh->prepare($qry);
			$stmt->execute($params);
			if($id) {
				return $this->dbh->lastInsertId();
			}else {
				return true;
			}
			
		}catch (Exception $ex) {
			error_log(json_encode($ex));
			return false;
		}
	}

	function executeProc($qry) {
		$stmt = $this->dbh->prepare($qry);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	function makeSQLStrings($theValue) {
		return $this->dbh->quote($theValue);
	}

    function connect() {
		self::loadConfig();
		$this->dbh = new PDO("mysql:host=".self::$server.";dbname=".self::$database.";port=".self::$port, self::$user, self::$pass, self::options);
		return true;
    }
}
?>
