<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CScreenLldSimpleGraph extends CScreenLldGraphBase {

	/**
	 * Adds simple graphs to surrogate screen.
	 */
	protected function addSurrogateScreenItems() {
		$discoveredItemIds = $this->getCreatedItemIds();
		$this->addSimpleGraphsToSurrogateScreen($discoveredItemIds);
	}

	/**
	 * Retrieves items created for item prototype given as resource for this screen item
	 * and returns array of the item IDs.
	 *
	 * @return array
	 */
	protected function getCreatedItemIds() {
		$itemPrototypeId = $this->screenitem['resourceid'];

		$hostId = $this->getCurrentHostId();

		// get all created (discovered) items for current host
		$allCreatedItems = API::Item()->get(array(
			'hostids' => array($hostId),
			'output' => array('itemid'),
			'selectItemDiscovery' => array('itemid', 'parent_itemid'),
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CREATED)
		));

		// collect those item IDs where parent item is item prototype selected for this screen item as resource
		$createdItemIds = array();
		foreach ($allCreatedItems as $item) {
			if ($item['itemDiscovery']['parent_itemid'] == $itemPrototypeId) {
				$createdItemIds[] = $item['itemid'];
			}
		}

		return $createdItemIds;
	}

	/**
	 * Makes and adds simple item graph items to surrogate screen from given item IDs.
	 *
	 * @param array $itemIds
	 */
	protected function addSimpleGraphsToSurrogateScreen(array $itemIds) {
		$screenItemTemplate = $this->getScreenItemTemplate(SCREEN_RESOURCE_SIMPLE_GRAPH);

		foreach ($itemIds as $itemId) {
			$screenItem = $screenItemTemplate;

			$screenItem['resourceid'] = $itemId;
			$screenItem['screenitemid'] = 'z' . $itemId;
			$screenItem['url'] = $this->screenitem['url'];

			$this->surrogateScreen['screenitems'][] = $screenItem;
		}
	}

	/**
	 * @return mixed
	 */
	protected function getHostIdFromScreenItemResource() {
		$itemPrototype = API::ItemPrototype()->get(array(
			'itemids' => $this->screenitem['resourceid'],
			'output' => array('itemid'),
			'selectDiscoveryRule' => array('hostid')
		));
		$itemPrototype = reset($itemPrototype);

		$hostId = $itemPrototype['discoveryRule']['hostid'];

		return $hostId;
	}
}
