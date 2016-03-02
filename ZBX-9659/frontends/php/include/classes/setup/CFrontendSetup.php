<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


/**
 * Class to operate with frontend setup information.
 * Currently only setup requirements are checked.
 */
class CFrontendSetup {

	const MIN_PHP_VERSION = '5.4.0';
	const MIN_PHP_MEMORY_LIMIT = 134217728; // 128*1024*1024
	const MIN_PHP_POST_MAX_SIZE = 16777216; // 16*1024*1024
	const MIN_PHP_UPLOAD_MAX_FILESIZE = 2097152; // 2*1024*1024
	const MIN_PHP_MAX_EXECUTION_TIME = 300;
	const MIN_PHP_MAX_INPUT_TIME = 300;
	const MIN_PHP_GD_VERSION = '2.0';
	const MIN_PHP_LIBXML_VERSION = '2.6.15';
	const REQUIRED_PHP_ARG_SEPARATOR_OUTPUT = '&';

	/**
	 * Check OK, setup can continue.
	 */
	const CHECK_OK = 1;

	/**
	 * Check failed, but setup can still continue. A warning will be displayed.
	 */
	const CHECK_WARNING = 2;

	/**
	 * Check failed, setup cannot continue. An error will be displayed.
	 */
	const CHECK_FATAL = 3;

	/**
	 * Perform all requirements checks.
	 *
	 * @return array
	 */
	public function checkRequirements() {
		$result = [];

		$result[] = $this->checkPhpVersion();
		$result[] = $this->checkPhpMemoryLimit();
		$result[] = $this->checkPhpPostMaxSize();
		$result[] = $this->checkPhpUploadMaxFilesize();
		$result[] = $this->checkPhpMaxExecutionTime();
		$result[] = $this->checkPhpMaxInputTime();
		$result[] = $this->checkPhpTimeZone();
		$result[] = $this->checkPhpDatabases();
		$result[] = $this->checkPhpBcmath();
		$result[] = $this->checkPhpMbstring();
		if (extension_loaded('mbstring')) {
			$result[] = $this->checkPhpMbstringFuncOverload();
		}

		// check for deprecated PHP 5.6.0 option 'always_populate_raw_post_data'
		if (version_compare(PHP_VERSION, '5.6', '>=') && version_compare(PHP_VERSION, '7.0', '<')) {
			$result[] = $this->checkPhpAlwaysPopulateRawPostData();
		}
		$result[] = $this->checkPhpSockets();
		$result[] = $this->checkPhpGd();
		$result[] = $this->checkPhpGdPng();
		$result[] = $this->checkPhpGdJpeg();
		$result[] = $this->checkPhpGdFreeType();
		$result[] = $this->checkPhpLibxml();
		$result[] = $this->checkPhpXmlWriter();
		$result[] = $this->checkPhpXmlReader();
		$result[] = $this->checkPhpCtype();
		$result[] = $this->checkPhpSession();
		$result[] = $this->checkPhpSessionAutoStart();
		$result[] = $this->checkPhpGettext();
		$result[] = $this->checkPhpArgSeparatorOutput();

		return $result;
	}

	/**
	 * Checks for minimum required PHP version.
	 *
	 * @return array
	 */
	public function checkPhpVersion() {
		$check = version_compare(phpversion(), self::MIN_PHP_VERSION, '>=');

		return [
			'name' => _('PHP version'),
			'current' => phpversion(),
			'required' => self::MIN_PHP_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP version is %s.', self::MIN_PHP_VERSION)
		];
	}

	/**
	 * Checks for minimum PHP memory limit.
	 *
	 * @return array
	 */
	public function checkPhpMemoryLimit() {
		$current = ini_get('memory_limit');
		$check = ($current == '-1' || str2mem($current) >= self::MIN_PHP_MEMORY_LIMIT);

		return [
			'name' => _s('PHP option "%1$s"', 'memory_limit'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_MEMORY_LIMIT),
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP memory limit is %s (configuration option "memory_limit").', mem2str(self::MIN_PHP_MEMORY_LIMIT))
		];
	}

	/**
	 * Checks for minimum PHP post max size.
	 *
	 * @return array
	 */
	public function checkPhpPostMaxSize() {
		$current = ini_get('post_max_size');

		return [
			'name' => _s('PHP option "%1$s"', 'post_max_size'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_POST_MAX_SIZE),
			'result' => (str2mem($current) >= self::MIN_PHP_POST_MAX_SIZE) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required size of PHP post is %s (configuration option "post_max_size").', mem2str(self::MIN_PHP_POST_MAX_SIZE))
		];
	}

