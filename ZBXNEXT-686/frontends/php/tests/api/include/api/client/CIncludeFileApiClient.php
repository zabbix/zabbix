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


/**
 * This class should be used to call API by mocking a HTTP request and including the API entry point.
 */
class CIncludeFileApiClient extends CApiClient {

	protected $endpoint;

	public function __construct(CJson $json, $endpoint = null) {
		parent::__construct($json);

		$this->endpoint = ($endpoint !== null) ? $endpoint : __DIR__.'/../../../../../api_jsonrpc.php';
	}

	public function callMethod($method, $params, $auth = null, $id = null, $jsonRpc = '2.0') {
		// TODO: mock the POST variable directly
		$this->setStreamWrapper($this->encode(array(
			'method' => $method,
			'params' => $params,
			'auth' => $auth,
			'id' => $id,
			'jsonrpc' => $jsonRpc
		)));

		$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();
		require $this->endpoint;
		$contents = ob_get_contents();
		ob_end_clean();

		$json = $this->decode($contents);

		if (null === $json || !is_array($json)) {
			throw new \Exception(sprintf('JSON returned by API call is not decodable ("%s" given)', $contents));
		}

		$this->restoreStreamWrapper();

		// TODO: the error handler should be unregistered by the application
		restore_error_handler();

		if (isset($json['result']) || isset($json['error'])) {
			return $this->createResponse($json);
		}
		else {
			throw new \Exception(
				sprintf('Wow. The API response does not look like both response and error, "%s" given', $contents)
			);
		}
	}

	protected function restoreStreamWrapper() {
		stream_wrapper_restore('php');
	}

	protected function setStreamWrapper($contents) {
		stream_wrapper_unregister('php');
		stream_wrapper_register('php', 'Zabbix\Test\Util\InputStreamWrapper');

		file_put_contents('php://input', $contents);
	}


}
