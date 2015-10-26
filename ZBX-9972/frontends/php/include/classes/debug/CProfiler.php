<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * Contains all api requests info.
	 *
	 * @var array
	 */
	protected $apiLog = [];

	/**
	 * Contains SQL queries info.
	 *
	 * @var array
	 */
	protected $sqlQueryLog = [];

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
	 * Root directory path
	 *
	 * @var string
	 */
	private $root_dir;

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
	private function __construct() {
		$this->root_dir = realpath(dirname(__FILE__).'/../../..');
	}

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
	 * Make profiling data.
	 *
	 * @return CPre
	 */
	public function make() {
		global $DB;

		$debug = [];
		$debug[] = (new CLink())
			->setAttribute('name', 'debug')
			->removeSid();
		$debug[] = '******************** '._('Script profiler').' ********************';
		$debug[] = BR();
		$debug[] = _s('Total time: %1$s', round($this->stopTime - $this->startTime, 6));
		$debug[] = BR();
		$debug[] = _s('Total SQL time: %1$s', $this->sqlTotalTime);
		$debug[] = BR();

		if (isset($DB) && isset($DB['SELECT_COUNT'])) {
			$debug[] = _s('SQL count: %1$s (selects: %2$s | executes: %3$s)',
				count($this->sqlQueryLog), $DB['SELECT_COUNT'], $DB['EXECUTE_COUNT']);
			$debug[] = BR();
		}

		$debug[] = _s('Peak memory usage: %1$s', mem2str($this->getMemoryPeak()));
		$debug[] = BR();
		$debug[] = _s('Memory limit: %1$s', ini_get('memory_limit'));
		$debug[] = BR();
		$debug[] = BR();

		foreach ($this->apiLog as $i => $apiCall) {
			list($class, $method, $params, $result, $file, $line) = $apiCall;

			// api method
			$debug[] = ($i + 1).'. ';
			$debug[] = bold($class.'.'.$method);
			$debug[] = ($file !== null ? ' ['.$file.':'.$line.']' : null);
			$debug[] = BR();
			$debug[] = BR();

			// parameters, result
			$debug[] = (new CTable())
				->addRow([
					[_('Parameters').':', BR(), print_r($params, true)],
					[_('Result').':', BR(), print_r($result, true)]
				]);

			$debug[] = BR();
		}

		$debug[] = BR();

		foreach ($this->sqlQueryLog as $query) {
			$time = $query[0];

			$sql = [
				'SQL ('.$time.'): ',
				(new CSpan($query[1]))
					->addClass(substr($query[1], 0, 6) === 'SELECT' ? ZBX_STYLE_GREEN : ZBX_STYLE_BLUE),
				BR()
			];

			if ($time > $this->slowSqlQueryTime) {
				$sql = bold($sql);
			}
			$debug[] = $sql;

			$debug[] = $this->formatCallStack($query[2]);
			$debug[] = BR();
			$debug[] = BR();
		}

		return (new CPre())
			->addClass(ZBX_STYLE_DEBUG_OUTPUT)
			->setAttribute('name', 'zbx_debug_info')
			->addStyle('display: none;')
			->addItem($debug);
	}

	/**
	 * Output profiling data.
	 */
	public function show() {
		return $this->make()->show();
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
		$this->sqlQueryLog[] = [
			$time,
			$sql,
			array_slice(debug_backtrace(), 1)
		];
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

		$this->apiLog[] = [
			$class,
			$method,
			$params,
			$result,
			$file,
			$line
		];
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

		$functions = [];
		$callWithFile = [];

		$callStack = array_reverse($callStack);
		$firstCall = reset($callStack);

		foreach ($callStack as $call) {
			// do not show the call to the error handler function
			if ($call['function'] != 'zbx_err_handler') {
				if (array_key_exists('class', $call)) {
					$functions[] = $call['class'].$call['type'].$call['function'].'()';
				}
				else {
					$functions[] = $call['function'].'()';
				}
			}

			// if the error is caused by an incorrect function call - the location of that call is contained in
			// the call of that function
			// if it's caused by something else (like an undefined index) - the location of the call is contained in the
			// call to the error handler function
			// to display the location we use the last call where this information is present
			if (array_key_exists('file', $call)) {
				$callWithFile = $call;
			}
		}

		$callStackString = '';

		if ($functions) {
			$callStackString .= pathinfo($firstCall['file'], PATHINFO_BASENAME).':'.$firstCall['line'].' &rarr; '.
				implode(' &rarr; ', $functions);
		}

		if ($callWithFile) {
			$file_name = $callWithFile['file'];

			if (substr_compare($file_name, $this->root_dir, 0, strlen($this->root_dir)) === 0) {
				$file_name = substr($file_name, strlen($this->root_dir) + 1);
			}
			$callStackString .= ' in '.$file_name.':'.$callWithFile['line'];
		}

		return $callStackString;
	}
}
