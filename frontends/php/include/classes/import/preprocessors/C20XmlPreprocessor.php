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
 * Zabbix 2.x preprocessor.
 */
class C20XmlPreprocessor {

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function transform(array $data) {
		$pathes = array(
			array('^zabbix_export$', '^(groups|hosts|templates|triggers|graphs|screens|images|maps)$'),
			array('^zabbix_export$', '^hosts$',
				'^host[0-9]*', '^(templates|groups|interfaces|applications|items|discovery_rules|macros|inventory)$'
			),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*', '^items$', '^item[0-9]*',
				'^(applications|valuemap)$'
			),
			array('^zabbix_export$', '^screens$', '^screen[0-9]*', '^screen_items$')
		);

		foreach ($pathes as $path) {
			$this->transformEmpStrToArr($path, $data);
		}

		return $data;
	}

	/**
	 * Transforms empty strings to the array for a specified path.
	 *
	 * @param array $path
	 * @param array $data
	 */
	protected function transformEmpStrToArr(array $path, array &$data) {
		if (count($path) > 1) {
			$curr_tag = array_shift($path);
			foreach ($data as $key => &$value) {
				if (is_array($value) && preg_match('/'.$curr_tag.'/', $key)) {
					$this->transformEmpStrToArr($path, $value);
				}
			}
			unset($value);
		}
		else {
			$last_tag = array_pop($path);
			foreach ($data as $key => &$value) {
				if ($value === '' && preg_match('/'.$last_tag.'/', $key)) {
					$value = array();
				}
			}
			unset($value);
		}
	}
}
