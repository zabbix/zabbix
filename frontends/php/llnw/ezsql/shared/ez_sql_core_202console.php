<?php
// =================================================================
// == TJH ==  To provide optional return value as opposed to simple echo 
// == TJH ==  of the $db->vardump  and   $db->debug  functions

// == TJH ==  Helpful for assigning the output to a var for handling in situations like template
// == TJH ==  engines where you want the debugging output rendered in a particular location.

// == TJH ==  This latest version 0.2 alpha includes a modification that allows
// == TJH ==  the original dump and debug behaviours to be maintained by default
// == TJH ==  and hopefully be backward compatible with previous ezSQL versions 

// == TJH ==  USAGE:     $ezdump = print_r($db->vardump($result),true);		
// == TJH ==  USAGE:     $ezconsole = print_r($db->console,true);		
// =================================================================

	/**********************************************************************
	*  Author: Justin Vincent (jv@jvmultimedia.com)
	*  Web...: http://twitter.com/justinvincent
	*  Name..: ezSQL
	*  Desc..: ezSQL Core module - database abstraction library to make
	*          it very easy to deal with databases.
	*
	*/

	/**********************************************************************
	*  ezSQL Constants
	*/

	define('EZSQL_VERSION','2.02-console');
	define('OBJECT','OBJECT',true);
	define('ARRAY_A','ARRAY_A',true);
	define('ARRAY_N','ARRAY_N',true);
	define('EZSQL_CORE_ERROR','ezSQLcore can not be used by itself (it is designed for use by database specific modules).');


	/**********************************************************************
	*  Core class containg common functions to manipulate query result
	*  sets once returned
	*/

	class ezSQLcore
	{

		var $trace           = false;  // same as $debug_all
		var $debug_all       = false;  // same as $trace
		// === TJH === 
		var $debug_echo = true; 	   // == TJH ==  // default now needed for echo of debug function
		var $debug_called    = false;
		var $vardump_called  = false;
		var $show_errors     = true;
		var $num_queries     = 0;
		var $last_query      = null;
		var $last_error      = null;
		var $col_info        = null;
		var $captured_errors = array();
		var $cache_dir       = false;
		var $cache_queries   = false;
		var $cache_inserts   = false;
		var $use_disk_cache  = false;
		var $cache_timeout   = 24; // hours

		/**********************************************************************
		*  Constructor
		*/

		function ezSQLcore()
		{
		}

		/**********************************************************************
		*  Connect to DB - over-ridden by specific DB class
		*/

		function connect()
		{
			die(EZSQL_CORE_ERROR);
		}

		/**********************************************************************
		*  Select DB - over-ridden by specific DB class
		*/

		function select()
		{
			die(EZSQL_CORE_ERROR);
		}

		/**********************************************************************
		*  Basic Query	- over-ridden by specific DB class
		*/

		function query()
		{
			die(EZSQL_CORE_ERROR);
		}

		/**********************************************************************
		*  Format a string correctly for safe insert - over-ridden by specific
		*  DB class
		*/

		function escape()
		{
			die(EZSQL_CORE_ERROR);
		}

		/**********************************************************************
		*  Return database specific system date syntax
		*  i.e. Oracle: SYSDATE Mysql: NOW()
		*/

		function sysdate()
		{
			die(EZSQL_CORE_ERROR);
		}

		/**********************************************************************
		*  Print SQL/DB error - over-ridden by specific DB class
		*/

		function register_error($err_str)
		{
			// Keep track of last error
			$this->last_error = $err_str;

			// Capture all errors to an error array no matter what happens
			$this->captured_errors[] = array
			(
				'error_str' => $err_str,
				'query'     => $this->last_query
			);
		}

		/**********************************************************************
		*  Turn error handling on or off..
		*/

		function show_errors()
		{
			$this->show_errors = true;
		}

		function hide_errors()
		{
			$this->show_errors = false;
		}

		/**********************************************************************
		*  Kill cached query results
		*/

		function flush()
		{
			// Get rid of these
			$this->last_result = null;
			$this->col_info = null;
			$this->last_query = null;
			$this->from_disk_cache = false;
		}

		/**********************************************************************
		*  Get one variable from the DB - see docs for more detail
		*/

		function get_var($query=null,$x=0,$y=0)
		{

			// Log how the function was called
			$this->func_call = "\$db->get_var(\"$query\",$x,$y)";

			// If there is a query then perform it if not then use cached results..
			if ( $query )
			{
				$this->query($query);
			}

			// Extract var out of cached results based x,y vals
			if ( $this->last_result[$y] )
			{
				$values = array_values(get_object_vars($this->last_result[$y]));
			}

			// If there is a value return it else return null
			return (isset($values[$x]) && $values[$x]!=='')?$values[$x]:null;
		}

		/**********************************************************************
		*  Get one row from the DB - see docs for more detail
		*/

		function get_row($query=null,$output=OBJECT,$y=0)
		{

			// Log how the function was called
			$this->func_call = "\$db->get_row(\"$query\",$output,$y)";

			// If there is a query then perform it if not then use cached results..
			if ( $query )
			{
				$this->query($query);
			}

			// If the output is an object then return object using the row offset..
			if ( $output == OBJECT )
			{
				return $this->last_result[$y]?$this->last_result[$y]:null;
			}
			// If the output is an associative array then return row as such..
			elseif ( $output == ARRAY_A )
			{
				return $this->last_result[$y]?get_object_vars($this->last_result[$y]):null;
			}
			// If the output is an numerical array then return row as such..
			elseif ( $output == ARRAY_N )
			{
				return $this->last_result[$y]?array_values(get_object_vars($this->last_result[$y])):null;
			}
			// If invalid output type was specified..
			else
			{
				$this->print_error(" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
			}

		}

		/**********************************************************************
		*  Function to get 1 column from the cached result set based in X index
		*  see docs for usage and info
		*/

		function get_col($query=null,$x=0)
		{

			// If there is a query then perform it if not then use cached results..
			if ( $query )
			{
				$this->query($query);
			}

			// Extract the column values
			for ( $i=0; $i < count($this->last_result); $i++ )
			{
				$new_array[$i] = $this->get_var(null,$x,$i);
			}

			return $new_array;
		}


		/**********************************************************************
		*  Return the the query as a result set - see docs for more details
		*/

		function get_results($query=null, $output = OBJECT)
		{

			// Log how the function was called
			$this->func_call = "\$db->get_results(\"$query\", $output)";

			// If there is a query then perform it if not then use cached results..
			if ( $query )
			{
				$this->query($query);
			}

			// Send back array of objects. Each row is an object
			if ( $output == OBJECT )
			{
				return $this->last_result;
			}
			elseif ( $output == ARRAY_A || $output == ARRAY_N )
			{
				if ( $this->last_result )
				{
					$i=0;
					foreach( $this->last_result as $row )
					{

						$new_array[$i] = get_object_vars($row);

						if ( $output == ARRAY_N )
						{
							$new_array[$i] = array_values($new_array[$i]);
						}

						$i++;
					}

					return $new_array;
				}
				else
				{
					return null;
				}
			}
		}


		/**********************************************************************
		*  Function to get column meta data info pertaining to the last query
		* see docs for more info and usage
		*/

		function get_col_info($info_type="name",$col_offset=-1)
		{

			if ( $this->col_info )
			{
				if ( $col_offset == -1 )
				{
					$i=0;
					foreach($this->col_info as $col )
					{
						$new_array[$i] = $col->{$info_type};
						$i++;
					}
					return $new_array;
				}
				else
				{
					return $this->col_info[$col_offset]->{$info_type};
				}

			}

		}

		/**********************************************************************
		*  store_cache
		*/

		function store_cache($query,$is_insert)
		{

			// The would be cache file for this query
			$cache_file = $this->cache_dir.'/'.md5($query);

			// disk caching of queries
			if ( $this->use_disk_cache && ( $this->cache_queries && ! $is_insert ) || ( $this->cache_inserts && $is_insert ))
			{
				if ( ! is_dir($this->cache_dir) )
				{
					$this->register_error("Could not open cache dir: $this->cache_dir");
					$this->show_errors ? trigger_error("Could not open cache dir: $this->cache_dir",E_USER_WARNING) : null;
				}
				else
				{
					// Cache all result values
					$result_cache = array
					(
						'col_info' => $this->col_info,
						'last_result' => $this->last_result,
						'num_rows' => $this->num_rows,
						'return_value' => $this->num_rows,
					);
					error_log ( serialize($result_cache), 3, $cache_file);
				}
			}

		}

		/**********************************************************************
		*  get_cache
		*/

		function get_cache($query)
		{

			// The would be cache file for this query
			$cache_file = $this->cache_dir.'/'.md5($query);

			// Try to get previously cached version
			if ( $this->use_disk_cache && file_exists($cache_file) )
			{
				// Only use this cache file if less than 'cache_timeout' (hours)
				if ( (time() - filemtime($cache_file)) > ($this->cache_timeout*3600) )
				{
					unlink($cache_file);
				}
				else
				{
					$result_cache = unserialize(file_get_contents($cache_file));

					$this->col_info = $result_cache['col_info'];
					$this->last_result = $result_cache['last_result'];
					$this->num_rows = $result_cache['num_rows'];

					$this->from_disk_cache = true;

					// If debug ALL queries
					$this->trace || $this->debug_all ? $this->debug() : null ;

					return $result_cache['return_value'];
				}
			}

		}

		/**********************************************************************
		*  Dumps the contents of any input variable to screen in a nicely
		*  formatted and easy to understand way - any type: Object, Var or Array
		*/

		// === TJH ===  This is hacked to OPTIONALLY generate a "$return_var" 	 															
		// === TJH ===   must also set $db->debug_echo = false; in your script to override default behaviour						
		// === TJH ===   instead of a simple "echo" to the current screen (DEFAULT)															
		// === TJH ===    USAGE:     $ezdebug = print_r($db->vardump($result),true);		

		function vardump($mixed='')
		{
			$return_var .=   "<p><table><tr><td bgcolor=ffffff><blockquote><font color=000090>";
			$return_var .=   "<pre><font face=arial>";

			if ( ! $this->vardump_called )
			{
				$return_var .=   "<font color=800080><b>ezSQL</b> (v".EZSQL_VERSION.") <b>Variable Dump..</b></font>\n\n";
			}

			$var_type = gettype ($mixed);
			$return_var .=  print_r(($mixed?$mixed:"<font color=red>No Value / False</font>"),true);
			$return_var .=   "\n\n<b>Type:</b> " . ucfirst($var_type) . "\n";
			$return_var .=   "<b>Last Query</b> [$this->num_queries]<b>:</b> ".($this->last_query?$this->last_query:"NULL")."\n";
			$return_var .=   "<b>Last Function Call:</b> " . ($this->func_call?$this->func_call:"None")."\n";
			$return_var .=   "<b>Last Rows Returned:</b> ".count($this->last_result)."\n";
			$return_var .=   "</font></pre></font></blockquote></td></tr></table>".$this->donation();
			$return_var .=   "\n<hr size=1 noshade color=dddddd>";

			$this->vardump_called = true;
			if($this->debug_echo){
			echo $return_var;
			}

			return $return_var;
		}

		/**********************************************************************
		*  Alias for the above function
		*/

		function dumpvar($mixed)
		{
			$this->vardump($mixed);
		}

		/**********************************************************************
		*  Displays the last query string that was sent to the database & a
		* table listing results (if there were any).
		* (abstracted into a seperate file to save server overhead).
		*/

		// === TJH ===  The debug() function is now hacked to OPTIOANLLY create a return result 			
		// === TJH ===  that can be called as a variable, just changed all "echo"s to "$this->console .= "		
		// === TJH ===  this is accessed with "$db->console" obviously 														
		// === TJH ===  n.b. you must also set $db->debug_echo = false; to override default behaviour		

		function debug($debug_echo)     // === TJH ===   set a default for function to be able to switch "echo" on/off
		{
					
			//$this->console .=  "<blockquote>"; // === TJH == commented out to change output formatting slightly
			// Only show ezSQL credits once..
			if ( ! $this->debug_called )
			{
				$this->console .=  "<font color=800080 face=arial size=2><b>ezSQL</b> (v".EZSQL_VERSION.") <b>Debug..</b></font><p>\n";
			}

			if ( $this->last_error )
			{
				$this->console .=  "<font face=arial size=2 color=000099><b>Last Error --</b> [<font color=000000><b>$this->last_error</b></font>]<p>";
			}

			if ( $this->from_disk_cache )
			{
				$this->console .=  "<font face=arial size=2 color=000099><b>Results retrieved from disk cache</b></font><p>";
			}

			$this->console .=  "<font face=arial size=2 color=000099><b>Query</b> [$this->num_queries] <b>--</b> ";
			$this->console .=  "[<font color=000000><b>$this->last_query</b></font>]</font><p>";

			$this->console .=  "<font face=arial size=2 color=000099><b>Query Result..</b></font>";
			$this->console .=  "<blockquote>";

			if ( $this->col_info )
			{

				// =====================================================
				// Results top rows

				$this->console .=  "<table cellpadding=5 cellspacing=1 bgcolor=555555>";
				$this->console .=  "<tr bgcolor=eeeeee><td nowrap valign=bottom><font color=555599 face=arial size=2><b>(row)</b></font></td>";


				for ( $i=0; $i < count($this->col_info); $i++ )
				{
			    $this->console .=  "<td nowrap align=left valign=top><font size=1 color=555599 face=arial>{$this->col_info[$i]->type} {$this->col_info[$i]->max_length}</font><br><span style='font-family: arial; font-size: 10pt; font-weight: bold;'>{$this->col_info[$i]->name}</span></td>";
				}

				$this->console .=  "</tr>";

				// ======================================================
				// print main results

			if ( $this->last_result )
			{

				$i=0;
				foreach ( $this->get_results(null,ARRAY_N) as $one_row )
				{
				$i++;
				$this->console .=  "<tr bgcolor=ffffff><td bgcolor=eeeeee nowrap align=middle><font size=2 color=555599 face=arial>$i</font></td>";

					foreach ( $one_row as $item )
					{
					$this->console .=  "<td nowrap><font face=arial size=2>$item</font></td>";
					}

					$this->console .=  "</tr>";
				}

			} // if last result
			else
			{
				$this->console .=  "<tr bgcolor=ffffff><td colspan=".(count($this->col_info)+1)."><font face=arial size=2>No Results</font></td></tr>";
			}

			$this->console .=  "</table>";

			} // if col_info
			else
			{
			$this->console .=  "<font face=arial size=2>No Results</font>";
			}

			$this->console .=  "</blockquote></blockquote>";
			$this->console .=  $this->donation();
			$this->console .=  "<hr noshade color=dddddd size=1>";

            // == TJH ==  more -- to try to make backward compatible with a default param that defaults to echo 
			if($this->debug_echo){
			echo $this->console;
			}
		
			$this->debug_called = true;
		//echo "Something tested";   // == TJH == just some breadcrumbs for testing
		}



		/**********************************************************************
		*  Naughty little function to ask for some remuniration!
		*/

		function donation()
		{
			return "<font size=1 face=arial color=000000>If ezSQL has helped <a href=\"https://www.paypal.com/xclick/business=justin%40justinvincent.com&item_name=ezSQL&no_note=1&tax=0\" style=\"color: 0000CC;\">make a donation!?</a> &nbsp;&nbsp;<!--[ go on! you know you want to! ]--></font>";
		}

	}

?>