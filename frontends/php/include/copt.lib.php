<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/* SPEED Measurement
	-= slow =-
	vs
	-= fast =-
1) zbx_strlen
	if (zbx_strlen($foo) < 5) { echo "Foo is too short"; }
	vs
	if (!isset($foo{5})) { echo "Foo is too short"; }

2) ++
	$i++
	vs
	++$i

3) print
	print
	vs
	echo

4) regexps
	preg_match("![0-9]+!", $foo);
	vs
	ctype_digit($foo);

5) _in_array
	$keys = array("apples", "oranges", "mangoes", "tomatoes", "pickles");
	if (_in_array('mangoes', $keys)) { ... }
	vs
	$keys = array("apples" => 1, "oranges" => 1, "mangoes" => 1, "tomatoes" => 1, "pickles" => 1);
	if (isset($keys['mangoes'])) { ... }

6) key_exist
	if(array_key_exists('mangoes', $keys))
	vs
	if (isset($keys['mangoes'])) { ... }

7) regexps
	POSIX-regexps
	vs
	Perl-regexps

8) constants
	UPPER case constans (TRUE, FALSE)
	vs
	lower case constans (true, false)

9) for
	for ($i = 0; $i < FUNCTION($j); $i++) {...}
	vs
	for ($i = 0, $k = FUNCTION($j); $i < $k; $i = $i + 1) {...}

10) strings
	"var=$var"
	vs
	'var='.$var
*/

/*
** Description:
**     Optimization class. Provide functions for
**     PHP code optimization.
**/

define('USE_PROFILING', 1);
define('USE_TIME_PROF', 1);
define('USE_MEM_PROF', 1);
define('USE_SQLREQUEST_PROF', 1);
define('SHOW_SQLREQUEST_DETAILS', 1);
define('USE_APICALL_PROF', 1);
define('LONG_QUERY', 0.01); // what is considered long query
define('QUERY_TOTAL_TIME', 0.5); // what is limit on total time spent on all SQL queries
define('TOTAL_TIME', 1.0); // what is limit on total time spent

if (!defined('OBR')) {
	define('OBR', "<br/>\n");
}

