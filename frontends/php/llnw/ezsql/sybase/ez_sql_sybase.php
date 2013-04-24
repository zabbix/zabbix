<?php

	sybase_min_server_severity(20);

	/**********************************************************************
	*  Author: Muhammad Iyas (iyasilias@gmail.com)
	*  Web...: 
	*  Name..: ezSQL_sybase
	*  Desc..: Sybase ASE component (part of ezSQL database abstraction library) - based on ezSql_mssql library class.
	*
	*/

	/**********************************************************************
	*  ezSQL error strings - sybase
	*/

	$ezsql_sybase_str = array
	(
		1 => 'Require $dbuser and $dbpassword to connect to a database server',
		2 => 'Error establishing sybase database connection. Correct user/password? Correct hostname? Database server running?',
		3 => 'Require $dbname to select a database',
		4 => 'SQL Server database connection is not active',
		5 => 'Unexpected error while trying to select database'
	);

	/**********************************************************************
	*  ezSQL Database specific class - sybase
	*/

	if ( ! function_exists ('sybase_connect') ) die('<b>Fatal Error:</b> ezSQL_sybase requires ntwdblib.dll to be present in your winowds\system32 folder. Also enable sybase extenstion in PHP.ini file ');
	if ( ! class_exists ('ezSQLcore') ) die('<b>Fatal Error:</b> ezSQL_sybase requires ezSQLcore (ez_sql_core.php) to be included/loaded before it can be used');

	class ezSQL_sybase extends ezSQLcore
	{

		var $dbuser = false;
		var $dbpassword = false;
		var $dbname = false;
		var $dbhost = false;
		//if we want to convert Queries in MySql syntax to Sybase syntax. Yes, there
		//are some differences in query syntax.
		var $convertMySqlTosybaseQuery = TRUE;

		/**********************************************************************
		*  Constructor - allow the user to perform a qucik connect at the
		*  same time as initialising the ezSQL_sybase class
		*/

		function ezSQL_sybase($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost', $convertMySqlTosybaseQuery=true)
		{
			$this->dbuser = $dbuser;
			$this->dbpassword = $dbpassword;
			$this->dbname = $dbname;
			$this->dbhost = $dbhost;
			$this->convertMySqlTosybaseQuery = $convertMySqlTosybaseQuery;
		}

		/**********************************************************************
		*  Short hand way to connect to sybase database server
		*  and select a sybase database at the same time
		*/

		function quick_connect($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost')
		{
			$return_val = false;
			if ( ! $this->connect($dbuser, $dbpassword, $dbhost,true) ) ;
			else if ( ! $this->select($dbname) ) ;
			else $return_val = true;
			return $return_val;
		}

		/**********************************************************************
		*  Try to connect to sybase database server
		*/

		function connect($dbuser='', $dbpassword='', $dbhost='localhost')
		{
			global $ezsql_sybase_str; $return_val = false;

			// Must have a user and a password
			if ( ! $dbuser )
			{
				$this->register_error($ezsql_sybase_str[1].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_sybase_str[1],E_USER_WARNING) : null;
			}
			// Try to establish the server database handle

			else if ( ! $this->dbh = @sybase_connect($dbhost,$dbuser,$dbpassword) )
			{
				$this->register_error($ezsql_sybase_str[2].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_sybase_str[2],E_USER_WARNING) : null;
			}
			else
			{
				$this->dbuser = $dbuser;
				$this->dbpassword = $dbpassword;
				$this->dbhost = $dbhost;
				$return_val = true;
			}

			return $return_val;
		}

		/**********************************************************************
		*  Try to select a sybase database
		*/

		function select($dbname='')
		{
			global $ezsql_sybase_str; $return_val = false;

			// Must have a database name
			if ( ! $dbname )
			{
				$this->register_error($ezsql_sybase_str[3].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_sybase_str[3],E_USER_WARNING) : null;
			}

			// Must have an active database connection
			else if ( ! $this->dbh )
			{
				$this->register_error($ezsql_sybase_str[4].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_sybase_str[4],E_USER_WARNING) : null;
			}

			// Try to connect to the database

			else if ( !@sybase_select_db($dbname,$this->dbh) )
			{
				$str = $ezsql_sybase_str[5];

				$this->register_error($str.' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($str,E_USER_WARNING) : null;
			}
			else
			{
				$this->dbname = $dbname;
				$return_val = true;
			}

			return $return_val;
		}

		/**********************************************************************
		*  Format a sybase string correctly for safe sybase insert
		*  (no mater if magic quotes are on or not)
		*/

		function escape($str)
		{
			//not sure about this.
			//applying following logic
			//1. add 1 more ' to ' character

			return  str_ireplace("'", "''", $str);

		}

		/**********************************************************************
		*  Return sybase specific system date syntax
		*  i.e. Oracle: SYSDATE sybase: getDate()
		*/

		function sysdate()
		{
			return 'getDate()';
		}

		/**********************************************************************
		*  Perform sybase query and try to detirmin result value
		*/

		function query($query)
		{

			//if flag to convert query from MySql syntax to Sybase syntax is true
			//convert the query
			if($this->convertMySqlTosybaseQuery == true)
				$query = $this->ConvertMySqlTosybase($query);



			// Initialise return
			$return_val = 0;


			// Flush cached values..
			$this->flush();

			// For reg expressions
			$query = trim($query);

			// Log how the function was called
			$this->func_call = "\$db->query(\"$query\")";

			// Keep track of the last query for debug..
			$this->last_query = $query;

			// Count how many queries there have been
			$this->num_queries++;

			// Use core file cache function
			if ( $cache = $this->get_cache($query) )
			{
				return $cache;
			}


			// If there is no existing database connection then try to connect
			if ( ! isset($this->dbh) || ! $this->dbh )
			{
				$this->connect($this->dbuser, $this->dbpassword, $this->dbhost);
				$this->select($this->dbname);
			}




			// Perform the query via std sybase_query function..

			$this->result = @sybase_query($query);



			// If there is an error then take note of it..
			if ($this->result == false )
			{

				$get_errorcodeSql = "SELECT @@ERROR as errorcode";
				$error_res = @sybase_query($get_errorcodeSql, $this->dbh);
				$errorCode = @sybase_result($error_res, 0, "errorcode");

				$get_errorMessageSql = "SELECT severity as errorSeverity, text as errorText FROM sys.messages  WHERE message_id = ".$errorCode  ;
				$errormessage_res =  @sybase_query($get_errorMessageSql, $this->dbh);
				if($errormessage_res)
				{
					$errorMessage_Row = @sybase_fetch_row($errormessage_res);
					$errorSeverity = $errorMessage_Row[0];
					$errorMessage = $errorMessage_Row[1];
				}

				$sqlError = "ErrorCode: ".$errorCode." ### Error Severity: ".$errorSeverity." ### Error Message: ".$errorMessage." ### Query: ".$query;

				$is_insert = true;
				$this->register_error($sqlError);
				$this->show_errors ? trigger_error($sqlError ,E_USER_WARNING) : null;
				return false;
			}




			// Query was an insert, delete, update, replace
			$is_insert = false;
			if ( preg_match("/^(insert|delete|update|replace)\s+/i",$query) )
			{
				$this->rows_affected = @sybase_rows_affected($this->dbh);

				// Take note of the insert_id
				if ( preg_match("/^(insert|replace)\s+/i",$query) )
				{

					$identityresultset = @sybase_query("select SCOPE_IDENTITY()");

					if ($identityresultset != false )
					{
						$identityrow = @sybase_fetch_row($identityresultset);
						$this->insert_id = $identityrow[0];
					}

				}

				// Return number of rows affected
				$return_val = $this->rows_affected;
			}
			// Query was a select
			else
			{

				// Take note of column info
				$i=0;
				while ($i < @sybase_num_fields($this->result))
				{
					$this->col_info[$i] = @sybase_fetch_field($this->result);
					$i++;

				}

				// Store Query Results
				$num_rows=0;

				while ( $row = @sybase_fetch_object($this->result) )
				{

					// Store relults as an objects within main array
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}

				@sybase_free_result($this->result);

				// Log number of rows the query returned
				$this->num_rows = $num_rows;

				// Return number of rows selected
				$return_val = $this->num_rows;
			}

			// disk caching of queries
			$this->store_cache($query,$is_insert);

			// If debug ALL queries
			$this->trace || $this->debug_all ? $this->debug() : null ;

			return $return_val;

		}



		/**********************************************************************
		*  Convert a Query From MySql Syntax to Sybase syntax
		   Following conversions are made:-
		   1. The '`' character used for MySql queries is not supported - the character is removed.
		   2. FROM_UNIXTIME method is not supported. The Function is removed.It is replaced with
		      getDate(). Warning: This logic may not be right.
		   3. unix_timestamp function is removed.
		   4. LIMIT keyowrd is replaced with TOP keyword. Warning: Logic not fully tested.

		   Note: This method is only a small attempt to convert the syntax. There are many aspects which are not covered here.
		   		This method doesn't at all guarantee complete conversion. Certain queries will still
		   		not work.
		*/

		function ConvertMySqlTosybase($query)
		{


			//replace the '`' character used for MySql queries, but not
			//supported in Sybase

			$query = str_replace("`", "", $query);

			//replace From UnixTime command in Sybase, doesn't work

			$pattern = "/FROM_UNIXTIME\(([^\/]{0,})\)/i";
			$replacement = "getdate()";
			//ereg($pattern, $query, $regs);
			//we can get the Unix Time function parameter value from this string
			//$valueInsideFromUnixTime = $regs[1];

			$query = preg_replace($pattern, $replacement, $query);


			//replace LIMIT keyword. Works only on MySql not on Sybase
			//replace it with TOP keyword

			$pattern = "/LIMIT[^\w]{1,}([0-9]{1,})([\,]{0,})([0-9]{0,})/i";
			$replacement = "";
			preg_match($pattern, $query, $regs);

			$query = preg_replace($pattern, $replacement, $query);

			if(count($regs) > 0):
			if($regs[2])
				$query = str_ireplace("SELECT ", "SELECT TOP ".$regs[3]." ", $query);
			else
				{
				if($regs[1])
					$query  = str_ireplace("SELECT ", "SELECT TOP ".$regs[1]." ", $query);
				}
			endif;

			//replace unix_timestamp function. Doesn't work in Sybase
			$pattern = "/unix_timestamp\(([^\/]{0,})\)/i";
			$replacement = "\\1";
			$query = preg_replace($pattern, $replacement, $query);

			return $query;

		}




	}


?>
