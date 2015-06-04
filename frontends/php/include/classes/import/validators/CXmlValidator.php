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
	protected $versionValidators = [];

	public function __construct() {
		$this->versionValidators = [
			'1.0' => 'C10XmlValidator',
			'2.0' => 'C20XmlValidator',
			'3.0' => 'C30XmlValidator'
		];
	}

	/**
	 * Base validation function.
	 *
	 * @param array $data	import data
	 *
	 * @return array		Validator does some manipulation for the incoming data. For example, converts empty tags to
	 *						an array, if desired. Converted array is returned.
	 */
	public function validate(array $data) {
		$rules = ['type' => XML_ARRAY, 'rules' => [
			'zabbix_export' => ['type' => XML_ARRAY | XML_REQUIRED, 'check_unexpected' => false, 'rules' => [
				'version' => ['type' => XML_STRING | XML_REQUIRED]
			]]
		]];

		$data = (new CXmlValidatorGeneral($rules))->validate($data, '/');
		$version = $data['zabbix_export']['version'];

		if (!array_key_exists($version, $this->versionValidators)) {
			throw new Exception(
				_s('Invalid XML tag "%1$s": %2$s.', '/zabbix_export/version', _('unsupported version number'))
			);
		}

		$data['zabbix_export'] = (new $this->versionValidators[$version]())->
			validate($data['zabbix_export'], '/zabbix_export');

		return $data;
	}
}
