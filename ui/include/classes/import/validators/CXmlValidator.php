<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
class CXmlValidator extends CXmlValidatorGeneral {

	private $factory;

	public function __construct(CRegistryFactory $factory, string $format) {
		parent::__construct($format);

		$this->factory = $factory;
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $data    Import data.
	 * @param string $format  Format of import source.
	 *
	 * @throws Exception if $data does not correspond to validation rules.
	 *
	 * @return array  Validator does some manipulations for the incoming data. For example, converts empty tags to an
	 *                array, if desired. Converted array is returned.
	 */
	public function validate(array $data, string $path): array {
		$rules = ['type' => XML_ARRAY, 'rules' => [
			'zabbix_export' => ['type' => XML_ARRAY | XML_REQUIRED, 'check_unexpected' => false, 'rules' => [
				'version' => ['type' => XML_STRING | XML_REQUIRED]
			]]
		]];

		$strict = $this->getStrict();
		$is_preview = $this->isPreview();

		$data = $this
			->setStrict(true)
			->doValidate($rules, $data, $path);

		$version = $data['zabbix_export']['version'];

		if (!$this->factory->hasObject($version)) {
			throw new Exception(
				_s('Invalid tag "%1$s": %2$s.', '/zabbix_export/version', _('unsupported version number'))
			);
		}

		if (in_array($version, ['1.0', '2.0', '3.0', '3.2', '3.4', '4.0', '4.2', '4.4', '5.0', '5.2'])) {
			unset($data['zabbix_export']['screens']);
		}

		$data['zabbix_export'] = $this->factory->getObject($version)
			->setStrict($strict)
			->setPreview($is_preview)
			->validate($data['zabbix_export'], '/zabbix_export');

		return $data;
	}
}
