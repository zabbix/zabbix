<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * Prepare data for favourite graphs menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteGraphs() {
		$graphs = [];
		$simple_graphs = [];

		$favourites = CFavorite::get('web.favorite.graphids');

		if ($favourites) {
			$graphids = [];
			$itemids = [];
			$db_graphs = [];
			$db_items = [];

			foreach ($favourites as $favourite) {
				if ($favourite['source'] === 'itemid') {
					$itemids[$favourite['value']] = true;
				}
				else {
					$graphids[$favourite['value']] = true;
				}
			}

			if ($graphids) {
				$db_graphs = API::Graph()->get([
					'output' => ['graphid', 'name'],
					'selectHosts' => ['hostid', 'name'],
					'expandName' => true,
					'graphids' => array_keys($graphids),
					'preservekeys' => true
				]);
			}

			if ($itemids) {
				$db_items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'name', 'key_'],
					'selectHosts' => ['hostid', 'name'],
					'itemids' => array_keys($itemids),
					'webitems' => true,
					'preservekeys' => true
				]);

				$db_items = CMacrosResolverHelper::resolveItemNames($db_items);
			}

			foreach ($favourites as $favourite) {
				$sourceid = $favourite['value'];

				if ($favourite['source'] === 'itemid') {
					if (array_key_exists($sourceid, $db_items)) {
						$db_item = $db_items[$sourceid];

						$simple_graphs[] = [
							'id' => $sourceid,
							'label' => $db_item['hosts'][0]['name'].NAME_DELIMITER.$db_item['name_expanded']
						];
					}
				}
				else {
					if (array_key_exists($sourceid, $db_graphs)) {
						$db_graph = $db_graphs[$sourceid];

						$graphs[] = [
							'id' => $sourceid,
							'label' => $db_graph['hosts'][0]['name'].NAME_DELIMITER.$db_graph['name']
						];
					}
				}
			}
		}

		return [
			'type' => 'favouriteGraphs',
			'graphs' => $graphs,
			'simpleGraphs' => $simple_graphs
		];
	}

	/**
	 * Prepare data for favourite maps menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteMaps() {
		$maps = [];

		$favourites = CFavorite::get('web.favorite.sysmapids');

		if ($favourites) {
			$mapids = [];

			foreach ($favourites as $favourite) {
				$mapids[$favourite['value']] = true;
			}

			$db_maps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => array_keys($mapids)
			]);

			foreach ($db_maps as $db_map) {
				$maps[] = [
					'id' => $db_map['sysmapid'],
					'label' => $db_map['name']
				];
			}
		}

		return [
			'type' => 'favouriteMaps',
			'maps' => $maps
		];
	}

	/**
	 * Prepare data for favourite screens menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteScreens() {
		$screens = $slideshows = [];

		$favourites = CFavorite::get('web.favorite.screenids');

		if ($favourites) {
			$screenIds = $slideshowIds = [];

			foreach ($favourites as $favourite) {
				if ($favourite['source'] === 'screenid') {
					$screenIds[$favourite['value']] = $favourite['value'];
				}
			}

			$dbScreens = API::Screen()->get([
				'output' => ['screenid', 'name'],
				'screenids' => $screenIds,
				'preservekeys' => true
			]);

			foreach ($favourites as $favourite) {
				$sourceId = $favourite['value'];

				if ($favourite['source'] === 'slideshowid') {
					if (slideshow_accessible($sourceId, PERM_READ)) {
						$dbSlideshow = get_slideshow_by_slideshowid($sourceId, PERM_READ);

						if ($dbSlideshow) {
							$slideshows[] = [
								'id' => $dbSlideshow['slideshowid'],
								'label' => $dbSlideshow['name']
							];
						}
					}
				}
				else {
					if (isset($dbScreens[$sourceId])) {
						$dbScreen = $dbScreens[$sourceId];

						$screens[] = [
							'id' => $dbScreen['screenid'],
							'label' => $dbScreen['name']
						];
					}
				}
			}
		}

		return [
			'type' => 'favouriteScreens',
			'screens' => $screens,
			'slideshows' => $slideshows
		];
	}

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

		if ($acknowledge !== null) {
			$data['acknowledge'] = $acknowledge;
		}

		if ($trigger['url'] !== '') {
			$data['url'] = $trigger['url'];
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
}