	/**
	 * Checks for minimum PHP upload max filesize.
	 *
	 * @return array
	 */
	public function checkPhpUploadMaxFilesize() {
		$current = ini_get('upload_max_filesize');

		return [
			'name' => _s('PHP option "%1$s"', 'upload_max_filesize'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE),
			'result' => (str2mem($current) >= self::MIN_PHP_UPLOAD_MAX_FILESIZE) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP upload filesize is %s (configuration option "upload_max_filesize").', mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE))
		];
	}

	/**
	 * Checks for minimum PHP max execution time.
	 *
	 * Value of "max_execution_time" is used to set up OS level timer that fires signal after specified number of
	 * seconds has passed. Handler of this signal interrupts execution. On *nix platforms this is done with call
	 * to "setitimer()" (http://linux.die.net/man/2/setitimer) and in this case integer value of "max_input_time"
	 * is used to specify how many seconds timer has to wait before sending signal:
	 * - Value "0" is valid because in this case timer is removed and will not fire and stop execution.
	 * - Value "-1" is valid because this signed integer and is used as value for "it_value.tv_sec" for "new_value"
	 *   argument in call to "setitimer()". As negative values for "tv_sec" are not allowed the call will fail. Errors
	 *   are not checked and timer is not set.
	 * - Any value bigger than "0" is valid and sets up timer to fire after specified number of seconds.
	 *
	 * @return array
	 */
	public function checkPhpMaxExecutionTime() {
		$current = ini_get('max_execution_time');

		$currentIsValid = ($current === '0' || $current === '-1' || $current >= self::MIN_PHP_MAX_EXECUTION_TIME);

		return [
			'name' => _s('PHP option "%1$s"', 'max_execution_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_EXECUTION_TIME,
			'result' => $currentIsValid ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required limit on execution time of PHP scripts is %s (configuration option "max_execution_time").', self::MIN_PHP_MAX_EXECUTION_TIME)
		];
	}

	/**
	 * Checks for minimum PHP max input time.
	 *
	 * @see checkPhpMaxExecutionTime()
	 *
	 * @return array
	 */
	public function checkPhpMaxInputTime() {
		$current = ini_get('max_input_time');

		$currentIsValid = ($current === '0' || $current === '-1' || $current >= self::MIN_PHP_MAX_INPUT_TIME);

		return [
			'name' => _s('PHP option "%1$s"', 'max_input_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_INPUT_TIME,
			'result' => $currentIsValid ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required limit on input parse time for PHP scripts is %s (configuration option "max_input_time").', self::MIN_PHP_MAX_INPUT_TIME)
		];
	}

	/**
	 * Checks for PHP timezone.
	 *
	 * @return array
	 */
	public function checkPhpTimeZone() {
		$current = ini_get('date.timezone');

		return [
			'name' => _s('PHP option "%1$s"', 'date.timezone'),
			'current' => $current ? $current : _('unknown'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('Time zone for PHP is not set (configuration parameter "date.timezone").')
		];
	}

	/**
	 * Checks for databases support.
	 *
	 * @return array
	 */
	public function checkPhpDatabases() {
		$current = [];

		$databases = self::getSupportedDatabases();
		foreach ($databases as $database => $name) {
			$current[] = $name;
			$current[] = BR();
		}

		return [
			'name' => _('PHP databases support'),
			'current' => empty($current) ? _('off') : new CSpan($current),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('At least one of MySQL, PostgreSQL, Oracle, SQLite3 or IBM DB2 should be supported.')
		];
	}

	/**
	 * Get list of supported databases.
	 *
	 * @return array
	 */
	public static function getSupportedDatabases() {
		$allowed_db = [];

		if (zbx_is_callable(['mysqli_close', 'mysqli_connect', 'mysqli_connect_error', 'mysqli_error',
				'mysqli_fetch_assoc', 'mysqli_free_result', 'mysqli_query', 'mysqli_real_escape_string'])) {
			$allowed_db[ZBX_DB_MYSQL] = 'MySQL';
		}

		if (zbx_is_callable(['pg_close', 'pg_connect', 'pg_escape_bytea', 'pg_escape_string', 'pg_fetch_assoc',
				'pg_free_result', 'pg_last_error', 'pg_parameter_status', 'pg_query', 'pg_unescape_bytea'])) {
			$allowed_db[ZBX_DB_POSTGRESQL] = 'PostgreSQL';
		}

		if (zbx_is_callable(['oci_bind_by_name', 'oci_close', 'oci_commit', 'oci_connect', 'oci_error', 'oci_execute',
				'oci_fetch_assoc', 'oci_field_type', 'oci_free_statement', 'oci_new_descriptor', 'oci_parse',
				'oci_rollback'])) {
			$allowed_db[ZBX_DB_ORACLE] = 'Oracle';
		}

		if (zbx_is_callable(['db2_autocommit', 'db2_bind_param', 'db2_close', 'db2_commit', 'db2_conn_errormsg',
				'db2_connect', 'db2_escape_string', 'db2_execute', 'db2_fetch_assoc', 'db2_free_result', 'db2_prepare',
				'db2_rollback', 'db2_set_option', 'db2_stmt_errormsg'])) {
			$allowed_db[ZBX_DB_DB2] = 'IBM DB2';
		}

		// Semaphore related functions are checked elsewhere. The 'false' is to prevent autoloading of the SQLite3 class.
		if (class_exists('SQLite3', false) && zbx_is_callable(['ftok', 'sem_acquire', 'sem_get', 'sem_release'])) {
			$allowed_db[ZBX_DB_SQLITE3] = 'SQLite3';
		}

		return $allowed_db;
	}

	/**
	 * Checks for PHP bcmath extension.
	 *
	 * @return array
	 */
	public function checkPhpBcmath() {
		$current = function_exists('bcadd') &&
				function_exists('bccomp') &&
				function_exists('bcdiv') &&
				function_exists('bcmod') &&
				function_exists('bcmul') &&
				function_exists('bcpow') &&
				function_exists('bcpowmod') &&
				function_exists('bcscale') &&
				function_exists('bcsqrt') &&
				function_exists('bcsub');

		return [
			'name' => _('PHP bcmath'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP bcmath extension missing (PHP configuration parameter --enable-bcmath).')
		];
	}

	/**
	 * Checks for PHP mbstring extension.
	 *
	 * @return array
	 */
	public function checkPhpMbstring() {
		$current = extension_loaded('mbstring');

		return [
			'name' => _('PHP mbstring'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP mbstring extension missing (PHP configuration parameter --enable-mbstring).')
		];
	}

	/**
	 * Checks for PHP mbstring.func_overload value.
	 *
	 * Note: disabling mbstring functions completely, mbstring.func_overload returns false.
	 * checkPhpMbstringFuncOverload() will be called after successful checkPhpMbstring(), to avoid duplicate
	 * error messages. mbstring.func_overload value in php.ini file represents a combination of bitmasks.
	 *
	 * @return array
	 */
	public function checkPhpMbstringFuncOverload() {
		$current = ini_get('mbstring.func_overload');

		return [
			'name' => _s('PHP option "%1$s"', 'mbstring.func_overload'),
			'current' => ($current & 2) ? _('on') : _('off'),
			'required' => _('off'),
			'result' => ($current & 2) ? self::CHECK_FATAL : self::CHECK_OK,
			'error' => _('PHP string function overloading must be disabled.')
		];
	}

	/**
	 * Checks for PHP option always_populate_raw_post_data. As of PHP version 5.6.0 this option is deprecated.
	 * In case this option is not set or is enabled, PHP will throw E_DEPRECATED error. This option should be set to -1
	 * ini php.ini and cannot be set at runtime.
	 *
	 * See: http://php.net/manual/en/ini.core.php#ini.always-populate-raw-post-data
	 *
	 * @return array
	 */
	public function checkPhpAlwaysPopulateRawPostData() {
		$current = ini_get('always_populate_raw_post_data');

		return [
			'name' => _s('PHP option "%1$s"', 'always_populate_raw_post_data'),
			'current' => ($current != -1) ? _('on') : _('off'),
			'required' => _('off'),
			'result' => ($current != -1) ? self::CHECK_FATAL : self::CHECK_OK,
			'error' => _s('PHP option "%1$s" must be set to "%2$s"', 'always_populate_raw_post_data', -1)
		];
	}

	/**
	 * Checks for PHP sockets extension.
	 *
	 * @return array
	 */
	public function checkPhpSockets() {
		$current = function_exists('socket_create');

		return [
			'name' => _('PHP sockets'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP sockets extension missing (PHP configuration parameter --enable-sockets).')
		];
	}

	/**
	 * Checks for PHP GD extension.
	 *
	 * @return array
	 */
	public function checkPhpGd() {
		if (is_callable('gd_info')) {
			$gdInfo = gd_info();
			preg_match('/(\d\.?)+/', $gdInfo['GD Version'], $current);
			$current = $current[0];
		}
		else {
			$current = _('unknown');
		}
		$check = version_compare($current, self::MIN_PHP_GD_VERSION, '>=');

		return [
			'name' => _('PHP gd'),
			'current' => $current,
			'required' => self::MIN_PHP_GD_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd extension missing (PHP configuration parameter --with-gd).')
		];
	}

	/**
	 * Checks for PHP GD PNG support.
	 *
	 * @return array
	 */
	public function checkPhpGdPng() {
		if (is_callable('gd_info')) {
			$gdInfo = gd_info();
			$current = $gdInfo['PNG Support'];
		}
		else {
			$current = false;
		}

		return [
			'name' => _('PHP gd PNG support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd PNG image support missing.')
		];
	}

	/**
	 * Checks for PHP GD JPEG support.
	 *
	 * @return array
	 */
	public function checkPhpGdJpeg() {
		if (is_callable('gd_info')) {
			$gdInfo = gd_info();

			// check for PHP prior 5.3.0, it returns 'JPG Support' key.
			$current = isset($gdInfo['JPG Support']) ? $gdInfo['JPG Support'] : $gdInfo['JPEG Support'];
		}
		else {
			$current = false;
		}

		return [
			'name' => _('PHP gd JPEG support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd JPEG image support missing.')
		];
	}

	/**
	 * Checks for PHP GD FreeType support.
	 *
	 * @return array
	 */
	public function checkPhpGdFreeType() {
		if (is_callable('gd_info')) {
			$gdInfo = gd_info();
			$current = $gdInfo['FreeType Support'];
		}
		else {
			$current = false;
		}

		return [
			'name' => _('PHP gd FreeType support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd FreeType support missing.')
		];
	}

	/**
	 * Checks for PHP libxml extension.
	 *
	 * @return array
	 */
	public function checkPhpLibxml() {
		if (defined('LIBXML_DOTTED_VERSION')) {
			$current = constant('LIBXML_DOTTED_VERSION');
		}
		else {
			$current = _('unknown');
		}
		$check = version_compare($current, self::MIN_PHP_LIBXML_VERSION, '>=');

		return [
			'name' => _('PHP libxml'),
			'current' => $current,
			'required' => self::MIN_PHP_LIBXML_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP libxml extension missing.')
		];
	}

	/**
	 * Checks for PHP xmlwriter extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlWriter() {
		$current = extension_loaded('xmlwriter');

		return [
			'name' => _('PHP xmlwriter'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP xmlwriter extension missing.')
		];
	}

	/**
	 * Checks for PHP xmlreader extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlReader() {
		$current = extension_loaded('xmlreader');

		return [
			'name' => _('PHP xmlreader'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP xmlreader extension missing.')
		];
	}

	/**
	 * Checks for PHP ctype extension.
	 *
	 * @return array
	 */
	public function checkPhpCtype() {
		$current = function_exists('ctype_alnum') &&
				function_exists('ctype_alpha') &&
				function_exists('ctype_cntrl') &&
				function_exists('ctype_digit') &&
				function_exists('ctype_graph') &&
				function_exists('ctype_lower') &&
				function_exists('ctype_print') &&
				function_exists('ctype_punct') &&
				function_exists('ctype_space') &&
				function_exists('ctype_xdigit') &&
				function_exists('ctype_upper');

		return [
			'name' => _('PHP ctype'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP ctype extension missing (PHP configuration parameter --enable-ctype).')
		];
	}

	/**
	 * Checks for PHP session extension.
	 *
	 * @return array
	 */
	public function checkPhpSession() {
		$current = (function_exists('session_start') && function_exists('session_write_close'));

		return [
			'name' => _('PHP session'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP session extension missing (PHP configuration parameter --enable-session).')
		];
	}

	/**
	 * Checks for PHP session auto start.
	 *
	 * @return array
	 */
	public function checkPhpSessionAutoStart() {
		$current = !ini_get('session.auto_start');

		return [
			'name' => _s('PHP option "%1$s"', 'session.auto_start'),
			'current' => $current ? _('off') : _('on'),
			'required' => _('off'),
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP session auto start must be disabled (PHP directive "session.auto_start").')
		];
	}

	/**
	 * Checks for PHP gettext extension.
	 *
	 * @return array
	 */
	public function checkPhpGettext() {
		$current = function_exists('bindtextdomain');

		return [
			'name' => _('PHP gettext'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_WARNING,
			'error' => _('PHP gettext extension missing (PHP configuration parameter --with-gettext). Translations will not be available.')
		];
	}

	/**
	 * Checks for arg_separator.output
	 *
	 * @return array
	 */
	public function checkPhpArgSeparatorOutput() {
		$current = ini_get('arg_separator.output');

		return [
			'name' => _s('PHP option "%1$s"', 'arg_separator.output'),
			'current' => htmlspecialchars($current),
			'required' => htmlspecialchars(self::REQUIRED_PHP_ARG_SEPARATOR_OUTPUT),
			'result' => ($current === self::REQUIRED_PHP_ARG_SEPARATOR_OUTPUT) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('PHP option "%1$s" must be set to "%2$s"', 'arg_separator.output',
				self::REQUIRED_PHP_ARG_SEPARATOR_OUTPUT
			)
		];
	}
}
