<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace SCIM;

use Throwable;

class HttpResponse {

	/** @var array $data  Array of response data to be sent. */
	protected array $data = [];

	/** @var Throwable $exception */
	protected $exception = null;

	/** @var int $response_code  HTTP response status code. */
	protected $response_code = 200;

	public function __construct(array $data = []) {
		$this->data = $data;
	}

	public function setData(array $data) {
		$this->data = $data;

		return $this;
	}

	public function getData(): array {
		return $this->data;
	}

	public function setException(Throwable $e) {
		$this->exception = $e;
		$this->setResponseCode($e->getCode());

		return $this;
	}

	public function setResponseCode($response_code) {
		$this->response_code = $response_code;

		return $this;
	}

	/**
	 * Send HTTP response.
	 *
	 * @return void
	 */
	public function send(): void {
		if ($this->exception instanceof Throwable) {
			$this->setData([
				'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
				'detail' => $this->exception->getMessage(),
				'status' => $this->exception->getCode()
			]);
		}

		header('Content-Type: application/json', true, $this->response_code);
		echo json_encode($this->data);
		exit;
	}
}
