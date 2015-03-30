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
 * Base XML preprocessor class.
 */
class CXmlPreprocessor {

	public function __construct() {
		$this->versionPreprocessors = array(
			'1.0' => new C10XmlPreprocessor(),
			'2.0' => new C20XmlPreprocessor()
//			'3.0' => new C10ImportConverter()
		);
	}

	/**
	 * @param array $data
	 */
	public function transform(array $data) {
		if (array_key_exists('zabbix_export', $data)
				&& array_key_exists('version', $data['zabbix_export'])
				&& is_string($data['zabbix_export']['version'])
				&& array_key_exists($data['zabbix_export']['version'], $this->versionPreprocessors)) {

			$data = $this->versionPreprocessors[$data['zabbix_export']['version']]->transform($data);
		}

		return $data;
	}
}
