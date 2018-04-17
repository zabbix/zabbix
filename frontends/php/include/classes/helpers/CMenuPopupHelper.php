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
	 * @param array $item                Item data.
	 * @param int   $item['itemid']      Item ID.
	 * @param int   $item['value_type']  Item value type.
	 * @param bool  $fullscreen          Fullscreen mode.
	 *
	 * @return array
	 */
	public static function getHistory(array $item, $fullscreen = false) {
		$data = [
			'type' => 'history',
			'itemid' => $item['itemid'],
			'hasLatestGraphs' => in_array($item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT])
		];

		if ($fullscreen) {
			$data['fullscreen'] = true;
		}

		return $data;
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
	 * @param bool   $fullscreen                 Fullscreen mode.
	 *
	 * @return array
	 */
	public static function getHost(array $host, array $scripts = null, $has_goto = true, $fullscreen = false) {
		$data = [
			'type' => 'host',
			'hostid' => $host['hostid'],
			'showGraphs' => (bool) $host['graphs'],
			'showScreens' => (bool) $host['screens'],
			'showTriggers' => ($host['status'] == HOST_STATUS_MONITORED),
			'hasGoTo' => $has_goto
		];

		if ($fullscreen) {
			$data['fullscreen'] = true;
		}

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
	 * @param string $hostId                     Host ID.
	 * @param array  $scripts                    Host scripts.
	 * @param string $scripts[]['name']          Script name.
	 * @param string $scripts[]['scriptid']      Script ID.
	 * @param string $scripts[]['confirmation']  Confirmation text.
	 * @param array  $gotos                      Enable goto links.
	 * @param array  $gotos['graphs']            Link to host graphs page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['screens']           Link to host screen page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['triggerStatus']     Link to trigger status page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['submap']            Link to submap page with url parameters ("name" => "value") (optional).
	 * @param array  $gotos['events']            Link to events page with url parameters ("name" => "value") (optional).
	 * @param array  $urls                       Local and global map links (optional).
	 * @param string $urls[]['name']             Link name.
	 * @param string $urls[]['url']              Link url.
	 * @param bool   $fullscreen                 Fullscreen mode.
	 *
	 * @return array
	 */
	public static function getMap($hostId, array $scripts = null, array $gotos = null, array $urls = null,
			$fullscreen = false) {

		$data = [
			'type' => 'map'
		];

		if ($fullscreen) {
			$data['fullscreen'] = true;
		}

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
	 * @param string $acknowledge['screenid']           Screen ID (optional).
	 * @param string $acknowledge['backurl']            Return URL (optional).
	 * @param array  $options
	 * @param bool   $options['show_description']       (optional) default: true
	 * @param bool   $options['description_enabled']    (optional) default: true
	 * @param bool   $options['fullscreen']             (optional) default: false
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

		if (array_key_exists('fullscreen', $options) && $options['fullscreen'] === true) {
			$data['fullscreen'] = true;
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
	 * Prepare data for dependent item (or item prototype) popup menu.
	 *
	 * @param string $page       Page name.
	 * @param string $itemid     Item id.
	 * @param string $parent     Name of parent object argument (hostid or parent_discoveryid).
	 * @param string $parentid   Parent object id (host id for hosts or discoveryid for discovery rules)
	 * @param string $name       Item name.
	 *
	 * @return array
	 */
	protected static function getDependent($page, $itemid, $parent, $parentid, $name) {
		$url = (new CUrl($page))
					->setArgument('form', 'create')->setArgument('type', ITEM_TYPE_DEPENDENT)
					->setArgument($parent, $parentid)->setArgument('master_itemid', $itemid)
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
	 * Prepare data for dependent item popup menu.
	 *
	 * @param string $itemid    Item id.
	 * @param string $hostid    Host id.
	 * @param string $name      Item name.
	 */
	public static function getDependentItem($itemid, $hostid, $name) {
		return self::getDependent('items.php', $itemid, 'hostid', $hostid, $name);
	}

	/**
	 * Prepare data for dependent item prototype popup menu.
	 *
	 * @param string $itemid                Item id.
	 * @param string $parent_discoveryid    Prent discovery rule id.
	 * @param string $name                  Item name.
	 */
	public static function getDependentItemPrototype($itemid, $parent_discoveryid, $name) {
		return self::getDependent('disc_prototypes.php', $itemid, 'parent_discoveryid', $parent_discoveryid, $name);
	}
}