if (defined('USE_PROFILING')) {
	$starttime = array();
	$memorystamp = array();
	$sqlrequests = defined('SHOW_SQLREQUEST_DETAILS') ? array() : 0;
	$sqlmark = array();
	$perf_counter = array();
	$var_list = array();

	class COpt {

		public static $memoryPick = 0;
		protected static $memory_limit_reached = false;
		protected static $max_memory_bytes = null;
		protected static $debug_info = array();
		protected static $api_calls = array();

		protected  static function getmicrotime() {
			if (defined('USE_TIME_PROF')) {
				list($usec, $sec) = explode(' ', microtime());
				return ((float)$usec + (float)$sec);
			}
			else {
				return 0;
			}
		}

		public static function showmemoryusage($descr = null) {
			if (defined('USE_MEM_PROF')) {
				$memory_usage = COpt::getmemoryusage();
				$memory_usage = $memory_usage.'b | '.($memory_usage >> 10).'K | '.($memory_usage >> 20).'M';
				SDI('PHP memory usage ['.$descr.'] '.$memory_usage);
			}
		}

		public static function memoryPick() {
			$memory_usage = COpt::getmemoryusage();
			if ($memory_usage > self::$memoryPick) {
				self::$memoryPick = $memory_usage;
			}
		}

		public static function getMemoryPick() {
			return defined('USE_MEM_PROF') ? self::$memoryPick : 0;
		}

		public static function showMemoryPick($descr = null) {
			if (defined('USE_MEM_PROF')) {
				$memory_usage = self::$memoryPick;
				$memory_usage = $memory_usage.'b | '.($memory_usage >> 10).'K | '.($memory_usage >> 20).'M';
				SDI('PHP memory PICK ['.$descr.'] '.$memory_usage);
			}
		}

		protected static function getmemoryusage() {
			if (defined('USE_MEM_PROF')) {
				$memory_usage = memory_get_usage('memory_limit');
				if ($memory_usage > self::$memoryPick) {
					self::$memoryPick = $memory_usage;
				}
				return $memory_usage;
			}
			else {
				return 0;
			}
		}

		public static function counter_up($type = null) {
			if (defined('USE_COUNTER_PROF')) {
				global $perf_counter, $starttime;

				foreach (array_keys($starttime) as $keys) {
					if (!isset($perf_counter[$keys][$type])) {
						$perf_counter[$keys][$type] = 1;
					}
					else {
						$perf_counter[$keys][$type]++;
					}
				}
			}
		}

		public static function profiling_start($type = null) {
			global $USER_DETAILS, $starttime, $memorystamp, $sqlmark, $sqlrequests, $var_list;

			if (is_null(self::$max_memory_bytes)) {
				self::$max_memory_bytes = ini_get('memory_limit') * 838860.8; // 0.8 * 1024 * 1024
			}
			if ((!empty($USER_DETAILS['debug_mode']) && $USER_DETAILS['debug_mode'] == GROUP_DEBUG_MODE_DISABLED)
					|| self::$memory_limit_reached) {
				return false;
			}

			if (self::getmemoryusage() > self::$max_memory_bytes) {
				self::$memory_limit_reached = true;
			}

			if (is_null($type)) {
				$type = 'global';
			}

			$starttime[$type] = COpt::getmicrotime();
			$memorystamp[$type] = COpt::getmemoryusage();

			if (defined('USE_VAR_MON')) {
				$var_list[$type] = isset($GLOBALS) ? array_keys($GLOBALS) : array();
			}

			if (defined('USE_SQLREQUEST_PROF')) {
				$sqlmark[$type] = defined('SHOW_SQLREQUEST_DETAILS') ? count($sqlrequests) : $sqlrequests;
			}
		}

		public static function savesqlrequest($time, $sql) {
			global $USER_DETAILS;

			if (is_null(self::$max_memory_bytes)) {
				self::$max_memory_bytes = ini_get('memory_limit') * 838860.8; // 0.8 * 1024 * 1024
			}

			if ((!empty($USER_DETAILS['debug_mode']) && $USER_DETAILS['debug_mode'] == GROUP_DEBUG_MODE_DISABLED)
					|| self::$memory_limit_reached ) {
				return false;
			}
			if (self::getmemoryusage() > self::$max_memory_bytes) {
				self::$memory_limit_reached = true;
			}

			if (defined('USE_SQLREQUEST_PROF')) {
				global $sqlrequests;

				$time = round($time, 6);
				if (defined('SHOW_SQLREQUEST_DETAILS')) {
					$callStack = array_slice(debug_backtrace(), 1);

					array_push($sqlrequests, array($time, $sql, $callStack));
				}
				else {
					$sqlrequests++;
				}
			}
		}

		public static function saveApiCall($class, $method, $params, $result) {
			$backtrace = debug_backtrace();
			$file = basename($backtrace[2]['file']);
			$line = basename($backtrace[2]['line']);
			self::$api_calls[] = array($class, $method, $params, $result, $file, $line);
		}

		public static function profiling_stop($type = null) {
			global $starttime, $memorystamp, $sqlrequests, $sqlmark, $perf_counter, $var_list, $DB;

			$endtime = COpt::getmicrotime();
			$memory = COpt::getmemoryusage();
			$pickMemory = COpt::getMemoryPick();

			if (is_null($type)) {
				$type = 'global';
			}

			$debug_str = '<a name="debug"></a>';
			$debug_str .= '******************* '._('Stats for').' '.$type.' *************************'.OBR;

			if (defined('USE_TIME_PROF')) {
				$debug_str .= OBR;
				$time = $endtime - $starttime[$type];
				if ($time < TOTAL_TIME) {
					$debug_str .= _('Total time').': '.round($time, 6).OBR;
				}
				else {
					$debug_str .= '<b>'._('Total time').': '.round($time, 6).'</b>'.OBR;
				}
			}

			if (defined('USE_MEM_PROF')) {
				$debug_str .= OBR;
				$debug_str .= _('Memory limit').' : '.ini_get('memory_limit').OBR;
				$debug_str .= _('Memory usage').' : '.mem2str($memorystamp[$type]).' - '.mem2str($memory).
					' ('.mem2str($memory - $memorystamp[$type]).')'.OBR;
				$debug_str .= _('Memory peak').' : '.mem2str($pickMemory).OBR;
			}

			if (defined('USE_VAR_MON')) {
				$debug_str .= OBR;
				$curr_var_list = isset($GLOBALS) ? array_keys($GLOBALS) : array();
				$var_diff = array_diff($curr_var_list, $var_list[$type]);
				$debug_str .= ' '._('Undeleted vars').' : '.count($var_diff).' [';
				print_r(implode(', ', $var_diff));
				$debug_str .= ']'.OBR;
			}

			if (defined('USE_COUNTER_PROF')) {
				$debug_str .= OBR;
				if (isset($perf_counter[$type])) {
					ksort($perf_counter[$type]);
					foreach ($perf_counter[$type] as $name => $value) {
						$debug_str .= _('Counter').' "'.$name.'" : '.$value.OBR;
					}
				}
			}

			if (defined('USE_APICALL_PROF') && USE_APICALL_PROF) {
				$debug_str .= OBR;

				foreach (self::$api_calls as $i => $apiCall) {
					$debug_str .= '<div style="border-bottom: 1px dotted gray; margin-bottom: 20px;">';
					list($class, $method, $params, $result, $file, $line) = $apiCall;

					// api method
					$debug_str .= '<div style="padding-bottom: 10px;">';
					$debug_str .= ($i + 1).'. <b>'.$class.'->'.$method.'</b> ['.$file.':'.$line.']';
					$debug_str .= '</div>';

					// parameters
					$debug_str .= '<table><tr><td width="300" valign="top">'._('Parameters').':';
					foreach ($params as $p) {
						$debug_str .= '<pre>'.print_r(CHtml::encode($p), true).'</pre>';
					}
					$debug_str .= '</td>';

					// result
					$debug_str .= '<td valign="top">Result:<pre>'.print_r(CHtml::encode($result), true).'</pre></td>';
					$debug_str .= '</tr></table>';
					$debug_str .= '</div>';
				}
			}

			if (defined('USE_SQLREQUEST_PROF')) {
				$debug_str .= OBR;
				if (defined('SHOW_SQLREQUEST_DETAILS')) {
					$requests_cnt = count($sqlrequests);
					if (isset($DB) && isset($DB['SELECT_COUNT'])) {
						$debug_str .= _('SQL selects count').': '.$DB['SELECT_COUNT'].OBR;
						$debug_str .= _('SQL executes count').': '.$DB['EXECUTE_COUNT'].OBR;
						$debug_str .= _('SQL requests count').': '.($requests_cnt - $sqlmark[$type]).OBR;
					}

					$sql_time = 0;
					for ($i = $sqlmark[$type]; $i < $requests_cnt; $i++) {
						$time = $sqlrequests[$i][0];
						$sqlrequests[$i][1] = str_replace(array('<', '>'), array('&lt;', '&gt;'), $sqlrequests[$i][1]);

						$sql_time += $time;
						$query = '<span style="color: green; font-size: 1.2em;">'.$sqlrequests[$i][1].'</span>';
						if ($time < LONG_QUERY) {
							$debug_str .= _('Time').':'.round($time, 8).'<br />SQL:&nbsp;'.$query.OBR;
						}
						else {
							$debug_str .= '<b>'._('Time').':'.round($time, 8).' LONG SQL:&nbsp;'.$query.'</b>'.OBR;
						}

						$callStack = array_reverse($sqlrequests[$i][2]);
						$callStackStr = '<i>';
						foreach ($callStack as $call) {
							if (isset($call['class'])) {
								$callStackStr .= $call['class'].$call['type'];
							}
							$callStackStr .= $call['function'].'() -> ';
						}
						$debug_str .= rtrim($callStackStr, '-> ').'</i>'.OBR.OBR;
					}
				}
				else {
					$debug_str .= _('SQL requests count').': '.($sqlrequests - $sqlmark[$type]).OBR;
				}

				if ($sql_time < QUERY_TOTAL_TIME) {
					$debug_str .= _('Total time spent on SQL').': '.round($sql_time, 8).OBR;
				}
				else {
					$debug_str .= '<b>'._('Total time spent on SQL').': '.round($sql_time, 8).'</b>'.OBR;
				}
			}
			$debug_str .= '******************** '._('End of').' '.$type.'***************************'.OBR;

			self::$debug_info[$type] = $debug_str;
		}

		public static function show() {
			$debug = new CDiv(null, 'textcolorstyles');
			$debug->setAttribute('name', 'zbx_gebug_info');
			$debug->setAttribute('style', 'display: none; overflow: auto; width: 95%; border: 1px #777777 solid; margin: 4px; padding: 4px;');

			if (self::$memory_limit_reached) {
				$debug->addItem(array(
					BR(),
					_('MEMORY LIMIT REACHED! Profiling was stopped to save memory for script processing.'),
					BR()
				));
			}
			foreach (self::$debug_info as $type => $info) {
				$debug->addItem(array(BR(), new CJSscript($info), BR()));
			}

			$debug->show();
		}

		public static function set_memory_limit($limit = '256M') {
			ini_set('memory_limit', $limit);
		}

		public static function compare_files_with_menu($menu = null) {
			if (defined('USE_MENU_PROF')) {
				$files_list = glob('*.php');

				$result = array();
				foreach ($files_list as $file) {
					$list = array();
					foreach ($menu as $label => $sub) {
						foreach ($sub['pages'] as $sub_pages) {
							if (empty($sub_pages)) {
								continue;
							}

							if (!isset($sub_pages['label'])) {
								$sub_pages['label'] = $sub_pages['url'];
							}

							$menu_path = $sub['label'].'->'.$sub_pages['label'];

							if ($sub_pages['url'] == $file) {
								array_push($list, $menu_path);
							}
							if (!str_in_array($sub_pages['url'], $files_list)) {
								$result['error'][$sub_pages['url']] = array($menu_path);
							}

							if (isset($sub_pages['sub_pages'])) {
								foreach ($sub_pages['sub_pages'] as $page) {
									$menu_path = $sub['label'].'->'.$sub_pages['label'].'->sub_pages';

									if (!str_in_array($page, $files_list)) {
										$result['error'][$page] = array($menu_path);
									}

									if ($page != $file) {
										continue;
									}
									array_push($list, $menu_path);
								}
							}
						}
					}

					if (count($list) != 1) {
						$level = 'worning';
					}
					else {
						$level = 'normal';
					}

					$result[$level][$file] = $list;
				}

				foreach ($result as $level => $files_list) {
					if (defined('USE_MENU_DETAILS')) {
						echo OBR.'('._('menu check').') ['.$level.OBR;

						foreach ($files_list as $file => $menu_list) {
							echo '('._('menu check').')'.SPACE.SPACE.SPACE.SPACE.$file.' {'.implode(',', $menu_list).'}'.OBR;
						}
					}
					else {
						echo OBR.'('._('menu check').') ['.$level.'] = '.count($files_list).OBR;
					}
				}
			}
		}
	}
	COpt::profiling_start('script');
}
else {
	class COpt {
		static function profiling_start($type = null) {}
		static function profiling_stop($type = null) {}
		static function show() {}
		static function savesqlrequest($sql) {}
		static function showmemoryusage($descr = null) {}
		static function compare_files_with_menu($menu = null) {}
		static function counter_up($type = null) {}
		public static function saveapicall($method, $params) {}
	}
}
?>
