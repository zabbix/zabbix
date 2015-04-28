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
		$validator = new CXmlValidatorGeneral(
			array('type' => CXmlValidatorGeneral::XML_ARRAY, 'rules' => array(
				'zabbix_export' => array('type' => CXmlValidatorGeneral::XML_ARRAY | CXmlValidatorGeneral::XML_REQUIRED, 'check_unexpected' => false, 'rules' => array(
					'version' => array('type' => CXmlValidatorGeneral::XML_STRING | CXmlValidatorGeneral::XML_REQUIRED)
				))
			))
		);

		$validator->validate($data, '/');

		if (!array_key_exists($data['zabbix_export']['version'], $this->versionValidators)) {
			throw new Exception(
				_s('Invalid XML tag "%1$s": %2$s.', '/zabbix_export/version', _('unsupported version number'))
			);
		}

		$this->versionValidators[$data['zabbix_export']['version']]->validate($data['zabbix_export'], '/zabbix_export');
	}
}
