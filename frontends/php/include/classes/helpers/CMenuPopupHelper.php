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


class CMenuPopupHelper {

	/**
	 * Prepare data for dashboard popup menu.
	 *
	 * @param string $dashboardid
	 */
	public static function getDashboard($dashboardid) {
		return [
			'type' => 'dashboard',
			'data' => [
				'dashboardid' => $dashboardid
			]
		];
	}

	/**
	 * Prepare data for item history menu popup.
	 *
	 * @param string $itemid
	 *
	 * @return array
	 */
	public static function getHistory($itemid) {
		return [
			'type' => 'history',
			'data' => [
				'itemid' => $itemid
			]
		];
	}

	/**
	 * Prepare data for Ajax host menu popup.
	 *
	 * @param string $hostid
	 * @param bool   $has_goto     Show "Go to" block in popup.
	 *
	 * @return array
	 */
	public static function getHost($hostid, $has_goto = true) {
		$data = [
			'type' => 'host',
			'data' => [
				'hostid' => $hostid
			]
		];

		if ($has_goto === false) {
			$data['data']['has_goto'] = '0';
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax map element menu popup.
	 *
	 * @param string $sysmapid
	 * @param string $selementid
	 * @param int    $severity_min
	 * @param string $hostid
	 *
	 * @return array
	 */
	public static function getMapElement($sysmapid, $selementid, $severity_min, $hostid) {
		$data = [
			'type' => 'map_element',
			'data' => [
				'sysmapid' => $sysmapid,
				'selementid' => $selementid
			]
		];

		if ($severity_min != TRIGGER_SEVERITY_NOT_CLASSIFIED) {
			$data['data']['severity_min'] = $severity_min;
		}
		if ($hostid != 0) {
			$data['data']['hostid'] = $hostid;
		}

		return $data;
	}

	/**
	 * Prepare data for refresh time menu popup.
	 *
	 * @param string $widgetName
	 * @param string $currentRate
	 * @param bool   $multiplier   Multiplier or time mode.
	 * @param array  $params       (optional) URL parameters.
	 *
	 * @return array
	 */
	public static function getRefresh($widgetName, $currentRate, $multiplier = false, array $params = []) {
		$data = [
			'type' => 'refresh',
			'data' => [
				'widgetName' => $widgetName,
				'currentRate' => $currentRate,
				'multiplier' => $multiplier ? '1' : '0'
			]
		];

		if ($params) {
			$data['data']['params'] = $params;
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax trigger menu popup.
	 *
	 * @param string $triggerid
	 * @param string $eventid                 (optional) Mandatory for Acknowledge and Description menus.
	 * @param array  $acknowledge             Acknowledge link parameters (optional).
	 * @param string $acknowledge['backurl']
	 * @param bool   $show_description
	 *
	 * @return array
	 */
	public static function getTrigger($triggerid, $eventid = 0, array $acknowledge = [], $show_description = true) {
		$data = [
			'type' => 'trigger',
			'data' => [
				'triggerid' => $triggerid
			]
		];

		if ($eventid != 0) {
			$data['data']['eventid'] = $eventid;
		}

		if ($acknowledge) {
			$data['data']['acknowledge'] = $acknowledge;
		}

		if ($show_description === false) {
			$data['data']['show_description'] = '0';
		}

		return $data;
	}

	/**
	 * Prepare data for trigger macro menu popup.
	 *
	 * @return array
	 */
	public static function getTriggerMacro() {
		return [
			'type' => 'trigger_macro'
		];
	}

	/**
	 * Prepare data for item popup menu.
	 *
	 * @param string $itemid
	 *
	 * @return array
	 */
	public static function getItem($itemid) {
		return [
			'type' => 'item',
			'data' => [
				'itemid' => $itemid
			]
		];
	}

	/**
	 * Prepare data for item prototype popup menu.
	 *
	 * @param string $itemid
	 */
	public static function getItemPrototype($itemid) {
		return [
			'type' => 'item_prototype',
			'data' => [
				'itemid' => $itemid
			]
		];
	}
}
