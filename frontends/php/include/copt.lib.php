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
	1)zbx_strlen
		if (zbx_strlen($foo) < 5) { echo "Foo is too short"; }
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

	5)_in_array
		$keys = array("apples", "oranges", "mangoes", "tomatoes", "pickles");
		if (_in_array('mangoes', $keys)) { ... }
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

define('USE_PROFILING',1);
//define('USE_VAR_MON',1);
define('USE_TIME_PROF',1);
define('USE_MEM_PROF',1);
//define('USE_COUNTER_PROF',1);
//define('USE_MENU_PROF',1);
//define('USE_MENU_DETAILS',1);
define('USE_SQLREQUEST_PROF',1);
define('SHOW_SQLREQUEST_DETAILS',1);
// What is considered long query
define('LONG_QUERY',0.01);
// WHat is limit on total time spent on all SQL queries
define('QUERY_TOTAL_TIME',0.5);
// WHat is limit on total time spent
define('TOTAL_TIME',1.0);
//define("SHOW_SQLREQUEST_LONG_QUERIES_ONLY",1);

if(!defined('OBR')) define('OBR',"<br/>\n");

if(defined('USE_PROFILING')){
	$starttime = array();
	$memorystamp = array();
	$sqlrequests = defined('SHOW_SQLREQUEST_DETAILS') ? array() : 0;
	$sqlmark = array();
	$perf_counter = array();
	$var_list = array();

	class COpt{
		public static $memoryPick = 0;
		protected static $memory_limit_reached = false;
		protected static $max_memory_bytes = null;
		protected static $debug_info = array();

		protected  static function getmicrotime() {
			if(defined('USE_TIME_PROF')) {
				list($usec, $sec) = explode(' ',microtime());
				return ((float)$usec + (float)$sec);
			}
			else {
				return 0;
			}
		}

		public static function showmemoryusage($descr=null){
			if(defined('USE_MEM_PROF')) {
				$memory_usage = COpt::getmemoryusage();
				$memory_usage = $memory_usage.'b | '.($memory_usage>>10).'K | '.($memory_usage>>20).'M';
				SDI('PHP memory usage ['.$descr.'] '.$memory_usage);
			}
		}

		public static function memoryPick(){
			$memory_usage = COpt::getmemoryusage();
			if($memory_usage>self::$memoryPick) self::$memoryPick = $memory_usage;
		}

		public static function getMemoryPick(){
			if(defined('USE_MEM_PROF')) {
				return self::$memoryPick;
			}
			else {
				return 0;
			}
		}

		public static function showMemoryPick($descr=null){
			if(defined('USE_MEM_PROF')) {
				$memory_usage = self::$memoryPick;
				$memory_usage = $memory_usage.'b | '.($memory_usage>>10).'K | '.($memory_usage>>20).'M';
				SDI('PHP memory PICK ['.$descr.'] '.$memory_usage);
			}
		}

		protected static function getmemoryusage() {
			if(defined('USE_MEM_PROF')) {
				$memory_usage = memory_get_usage('memory_limit');
				if($memory_usage>self::$memoryPick) self::$memoryPick = $memory_usage;
				return $memory_usage;
			}
			else {
				return 0;
			}
		}

		public static function counter_up($type=NULL){
			if(defined('USE_COUNTER_PROF')){
				global $perf_counter;
				global $starttime;

				foreach(array_keys($starttime) as $keys){
					if(!isset($perf_counter[$keys][$type]))
						$perf_counter[$keys][$type]=1;
					else
						$perf_counter[$keys][$type]++;
				}
			}
		}

		public static function profiling_start($type=NULL){
			global $starttime;
			global $memorystamp;
			global $sqlmark;
			global $sqlrequests;
			global $var_list;
			global $USER_DETAILS;

			if (is_null(self::$max_memory_bytes)) {
				self::$max_memory_bytes = str2mem(ini_get('memory_limit')) * 0.8;
			}
			if((!is_null($USER_DETAILS) && isset($USER_DETAILS['debug_mode']) && ($USER_DETAILS['debug_mode'] == GROUP_DEBUG_MODE_DISABLED))
				|| self::$memory_limit_reached){
				return false;
			}

			if(self::getmemoryusage() > self::$max_memory_bytes){
				self::$memory_limit_reached = true;
			}

			if(is_null($type)) $type='global';

			$starttime[$type] = COpt::getmicrotime();
			$memorystamp[$type] = COpt::getmemoryusage();
			if(defined('USE_VAR_MON')){
				$var_list[$type] = isset($GLOBALS) ? array_keys($GLOBALS) : array();
			}

			if(defined('USE_SQLREQUEST_PROF')){
				if(defined('SHOW_SQLREQUEST_DETAILS')){
					$sqlmark[$type] = count($sqlrequests);
				}
				else{
					$sqlmark[$type] = $sqlrequests;
				}
			}
		}

		public static function savesqlrequest($time, $sql){
			global $USER_DETAILS;

			if(is_null(self::$max_memory_bytes)) self::$max_memory_bytes = (ini_get('memory_limit') * 0.8 * 1024 * 1024);

			if((!is_null($USER_DETAILS) && isset($USER_DETAILS['debug_mode']) && ($USER_DETAILS['debug_mode'] == GROUP_DEBUG_MODE_DISABLED))
				|| self::$memory_limit_reached){
				return false;
			}
			if(self::getmemoryusage() > self::$max_memory_bytes){
				self::$memory_limit_reached = true;
			}

			if(defined('USE_SQLREQUEST_PROF')){
				global $sqlrequests;
				$time=round($time,6);
//				echo $time,"<br>";
				if(defined('SHOW_SQLREQUEST_DETAILS')){
					array_push($sqlrequests, array($time,$sql));
				}
				else{
					$sqlrequests++;
				}
			}
		}

		public static function profiling_stop($type=NULL){
			global $starttime;
			global $memorystamp;
			global $sqlrequests;
			global $sqlmark;
			global $perf_counter;
			global $var_list;
			global $USER_DETAILS;
			global $DB;

			$endtime = COpt::getmicrotime();
			$memory = COpt::getmemoryusage();
			$pickMemory = COpt::getMemoryPick();

			if(is_null($type)) $type='global';

			$debug_str = '';
			$debug_str.= '<a name="debug"></a>';
			$debug_str.= "******************* ".S_STATS_FOR." $type *************************".OBR;
			if(defined('USE_TIME_PROF')){
				$time = $endtime - $starttime[$type];
				if($time < TOTAL_TIME){
					$debug_str.= S_TOTAL_TIME.': '.round($time,6).OBR;
				}
				else{
					$debug_str.= '<b>'.S_TOTAL_TIME.': '.round($time,6).'</b>'.OBR;
				}
			}

			if(defined('USE_MEM_PROF')){
				$debug_str.= S_MEMORY_LIMIT.'	: '.ini_get('memory_limit').OBR;
				$debug_str.= S_MEMORY_USAGE.'	: '.mem2str($memorystamp[$type]).' - '.mem2str($memory).
						' ('.mem2str($memory - $memorystamp[$type]).')'.OBR;
				$debug_str.= 'Memory peak	: '.mem2str($pickMemory).OBR;
			}

			if(defined('USE_VAR_MON')){
				$curr_var_list = isset($GLOBALS) ? array_keys($GLOBALS) : array();
				$var_diff = array_diff($curr_var_list, $var_list[$type]);
				$debug_str.= ' Undeleted vars : '.count($var_diff).' [';
				print_r(implode(', ',$var_diff));
				$debug_str.= ']'.OBR;
			}

			if(defined('USE_COUNTER_PROF')){
				if(isset($perf_counter[$type])){
					ksort($perf_counter[$type]);
					foreach($perf_counter[$type] as $name => $value){
						$debug_str.= 'Counter "'.$name.'" : '.$value.OBR;
					}
				}
			}

			if(defined('USE_SQLREQUEST_PROF')){
				if(defined('SHOW_SQLREQUEST_DETAILS')){
					$requests_cnt = count($sqlrequests);
					if(isset($DB) && isset($DB['SELECT_COUNT'])){
						$debug_str.= S_SQL_SELECTS_COUNT.': '.$DB['SELECT_COUNT'].OBR;
						$debug_str.= S_SQL_EXECUTES_COUNT.': '.$DB['EXECUTE_COUNT'].OBR;
						$debug_str.= S_SQL_REQUESTS_COUNT.': '.($requests_cnt - $sqlmark[$type]).OBR;
					}

					$sql_time=0;
					for($i = $sqlmark[$type]; $i < $requests_cnt; $i++){
						$time = $sqlrequests[$i][0];
						$sqlrequests[$i][1] = str_replace(array('<','>'), array('&lt;','&gt;'), $sqlrequests[$i][1]);

						$sql_time+=$time;
						if($time < LONG_QUERY){
							$debug_str.= S_TIME.':'.round($time,8).' SQL:&nbsp;'.$sqlrequests[$i][1].OBR;
						}
						else{
							$debug_str.= '<b>'.S_TIME.':'.round($time,8).' LONG SQL:&nbsp;'.$sqlrequests[$i][1].'</b>'.OBR;
						}
					}
				}
				else{
					$debug_str.= 'SQL requests count: '.($sqlrequests - $sqlmark[$type]).OBR;
				}

				if($sql_time < QUERY_TOTAL_TIME){
					$debug_str.= S_TOTAL_TIME_SPENT_ON_SQL.': '.round($sql_time,8).OBR;
				}
				else{
					$debug_str.= '<b>'.S_TOTAL_TIME_SPENT_ON_SQL.': '.round($sql_time,8).'</b>'.OBR;
				}
			}
			$debug_str.= "******************** ".S_END_OF." $type ***************************".OBR;

			self::$debug_info[$type] = $debug_str;
		}

		public static function show(){
// DEBUG of ZBX FrontEnd
			$debug = new CDiv(null,'textcolorstyles');
			$debug->setAttribute('name','zbx_gebug_info');
			$debug->setAttribute('style','display: none; overflow: auto; width: 95%; border: 1px #777777 solid; margin: 4px; padding: 4px;');

			if(self::$memory_limit_reached){
				$debug->addItem(array(BR(), S_MEMORY_LIMIT_REACHED,BR()));
			}
			foreach(self::$debug_info as $type => $info){
				$debug->addItem(array(BR(),new CJSscript($info),BR()));
			}

			$debug->show();
//----------------
		}

		public static function set_memory_limit($limit='256M'){
			ini_set('memory_limit',$limit);
		}

		public static function compare_files_with_menu($menu=null){
			if(defined('USE_MENU_PROF')){

				$files_list = glob('*.php');

				$result = array();
				foreach($files_list as $file){
					$list = array();
					foreach($menu as $label=>$sub){
						foreach($sub['pages'] as $sub_pages){
							if(empty($sub_pages)) continue;

							if(!isset($sub_pages['label'])) $sub_pages['label']=$sub_pages['url'];

							$menu_path = $sub['label'].'->'.$sub_pages['label'];

							if($sub_pages['url'] == $file){
								array_push($list, $menu_path);
							}
							if(!str_in_array($sub_pages['url'], $files_list))
								$result['error'][$sub_pages['url']] = array($menu_path);

							if(isset($sub_pages['sub_pages'])) foreach($sub_pages['sub_pages'] as $page){
								$menu_path = $sub['label'].'->'.$sub_pages['label'].'->sub_pages';

								if(!str_in_array($page, $files_list))
									$result['error'][$page] = array($menu_path);

								if($page != $file) continue;
								array_push($list, $menu_path);
							}
						}
					}

					if(count($list) != 1)	$level = 'worning';
					else			$level = 'normal';

					$result[$level][$file] = $list;
				}

				foreach($result as $level => $files_list){
					if(defined('USE_MENU_DETAILS')){
						echo OBR.'(menu check) ['.$level.OBR;
						foreach($files_list as $file => $menu_list){
							echo '(menu check)'.SPACE.SPACE.SPACE.SPACE.$file.' {'.implode(',',$menu_list).'}'.OBR;
						}
					}
					else{
						echo OBR.'(menu check) ['.$level.'] = '.count($files_list).OBR;
					}
				}
			}
		}
	}

	//COpt::set_memory_limit('256M');
	COpt::profiling_start('script');
}
else{
	class COpt{
		static function profiling_start($type=NULL) {}
		static function profiling_stop($type=NULL) {}
		static function show() {}
		static function savesqlrequest($sql) {}
		static function showmemoryusage($descr=null) {}
		static function compare_files_with_menu($menu=null) {}
		static function counter_up($type=NULL) {}
	}
}

?>
