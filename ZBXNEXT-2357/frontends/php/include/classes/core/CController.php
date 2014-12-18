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

class CController {

	const VALIDATION_OK = 0;
	const VALIDATION_ERROR = 1;
	const VALIDATION_FATAL_ERROR = 2;

	private $action;
	private $response;
	private$validationResult;

	public $input = array();

	public function __construct() {
		session_start();
	}

	public function setAction($action) {
		$this->action = $action;
	}

	public function setResponse($response) {
		$this->response = $response;
	}

	public function getResponse() {
		return $this->response;
	}

	public function getAction() {
		return $this->action;
	}

	public function getUserType() {
		return CWebUser::getType();
	}

	public function validateInput($validationRules) {
		if (isset($_SESSION['formData']))
		{
			$this->input = array_merge($_REQUEST, $_SESSION['formData']);
			unset($_SESSION['formData']);
		}
		else {
			$this->input = $_REQUEST;
		}

		$validator = new CNewValidator($this->input, $validationRules);
		$result = !$validator->isError() && !$validator->isErrorFatal();

		foreach ($validator->getAllErrors() as $error) {
			info($error);
		}

		if ($validator->isErrorFatal()) {
			$this->validationResult = self::VALIDATION_FATAL_ERROR;
		}
		else if ($validator->isError()) {
			$this->validationResult = self::VALIDATION_ERROR;
		}
		else {
			$this->validationResult = self::VALIDATION_OK;
		}

		return $result;
	}

	public function getValidationError() {
		return $this->validationResult;
	}

	public function hasInput($var) {
		return isset($this->input[$var]);
	}

	public function getInput($var, $default = null) {
		return isset($this->input[$var]) ? $this->input[$var] : $default;
	}

	public function getInputAll() {
		return $this->input;
	}

	protected function checkPermissions() {
		access_deny();
	}

	protected function checkInput() {
		access_deny();
	}

	protected function doAction() {
		return null;
	}

	final public function run() {
		if ($this->checkInput()) {
			$this->checkPermissions();
			$this->doAction();
		}
		return $this->getResponse();
	}
}
