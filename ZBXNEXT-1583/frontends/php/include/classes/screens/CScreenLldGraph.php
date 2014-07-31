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


class CScreenLldGraph extends CScreenLldGraphBase {

	/**
	 * Adds graph items to surrogate screen.
	 */
	public function addSurrogateScreenItems() {
		$discoveredGraphIds = $this->getCreatedGraphIds();
		$this->addGraphsToSurrogateScreen($discoveredGraphIds);
	}

	/**
	 * Retrieves graphs created for graph prototype given as resource for this screen item
	 * and returns array of the graph IDs.
	 *
	 * @return array
	 */
	protected function getCreatedGraphIds() {
		$graphPrototypeId = $this->screenitem['resourceid'];

		$hostId = $this->getCurrentHostId();

		// get all created (discovered) graphs for current graph host
		$allCreatedGraphs = API::Graph()->get(array(
			'hostids' => array($hostId),
			'output' => array('graphid'),
			'selectGraphDiscovery' => array('graphid', 'parent_graphid'),
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CREATED)
		));

		// collect those graph IDs where parent graph is graph prototype selected for this screen item as resource
		$createdGraphIds = array();
		foreach ($allCreatedGraphs as $graph) {
			if ($graph['graphDiscovery']['parent_graphid'] == $graphPrototypeId) {
				$createdGraphIds[] = $graph['graphid'];
			}
		}

		return $createdGraphIds;
	}

	/**
	 * Makes and adds graph items to surrogate screen.
	 *
	 * @param array $graphIds
	 */
	protected function addGraphsToSurrogateScreen(array $graphIds) {
		$screenItemTemplate = $this->getScreenItemTemplate(SCREEN_RESOURCE_GRAPH);

		foreach ($graphIds as $graphId) {
			$screenItem = $screenItemTemplate;

			$screenItem['resourceid'] = $graphId;
			$screenItem['screenitemid'] = 'z' . $graphId;

			$this->surrogateScreen['screenitems'][] = $screenItem;
		}
	}

	/**
	 * @return integer
	 */
	function getHostIdFromScreenItemResource() {
		$graphPrototype = API::GraphPrototype()->get(array(
			'graphids' => $this->screenitem['resourceid'],
			'output' => array('graphid'),
			'selectDiscoveryRule' => array('hostid')
		));
		$graphPrototype = reset($graphPrototype);

		$hostId = $graphPrototype['discoveryRule']['hostid'];

		return $hostId;
	}
}
