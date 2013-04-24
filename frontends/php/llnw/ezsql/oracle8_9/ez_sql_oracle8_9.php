<?php

	/**********************************************************************
	*  Author: Justin Vincent (jv@jvmultimedia.com)
	*  Web...: http://twitter.com/justinvincent
	*  Name..: ezSQL_oracle8_9
	*  Desc..: Oracle 8i/9i component (part of ezSQL databse abstraction library)
	*
	*/

	/**********************************************************************
	*  ezSQL error strings - Oracle
	*/

	$ezsql_oracle8_9_str = array
	(
		1 => 'Require $dbuser, $dbpassword and $dbname to connect to a database server',
		2 => 'ezSQL auto created the following Oracle sequence'
	);

	/**********************************************************************
	*  ezSQL Database specific class - Oracle
	*/

	if ( ! function_exists ('OCILogon') ) die('<b>Fatal Error:</b> ezSQL_oracle8_9 requires Oracle OCI Lib to be compiled and/or linked in to the PHP engine');
	if ( ! class_exists ('ezSQLcore') ) die('<b>Fatal Error:</b> ezSQL_oracle8_9 requires ezSQLcore (ez_sql_core.php) to be included/loaded before it can be used');

	class ezSQL_oracle8_9 extends ezSQLcore
	{

		var $dbuser = false;
		var $dbpassword = false;
		var $dbname = false;

		/**********************************************************************
		*  Constructor - allow the user to perform a qucik connect at the
		*  same time as initialising the ezSQL_oracle8_9 class
		*/

		function ezSQL_oracle8_9($dbuser='', $dbpassword='', $dbname='')
		{

			// Turn on track errors
			ini_set('track_errors',1);

			$this->dbuser = $dbuser;
			$this->dbpassword = $dbpassword;
			$this->dbname = $dbname;

		}

		/**********************************************************************
		*  Try to connect to Oracle database server
		*/

		function connect($dbuser='', $dbpassword='', $dbname='')
		{
			global $ezsql_oracle8_9_str; $return_val = false;

			// Must have a user and a password
			if ( ! $dbuser || ! $dbpassword || ! $dbname )
			{
				$this->register_error($ezsql_oracle8_9_str[1].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_oracle8_9_str[1],E_USER_WARNING) : null;
			}
			// Try to establish the server database handle
			else if ( ! $this->dbh = OCILogon($dbuser, $dbpassword, $dbname) )
			{
				$this->register_error($php_errormsg);
				$this->show_errors ? trigger_error($php_errormsg,E_USER_WARNING) : null;
			}
			else
			{
				$this->dbuser = $dbuser;
				$this->dbpassword = $dbpassword;
				$this->dbname = $dbname;
				$return_val = true;
			}

			return $return_val;
		}

		/**********************************************************************
		*  In the case of Oracle quick_connect is not really needed
		*  because std. connect already does what quick connect does -
		*  but for the sake of consistency it has been included
		*/

		function quick_connect($dbuser='', $dbpassword='', $dbname='')
		{
			return $this->connect($dbuser='', $dbpassword='', $dbname='');
		}

		/**********************************************************************
		*  No real equivalent of mySQL select in Oracle
		*  once again, function included for the sake of consistency
		*/

		function select($dbuser='', $dbpassword='', $dbname='')
		{
			return $this->connect($dbuser='', $dbpassword='', $dbname='');
		}

		/**********************************************************************
		*  Format a Oracle string correctly for safe Oracle insert
		*  (no mater if magic quotes are on or not)
		*/

		function escape($str)
		{
			return str_replace("'","''",str_replace("''","'",stripslashes($str)));
		}

		/**********************************************************************
		*  Return Oracle specific system date syntax
		*  i.e. Oracle: SYSDATE Mysql: NOW()
		*/

		function sysdate()
		{
			return "SYSDATE";
		}

		/**********************************************************************
		*  These special Oracle functions make sure that even if your test
		*  pattern is '' it will still match records that are null if
		*  you don't use these funcs then oracle will return no results
		*  if $user = ''; even if there were records that = ''
		*
		*  SELECT * FROM USERS WHERE USER = ".$db->is_equal_str($user)."
		*/

		function is_equal_str($str='')
		{
			return ($str==''?'IS NULL':"= '".$this->escape($str)."'");
		}

		function is_equal_int($int)
		{
			return ($int==''?'IS NULL':'= '.$int);
		}

		/**********************************************************************
		*  Another oracle specific function - if you have set up a sequence
		*  this function returns the next ID from that sequence
		*/

		function insert_id($seq_name)
		{
			global $ezsql_oracle8_9_str;

			$return_val = $this->get_var("SELECT $seq_name.nextVal id FROM Dual");

			// If no return value then try to create the sequence
			if ( ! $return_val )
			{
				$this->query("CREATE SEQUENCE $seq_name maxValue 9999999999 INCREMENT BY 1 START WITH 1 CACHE 20 CYCLE");
				$return_val = $this->get_var("SELECT $seq_name.nextVal id FROM Dual");
				$this->register_error($ezsql_oracle8_9_str[2].": $seq_name");
				$this->show_errors ? trigger_error($ezsql_oracle8_9_str[2].": $seq_name",E_USER_NOTICE) : null;
			}

			return $return_val;
		}

		/**********************************************************************
		*  Perform Oracle query and try to determine result value
		*/

		function query($query)
		{

			$return_value = 0;

			// Flush cached values..
			$this->flush();

			// Log how the function was called
			$this->func_call = "\$db->query(\"$query\")";

			// Keep track of the last query for debug..
			$this->last_query = $query;

			$this->num_queries++;

			// Use core file cache function
			if ( $cache = $this->get_cache($query) )
			{
				return $cache;
			}

			// If there is no existing database connection then try to connect
			if ( ! isset($this->dbh) || ! $this->dbh )
			{
				$this->connect($this->dbuser, $this->dbpassword, $this->dbname);
			}

			// Parses the query and returns a statement..
			if ( ! $stmt = OCIParse($this->dbh, $query))
			{
				$error = OCIError($this->dbh);
				$this->register_error($error["message"]);
				$this->show_errors ? trigger_error($error["message"],E_USER_WARNING) : null;
				return false;
			}

			// Execut the query..
			elseif ( ! $this->result = OCIExecute($stmt))
			{
				$error = OCIError($stmt);
				$this->register_error($error["message"]);
				$this->show_errors ? trigger_error($error["message"],E_USER_WARNING) : null;
				return false;
			}

			// If query was an insert
			$is_insert = false;
			if ( preg_match('/^(insert|delete|update|create) /i', $query) )
			{
				$is_insert = true;

				// num afected rows
				$return_value = $this->rows_affected = @OCIRowCount($stmt);
			}
			// If query was a select
			else
			{

				// Get column information
				if ( $num_cols = @OCINumCols($stmt) )
				{
					// Fetch the column meta data
	    			for ( $i = 1; $i <= $num_cols; $i++ )
	    			{
	    				$this->col_info[($i-1)]->name = @OCIColumnName($stmt,$i);
	    				$this->col_info[($i-1)]->type = @OCIColumnType($stmt,$i);
	    				$this->col_info[($i-1)]->size = @OCIColumnSize($stmt,$i);
				    }
				}

				// If there are any results then get them
				if ($this->num_rows = @OCIFetchStatement($stmt,$results))
				{
					// Convert results into object orientated results..
					// Due to Oracle strange return structure - loop through columns
					foreach ( $results as $col_title => $col_contents )
					{
						$row_num=0;
						// then - loop through rows
						foreach (  $col_contents as $col_content )
						{
							$this->last_result[$row_num]->{$col_title} = $col_content;
							$row_num++;
						}
					}
				}

				// num result rows
				$return_value = $this->num_rows;
			}

			// disk caching of queries
			$this->store_cache($query,$is_insert);

			// If debug ALL queries
			$this->trace || $this->debug_all ? $this->debug() : null ;

			return $return_value;

		}

	}

?>