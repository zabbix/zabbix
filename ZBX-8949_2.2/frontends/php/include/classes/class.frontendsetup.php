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


/**
 * Class to operate with frontend setup information.
 * Currently only setup requirements are checked.
 */
class FrontendSetup {

	const MIN_PHP_VERSION = '5.3.0';
	const MIN_PHP_MEMORY_LIMIT = 134217728; // 128*1024*1024
	const MIN_PHP_POST_MAX_SIZE = 16777216; // 16*1024*1024
	const MIN_PHP_UPLOAD_MAX_FILESIZE = 2097152; // 2*1024*1024
	const MIN_PHP_MAX_EXECUTION_TIME = 300;
	const MIN_PHP_MAX_INPUT_TIME = 300;
	const MIN_PHP_GD_VERSION = '2.0';
	const MIN_PHP_LIBXML_VERSION = '2.6.15';

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
		$result = array();

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

		return $result;
	}

	/**
	 * Checks for minimum required PHP version.
	 *
	 * @return array
	 */
	public function checkPhpVersion() {
		$check = version_compare(phpversion(), self::MIN_PHP_VERSION, '>=');

		return array(
			'name' => _('PHP version'),
			'current' => phpversion(),
			'required' => self::MIN_PHP_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP version is %s.', self::MIN_PHP_VERSION)
		);
	}

	/**
	 * Checks for minimum PHP memory limit.
	 *
	 * @return array
	 */
	public function checkPhpMemoryLimit() {
		$current = ini_get('memory_limit');
		$check = ($current == '-1' || str2mem($current) >= self::MIN_PHP_MEMORY_LIMIT);

		return array(
			'name' => _('PHP option memory_limit'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_MEMORY_LIMIT),
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP memory limit is %s (configuration option "memory_limit").', mem2str(self::MIN_PHP_MEMORY_LIMIT))
		);
	}

	/**
	 * Checks for minimum PHP post max size.
	 *
	 * @return array
	 */
	public function checkPhpPostMaxSize() {
		$current = ini_get('post_max_size');

		return array(
			'name' => _('PHP option post_max_size'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_POST_MAX_SIZE),
			'result' => (str2mem($current) >= self::MIN_PHP_POST_MAX_SIZE) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required size of PHP post is %s (configuration option "post_max_size").', mem2str(self::MIN_PHP_POST_MAX_SIZE))
		);
	}

	/**
	 * Checks for minimum PHP upload max filesize.
	 *
	 * @return array
	 */
	public function checkPhpUploadMaxFilesize() {
		$current = ini_get('upload_max_filesize');

		return array(
			'name' => _('PHP option upload_max_filesize'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE),
			'result' => (str2mem($current) >= self::MIN_PHP_UPLOAD_MAX_FILESIZE) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required PHP upload filesize is %s (configuration option "upload_max_filesize").', mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE))
		);
	}

	/**
	 * Checks for minimum PHP max execution time.
	 *
	 * @return array
	 */
	public function checkPhpMaxExecutionTime() {
		$current = ini_get('max_execution_time');

		return array(
			'name' => _('PHP option max_execution_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_EXECUTION_TIME,
			'result' => ($current >= self::MIN_PHP_MAX_EXECUTION_TIME) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required limit on execution time of PHP scripts is %s (configuration option "max_execution_time").', self::MIN_PHP_MAX_EXECUTION_TIME)
		);
	}

	/**
	 * Checks for minimum PHP max input time.
	 *
	 * @return array
	 */
	public function checkPhpMaxInputTime() {
		$current = ini_get('max_input_time');

		return array(
			'name' => _('PHP option max_input_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_INPUT_TIME,
			'result' => ($current >= self::MIN_PHP_MAX_INPUT_TIME) ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _s('Minimum required limit on input parse time for PHP scripts is %s (configuration option "max_input_time").', self::MIN_PHP_MAX_INPUT_TIME)
		);
	}

	/**
	 * Checks for PHP timezone.
	 *
	 * @return array
	 */
	public function checkPhpTimeZone() {
		$current = ini_get('date.timezone');

		return array(
			'name' => _('PHP time zone'),
			'current' => $current ? $current : _('unknown'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('Time zone for PHP is not set (configuration parameter "date.timezone").')
		);
	}

	/**
	 * Checks for databases support.
	 *
	 * @return array
	 */
	public function checkPhpDatabases() {
		$current = array();

		$databases = $this->getSupportedDatabases();
		foreach ($databases as $database => $name) {
			$current[] = $name;
			$current[] = BR();
		}

		return array(
			'name' => _('PHP databases support'),
			'current' => empty($current) ? _('off') : new CSpan($current),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('At least one of MySQL, PostgreSQL, Oracle, SQLite3 or IBM DB2 should be supported.')
		);
	}

	/**
	 * Get list of supported databases.
	 *
	 * @return array
	 */
	public function getSupportedDatabases() {
		$allowedDb = array();
		if (zbx_is_callable(array('mysqli_connect', 'mysqli_connect_error', 'mysqli_error', 'mysqli_query',
				'mysqli_fetch_assoc', 'mysqli_free_result', 'mysqli_real_escape_string', 'mysqli_close'))) {
			$allowedDb[ZBX_DB_MYSQL] = 'MySQL';
		}

		if (zbx_is_callable(array('pg_pconnect', 'pg_fetch_array', 'pg_fetch_row', 'pg_exec', 'pg_getlastoid'))) {
			$allowedDb[ZBX_DB_POSTGRESQL] = 'PostgreSQL';
		}

		if (zbx_is_callable(array('oci_connect', 'oci_error', 'oci_parse', 'oci_execute', 'oci_fetch_assoc',
				'oci_commit', 'oci_close', 'oci_rollback', 'oci_field_type', 'oci_new_descriptor',
				'oci_bind_by_name', 'oci_free_statement'))) {
			$allowedDb[ZBX_DB_ORACLE] = 'Oracle';
		}

		if (zbx_is_callable(array('db2_connect', 'db2_set_option', 'db2_commit', 'db2_rollback', 'db2_autocommit',
				'db2_prepare', 'db2_execute', 'db2_stmt_errormsg', 'db2_fetch_assoc', 'db2_free_result',
				'db2_escape_string', 'db2_close'))) {
			$allowedDb[ZBX_DB_DB2] = 'IBM DB2';
		}

		// Semaphore related functions are checked elsewhere. The 'false' is to prevent autoloading of the SQLite3 class.
		if (class_exists('SQLite3', false) && zbx_is_callable(array('ftok', 'sem_acquire', 'sem_release', 'sem_get'))) {
			$allowedDb[ZBX_DB_SQLITE3] = 'SQLite3';
		}

		return $allowedDb;
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

		return array(
			'name' => _('PHP bcmath'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP bcmath extension missing (PHP configuration parameter --enable-bcmath).')
		);
	}

	/**
	 * Checks for PHP mbstring extension.
	 *
	 * @return array
	 */
	public function checkPhpMbstring() {
		$current = extension_loaded('mbstring');

		return array(
			'name' => _('PHP mbstring'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP mbstring extension missing (PHP configuration parameter --enable-mbstring).')
		);
	}

	/**
	 * Checks for PHP sockets extension.
	 *
	 * @return array
	 */
	public function checkPhpSockets() {
		$current = function_exists('socket_create');

		return array(
			'name' => _('PHP sockets'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP sockets extension missing (PHP configuration parameter --enable-sockets).')
		);
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

		return array(
			'name' => _('PHP gd'),
			'current' => $current,
			'required' => self::MIN_PHP_GD_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd extension missing (PHP configuration parameter --with-gd).')
		);
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

		return array(
			'name' => _('PHP gd PNG support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd PNG image support missing.')
		);
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

		return array(
			'name' => _('PHP gd JPEG support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd JPEG image support missing.')
		);
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

		return array(
			'name' => _('PHP gd FreeType support'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP gd FreeType support missing.')
		);
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

		return array(
			'name' => _('PHP libxml'),
			'current' => $current,
			'required' => self::MIN_PHP_LIBXML_VERSION,
			'result' => $check ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP libxml extension missing.')
		);
	}

	/**
	 * Checks for PHP xmlwriter extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlWriter() {
		$current = extension_loaded('xmlwriter');

		return array(
			'name' => _('PHP xmlwriter'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP xmlwriter extension missing.')
		);
	}

	/**
	 * Checks for PHP xmlreader extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlReader() {
		$current = extension_loaded('xmlreader');

		return array(
			'name' => _('PHP xmlreader'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP xmlreader extension missing.')
		);
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

		return array(
			'name' => _('PHP ctype'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP ctype extension missing (PHP configuration parameter --enable-ctype).')
		);
	}

	/**
	 * Checks for PHP session extension.
	 *
	 * @return array
	 */
	public function checkPhpSession() {
		$current = (function_exists('session_start') && function_exists('session_write_close'));

		return array(
			'name' => _('PHP session'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP session extension missing (PHP configuration parameter --enable-session).')
		);
	}

	/**
	 * Checks for PHP session auto start.
	 *
	 * @return array
	 */
	public function checkPhpSessionAutoStart() {
		$current = !ini_get('session.auto_start');

		return array(
			'name' => _('PHP session auto start'),
			'current' => $current ? _('off') : _('on'),
			'required' => _('off'),
			'result' => $current ? self::CHECK_OK : self::CHECK_FATAL,
			'error' => _('PHP session auto start must be disabled (PHP directive "session.auto_start").')
		);
	}

	/**
	 * Checks for PHP gettext extension.
	 *
	 * @return array
	 */
	public function checkPhpGettext() {
		$current = function_exists('bindtextdomain');

		return array(
			'name' => _('PHP gettext'),
			'current' => $current ? _('on') : _('off'),
			'required' => null,
			'result' => $current ? self::CHECK_OK : self::CHECK_WARNING,
			'error' => _('PHP gettext extension missing (PHP configuration parameter --with-gettext). Translations will not be available.')
		);
	}
}
