<?php

class DbConnector{
	private $mysql_charset = 'utf8';
	/* recommend you set these values in the switch statement below, not here */
	private $hostname = "";
	private $database_name = "";
	private $username = "";
	private $password = "";
	public function __construct() 
	{
		if (array_key_exists('SERVER_NAME', $_SERVER))  
		{
			switch ($_SERVER['SERVER_NAME']) 
			{ 
				case "your_development_server":
				case "127.0.0.1":
				case "yoursite.local":
				case "www.yoursite.local":
					$this->hostname="";
					$this->database_name = "";
					$this->username = "";
					$this->password = "";
					break;
				case "your_acceptance_servers":
					$this->hostname="";
					$this->database_name="";
					$this->username="";
					$this->password="";
					break;
				case "your_production_servers":
				case "www.yoursite.com":
				case "yoursite.com":
				case "m.yoursite.com":
					$this->hostname="";
					$this->database_name="";
					$this->username="";
					$this->password="";
					break;
				default:
					// echo "DEBUG in DbConnector.php and didn't match this value: " . $_SERVER['SERVER_NAME'];
			}
		} else {
			// echo "DEBUG in DbConnector.php and no server variable was set";
		}
	}
	function getConnection () {
		$cn = mysql_pconnect(
							$this->hostname, 
							$this->username, 
							$this->password
							) or die(/*mysql_error()*/ 'PHPDO Error 5736: Unable to get database connection.');
		mysql_select_db($this->database_name) or die(/*mysql_error()*/ 'PHPDO Error 3770: Database access denied' );
		if (function_exists('mysql_set_charset'))
		{
			mysql_set_charset($this->mysql_charset ,$cn);
		} else {
			mysql_query("SET NAMES '{$this->mysql_charset}'");
		}
		return $cn;
	}
}

?>