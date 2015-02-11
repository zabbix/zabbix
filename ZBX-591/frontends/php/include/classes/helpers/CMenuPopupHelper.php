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


class CMenuPopupHelper {

	/**
	 * Prepare data for favourite graphs menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteGraphs() {
		$graphs = $simpeGraphs = array();

		$favourites = CFavorite::get('web.favorite.graphids');

		if ($favourites) {
			$graphIds = $itemIds = $dbGraphs = $dbItems = array();

			foreach ($favourites as $favourite) {
				if ($favourite['source'] === 'itemid') {
					$itemIds[$favourite['value']] = $favourite['value'];
				}
				else {
					$graphIds[$favourite['value']] = $favourite['value'];
				}
			}

			if ($graphIds) {
				$dbGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'selectHosts' => array('hostid', 'host'),
					'expandName' => true,
					'graphids' => $graphIds,
					'preservekeys' => true
				));
			}

			if ($itemIds) {
				$dbItems = API::Item()->get(array(
					'output' => array('itemid', 'hostid', 'name', 'key_'),
					'selectHosts' => array('hostid', 'host'),
					'itemids' => $itemIds,
					'webitems' => true,
					'preservekeys' => true
				));

				$dbItems = CMacrosResolverHelper::resolveItemNames($dbItems);
			}

			foreach ($favourites as $favourite) {
				$sourceId = $favourite['value'];

				if ($favourite['source'] === 'itemid') {
					if (isset($dbItems[$sourceId])) {
						$dbItem = $dbItems[$sourceId];
						$dbHost = reset($dbItem['hosts']);

						$simpeGraphs[] = array(
							'id' => $sourceId,
							'label' => $dbHost['host'].NAME_DELIMITER.$dbItem['name_expanded']
						);
					}
				}
				else {
					if (isset($dbGraphs[$sourceId])) {
						$dbGraph = $dbGraphs[$sourceId];
						$dbHost = reset($dbGraph['hosts']);

						$graphs[] = array(
							'id' => $sourceId,
							'label' => $dbHost['host'].NAME_DELIMITER.$dbGraph['name']
						);
					}
				}
			}
		}

		return array(
			'type' => 'favouriteGraphs',
			'graphs' => $graphs,
			'simpleGraphs' => $simpeGraphs
		);
	}

	/**
	 * Prepare data for favourite maps menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteMaps() {
		$maps = array();

		$favourites = CFavorite::get('web.favorite.sysmapids');

		if ($favourites) {
			$mapIds = array();

			foreach ($favourites as $favourite) {
				$mapIds[$favourite['value']] = $favourite['value'];
			}

			$dbMaps = API::Map()->get(array(
				'output' => array('sysmapid', 'name'),
				'sysmapids' => $mapIds
			));

			foreach ($dbMaps as $dbMap) {
				$maps[] = array(
					'id' => $dbMap['sysmapid'],
					'label' => $dbMap['name']
				);
			}
		}

		return array(
			'type' => 'favouriteMaps',
			'maps' => $maps
		);
	}

	/**
	 * Prepare data for favourite screens menu popup.
	 *
	 * @return array
	 */
	public static function getFavouriteScreens() {
		$screens = $slideshows = array();

		$favourites = CFavorite::get('web.favorite.screenids');

		if ($favourites) {
			$screenIds = $slideshowIds = array();

			foreach ($favourites as $favourite) {
				if ($favourite['source'] === 'screenid') {
					$screenIds[$favourite['value']] = $favourite['value'];
				}
			}

			$dbScreens = API::Screen()->get(array(
				'output' => array('screenid', 'name'),
				'screenids' => $screenIds,
				'preservekeys' => true
			));

			foreach ($favourites as $favourite) {
				$sourceId = $favourite['value'];

				if ($favourite['source'] === 'slideshowid') {
					if (slideshow_accessible($sourceId, PERM_READ)) {
						$dbSlideshow = get_slideshow_by_slideshowid($sourceId);

						if ($dbSlideshow) {
							$slideshows[] = array(
								'id' => $dbSlideshow['slideshowid'],
								'label' => $dbSlideshow['name']
							);
						}
					}
				}
				else {
					if (isset($dbScreens[$sourceId])) {
						$dbScreen = $dbScreens[$sourceId];

						$screens[] = array(
							'id' => $dbScreen['screenid'],
							'label' => $dbScreen['name']
						);
					}
				}
			}
		}

		return array(
			'type' => 'favouriteScreens',
			'screens' => $screens,
			'slideshows' => $slideshows
		);
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
		return array(
			'type' => 'history',
			'itemid' => $item['itemid'],
			'hasLatestGraphs' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT))
		);
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
		$data = array(
			'type' => 'host',
			'hostid' => $host['hostid'],
			'showGraphs' => (bool) $host['graphs'],
			'showScreens' => (bool) $host['screens'],
			'showTriggers' => ($host['status'] == HOST_STATUS_MONITORED),
			'hasGoTo' => $hasGoTo
		);

		if ($scripts) {
			CArrayHelper::sort($scripts, array('name'));

			foreach (array_values($scripts) as $script) {
				$data['scripts'][] = array(
					'name' => $script['name'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				);
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
		$data = array(
			'type' => 'map'
		);

		if ($scripts) {
			CArrayHelper::sort($scripts, array('name'));

			$data['hostid'] = $hostId;

			foreach (array_values($scripts) as $script) {
				$data['scripts'][] = array(
					'name' => $script['name'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				);
			}
		}

		if ($gotos) {
			$data['gotos'] = $gotos;
		}

		if ($urls) {
			foreach ($urls as $url) {
				$data['urls'][] = array(
					'label' => $url['name'],
					'url' => $url['url']
				);
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
	public static function getRefresh($widgetName, $currentRate, $multiplier = false, array $params = array()) {
		return array(
			'type' => 'refresh',
			'widgetName' => $widgetName,
			'currentRate' => $currentRate,
			'multiplier' => $multiplier,
			'params' => $params
		);
	}

	/**
	 * Prepare data for service configuration menu popup.
	 *
	 * @param string $serviceId		service id
	 * @param string $name			service name
	 * @param bool   $deletable		services without dependencies can be deleted
	 *
	 * @return array
	 */
	public static function getServiceConfiguration($serviceId, $name, $deletable) {
		return array(
			'type' => 'serviceConfiguration',
			'serviceid' => $serviceId,
			'name' => $name,
			'deletable' => $deletable
		);
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
	 * @param string $eventTime							event navigation time parameter (optional)
	 *
	 * @return array
	 */
	public static function getTrigger(array $trigger, array $acknowledge = null, $eventTime = null) {
		$hosts = array();
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

		CArrayHelper::sort($trigger['items'], array('name', 'hostname', 'itemid'));

		$hostCount = count($hosts);
		$items = array();

		foreach ($trigger['items'] as $item) {
			$items[] = array(
				'name' => ($hostCount > 1)
					? $hosts[$item['hostid']].NAME_DELIMITER.$item['name_expanded']
					: $item['name_expanded'],
				'params' => array(
					'itemid' => $item['itemid'],
					'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
						? HISTORY_GRAPH
						: HISTORY_VALUES
				)
			);
		}

		$data = array(
			'type' => 'trigger',
			'triggerid' => $trigger['triggerid'],
			'items' => $items,
			'acknowledge' => $acknowledge,
			'eventTime' => $eventTime,
			'url' => resolveTriggerUrl($trigger)
		);

		if ($showEvents) {
			$data['showEvents'] = true;
		}

		if (in_array(CWebUser::$data['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN))
				&& $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			$data['configuration'] = true;
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
		return array(
			'type' => 'triggerLog',
			'itemid' => $itemId,
			'itemName' => $itemName,
			'triggers' => $triggers
		);
	}

	/**
	 * Prepare data for trigger macro menu popup.
	 *
	 * @return array
	 */
	public static function getTriggerMacro() {
		return array(
			'type' => 'triggerMacro'
		);
	}
}
