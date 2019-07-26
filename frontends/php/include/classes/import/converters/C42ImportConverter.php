<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Converter for converting import data from 4.2 to 4.4.
 */
class C42ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '4.4';

		if (array_key_exists('screens', $data['zabbix_export'])) {
			$data['zabbix_export']['screens'] = $this->convertScreens($data['zabbix_export']['screens']);
		}

		return $data;
	}

	/**
	 * Convert screens.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	protected function convertScreens(array $screens) {
		foreach ($screens as &$screen) {
			if (array_key_exists('screen_items', $screen)) {
				$screen['screen_items'] = $this->convertScreenItems($screen['screen_items']);
			}
		}
		unset($screen);

		return $screens;
	}

	/**
	 * Convert screen items.
	 *
	 * @param array $screen_items
	 *
	 * @return array
	 */
	protected function convertScreenItems(array $screen_items) {
		foreach ($screen_items as $index => $screen_item) {
			if ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN) {
				unset($screen_items[$index]);
			}
		}

		return $screen_items;
	}
}
