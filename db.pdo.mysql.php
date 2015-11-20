<?php
	/**
	 * this class handles basic MySQL PDO preperation, execution and result passback
	 */
	class db {

		// db connection settings
		private $dbSettings;
		// the pdo object
		private $pdo;
		// the prepared query in the pdo object
		private $query;
		// whether or not we are connected
		private $connected = false;
		// the sql params that will be bound the quer (if any)
		private $sqlParams = [];

		/**
		* Constructor for class: gets the db settings and connects
		*
		* @param string $dbSettingsJSON the filename of JSON file containing serer connection settings
		* @return void
		*/
		function __construct($dbSettingsJSON){
			// get db settings from supplied JSON file
			// ensure to make sure your json files cannot be requested from a browser, or move them out of web root
			// todo: handle json formatting errors
			$this->dbSettings = json_decode(file_get_contents($dbSettingsJSON));
			// make the actual db connection
			$this->connect();
		}
		/**
		* Destructor for class: closes any db connection that may exist
		*
		* @param void
		* @return void
		* 
		*/
		function __destruct(){
			// kill the connection on destruct
			$this->disconnect();
		}
		/**
		* Connects to the database or logs an error message about connection failure
		*
		* @param void 
		* @return void
		*/
		function connect(){
			// set db connection configs 
			// were setting character set to utf8, making sure that db level query errors get reported as PDO exceptions
			$dbConnConfigs = array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
			// attempt to connect
			try {
				$this->pdo = new PDO('mysql:host='.$this->dbSettings->host.';dbname='.$this->dbSettings->db, $this->dbSettings->user, $this->dbSettings->password,$dbConnConfigs);
				$this->connected = true;
			}
			// no connection, log issue
			catch (PDOException $error) {
				$this->logError('DB Connection Error: '.$error->getMessage());
			}
		}
		/**
		* disconnects the db connection
		*
		* @param void 
		* @return void
		*/
		function disconnect(){
			$this->pdo = null;
		}
		/**
		* Logs any errors to the default php error log, kills further processing
		*
		* @param string $error the error message
		* @return void
		*/
		function logError($error){
			// log to default errorlog, ensure new line after error message
			error_log($error.PHP_EOL,3,ini_get('error_log'));
			// disconnect connection (if applicable)
			$this->disconnect();
			die();
		}
		/**
		* Adds parameter data (its name, value and data type) in an array to the main parameter array used in all PDO SQL binding
		*
		* @param string $name the name of the parameter, must start with ":"
		* @param string $value the value of the parameter
		* @param constant $type the expected data type for the value
		* @return void
		*/
		function addParam ($name,$value,$type){
			$this->sqlParams[] = [$name,$value,$type];
		}
		/**
		* Binds all the parameters in the main parameter array to a query pending execution
		*
		* @param void
		* @return void
		*/
		function bindSQLparams(){
			foreach ($this->sqlParams as $currParam){
				$this->query->bindParam($currParam[0],$currParam[1],$currParam[2]);
			}
		}
		/**
		* Coordinates the execution of a query, and the data that is passed back
		*
		* @param string $query the query to exectute. ensure inputs are parameterized 
		* @param constant $fetchmode the fetch mode to use when executing selet or show queries
		* @return array/int/null : array is passed back for select / show statements, INT for insert, update, delete - null for everything else
		*/
		function processQuery($query,$fetchmode = PDO::FETCH_ASSOC){
			try {
				// trim possible whitespace in query
				$query = trim($query);
				// if for some reason the DB is not connected, try again
				// todo: this may be uneccesary because $this-connected is not updated if a connection is dropped 
				if (!$this->connected){
					$this->connect();
				}
				// prepare the query, bind any params
				$this->query = $this->pdo->prepare($query);
				$this->bindSQLparams();
				// execute the query 
				$this->query->execute();
				// reset the query params so that subsequent queries don't get old params
				$this->sqlParams = [];
				// get first word in query in order to determine what info to pass back
				$statement = substr($query,0,strpos($query,' '));
				// if a record set is to be passed back
				if ($statement === 'select' || $statement === 'show') {
					return $this->query->fetchAll($fetchmode);
				}
				// if a number of rows effected is to be passed back
				elseif ( $statement === 'insert' ||  $statement === 'update' || $statement === 'delete' ) {
					return $this->query->rowCount();	
				}
				// anything else? return null
				else {
					return NULL;
				}
			}
			// if there was an error report
			catch (PDOException $error) {
				$this->logError('Query Execution Error: '.$error->getMessage());
			}
		}
	}
?>