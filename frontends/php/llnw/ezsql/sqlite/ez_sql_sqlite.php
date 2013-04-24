<?php

	/**********************************************************************
	*  Author: Justin Vincent (jv@jvmultimedia.com)
	*  Web...: http://twitter.com/justinvincent
	*  Name..: ezSQL_sqlite
	*  Desc..: SQLite component (part of ezSQL databse abstraction library)
	*
	*/

	/**********************************************************************
	*  ezSQL error strings - SQLite
	*/

	$ezsql_sqlite_str = array
	(
		1 => 'Require $dbpath and $dbname to open an SQLite database'
	);

	/**********************************************************************
	*  ezSQL Database specific class - SQLite
	*/

	if ( ! function_exists ('sqlite_open') ) die('<b>Fatal Error:</b> ezSQL_sqlite requires SQLite Lib to be compiled and or linked in to the PHP engine');
	if ( ! class_exists ('ezSQLcore') ) die('<b>Fatal Error:</b> ezSQL_sqlite requires ezSQLcore (ez_sql_core.php) to be included/loaded before it can be used');

	class ezSQL_sqlite extends ezSQLcore
	{

		/**********************************************************************
		*  Constructor - allow the user to perform a qucik connect at the 
		*  same time as initialising the ezSQL_sqlite class
		*/

		function ezSQL_sqlite($dbpath='', $dbname='')
		{
			// Turn on track errors 
			ini_set('track_errors',1);
			
			if ( $dbpath && $dbname )
			{
				$this->connect($dbpath, $dbname);
			}
		}

		/**********************************************************************
		*  Try to connect to SQLite database server
		*/

		function connect($dbpath='', $dbname='')
		{
			global $ezsql_sqlite_str; $return_val = false;
			
			// Must have a user and a password
			if ( ! $dbpath || ! $dbname )
			{
				$this->register_error($ezsql_sqlite_str[1].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_sqlite_str[1],E_USER_WARNING) : null;
			}
			// Try to establish the server database handle
			else if ( ! $this->dbh = @sqlite_open($dbpath.$dbname) )
			{
				$this->register_error($php_errormsg);
				$this->show_errors ? trigger_error($php_errormsg,E_USER_WARNING) : null;
			}
			else
				$return_val = true;

			return $return_val;			
		}

		/**********************************************************************
		*  In the case of SQLite quick_connect is not really needed
		*  because std. connect already does what quick connect does - 
		*  but for the sake of consistency it has been included
		*/

		function quick_connect($dbpath='', $dbname='')
		{
			return $this->connect($dbpath, $dbname);
		}

		/**********************************************************************
		*  No real equivalent of mySQL select in SQLite 
		*  once again, function included for the sake of consistency
		*/

		function select($dbpath='', $dbname='')
		{
			return $this->connect($dbpath, $dbname);
		}

		/**********************************************************************
		*  Format a SQLite string correctly for safe SQLite insert
		*  (no mater if magic quotes are on or not)
		*/

		function escape($str)
		{
			return sqlite_escape_string(stripslashes(preg_replace("/[\r\n]/",'',$str)));				
		}

		/**********************************************************************
		*  Return SQLite specific system date syntax 
		*  i.e. Oracle: SYSDATE Mysql: NOW()
		*/

		function sysdate()
		{
			return 'now';			
		}

		/**********************************************************************
		*  Perform SQLite query and try to detirmin result value
		*/

		// ==================================================================
		//	Basic Query	- see docs for more detail
	
		function query($query)
		{

			// For reg expressions
			$query = str_replace("/[\n\r]/",'',trim($query)); 

			// initialise return
			$return_val = 0;

			// Flush cached values..
			$this->flush();

			// Log how the function was called
			$this->func_call = "\$db->query(\"$query\")";

			// Keep track of the last query for debug..
			$this->last_query = $query;

			// Perform the query via std mysql_query function..
			$this->result = @sqlite_query($this->dbh,$query);
			$this->num_queries++;

			// If there is an error then take note of it..
			if (@sqlite_last_error($this->dbh))
			{
				$err_str = sqlite_error_string (sqlite_last_error($this->dbh));
				$this->register_error($err_str);
				$this->show_errors ? trigger_error($err_str,E_USER_WARNING) : null;
				return false;
			}
			
			// Query was an insert, delete, update, replace
			if ( preg_match("/^(insert|delete|update|replace)\s+/i",$query) )
			{
				$this->rows_affected = @sqlite_changes($this->dbh);
				
				// Take note of the insert_id
				if ( preg_match("/^(insert|replace)\s+/i",$query) )
				{
					$this->insert_id = @sqlite_last_insert_rowid($this->dbh);	
				}
				
				// Return number fo rows affected
				$return_val = $this->rows_affected;
	
			}
			// Query was an select
			else
			{
				
				// Take note of column info	
				$i=0;
				while ($i < @sqlite_num_fields($this->result))
				{
					$this->col_info[$i]->name       = sqlite_field_name ( $this->result, $i);
					$this->col_info[$i]->type       = null;
					$this->col_info[$i]->max_length = null;
					$i++;
				}
				
				// Store Query Results
				$num_rows=0;
				while ($row =  @sqlite_fetch_array($this->result,SQLITE_ASSOC) )
				{
					// Store relults as an objects within main array
					$obj= (object) $row; //convert to object
					$this->last_result[$num_rows] = $obj;
					$num_rows++;
				}

				// Log number of rows the query returned
				$this->num_rows = $num_rows;
				
				// Return number of rows selected
				$return_val = $this->num_rows;
			
			}

			// If debug ALL queries
			$this->trace||$this->debug_all ? $this->debug() : null ;

			return $return_val;
		
		}

	}

?>
