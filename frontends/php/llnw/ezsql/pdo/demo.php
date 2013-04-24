<?php

	// Include ezSQL core
	include_once "../shared/ez_sql_core.php";

	// This is how to initialse ezsql for sqlite PDO
	$db = new ezSQL_pdo('sqlite:my_database.sq3','someuser','somepassword');

?>