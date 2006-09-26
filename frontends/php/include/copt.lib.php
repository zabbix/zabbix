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
	
	define("USE_PROFILING",1);
	define("USE_TIME_PROF",1);
	define("USE_MEM_PROF",1);
	define("USE_SQLREQUEST_PROF",1);
	//define("SHOW_SQLREQUEST_DETAILS",1);

if(defined('USE_PROFILING'))
{
	$starttime=array();
	$memorystamp=array();
	$sqlrequests=array();
	$sqlmark = array();

	class COpt
	{
		/* protected static $starttime[]=array(); */


		/* protected static */ function getmicrotime() { 
if(defined('USE_TIME_PROF')) {
			list($usec, $sec) = explode(' ',microtime());	return ((float)$usec + (float)$sec); 
}else {
			return 0;
}
		}


		/* public static */ function showmemoryusage($descr=null)
		{
if(defined('USE_MEM_PROF')) {
			$memory_usage = COpt::getmemoryusage();
			$memory_usage = $memory_usage.'b | '.($memory_usage>>10).'K | '.($memory_usage>>20).'M';
			SDI('PHP memory usage ['.$descr.'] '.$memory_usage);
}
		}

		/* protected static */ function getmemoryusage() {
if(defined('USE_MEM_PROF')) {
			return memory_get_usage('memory_limit');
} else {
			return 0;
}
		}

		/* protected static */ function mem2str($size)
		{
			$prefix = 'B';
			if($size > 1048576) {	$size = $size/1048576;	$prefix = 'M'; }
			elseif($size > 1024) {	$size = $size/1024;	$prefix = 'K'; }
			return round($size, 6).$prefix;
		}

		/* public static */ function profiling_start($type=NULL)
		{
			global $starttime;
			global $memorystamp;
			global $sqlmark;
			global $sqlrequests;

			if(is_null($type)) $type='global';

			$starttime[$type] = COpt::getmicrotime();
			$memorystamp[$type] = COpt::getmemoryusage();
if(defined('USE_SQLREQUEST_PROF'))
{
			$sqlmark[$type] = count($sqlrequests);
}
		}

		/* public static */ function savesqlrequest($sql)
		{
if(defined('USE_SQLREQUEST_PROF'))
{
			global $sqlrequests;
			array_push($sqlrequests, $sql);
}
		}

		/* public static */ function profiling_stop($type=NULL)
		{
			global $starttime;
			global $memorystamp;
			global $sqlrequests;
			global $sqlmark;

			$endtime = COpt::getmicrotime();
			$memory = COpt::getmemoryusage();

			if(is_null($type)) $type='global';

			echo "<br>\n";
if(defined('USE_TIME_PROF'))
{
			echo "(".$type.") Time to execute: ".round($endtime - $starttime[$type],6)." seconds!\n<br>\n";
}
if(defined('USE_MEM_PROF'))
{
			echo "(".$type.") Memory limit	 : ".ini_get('memory_limit')."<br>\n";
			echo "(".$type.") Memory usage	 : ".COpt::mem2str($memorystamp[$type])." - ".COpt::mem2str($memory)."\n<br>\n";
			echo "(".$type.") Memory leak	 : ".COpt::mem2str($memory - $memorystamp[$type])."\n<br>\n";
}
if(defined('USE_SQLREQUEST_PROF'))
{
			$requests_cnt = count($sqlrequests);
			echo "(".$type.") SQL requests count: ".($requests_cnt - $sqlmark[$type])."<br>\n";
	if(defined('SHOW_SQLREQUEST_DETAILS'))
	{
			for($i = $sqlmark[$type]; $i < $requests_cnt; $i++)
			{
				echo "(".$type.") SQL request    : ".$sqlrequests[$i]."<br>\n";;
			}
	}
}
		}


		/* public static */ function set_memory_limit($limit='8M')
		{
			ini_set('memory_limit',$limit);
		}
	}

	COpt::set_memory_limit('8M');
	COpt::profiling_start("script");
}
else
{
	class COpt
	{
		/* public static */ function profiling_start($type=NULL) {}
		/* public static */ function profiling_stop($type=NULL) {}
		/* public static */ function savesqlrequest($sql) {}
		/* public static */ function showmemoryusage($descr=null) {}
	}
}

?>
