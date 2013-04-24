=======================================================================
Author: Justin Vincent - http://justinvincent.com/ezsql
Name: ezSQL
Desc: Class to make it very easy to deal with database connections.
License: FREE / Donation (LGPL - You may do what you like with ezSQL - no exceptions.)
=======================================================================

Follow me on twitter:
http://twitter.com/justinvincent

Check out my podcast TechZing where we talk about tech and tech startups:
http://techzinglive.com

=======================================================================

Change Log:

2.17 - Updates to ezSQL_postgresql (thx Stefanie Janine Stoelting)

2.16 - Added profiling functions to mySQL version & added fix to stop mySQL hanging on very long runnign scripts

2.15 - Fixed long standing bug with $db->rows_affected (thx Pere Pasqual)

2.14 - Added sybase connector by Muhammad Iyas

2.13 - Support for transations. See: http://stackoverflow.com/questions/8754215/ezsql-with-multiple-queries/8781798

2.12 - Added $db->get_set() - Creates a SET nvp sql string from an associative array (and escapes all values)

2.11 - Fixed $db->insert_id in postgress version

2.10 - Added isset($this->dbh) check to orcale version

2.09 - Fixed issues with mysql_real_escape_string not woirkign if no connection 
       (Thanks to Nicolas Vannier)

2.08 - Re-added timer functions that seemed to have disappeared

2.07 - Used mysql_real_escape_string instead of mysql_escape_string

2.02 - Please note, this change log is no longer being used
       please see change_log.htm for changes later than
       2.02

2.01 - Added Disk Caching & Multiple DB connection support

2.00 - Re-factored ezSQL for Oracle, mySQL & SQLite

        - DB Object is no longer initialized by default
          (makes it easier to use multiple connections)

        - Core ezSQL functions have been separated from DB
          specific functions (makes it easier to add new databases)

        - Errors are being piped through trigger_error
          (using standard PHP error logging)

		- Abstracted error messages (enabling future translation)

		- Upgraded $db->query error return functionality

		- Added $db->systdate function to abstract mySQL NOW()
		  and Oracle SYSDATE

		Note: For other DB Types please use version 1.26

1.26 - Update (All)

        - Fixed the pesky regular expression that tests for
          an insert/update etc. Now it works even for the most
          weirdly formatted queries! (thx dille)

1.25 - Update (mySQL/Oracle)

        - Optimised $db->query function in both mySQL and Oracle versions.
          Now the return value is working 'as expected' in ALL cases so you
          are always safe using:

          if ( $db->query("some query") )

          No matter if an insert or a select.

          In the case of insert/update the return value is based on the
          number of rows affected (used to be insert id which is
          not valid for an update)

          In the case of select the return value is based on number of
          rows returned.

          Thx Bill Bruggemeyer :)

1.24 - Update (Docs)

        - Now includes tutorial for using EZ Results with Smarty
          templating engine - thx Steve Warwick

1.23 - Update (All PHP versions)

        - Fixed the age old problem of returning false on
          successful insert. $db->query()now returns the insert_id
          if there was a successful insert or false if not. Sorry
          that this took so long to fix!

          Version Affected: mySQL/Porgress/ms-sql/sqlite

        - Added new variable $db->debug_all

          By default it is set to false but if you change it
          to true. i.e.

          include_once "ez_sql.php";
          $db->debug_all = true;

          Then it will print out each and every query and all
          of the results that your script creates.

          Very useful if you want to follow the entire logic
          path of what ALL your queries are doing, but can't
          be bothered to put $db->debug() statements all over
          the place!

        Update (Postgress SQL Version)

         - Our old friend Tom De Bruyne as updated the postgress
           version.

           1) It now works without throwing errors (also thanks Geert Nijpels)

           2) It now, in theory, returns $this->insert_id after an insert.


1.22 - Update (All PHP versions)

          - Added new variable $db->num_queries it keeps track
            of exactly how many 'real' (not cached) queries were
            executed (using ezSQL) during the lifetime of one script.
            Useful for debugging and optimizing.

          - Put a white table behind the vardump output so that
            it doesn't get lost on dark websites.

1.21 - Update (All Versions)

		- Now 'replace' really does return an insert id..
		  (the 1.19 fix did not complete the job. Doh!)

1.20 - Update (New Version)

		- C++ SQLite version added. Look at ez_demo.cpp.
		  (thanks Brennan Falkner)

