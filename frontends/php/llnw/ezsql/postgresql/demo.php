<?php

	/**********************************************************************
	*  ezSQL initialisation for PostgreSQL
	*/

	// Include ezSQL core
	include_once "../shared/ez_sql_core.php";

	// Include ezSQL database specific component
	include_once "ez_sql_postgresql.php";

	// Initialise database object and establish a connection
	// at the same time - db_user / db_password / db_name / db_host
        $db = new ezSQL_postgresql('db_user','db_password','db_name','db_host');
	/**********************************************************************
	*  ezSQL demo for PostgreSQL database
	*/

	// Demo of getting a single variable from the db
	// (and using abstracted function sysdate)
    
	$current_time = $db->get_var("SELECT " . $db->sysdate());
	print "ezSQL demo for PostgreSQL database run @ $current_time";

	// Print out last query and results..
	$db->debug();

	// Get list of tables from current database..
	$my_tables = $db->get_results("SELECT " . $db->showTables(), ARRAY_N);
	// Print out last query and results..
	$db->debug();

	// Loop through each row of results..
	foreach ( $my_tables as $table )
	{
		// Get results of DESC table..
		$db->get_results("SELECT " . $db->descTable($table[0]));

		// Print out last query and results..
		$db->debug();
	}
	//works
	$dball = $db->get_results("SELECT " . $db->showDatabases());
	// Print out last query and results..
	$db->debug();
 
	//works
	$dball = $db->get_results("SHOW ALL");
	// Print out last query and results..
	$db->debug();
echo 'thanks to the following' . '<br />';
echo 'http://michael.paesold.at/download/' . '<br />';
echo 'http://www.alberton.info/postgresql_meta_info.html' . '<br />';
?>
