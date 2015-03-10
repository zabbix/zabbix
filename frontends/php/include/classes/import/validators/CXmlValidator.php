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
		$this->validateMainParameters($data);
		$this->validateVersion($data);
		$this->validateByVersion($data);
	}

	/**
	 * Validate main zabbix_export parameter.
	 *
	 * @param array $data	import data
	 *
	 * @throws Exception	if the data is invalid
	 */
	private function validateMainParameters($data) {
		if (isset($data['zabbix_export']) && is_array($data['zabbix_export'])) {
			$validationRules = array(
				'version' =>	'required|string',
				'date' =>		'required|string',
				'time' =>		'required|string'
			);
			$validator = new CNewValidator($data['zabbix_export'], $validationRules);
			foreach ($validator->getAllErrors() as $error) {
				throw new Exception($error);
			}
		}
		else {
			throw new Exception(_s('Not valid Zabbix export data format.'));
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
		$version = $data['zabbix_export']['version'];

		if (!array_key_exists($version, $this->versionValidators)) {
			throw new Exception(_s('Unsupported import version "%1$s".', $version));
		}
	}

	/**
	 * Run XML validators.
	 *
	 * @param array $data	import data
	 */
	private function validateByVersion($data) {
		$content = $data['zabbix_export'];
		$this->versionValidators[$content['version']]->validate($content);
	}
}
