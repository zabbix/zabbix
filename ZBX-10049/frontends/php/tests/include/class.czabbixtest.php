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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/dbfunc.php';

class CZabbixTest extends PHPUnit_Framework_TestCase {
	public $ID = 0;

	function do_post_request($data, &$debug) {
		global $URL;

		if (is_array($data)) $data = json_encode($data);

//print("Request:\n".$data."\n");

		$debug="\n----DATA FLOW-------\nRequest:\n$data\n\n";

		$params = array(
				'http' => array(
					'method' => 'post',
					'content' => $data
				));


		$params['http']['header'] = "Content-type: application/json-rpc\r\n".
			"Content-Length: ".strlen($data)."\r\n".
			"\r\n";

		$ctx = stream_context_create($params);

		$fp = fopen($URL, 'rb', false, $ctx);
		if (!$fp) {
			throw new Exception("Problem with $URL, $php_errormsg");
		}

		$response = @stream_get_contents($fp);

		fclose($fp);

		if ($response === false) {
			throw new Exception("Problem reading data from $URL, $php_errormsg");
		}

		$this->ID++;
//print("Response:\n".$response."\n\n");
		$debug=$debug."Response:\n$response\n--------------------\n\n";

		return $response;
	}

	function api_call_raw($json, &$debug) {
		$response = $this->do_post_request($json, $debug);
		$decoded = json_decode($response, true);

		return $decoded;
	}

	function api_call($method, $params, &$debug) {
		global $ID;

		$data = array(
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'id' => $this->ID
		);

		$response = $this->do_post_request($data, $debug);
		$decoded = json_decode($response, true);

		return $decoded;
	}

	function api_acall($method, $params, &$debug) {
		global $ID;

		$data = array(
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => array('user' => 'Admin', 'password' => 'zabbix'),
			'id' => $this->ID
		);

		$response = $this->do_post_request($data, $debug);
		$decoded = json_decode($response, true);
		$auth=$decoded["result"];

		$data = array(
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'auth' => $auth,
			'id' => $this->ID
		);

		$response = $this->do_post_request($data, $debug);
		$decoded = json_decode($response, true);

		return $decoded;
	}


	protected function setUp() {
		global $DB, $URL;

		if (strstr(PHPUNIT_URL, 'http://')) {
			$URL=PHPUNIT_URL.'api_jsonrpc.php';
		}
		else {
			$URL='http://hudson/~hudson/'.PHPUNIT_URL.'/frontends/php/api_jsonrpc.php';
		}

		if (!isset($DB['DB'])) DBConnect($error);
	}

	protected function tearDown() {
		DBclose();
	}
}
