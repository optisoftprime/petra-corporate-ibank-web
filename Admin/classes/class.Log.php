<?php
class Log  extends dbConnect{
	private $USERNAME;
	
	
	function logActivity($logDesc,$category="") {

		$this->insert_data("INSERT INTO Admin.`Logs` (`idno`, `logdate`, `usr`, `activity`, `ipaddress`, `category`) VALUES (NULL, CURRENT_TIMESTAMP, ?,?,?,?)",[$_SESSION['uname'],$logDesc,$_SERVER['REMOTE_ADDR'],$category]);
		return true;
	}
	

	
	function getAuditLogs($startDate = null, $endDate = null, $category = null, $user = null, $limit = 1000, $offset = 0) {
		$whereConditions = array();
		
		// Build WHERE clause based on filters
		if ($startDate) {
			$whereConditions[] = "DATE(logdate) >= " . self::makeSQLStrings($startDate);
		}
		
		if ($endDate) {
			$whereConditions[] = "DATE(logdate) <= " . self::makeSQLStrings($endDate);
		}
		
		if ($category) {
			$whereConditions[] = "category = " . self::makeSQLStrings($category);
		}else {
			$whereConditions[] = "category is not null  ";
		}
		
		if ($user) {
			$whereConditions[] = "usr = " . self::makeSQLStrings($user);
		}
		
		$whereClause = "";
		if (!empty($whereConditions)) {
			$whereClause = "WHERE " . implode(" AND ", $whereConditions);
		}
		
		$qry = "SELECT idno, logdate, usr, activity, ipaddress, category 
				FROM Admin.`Logs` 
				$whereClause 
				ORDER BY logdate DESC 
				LIMIT $limit OFFSET $offset";
		
		return $this->get_data($qry);
	}
	
	function getAuditLogCategories() {
		$qry = "SELECT DISTINCT category FROM Admin.`Logs` WHERE category IS NOT NULL AND category != '' ORDER BY category";
		return self::get_data($qry);
	}
	
	function getAuditLogUsers() {
		$qry = "SELECT DISTINCT usr FROM Admin.`Logs` WHERE usr IS NOT NULL AND usr != '' ORDER BY usr";
		return self::get_data($qry);
	}
	
	function getAuditLogStats($startDate = null, $endDate = null) {
		$whereConditions = array();
		
		if ($startDate) {
			$whereConditions[] = "DATE(logdate) >= " . self::makeSQLStrings($startDate);
		}
		
		if ($endDate) {
			$whereConditions[] = "DATE(logdate) <= " . self::makeSQLStrings($endDate);
		}
		
		$whereClause = "";
		if (!empty($whereConditions)) {
			$whereClause = "WHERE " . implode(" AND ", $whereConditions);
		}
		
		$qry = "SELECT 
					COUNT(*) as total_logs,
					COUNT(DISTINCT usr) as unique_users,
					COUNT(DISTINCT category) as unique_categories,
					MIN(logdate) as earliest_log,
					MAX(logdate) as latest_log
				FROM Admin.`Logs` 
				$whereClause";
		
		$result = self::get_data($qry);
		return $result ? $result[0] : false;
	}
}
?>