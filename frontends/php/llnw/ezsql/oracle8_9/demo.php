<?php

	/**********************************************************************
	*  ezSQL initialisation for Oracle
	*/

	// Include ezSQL core
	include_once "../shared/ez_sql_core.php";

	// Include ezSQL database specific component
	include_once "ez_sql_oracle8_9.php";

	// Initialise database object and establish a connection
	// at the same time - db_user / db_password / db_name
	$db = new ezSQL_oracle8_9('user','password','oracle.instance');        

	/**********************************************************************
	*  ezSQL demo for Oracle database
	*/

	// Demo of getting a single variable from the db 
	// (and using abstracted function sysdate)
	$current_date = $db->get_var("SELECT " . $db->sysdate() . " FROM DUAL");
	print "ezSQL demo for mySQL database run on $current_date";
	
	// Get list of tables from current database..
	$my_tables = $db->get_results("SELECT TABLE_NAME FROM USER_TABLES",ARRAY_N);

	// Print out last query and results..
	$db->debug();

	// Loop through each row of results..
	foreach ( $my_tables as $table )
	{
		// Get results of DESC table..
		$db->get_results("SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, DATA_PRECISION FROM USER_TAB_COLUMNS WHERE TABLE_NAME = '$table[0]'");

		// Print out last query and results..
		$db->debug();
	}

?>
