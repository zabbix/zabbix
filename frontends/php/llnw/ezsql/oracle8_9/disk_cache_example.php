<?php

	// Standard ezSQL Libs
	include_once "../shared/ez_sql_core.php";

	// Include ezSQL database specific component
	include_once "ez_sql_oracle8_9.php";

	// Initialise database object and establish a connection
	// at the same time - db_user / db_password / db_name
	$db = new ezSQL_oracle8_9('user','password','oracle.instance');

	// Cache expiry
	$db->cache_timeout = 24; // Note: this is hours

	// Specify a cache dir. Path is taken from calling script
	$db->cache_dir = 'ezsql_cache';

	// (1. You must create this dir. first!)
	// (2. Might need to do chmod 775)

	// Global override setting to turn disc caching off
	// (but not on)
	$db->use_disk_cache = true;

	// By wrapping up queries you can ensure that the default
	// is NOT to cache unless specified
	$db->cache_queries = true;

		// At last.. a query!
		$db->get_var("SELECT " . $db->sysdate() . " FROM DUAL");
		$db->debug();

		// Now get it from the cache
		$db->get_var("SELECT " . $db->sysdate() . " FROM DUAL");
		$db->debug();

	// This ensures only the above querys are cached
	$db->cache_queries = false;

	// This query is NOT cached
	$db->get_var("SELECT " . $db->sysdate() . " FROM DUAL");
	$db->debug();

/*

	Of course, if you want to cache EVERYTHING just do..

	$db = new ezSQL_oracle8_9('user','password','oracle.instance');
	$db->use_disk_cache = true;
	$db->cache_queries = true;
	$db->cache_timeout = 24;

*/

?>