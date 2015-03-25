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
 * Base XML validation class.
 */
class CXmlValidator {

	/**
	 * Accepted versions.
	 *
	 * @var CXmlValidator[]
	 */
	protected $versionValidators = array();

	public function __construct() {
		$this->versionValidators = array(
			'1.0' => new C10XmlValidator()
//			'2.0' => new C10ImportConverter(),
//			'3.0' => new C10ImportConverter()
		);
	}

	/**
	 * Base validation function.
	 *
	 * @param array $data	import data
	 */
	public function validate($data) {
		$this->validateZabbixExport($data);
		$this->validateMainParameters($data);
		$this->validateVersion($data);
		$this->validateContent($data);
	}

	/**
	 * Validate zabbix_export parameter.
	 *
	 * @param array $data	import data
	 *
	 * @throws Exception	if the data is invalid
	 */
	private function validateZabbixExport($data) {
		$fields = array(
			'zabbix_export' =>	'required|array',
		);
		$validator = new CNewValidator($data, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception($errors[0]);
		}
	}

	/**
	 * Validate main zabbix_export parameters.
	 *
	 * @param array $data	import data
	 *
	 * @throws Exception	if the data is invalid
	 */
	private function validateMainParameters($data) {
		$fields = array(
			'version' =>	'required|string',
			'date' =>		'required|string',
			'time' =>		'required|string'
		);
		$validator = new CNewValidator($data['zabbix_export'], $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "zabbix_export": %1$s', $errors[0]));
		}
	}

	/**
	 * Check if this import version is supported.
	 *
	 * @param array $data	import data
	 *
	 * @throws Exception	if the data is invalid
	 */
	private function validateVersion($data) {
		if (!array_key_exists($data['zabbix_export']['version'], $this->versionValidators)) {
			throw new Exception(_s('Unsupported import version "%1$s".', $data['zabbix_export']['version']));
		}
	}

	/**
	 * Run XML validators.
	 *
	 * @param array $data	import data
	 */
	private function validateContent($data) {
		$this->versionValidators[$data['zabbix_export']['version']]->validate($data['zabbix_export']);
	}
}
