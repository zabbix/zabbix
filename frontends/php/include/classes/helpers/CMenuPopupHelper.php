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
	 * Prepare data for item history menu popup.
	 *
	 * @param string $itemid
	 *
	 * @return array
	 */
	public static function getAjaxHistory($itemid) {
		return [
			'ajax' => true,
			'type' => 'history',
			'data' => [
				'itemid' => $itemid
			]
		];
	}

	/**
	 * Prepare data for host menu popup.
	 *
	 * @param array  $host                       Host data.
	 * @param string $host['hostid']             Host ID.
	 * @param string $host['status']             Host status.
	 * @param array  $host['graphs']             Host graphs.
	 * @param array  $host['screens']            Host screens.
	 * @param array  $scripts                    Host scripts (optional).
	 * @param string $scripts[]['name']          Script name.
	 * @param string $scripts[]['scriptid']      Script ID.
	 * @param string $scripts[]['confirmation']  Confirmation text.
	 * @param bool   $has_goto                   "Go to" block in popup.
	 *
	 * @return array
	 */
	public static function getHost(array $host, array $scripts = [], $has_goto = true) {
		$data = [
			'type' => 'host',
			'hostid' => $host['hostid'],
			'hasGoTo' => $has_goto
		];

		if ($has_goto) {
			$data['showGraphs'] = (bool) $host['graphs'];
			$data['showScreens'] = (bool) $host['screens'];
			$data['showTriggers'] = ($host['status'] == HOST_STATUS_MONITORED);
		}

		foreach ($scripts as &$script) {
			$script['name'] = trimPath($script['name']);
		}
		unset($script);

		CArrayHelper::sort($scripts, ['name']);

		foreach (array_values($scripts) as $script) {
			$data['scripts'][] = [
				'name' => $script['name'],
				'scriptid' => $script['scriptid'],
				'confirmation' => $script['confirmation']
			];
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax host menu popup.
	 *
	 * @param string $hostid
	 * @param bool   $has_goto     Show "Go to" block in popup.
	 *
	 * @return array
	 */
	public static function getAjaxHost($hostid, $has_goto = true) {
		$data = [
			'ajax' => true,
			'type' => 'host',
			'data' => [
				'hostid' => $hostid
			]
		];

		if ($has_goto === false) {
			$data['data']['has_goto'] = $has_goto ? '1' : '0';
		}

		return $data;
	}

	/**
	 * Prepare data for map menu popup.
	 *
	 * @param string $hostId                     Host ID.
	 * @param array  $scripts                    Host scripts.
	 * @param string $scripts[]['name']          Script name.
	 * @param string $scripts[]['scriptid']      Script ID.
	 * @param string $scripts[]['confirmation']  Confirmation text.
	 * @param array  $gotos                      Enable goto links.
	 * @param array  $gotos['graphs']            Link to host graphs page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['screens']           Link to host screen page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['triggerStatus']     Link to "Problems" page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['submap']            Link to submap page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['events']            Link to events page with url parameters ("name" => "value") (optional).
	 * @param array  $urls                       Local and global map links (optional).
	 * @param string $urls[]['name']             Link name.
	 * @param string $urls[]['url']              Link url.
	 *
	 * @return array
	 */
	public static function getMap($hostId, array $scripts = null, array $gotos = null, array $urls = null) {

		$data = [
			'type' => 'map'
		];


		if ($scripts) {
			foreach ($scripts as &$script) {
				$script['name'] = trimPath($script['name']);
			}
			unset($script);
			CArrayHelper::sort($scripts, ['name']);

			$data['hostid'] = $hostId;

			foreach (array_values($scripts) as $script) {
				$data['scripts'][] = [
					'name' => $script['name'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				];
			}
		}

		if ($gotos) {
			$data['gotos'] = $gotos;
		}

		if ($urls) {
			foreach ($urls as $url) {
				$data['urls'][] = [
					'label' => $url['name'],
					'url' => $url['url']
				];
			}
		}

		return $data;
	}

	/**
	 * Prepare data for refresh time menu popup.
	 *
	 * @param string $widgetName		widget name
	 * @param string $currentRate		current rate value
	 * @param bool   $multiplier		multiplier or time mode
	 * @param array  $params			url parameters (optional)
	 *
	 * @return array
	 */
	public static function getRefresh($widgetName, $currentRate, $multiplier = false, array $params = []) {
		return [
			'type' => 'refresh',
			'widgetName' => $widgetName,
			'currentRate' => $currentRate,
			'multiplier' => $multiplier,
			'params' => $params
		];
	}

	/**
	 * Prepare data for trigger menu popup.
	 *
	 * @param array  $trigger                           Trigger data.
	 * @param string $trigger['triggerid']              Trigger ID.
	 * @param int    $trigger['flags']                  Trigger flags (TRIGGER_FLAG_DISCOVERY*).
	 * @param array  $trigger['hosts']                  Hosts, used by trigger expression.
	 * @param string $trigger['hosts'][]['hostid']      Host ID.
	 * @param string $trigger['hosts'][]['name']        Host name.
	 * @param string $trigger['hosts'][]['status']      Host status.
	 * @param array  $trigger['items']                  Trigger items.
	 * @param string $trigger['items'][]['itemid']      Item ID.
	 * @param string $trigger['items'][]['hostid']      Host ID.
	 * @param string $trigger['items'][]['name']        Item name.
	 * @param string $trigger['items'][]['key_']        Item key.
	 * @param string $trigger['items'][]['value_type']  Type of information of the item.
	 * @param string $trigger['url']                    Trigger URL.
	 * @param array  $acknowledge                       Acknowledge link parameters (optional).
	 * @param string $acknowledge['eventid']            Event ID.
	 * @param string $acknowledge['backurl']            Return URL (optional).
	 * @param array  $options
	 * @param bool   $options['show_description']       (optional) default: true
	 * @param bool   $options['description_enabled']    (optional) default: true
	 *
	 * @return array
	 */
	public static function getTrigger(array $trigger, array $acknowledge = null, array $options = []) {
		$hosts = [];
		$showEvents = true;

		foreach ($trigger['hosts'] as $host) {
			$hosts[$host['hostid']] = $host['name'];

			if ($host['status'] != HOST_STATUS_MONITORED) {
				$showEvents = false;
			}
		}

		$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

		foreach ($trigger['items'] as &$item) {
			$item['hostname'] = $hosts[$item['hostid']];
		}
		unset($item);

		CArrayHelper::sort($trigger['items'], ['name', 'hostname', 'itemid']);

		$hostCount = count($hosts);
		$items = [];

		foreach ($trigger['items'] as $item) {
			$items[] = [
				'name' => ($hostCount > 1)
					? $hosts[$item['hostid']].NAME_DELIMITER.$item['name_expanded']
					: $item['name_expanded'],
				'params' => [
					'itemid' => $item['itemid'],
					'action' => in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
						? HISTORY_GRAPH
						: HISTORY_VALUES
				]
			];
		}

		$data = [
			'type' => 'trigger',
			'triggerid' => $trigger['triggerid'],
			'items' => $items,
			'showEvents' => $showEvents,
			'configuration' => in_array(CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])
		];

		if (array_key_exists('show_description', $options) && $options['show_description'] === false) {
			$data['show_description'] = false;
		}
		else if (array_key_exists('description_enabled', $options) && $options['description_enabled'] === false) {
			$data['description_enabled'] = false;
		}

		if ($acknowledge !== null) {
			$data['acknowledge'] = $acknowledge;
		}

		if ($trigger['url'] !== '') {
			$data['url'] = CHtmlUrlValidator::validate($trigger['url'])
				? $trigger['url']
				: 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.', zbx_jsvalue($trigger['url'], false,
						false)).'\');';
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax trigger menu popup.
	 *
	 * @param string $triggerid
	 * @param array  $acknowledge             Acknowledge link parameters (optional).
	 * @param string $acknowledge['eventid']
	 * @param string $acknowledge['backurl']
	 * @param bool   $show_description
	 *
	 * @return array
	 */
	public static function getAjaxTrigger($triggerid, array $acknowledge = [], $show_description = true) {
		$data = [
			'ajax' => true,
			'type' => 'trigger',
			'data' => [
				'triggerid' => $triggerid
			]
		];

		if ($acknowledge) {
			$data['data']['acknowledge'] = $acknowledge;
		}

		if ($show_description === false) {
			$data['data']['show_description'] = $show_description ? '1' : '0';
		}

		return $data;
	}

	/**
	 * Prepare data for trigger macro menu popup.
	 *
	 * @return array
	 */
	public static function getAjaxTriggerMacro() {
		return [
			'ajax' => true,
			'type' => 'triggerMacro'
		];
	}

	/**
	 * Prepare data for item popup menu.
	 *
	 * @param string $itemid
	 *
	 * @return array
	 */
	public static function getAjaxItem($itemid) {
		return [
			'ajax' => true,
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
	public static function getAjaxItemPrototype($itemid) {
		return [
			'ajax' => true,
			'type' => 'item_prototype',
			'data' => [
				'itemid' => $itemid
			]
		];
	}
}
