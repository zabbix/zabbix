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


class CScreenGraph extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$resourceId = isset($this->screenitem['real_resourceid'])
			? $this->screenitem['real_resourceid']
			: $this->screenitem['resourceid'];
		$containerId = 'graph_container_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$graphDims = getGraphDims($resourceId);
		$graphDims['graphHeight'] = (int) $this->screenitem['height'];
		$graphDims['width'] = (int) $this->screenitem['width'];
		$graph = getGraphByGraphId($resourceId);
		$src = null;

		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid) {
			// get host
			$hosts = API::Host()->get([
				'hostids' => $this->hostid,
				'output' => ['hostid', 'name']
			]);
			$host = reset($hosts);

			// get graph
			$graph = API::Graph()->get([
				'graphids' => $resourceId,
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => ['hostid'],
				'selectGraphItems' => API_OUTPUT_EXTEND
			]);
			$graph = reset($graph);

			// if items from one host we change them, or set calculated if not exist on that host
			if (count($graph['hosts']) == 1) {
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
					$newDynamic = getSameGraphItemsForHost(
						[['itemid' => $graph['ymax_itemid']]],
						$this->hostid,
						false
					);
					$newDynamic = reset($newDynamic);

					if (isset($newDynamic['itemid']) && $newDynamic['itemid'] > 0) {
						$graph['ymax_itemid'] = $newDynamic['itemid'];
					}
					else {
						$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}

				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
					$newDynamic = getSameGraphItemsForHost(
						[['itemid' => $graph['ymin_itemid']]],
						$this->hostid,
						false
					);
					$newDynamic = reset($newDynamic);

					if (isset($newDynamic['itemid']) && $newDynamic['itemid'] > 0) {
						$graph['ymin_itemid'] = $newDynamic['itemid'];
					}
					else {
						$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}
			}

			// get url
			if ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED) {
				$src = (new CUrl('chart7.php'))
					->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name'])
					->setArgument('graphtype', $graph['graphtype'])
					->setArgument('graph3d', $graph['show_3d'])
					->setArgument('legend', $graph['show_legend']);
			}
			else {
				$src = (new CUrl('chart3.php'))
					->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name'])
					->setArgument('ymin_type', $graph['ymin_type'])
					->setArgument('ymax_type', $graph['ymax_type'])
					->setArgument('ymin_itemid', $graph['ymin_itemid'])
					->setArgument('ymax_itemid', $graph['ymax_itemid'])
					->setArgument('legend', $graph['show_legend'])
					->setArgument('showworkperiod', $graph['show_work_period'])
					->setArgument('showtriggers', $graph['show_triggers'])
					->setArgument('graphtype', $graph['graphtype'])
					->setArgument('yaxismin', $graph['yaxismin'])
					->setArgument('yaxismax', $graph['yaxismax'])
					->setArgument('percent_left', $graph['percent_left'])
					->setArgument('percent_right', $graph['percent_right']);
			}

			$newGraphItems = getSameGraphItemsForHost($graph['gitems'], $this->hostid, false);
			foreach ($newGraphItems as $i => &$newGraphItem) {
				unset($newGraphItem['gitemid'], $newGraphItem['graphid']);
			}
			unset($newGraphItem);

			$src->setArgument('items', $newGraphItems);
		}

		// get time control
		$timeControlData = [
			'id' => $this->getDataId(),
			'containerid' => $containerId,
			'objDims' => $graphDims,
			'loadSBox' => 0,
			'loadImage' => 1
		];

		$isDefault = false;
		if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || $src === null) {
				$src = (new CUrl('chart6.php'))
					->setArgument('graphid', $resourceId)
					->setArgument('screenid', $this->screenitem['screenid']);

				$isDefault = true;
			}

			$src->setArgument('graph3d', $graph['show_3d']);
		}
		else {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || $src === null) {
				$src = (new CUrl('chart2.php'))
					->setArgument('graphid', $resourceId)
					->setArgument('screenid', $this->screenitem['screenid']);

				$isDefault = true;
			}

			if ($this->mode != SCREEN_MODE_EDIT && $graph['graphid']) {
				if ($this->mode == SCREEN_MODE_PREVIEW) {
					$timeControlData['loadSBox'] = 1;
				}
			}
		}

		$src
			->setArgument('width', $this->screenitem['width'])
			->setArgument('height', $this->screenitem['height'])
			->setArgument('legend', $graph['show_legend'])
			->setArgument('profileIdx', $this->profileIdx)
			->setArgument('profileIdx2', $this->profileIdx2);

		if ($this->mode == SCREEN_MODE_EDIT) {
			$src
				->setArgument('from', ZBX_PERIOD_DEFAULT_FROM)
				->setArgument('to', ZBX_PERIOD_DEFAULT_TO);
		}
		else {
			$src
				->setArgument('from', $this->timeline['from'])
				->setArgument('to', $this->timeline['to']);
		}

		$timeControlData['src'] = $src->getUrl();

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			return 'timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.
				CJs::encodeJson($timeControlData).')';
		}
		else {
			if ($this->mode == SCREEN_MODE_SLIDESHOW) {
				insert_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.
					CJs::encodeJson($timeControlData).');'
				);
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).
					', '.CJs::encodeJson($timeControlData).');'
				);
			}

			if (($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_SLIDESHOW) || !$isDefault) {
				$item = new CDiv();
			}
			elseif ($this->mode == SCREEN_MODE_PREVIEW) {
				$item = new CLink(null, 'charts.php?graphid='.$resourceId.'&from='.$this->timeline['from'].
					'&to='.$this->timeline['to']
				);
			}

			$item
				->addClass(ZBX_STYLE_GRAPH_WRAPPER)
				->setId($containerId);

			return $this->getOutput($item);
		}
	}
}
