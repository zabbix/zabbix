<?php

	/**********************************************************************
	*  Author: Justin Vincent (jv@jvmultimedia.com)
	*  Web...: http://twitter.com/justinvincent
	*          Stefanie Janine Stoelting (mail@stefanie-stoelting.de)
	*  Name..: ezSQL_postgresql
	*  Desc..: PostgreSQL component (part of ezSQL databse abstraction library)
	*
	*/

	/**********************************************************************
	*  ezSQL error strings - PostgreSQL
	*/

	$ezsql_postgresql_str = array
	(
		1 => 'Require $dbuser and $dbpassword to connect to a database server',
		2 => 'Error establishing PostgreSQL database connection. Correct user/password? Correct hostname? Database server running?',
		3 => 'Require $dbname to select a database',
		4 => 'mySQL database connection is not active',
		5 => 'Unexpected error while trying to select database'
	);

	/**********************************************************************
	*  ezSQL Database specific class - PostgreSQL
	*/

	if ( ! function_exists ('pg_connect') ) die('<b>Fatal Error:</b> ezSQL_postgresql requires PostgreSQL Lib to be compiled and or linked in to the PHP engine');
	if ( ! class_exists ('ezSQLcore') ) die('<b>Fatal Error:</b> ezSQL_postgresql requires ezSQLcore (ez_sql_core.php) to be included/loaded before it can be used');

	class ezSQL_postgresql extends ezSQLcore
	{

		var $dbuser = false;
		var $dbpassword = false;
		var $dbname = false;
		var $dbhost = false;

		/**********************************************************************
		*  Constructor - allow the user to perform a qucik connect at the
		*  same time as initialising the ezSQL_postgresql class
		*/

		function ezSQL_postgresql($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost', $port='5432')
		{
			$this->dbuser = $dbuser;
			$this->dbpassword = $dbpassword;
			$this->dbname = $dbname;
			$this->dbhost = $dbhost;
			$this->port = $port;
		}

		/**********************************************************************
		*  In the case of PostgreSQL quick_connect is not really needed
		*  because std. connect already does what quick connect does - 
		*  but for the sake of consistency it has been included
		*/

		function quick_connect($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost', $port='5432')
		{
			$return_val = false;
			if ( ! $this->connect($dbuser, $dbpassword, $dbname, $dbhost, $port) ) ;
			else if ( ! $this->select($dbname) ) ;
			else $return_val = true;
			return $return_val;
		}

		/**********************************************************************
		*  Try to connect to mySQL database server
		*/

		function connect($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost', $port='5432')
		{
			global $ezsql_postgresql_str; $return_val = false;

			// Must have a user and a password
			if ( ! $dbuser )
			{
				$this->register_error($ezsql_postgresql_str[1].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_postgresql_str[1],E_USER_WARNING) : null;
			}
			// Try to establish the server database handle
			else if ( ! $this->dbh = @pg_connect("host=$dbhost port=$port dbname=$dbname user=$dbuser password=$dbpassword", true) )
			{       
				$this->register_error($ezsql_postgresql_str[2].' in '.__FILE__.' on line '.__LINE__);
				$this->show_errors ? trigger_error($ezsql_postgresql_str[2],E_USER_WARNING) : null;
			}
			else
			{
				$this->dbuser = $dbuser;
				$this->dbpassword = $dbpassword;
				$this->dbhost = $dbhost;
				$this->dbname = $dbname;
				$this->port = $port;
				$return_val = true;
			}

			return $return_val;
		}

		/**********************************************************************
		*  No real equivalent of mySQL select in PostgreSQL
		*  once again, function included for the sake of consistency
		*/

		function select($dbuser='', $dbpassword='', $dbname='', $dbhost='localhost', $port='5432')
		{  
			$return_val = false;
			if ( ! $this->connect($dbuser, $dbpassword, $dbname, $dbhost,true) ) ;
			else if ( ! $this->select($dbname) ) ;
			else $return_val = true;
			return $return_val;
		}

		/**********************************************************************
		*  Format a mySQL string correctly for safe mySQL insert
		*  (no mater if magic quotes are on or not)
		*/

		function escape($str)
		{
			return pg_escape_string(stripslashes($str));
		}

		/**********************************************************************
		*  Return PostgreSQL specific system date syntax
		*  i.e. Oracle: SYSDATE Mysql: NOW()
		*/

		function sysdate()
		{
			return 'NOW()';
		}

		/**********************************************************************
		*  Return PostgreSQL specific values
		*/

		function showTables()
		{
			return "table_name FROM information_schema.tables WHERE table_schema = '$this->dbname' and table_type='BASE TABLE'";
		}

		function descTable($tbl_name)
		{
			return return "ordinal_position, column_name, data_type, column_default, is_nullable, character_maximum_length, numeric_precision FROM information_schema.columns WHERE table_name = '$tbl_name' AND table_schema='$this->dbname' ORDER BY ordinal_position";
		}

		function showDatabases()
		{
			return "datname from pg_database WHERE datname NOT IN ('template0', 'template1') ORDER BY 1";
		}

		/**********************************************************************
		*  Perform PostgreSQL query and try to detirmin result value
		*/

		function query($query)
		{

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
				$this->connect($this->dbuser, $this->dbpassword, $this->dbname, $this->dbhost, $this->port);
			}

			// Perform the query via std postgresql_query function..
			$this->result = @pg_query($this->dbh, $query);


			// If there is an error then take note of it..
			if ( $str = @pg_last_error($this->dbh) )
			{
				$is_insert = true;
				$this->register_error($str);
				$this->show_errors ? trigger_error($str,E_USER_WARNING) : null;
				return false;
			}
			// Query was an insert, delete, update, replace
			$is_insert = false;
			if ( preg_match("/^(insert|delete|update|replace)\s+/i",$query) )
			{
				$this->rows_affected = @pg_affected_rows($this->result);

				// Take note of the insert_id
				if ( preg_match("/^(insert|replace)\s+/i",$query) )
				{
					//$this->insert_id = @postgresql_insert_id($this->dbh);
                    //$this->insert_id = pg_last_oid($this->result);

					// Thx. Rafael Bernal
					$insert_query = pg_query("SELECT lastval();");
					$insert_row = pg_fetch_row($insert_query);
					$this->insert_id = $insert_row[0];
				}

				// Return number fo rows affected
				$return_val = $this->rows_affected;
			}
			// Query was a select
			else
			{               $num_rows=0;
                			//if ( $this->result )  //may be needed but my tests did not
                			//{	
                			
        					// =======================================================
        					// Take note of column info

        					$i=0;
        					while ($i < @pg_num_fields($this->result))
        					{
        	    					$this->col_info[$i]->name = pg_field_name($this->result,$i);
            						$this->col_info[$i]->type = pg_field_type($this->result,$i);
            						$this->col_info[$i]->size = pg_field_size($this->result,$i);
        						$i++;
        					}

        					// =======================================================
        					// Store Query Results

        					//while ( $row = @pg_fetch_object($this->result, $i, PGSQL_ASSOC) ) doesn't work? donno
        					//while ( $row = @pg_fetch_object($this->result,$num_rows) ) does work
        					while ( $row = @pg_fetch_object($this->result) )
        					{
        						// Store results as an objects within main array
        						$this->last_result[$num_rows] = $row ;
        						$num_rows++;
        					}

				                @pg_free_result($this->result);
                                        //}
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
		
		/**
		* Close the database connection
		*/
		
		function disconnect()
		{
			if ( $this->dbh )
			{
			    @pg_close($this->dbh);
			}
		}

	}