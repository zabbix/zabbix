<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CProfiler {

	/**
	 * Determines time for single sql query to be considered slow.
	 *
	 * @var float
	 */
	protected $slowSqlQueryTime = 0.01;

	/**
	 * Determines time for sum of all script sql query times to be considered slow.
	 *
	 * @var float
	 */
	protected $slowTotalSqlTime = 0.5;

	/**
	 * Determines time for script execution time to be considered slow.
	 *
	 * @var float
	 */
	protected $slowScriptTime = 1.0;

	/**
	 * Contains all api requests info.
	 *
	 * @var array
	 */
	protected $apiLog = array();

	/**
	 * Contains SQL queries info.
	 *
	 * @var array
	 */
	protected $sqlQueryLog = array();

	/**
	 * Total time of all performed sql queries.
	 *
	 * @var float
	 */
	protected $sqlTotalTime = 0.0;

	/**
	 * Timestamp of profiling start.
	 *
	 * @var float
	 */
	private $startTime;

	/**
	 * Timestamp of profiling stop.
	 *
	 * @var float
	 */
	private $stopTime;

	/**
	 * Instance of this class object.
	 *
	 * @var CProfiler
	 */
	private static $instance;

	/**
	 * @static
	 *
	 * @return CProfiler
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Start script profiling.
	 */
	public function start() {
		$this->startTime = microtime(true);
	}

	/**
	 * Stop script profiling.
	 */
	public function stop() {
		$this->stopTime = microtime(true);
	}

	/**
	 * Output profiling data.
	 */
	public function show() {
		global $DB;

		$debug_str = '<a name="debug"></a>';
		$debug_str .= '******************** '._('Script profiler').' ********************'.'<br>';

		$totalScriptTime = $this->stopTime - $this->startTime;
		$totalTimeStr = _s('Total time: %s', round($totalScriptTime, 6));
		if ($totalTimeStr > $this->slowScriptTime) {
			$totalTimeStr = '<b>'.$totalTimeStr.'</b>';
		}
		$debug_str .= $totalTimeStr.'<br>';

		$sqlTotalTimeStr = _s('Total SQL time: %s', $this->sqlTotalTime);
		if ($sqlTotalTimeStr > $this->slowTotalSqlTime) {
			$sqlTotalTimeStr = '<b>'.$sqlTotalTimeStr.'</b>';
		}
		$debug_str .= $sqlTotalTimeStr.'<br>';

		if (isset($DB) && isset($DB['SELECT_COUNT'])) {
			$debug_str .= _s('SQL count: %s (selects: %s | executes: %s)',
				count($this->sqlQueryLog), $DB['SELECT_COUNT'], $DB['EXECUTE_COUNT']).'<br>';
		}

		$debug_str .= _s('Peak memory usage: %s', mem2str($this->getMemoryPeak())).'<br>';
		$debug_str .= _s('Memory limit: %s', ini_get('memory_limit')).'<br>';

		$debug_str .= '<br>';

		foreach ($this->apiLog as $i => $apiCall) {
			$debug_str .= '<div style="border-bottom: 1px dotted gray; margin-bottom: 20px;">';
			list($class, $method, $params, $result, $file, $line) = $apiCall;
			// api method
			$debug_str .= '<div style="padding-bottom: 10px;">';
			$debug_str .= ($i + 1).'. <b>'.$class.'.'.$method.'</b>'.(($file !== null) ? ' ['.$file.':'.$line.']' : '');
			$debug_str .= '</div>';
			// parameters
			$debug_str .= '<table><tr><td style="width: 300px" valign="top">Parameters:';
			$debug_str .= '<pre>'.print_r(CHtml::encode($params), true).'</pre>';
			$debug_str .= '</td>';
			// result
			$debug_str .= '<td valign="top">Result:<pre>'.print_r(CHtml::encode($result), true).'</pre></td>';

			$debug_str .= '</tr></table>';
			$debug_str .= '</div>';
		}

		$debug_str .= '<br>';

		foreach ($this->sqlQueryLog as $query) {
			$time = $query[0];
			$sql = htmlspecialchars($query[1], ENT_QUOTES, 'UTF-8');

			if (strpos($sql, 'SELECT ') !== false) {
				$sqlString = '<span style="color: green; font-size: 1.2em;">'.$sql.'</span>';
			}
			else {
				$sqlString = '<span style="color: blue; font-size: 1.2em;">'.$sql.'</span>';
			}
			$sqlString = 'SQL ('.$time.'): '.$sqlString.'<br>';
			if ($time > $this->slowSqlQueryTime) {
				$sqlString = '<b>'.$sqlString.'</b>';
			}
			$debug_str .= $sqlString;

			$callStackString = '<span style="font-style: italic;">'.$this->formatCallStack($query[2]).'</span>'.'<br>'.'<br>';
			$debug_str .= rtrim($callStackString, '-> ').'</span>'.'<br>'.'<br>';
		}

		$debug = new CDiv(null, 'textcolorstyles');
		$debug->attr('name', 'zbx_debug_info');
		$debug->attr('style', 'display: none; overflow: auto; width: 95%; border: 1px #777777 solid; margin: 4px; padding: 4px;');
		$debug->addItem(array(BR(), new CJsScript($debug_str), BR()));
		$debug->show();
	}

	/**
	 * Store sql query data.
	 *
	 * @param float  $time
	 * @param string $sql
	 */
	public function profileSql($time, $sql) {
		if (!is_null(CWebUser::$data) && isset(CWebUser::$data['debug_mode'])
				&& CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_DISABLED) {
			return;
		}

		$time = round($time, 6);

		$this->sqlTotalTime += $time;
		$this->sqlQueryLog[] = array(
			$time,
			$sql,
			array_slice(debug_backtrace(), 1)
		);
	}

	/**
	 * Store api call data.
	 *
	 * @param string $class
	 * @param string $method
	 * @param array  $params
	 * @param array  $result
	 */
	public function profileApiCall($class, $method, $params, $result) {
		if (!is_null(CWebUser::$data) && isset(CWebUser::$data['debug_mode'])
				&& CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_DISABLED) {
			return;
		}

		$backtrace = debug_backtrace();

		// Use the file name and line number from the first call to the API wrapper object.
		// Due to a bug earlier versions of PHP 5.3 did not provide the file name and line number
		// of calls to magic methods.
		if (isset($backtrace[2]['file'])) {
			$file = basename($backtrace[2]['file']);
			$line = basename($backtrace[2]['line']);
		}
		else {
			$file = null;
			$line = null;
		}

		$this->apiLog[] = array(
			$class,
			$method,
			$params,
			$result,
			$file,
			$line
		);
	}

	/**
	 * Return memory used by PHP.
	 *
	 * @return int
	 */
	private function getMemoryPeak() {
		return function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : memory_get_usage(true);
	}

	/**
	 * Formats the function call stack and returns it as a string.
	 *
	 * The call stack can be obtained from Exception::getTrace() or from an API result debug stack trace. If no call
	 * stack is given, it will be taken from debug_backtrace().
	 *
	 * @param array $callStack
	 *
	 * @return string
	 */
	public function formatCallStack(array $callStack = null) {
		if (!$callStack) {
			$callStack = debug_backtrace(false);

			// never show the call to this method
			array_shift($callStack);
		}

		$callStackString = '';
		$callWithFile = array();

		$callStack = array_reverse($callStack);
		$firstCall = reset($callStack);

		foreach ($callStack as $call) {
			// do not show the call to the error handler function
			if ($call['function'] != 'zbx_err_handler') {
				if (isset($call['class'])) {
					$callStackString .= $call['class'].$call['type'];
				}

				$callStackString .= $call['function'].'() &rarr; ';
			}

			// if the error is caused by an incorrect function call - the location of that call is contained in
			// the call of that function
			// if it's caused by something else (like an undefined index) - the location of the call is contained in the
			// call to the error handler function
			// to display the location we use the last call where this information is present
			if (isset($call['file'])) {
				$callWithFile = $call;
			}
		}

		if ($callStackString) {
			$path = pathinfo($firstCall['file']);
			$callStackString = $path['basename'].':'.$firstCall['line'] . ' &rarr; '.rtrim($callStackString, '&rarr; ');
		}

		if ($callWithFile) {
			$callStackString .= ' in '.$callWithFile['file'].':'.$callWithFile['line'];
		}

		return $callStackString;
	}
}
