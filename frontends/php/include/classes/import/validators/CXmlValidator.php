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
			'1.0' => new C10XmlValidator(),
			'2.0' => new C20XmlValidator(),
			'3.0' => new C30XmlValidator()
		);
	}

	/**
	 * Base validation function.
	 *
	 * @param array $data	import data
	 */
	public function validate($data) {
		$fields = array('zabbix_export' => 'required|array');
		$validator = new CNewValidator($data, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception($errors[0]);
		}

		$this->validateMainParameters($data['zabbix_export'], 'zabbix_export');
		$this->versionValidators[$data['zabbix_export']['version']]->validate($data['zabbix_export'], 'zabbix_export');
	}

	/**
	 * Validate main zabbix_export parameters.
	 *
	 * @param array  $zabbix_export	import data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if the data is invalid
	 */
	private function validateMainParameters(array $zabbix_export, $path) {
		$fields = array(
			'version' =>	'required|string',
			'date' =>		'string',
			'time' =>		'string'
		);
		$validator = new CNewValidator($zabbix_export, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		$this->validateVersion($zabbix_export['version']);
	}

	/**
	 * Check if this import version is supported.
	 *
	 * @param array $version	version data
	 *
	 * @throws Exception		if the data is invalid
	 */
	private function validateVersion($version) {
		if (!array_key_exists($version, $this->versionValidators)) {
			throw new Exception(_s('Unsupported import version "%1$s".', $version));
		}
	}
}
