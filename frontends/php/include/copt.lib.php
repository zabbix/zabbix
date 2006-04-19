<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

	/* SPEED Measurement
	 	-= slow =-
		vs
		-= fast =-
	1)strlen
		if (strlen($foo) < 5) { echo "Foo is too short"; }
		vs
		if (!isset($foo{5})) { echo "Foo is too short"; }

	2)++
		$i++
		vs
		++$i

	3)print
		print
		vs
		echo

	4)regexps 
		preg_match("![0-9]+!", $foo);
		vs
		ctype_digit($foo);

	5)in_array
		$keys = array("apples", "oranges", "mangoes", "tomatoes", "pickles");
		if (in_array('mangoes', $keys)) { ... }
		vs
		$keys = array("apples" => 1, "oranges" => 1, "mangoes" => 1, "tomatoes" => 1, "pickles" => 1);
		if (isset($keys['mangoes'])) { ... }

	6)key_exist
		if(array_key_exists('mangoes', $keys))
		vs
		if (isset($keys['mangoes'])) { ... }

	7)regexps
		POSIX-regexps
		vs
		Perl-regexps

	8)constants
		UPPER case constans (TRUE, FALSE)
		vs
		lower case constans (true, false)

	9)for
		for ($i = 0; $i < FUNCTION($j); $i++) {...}
		vs
		for ($i = 0, $k = FUNCTION($j); $i < $k; $i = $i + 1) {...}

	10)strings
		"var=$var"
		vs
		'var='.$var
		
	*/


	/*
	** Description:
	**     Optimization class. Provide functions for
	**     PHP code optimization.
	**
	** Author: 
	**     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	**/
	
	//define("USE_PROFILING",1);

	$starttime[]=array();

	class COpt
	{
		/* protected static $starttime[]=array(); */

		/* protected static */ function getmicrotime()
		{
			list($usec, $sec) = explode(" ",microtime());
			return ((float)$usec + (float)$sec);
		}
		/* public static */ function profiling_start($type=NULL)
		{
			if(!defined('USE_PROFILING')) return;

			global $starttime;
			if(is_null($type)) $type="global";

			$starttime[$type] = COpt::getmicrotime();
		}
		/* public static */ function profiling_stop($type=NULL)
		{
			if(!defined('USE_PROFILING')) return;

			global $starttime;

			$endtime = COpt::getmicrotime();

			if(is_null($type)) $type="global";
			echo "<br/>\nTime to execute (".$type."): ". bcsub($endtime,$starttime[$type],6)." seconds!\n<br/>";
		}
	}

?>
