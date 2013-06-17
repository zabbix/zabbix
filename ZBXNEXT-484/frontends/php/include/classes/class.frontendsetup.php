<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

	const MIN_PHP_VERSION = '5.1.6';
	const MIN_PHP_MEMORY_LIMIT = 134217728; // 128*1024*1024
	const MIN_PHP_POST_MAX_SIZE = 16777216; // 16*1024*1024
	const MIN_PHP_UPLOAD_MAX_FILESIZE = 2097152; // 2*1024*1024
	const MIN_PHP_MAX_EXECUTION_TIME = 300;
	const MIN_PHP_MAX_INPUT_TIME = 300;
	const MIN_PHP_GD_VERSION = '2.0';
	const MIN_PHP_LIBXML_VERSION = '2.6.15';

	private static $_instance = null;

	/**
	 * Function for getting class object, implements Singleton.
	 *
	 * @static
	 * @return object
	 */
	public static function i() {
		if (null === self::$_instance) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	private function __construct() {}

	private function __clone() {}

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
		$result = array(
			'name' => _('PHP version'),
			'current' => phpversion(),
			'required' => self::MIN_PHP_VERSION,
			'result' => version_compare(phpversion(), self::MIN_PHP_VERSION, '>='),
			'error' => _s('Minimum required PHP version is %s', self::MIN_PHP_VERSION)
		);

		return $result;
	}

	/**
	 * Checks for minimum PHP memory limit.
	 *
	 * @return array
	 */
	public function checkPhpMemoryLimit() {
		$current = ini_get('memory_limit');

		$result = array(
			'name' => _('PHP option memory_limit'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_MEMORY_LIMIT),
			'result' => str2mem($current) >= self::MIN_PHP_MEMORY_LIMIT,
			'error' => _s('Minimum required PHP memory limit is %s (configuration option "memory_limit")', mem2str(self::MIN_PHP_MEMORY_LIMIT))
		);

		return $result;
	}

	/**
	 * Checks for minimum PHP post max size.
	 *
	 * @return array
	 */
	public function checkPhpPostMaxSize() {
		$current = ini_get('post_max_size');

		$result = array(
			'name' => _('PHP option post_max_size'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_POST_MAX_SIZE),
			'result' => str2mem($current) >= self::MIN_PHP_POST_MAX_SIZE,
			'error' => _s('Minimum required size of PHP post is %s (configuration option "post_max_size")', mem2str(self::MIN_PHP_POST_MAX_SIZE))
		);

		return $result;
	}

	/**
	 * Checks for minimum PHP upload max filesize.
	 *
	 * @return array
	 */
	public function checkPhpUploadMaxFilesize() {
		$current = ini_get('upload_max_filesize');

		$result = array(
			'name' => _('PHP option upload_max_filesize'),
			'current' => $current,
			'required' => mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE),
			'result' => str2mem($current) >= self::MIN_PHP_UPLOAD_MAX_FILESIZE,
			'error' => _s('Minimum required PHP upload filesize is %s (configuration option "upload_max_filesize")', mem2str(self::MIN_PHP_UPLOAD_MAX_FILESIZE))
		);

		return $result;
	}

	/**
	 * Checks for minimum PHP max execution time.
	 *
	 * @return array
	 */
	public function checkPhpMaxExecutionTime() {
		$current = ini_get('max_execution_time');

		$result = array(
			'name' => _('PHP option max_execution_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_EXECUTION_TIME,
			'result' => $current >= self::MIN_PHP_MAX_EXECUTION_TIME,
			'error' => _s('Minimum required limit on execution time of PHP scripts is %s (configuration option "max_execution_time")', self::MIN_PHP_MAX_EXECUTION_TIME)
		);

		return $result;
	}

	/**
	 * Checks for minimum PHP max input time.
	 *
	 * @return array
	 */
	public function checkPhpMaxInputTime() {
		$current = ini_get('max_input_time');

		$result = array(
			'name' => _('PHP option max_input_time'),
			'current' => $current,
			'required' => self::MIN_PHP_MAX_INPUT_TIME,
			'result' => $current >= self::MIN_PHP_MAX_INPUT_TIME,
			'error' => _s('Minimum required limit on input parse time for PHP scripts is %s (configuration option "max_input_time")', self::MIN_PHP_MAX_INPUT_TIME)
		);

		return $result;
	}

	/**
	 * Checks for PHP timezone.
	 *
	 * @return array
	 */
	public function checkPhpTimeZone() {
		$current = ini_get('date.timezone');

		$result = array(
			'name' => _('PHP time zone'),
			'current' => $current ? $current : _('unknown'),
			'required' => null,
			'result' => !empty($current),
			'error' => _('Time zone for PHP is not set (configuration parameter "date.timezone")')
		);

		return $result;
	}

	/**
	 * Checks for databases support.
	 *
	 * @return array
	 */
	public function checkPhpDatabases() {
		$current = array();

		if (function_exists('mysql_pconnect') &&
				function_exists('mysql_select_db') &&
				function_exists('mysql_error') &&
				function_exists('mysql_query') &&
				function_exists('mysql_fetch_array') &&
				function_exists('mysql_fetch_row') &&
				function_exists('mysql_data_seek') &&
				function_exists('mysql_insert_id')
		) {
			$current[] = 'MySQL';
			$current[] = BR();
		}

		if (function_exists('pg_pconnect') &&
				function_exists('pg_fetch_array') &&
				function_exists('pg_fetch_row') &&
				function_exists('pg_exec') &&
				function_exists('pg_getlastoid')
		) {
			$current[] = 'PostgreSQL';
			$current[] = BR();
		}

		if (function_exists('oci_connect') &&
				function_exists('oci_error') &&
				function_exists('oci_parse') &&
				function_exists('oci_execute') &&
				function_exists('oci_fetch_assoc') &&
				function_exists('oci_commit') &&
				function_exists('oci_close') &&
				function_exists('oci_rollback') &&
				function_exists('oci_field_type') &&
				function_exists('oci_new_descriptor') &&
				function_exists('oci_bind_by_name') &&
				function_exists('oci_free_statement')
		) {

			$current[] = 'Oracle';
			$current[] = BR();
		}

		if (function_exists('db2_connect') &&
				function_exists('db2_set_option') &&
				function_exists('db2_commit') &&
				function_exists('db2_rollback') &&
				function_exists('db2_autocommit') &&
				function_exists('db2_prepare') &&
				function_exists('db2_execute') &&
				function_exists('db2_stmt_errormsg') &&
				function_exists('db2_fetch_assoc') &&
				function_exists('db2_free_result') &&
				function_exists('db2_escape_string') &&
				function_exists('db2_close')
		) {
			$current[] = 'IBM DB2';
			$current[] = BR();
		}

		// Semaphore related functions are checked elsewhere. The 'false' is to prevent autoloading of the SQLite3 class.
		if (class_exists('SQLite3', false)) {
			$current[] = 'SQLite3';
			$current[] = BR();
		}

		$result = array(
			'name' => _('PHP databases support'),
			'current' => empty($current) ? _('no') : new CSpan($current),
			'required' => null,
			'result' => !empty($current),
			'error' => _('At least one of MySQL, PostgreSQL, Oracle, SQLite3 or IBM DB2 should be supported')
		);

		return $result;
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

		$result = array(
			'name' => _('PHP bcmath'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP bcmath extension missing (PHP configuration parameter --enable-bcmath)')
		);

		return $result;
	}

	/**
	 * Checks for PHP mbstring extension.
	 *
	 * @return array
	 */
	public function checkPhpMbstring() {
		$current = mbstrings_available();

		$result = array(
			'name' => _('PHP mbstring'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP mbstring extension missing (PHP configuration parameter --enable-mbstring)')
		);

		return $result;
	}

	/**
	 * Checks for PHP sockets extension.
	 *
	 * @return array
	 */
	public function checkPhpSockets() {
		$current = function_exists('socket_create');

		$result = array(
			'name' => _('PHP sockets'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP sockets extension missing (PHP configuration parameter --enable-sockets)')
		);

		return $result;
	}

	/**
	 * Checks for PHP GD extension.
	 *
	 * @return array
	 */
	public function checkPhpGd() {
		if (is_callable('gd_info')) {
			$gd_info = gd_info();
			preg_match('/(\d\.?)+/', $gd_info['GD Version'], $current);
			$current = $current[0];
		}
		else {
			$current = _('unknown');
		}

		$result = array(
			'name' => _('PHP gd'),
			'current' => $current,
			'required' => self::MIN_PHP_GD_VERSION,
			'result' => version_compare($current, self::MIN_PHP_GD_VERSION, '>='),
			'error' => _('PHP gd extension missing (PHP configuration parameter --with-gd)')
		);

		return $result;
	}

	/**
	 * Checks for PHP GD PNG support.
	 *
	 * @return array
	 */
	public function checkPhpGdPng() {
		if (is_callable('gd_info')) {
			$gd_info = gd_info();
			$current = $gd_info['PNG Support'];
		}
		else {
			$current = false;
		}

		$result = array(
			'name' => _('PHP gd PNG support'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP gd PNG image support missing')
		);

		return $result;
	}

	/**
	 * Checks for PHP GD JPEG support.
	 *
	 * @return array
	 */
	public function checkPhpGdJpeg() {
		if (is_callable('gd_info')) {
			$gd_info = gd_info();
			// Check for PHP prior 5.3.0, it returns 'JPG Support' key.
			$current = isset($gd_info['JPG Support']) ? $gd_info['JPG Support'] : $gd_info['JPEG Support'];
		}
		else {
			$current = false;
		}

		$result = array(
			'name' => _('PHP gd JPEG support'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP gd JPEG image support missing')
		);

		return $result;
	}

	/**
	 * Checks for PHP GD FreeType support.
	 *
	 * @return array
	 */
	public function checkPhpGdFreeType() {
		if (is_callable('gd_info')) {
			$gd_info = gd_info();
			$current = $gd_info['FreeType Support'];
		}
		else {
			$current = false;
		}

		$result = array(
			'name' => _('PHP gd FreeType support'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP gd FreeType support missing')
		);

		return $result;
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

		$result = array(
			'name' => _('PHP libxml'),
			'current' => $current,
			'required' => self::MIN_PHP_LIBXML_VERSION,
			'result' => version_compare($current, self::MIN_PHP_LIBXML_VERSION, '>='),
			'error' => _('PHP libxml extension missing')
		);

		return $result;
	}

	/**
	 * Checks for PHP xmlwriter extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlWriter() {
		$current = extension_loaded('xmlwriter');

		$result = array(
			'name' => _('PHP xmlwriter'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP xmlwriter extension missing')
		);

		return $result;
	}

	/**
	 * Checks for PHP xmlreader extension.
	 *
	 * @return array
	 */
	public function checkPhpXmlReader() {
		$current = extension_loaded('xmlreader');

		$result = array(
			'name' => _('PHP xmlreader'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP xmlreader extension missing')
		);

		return $result;
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

		$result = array(
			'name' => _('PHP ctype'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP ctype extension missing (PHP configuration parameter --enable-ctype)')
		);

		return $result;
	}

	/**
	 * Checks for PHP session extension.
	 *
	 * @return array
	 */
	public function checkPhpSession() {
		$current = function_exists('session_start') && function_exists('session_write_close');

		$result = array(
			'name' => _('PHP session'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP session extension missing (PHP configuration parameter --enable-session)')
		);

		return $result;
	}

	/**
	 * Checks for PHP session auto start.
	 *
	 * @return array
	 */
	public function checkPhpSessionAutoStart() {
		$current = !ini_get('session.auto_start');

		$result = array(
			'name' => _('PHP session auto start'),
			'current' => $current ? _('no') : _('yes'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP session auto start must be disabled (PHP directive "session.auto_start")')
		);

		return $result;
	}

	/**
	 * Checks for PHP gettext extension.
	 *
	 * @return array
	 */
	public function checkPhpGettext() {
		$current = function_exists('bindtextdomain');

		$result = array(
			'name' => _('PHP gettext'),
			'current' => $current ? _('yes') : _('no'),
			'required' => null,
			'result' => $current,
			'error' => _('PHP gettext extension missing (PHP configuration parameter --with-gettext)')
		);

		return $result;
	}
}
