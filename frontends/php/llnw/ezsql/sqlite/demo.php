<?php

	/**********************************************************************
	*  ezSQL initialisation for SQLite
	*/

	// Include ezSQL core
	include_once "../shared/ez_sql_core.php";
	
	// Include ezSQL database specific component
	include_once "ez_sql_sqlite.php";

	// Initialise database object and establish a connection
	// at the same time - db_path / db_name
	$db = new ezSQL_sqlite('./','sqlite_test.db');        

	// Create a table..
	$db->query("CREATE TABLE test_table ( MyColumnA INTEGER PRIMARY KEY, MyColumnB TEXT(32) );");

	// Insert test data
	for($i=0;$i<3;++$i)
	{
		$db->query('INSERT INTO test_table (MyColumnB) VALUES ("'.md5(microtime()).'");');
	}
	
	// Get list of tables from current database..
	$my_tables = $db->get_results("SELECT * FROM sqlite_master WHERE sql NOTNULL;");

	// Print out last query and results..
	$db->debug();

	// Loop through each row of results..
	foreach ( $my_tables as $table )
	{
		// Get results of DESC table..
		$db->get_results("SELECT * FROM $table->name;");

		// Print out last query and results..
		$db->debug();
	}

	// Get rid of the table we created..
	$db->query("DROP TABLE test_table;");

?>