1.19 - Update (All Versions)

		- Fixed bug where any string containing the word 'insert',
		  'delete' or 'update' (where those words were not the actual
		  query) was causing unexpected results (thx Simon Willison).

		  The fix was to alter the regular expression to only match
		  queries containing those words at the beginning of the query
		  (with optional whitespace allowed before the words)

		  i.e.

		  THIS:    preg_match("/$word /",strtolower($query))
		  TO THIS: preg_match("/^\\s*$word /",strtolower($query))

		  - Added new sql word 'replace' to the above match pattern
		    so that the $db->insert_id would be also be populated
		    on 'replace' queries (thx Rolf Dahl)

1.18 - Update (All Versions)

		- Added new SQLite version (thanks Brennan Falkner)

		- Fixed new bug that was introduced with bug fix 1.14
		  false was being returned on successful insert update etc.
		  now it is true

1.17 - Update (All Versions)

		- New MS-SQL version added (thanks to Tom De Bruyne)
		- Made the donation request 'less annoying' by making it more subtle!

1.16 - Update (All Versions)

		- Added new function $db->escape()
		  Formats a string correctly to stop accidental
		  mal formed queries under all PHP conditions

1.15 - Bug fixes

		- (Postgress)
		  $this->result = false; was in the wrong place.
		  Fixed! Thanks (Carlos Camiña García)

		- (all versions)
		  Pesky get_var was still returning null instead of 0 in
		  certain cases. Bug fix of !== suggested by Osman

1.14 - Bug fixes

		- (all versions)
		  Added !='' into the conditional return of get_var.
		  because if the result was the number 0 it was not returning anything

		- (mySQL / Interbase / Postgress)
		  Added $this->result = false; if insert / update / delete
		  because it was causing mysql retrieval errors that no one
		  knew about due to the @ signs.

1.13 - Update (All Versions)

		- Swapped 2nd and 3rd argument order.
		- From.. get_row(query, int row offset, output type)
		- To.... get_row(query, output type, int row offset)

1.12 - Update (All Versions)

		- Tweaked the new hide/show error code
		- Made sure the $this->show_errors was always initialised
		- $db->query() function now returns false if there was an SQL error.
		  So that you can now do the following when you hide errors.

		  if ( $db->query("BAD SYNTAX") )
		  {
		  	echo "Bad Query";
		  }

1.11 - Update (All Versions)

		- added $db->hide_errors();
		- added $db->show_errors();
		- added global array $EZSQL_ERROR;

1.10 - Fix (mySQL)

		- Insist that mysql_insert_id(); uses $this->dbh.

1.09 - Bug Fix

		- Oracle version had the wrong number of parameters in the
		  $db = new db(etc,etc,etc,etc) part.

		- Also added var $vardump_called; to all versions.

1.08 - Bug Fix

		- Michael fixed the select function in PostgreSQL version.

1.07 - Bug Fix

		- Added var $debug_called; to all versions.

1.06 - Update

		- Fixed Bug In Oracle Version where an insert was
		  causing an error with OCIFetch
		- New PostgreSQL Version Added by Michael Paesold (mpaesold@gmx.at)

1.05 - Bug Fix (mySQL)

		- Removed repeated piece of code.

1.04 - Update

		- $db->num_rows - variable added (All Versions)
		- $db->rows_affected - variable added ( mySQL / Oracle )
		- New InterBase/FireBase Version Added by LLCedar (llceder@wxs.nl)

1.03 - Update (All Versions)

	Enhancements to vardump..

		- Added display variable type
		- If no value display No Value / False
		- Added this readme file

1.02 - Update (mySQL version)

	Added $db->insert_id to

1.01 - New Version

	Oracle 8 Version as below

1.00 - Initial Release

	Functions..

		- $db->get_results - get multiple row result set from the database (or previously cached results)
		- $db->get_row -- get one row from the database (or previously cached results)
		- $db->get_col - get one column from query (or previously cached results) based on column offset
		- $db->get_var -- get one variable, from one row, from the database (or previously cached results)
		- $db->query -- send a query to the database (and if any results, cache them)
		- $db->debug - print last sql query and returned results (if any)
		- $db->vardump - print the contents and structure of any variable
		- $db->select -- select a new database to work with
		- $db->get_col_info - get information about one or all columns such as column name or type
		- $db = new db -- Initiate new db object.
