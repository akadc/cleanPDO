<?php

	/**
	 * this class handles basic MySQL PDO preperation, execution and result passback
	 */

	namespace AkaDMC\CleanPDO;
	use PDO;

	class db {

		// db connection settings
		private $dbSettings;
		// the pdo object
		private $pdo;
		// the prepared query in the pdo object
		private $query;
		// whether or not there is a connection the db
		private $connected = false;
		// the sql params that will be bound the query (if any)
		private $sqlParams = [];

		/**
		* Constructor for class: gets the db settings and connects
		*
		* @param string $dbSettingsJSON the filename of JSON file containing serer connection settings
		* @return void
		*/
		function __construct($dbSettingsJSON){
			// get db settings from supplied JSON file. ensure your JSON file is can't be requested from browser
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
			$this->disconnect();
		}
		/**
		* Connects to the database or logs an error message about connection failure
		*
		* @param void 
		* @return string returns "connected successfully" or connection error message
		*/
		private function connect(){
			// set db connection configs: character set to utf8, db level query errors get reported as PDO exceptions
			$dbConnConfigs = [PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES 'utf8'",PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
			// attempt to connect
			try {
				$this->pdo = new PDO('mysql:host='.$this->dbSettings->host.';dbname='.$this->dbSettings->db
									, $this->dbSettings->user
									, $this->dbSettings->password
                                    , $dbConnConfigs);
				$this->connected = true;
				return "Connected successfully";
			}
			// no connection, log issue & return error
			catch (PDOException $error) {
				return $this->logError('DB Connection Error: '.$error->getMessage());
			}
		}
		/**
		* disconnects the db connection
		*
		* @param void 
		* @return void
		*/
		private function disconnect(){
			$this->pdo = null;
		}
		/**
		* Logs any errors to the default php error log, kills further processing
		*
		* @param string $error the error message
		* @return string $error the error message passed in, unaltered
		*/
		private function logError($error){
			// log to default errorlog, disconnect (in case we are connected)
			error_log($error.PHP_EOL,3,ini_get('error_log'));
			$this->disconnect();
			return $error;
		}
		/**
		* Adds parameter data (its name, value and data type) in an array to the main parameter array used in all PDO SQL binding
		*
		* @param string $name the name of the parameter, must start with ":"
		* @param string $value the value of the parameter
		* @param constant $type the expected data type for the value
		* @return void
		*/
		public function addParam ($name,$value,$type){
			$this->sqlParams[] = [$name,$value,$type];
		}
		/**
		* Binds all the parameters in the main parameter array to a query pending execution
		*
		* @param void
		* @return void
		*/
		private function bindSQLparams(){
			foreach ($this->sqlParams as $currParam){
				$this->query->bindParam($currParam[0],$currParam[1],$currParam[2]);
			}
		}
		/**
		* Coordinates the execution of a query, and the data that is passed back
		*
		* @param string $query the query to exectute. ensure inputs are parameterized 
		* @param constant $fetchmode the fetch mode to use when executing selet or show queries
		* @return array/int/string/null : array is passed back for select and show statements / INT for insert, update, delete / string for errors / null for everything else
		*/
		public function processQuery($query,$fetchmode = PDO::FETCH_ASSOC){
			try {
				$query = trim($query);
				// if the db didn't connect on __construct, try again
				if (!$this->connected){
					$connStatus = $this->connect();
					// if the db still hasn't connected return connection error
					if (!$this->connected){
						return $connStatus;
					}
				}
				// prepare query, bind params, execute query
				$this->query = $this->pdo->prepare($query);
				$this->bindSQLparams();
				$this->query->execute();
				// always reset query params after use so they aren't used on subsequent queries
				$this->sqlParams = [];
				// get SQL statement type by first word in query
				$statement = substr($query,0,strpos($query,' '));
				// if a record set is to be passed back
				if ($statement === 'select' || $statement === 'show') {
					return $this->query->fetchAll($fetchmode);
				}
				// if a number of rows effected is to be passed back
				if ($statement === 'insert' ||  $statement === 'update' || $statement === 'delete' ) {
					return $this->query->rowCount();	
				}
				return NULL;
			}
			// if there was an error report
			catch (PDOException $error) {
				return $this->logError('Query Execution Error: '.$error->getMessage());
			}
		}
	}
?>