<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * @param array $item				item data
	 * @param int   $item['itemid']		item id
	 * @param int   $item['value_type']	item value type
	 *
	 * @return array
	 */
	public static function getHistory(array $item) {
		return [
			'type' => 'history',
			'itemid' => $item['itemid'],
			'hasLatestGraphs' => in_array($item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT])
		];
	}

	/**
	 * Prepare data for host menu popup.
	 *
	 * @param array  $host						host data
	 * @param string $host['hostid']			host id
	 * @param string $host['status']			host status
	 * @param array  $host['graphs']			host graphs
	 * @param array  $host['screens']			host screens
	 * @param array  $scripts					host scripts (optional)
	 * @param string $scripts[]['name']			script name
	 * @param string $scripts[]['scriptid']		script id
	 * @param string $scripts[]['confirmation']	confirmation text
	 * @param bool   $hasGoTo					"Go to" block in popup
	 *
	 * @return array
	 */
	public static function getHost(array $host, array $scripts = null, $hasGoTo = true) {
		$data = [
			'type' => 'host',
			'hostid' => $host['hostid'],
			'showGraphs' => (bool) $host['graphs'],
			'showScreens' => (bool) $host['screens'],
			'showTriggers' => ($host['status'] == HOST_STATUS_MONITORED),
			'hasGoTo' => $hasGoTo
		];

		if ($scripts) {
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
		}

		return $data;
	}

	/**
	 * Prepare data for map menu popup.
	 *
	 * @param string $hostId					host id
	 * @param array  $scripts					host scripts (optional)
	 * @param string $scripts[]['name']			script name
	 * @param string $scripts[]['scriptid']		script id
	 * @param string $scripts[]['confirmation']	confirmation text
	 * @param array  $gotos						goto links (optional)
	 * @param array  $gotos['graphs']			link to host graphs page with url parameters ("name" => "value") (optional)
	 * @param array  $gotos['screens']			link to host screen page with url parameters ("name" => "value") (optional)
	 * @param array  $gotos['triggerStatus']	link to trigger status page with url parameters ("name" => "value") (optional)
	 * @param array  $gotos['submap']			link to submap page with url parameters ("name" => "value") (optional)
	 * @param array  $gotos['events']			link to events page with url parameters ("name" => "value") (optional)
	 * @param array  $urls						local and global map urls (optional)
	 * @param string $urls[]['name']			url name
	 * @param string $urls[]['url']				url
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
	 * @param array  $trigger							trigger data
	 * @param string $trigger['triggerid']				trigger ID
	 * @param int    $trigger['flags']					trigger flags (TRIGGER_FLAG_DISCOVERY*)
	 * @param array  $trigger['hosts']					hosts, used by trigger expression
	 * @param string $trigger['hosts'][]['hostid']		host ID
	 * @param string $trigger['hosts'][]['name']		host name
	 * @param string $trigger['hosts'][]['status']		host status
	 * @param array  $trigger['items']					trigger items
	 * @param string $trigger['items'][]['itemid']		item ID
	 * @param string $trigger['items'][]['hostid']		host ID
	 * @param string $trigger['items'][]['name']		item name
	 * @param string $trigger['items'][]['key_']		item key
	 * @param string $trigger['items'][]['value_type']	type of information of the item
	 * @param string $trigger['url']					trigger URL
	 * @param array  $acknowledge						acknowledge link parameters (optional)
	 * @param string $acknowledge['eventid']			event ID
	 * @param string $acknowledge['screenid']			screen ID (optional)
	 * @param string $acknowledge['backurl']			return URL (optional)
	 *
	 * @return array
	 */
	public static function getTrigger(array $trigger, array $acknowledge = null) {
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

		// show_description is TRUE by default. Only FALSE should be specidied.
		if (array_key_exists('show_description', $trigger) && $trigger['show_description'] === false) {
			$data['show_description'] = $trigger['show_description'];
		}

		// description_enabled is TRUE by default. Only FALSE should be specidied.
		if (array_key_exists('description_enabled', $trigger) && $trigger['description_enabled'] === false) {
			$data['description_enabled'] = $trigger['description_enabled'];
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
	 * Prepare data for trigger log menu popup.
	 *
	 * @param string $itemId				item id
	 * @param string $itemName				item name
	 * @param array  $triggers				triggers (optional)
	 * @param string $triggers[n]['id']		trigger id
	 * @param string $triggers[n]['name']	trigger name
	 *
	 * @return array
	 */
	public static function getTriggerLog($itemId, $itemName, $triggers) {
		return [
			'type' => 'triggerLog',
			'itemid' => $itemId,
			'itemName' => $itemName,
			'triggers' => $triggers
		];
	}

	/**
	 * Prepare data for trigger macro menu popup.
	 *
	 * @return array
	 */
	public static function getTriggerMacro() {
		return [
			'type' => 'triggerMacro'
		];
	}

	/**
	 * Prepare data for dependent item popup menu.
	 *
	 * @param string $itemid    Item id.
	 * @param string $hostid    Host id.
	 * @param string $name      Item name.
	 */
	public static function getDependentItem($itemid, $hostid, $name) {
		$url = (new CUrl('items.php'))
					->setArgument('form', _('Create item'))->setArgument('type', ITEM_TYPE_DEPENDENT)
					->setArgument('hostid', $hostid)->setArgument('master_itemid', $itemid)
					->getUrl();

		return [
			'type' => 'dependent_items',
			'itemid' => $itemid,
			'item_name' => $name,
			'add_label' => _('Create dependent item'),
			'add_url' => $url
		];
	}

	/**
	 * Prepare data for dependent item prototype popup menu.
	 *
	 * @param string $itemid                Item id.
	 * @param string $parent_discoveryid    Prent discovery rule id.
	 * @param string $name                  Item name.
	 */
	public static function getDependentItemPrototype($itemid, $parent_discoveryid, $name) {
		$url = (new CUrl('disc_prototypes.php'))
					->setArgument('form', _('Create item'))->setArgument('type', ITEM_TYPE_DEPENDENT)
					->setArgument('parent_discoveryid', $parent_discoveryid)->setArgument('master_itemid', $itemid)
					->getUrl();

		return [
			'type' => 'dependent_items',
			'itemid' => $itemid,
			'item_name' => $name,
			'add_label' => _('Create dependent item'),
			'add_url' => $url
		];
	}
}
