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
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];
		$graph = getGraphByGraphId($resourceId);
		$graphId = $graph['graphid'];
		$legend = $graph['show_legend'];
		$graph3d = $graph['show_3d'];

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
			$this->screenitem['url'] = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
				? 'chart7.php'
				: 'chart3.php';
			$this->screenitem['url'] = new CUrl($this->screenitem['url']);

			foreach ($graph as $name => $value) {
				if ($name == 'width' || $name == 'height') {
					continue;
				}
				$this->screenitem['url']->setArgument($name, $value);
			}

			$newGraphItems = getSameGraphItemsForHost($graph['gitems'], $this->hostid, false);
			foreach ($newGraphItems as $newGraphItem) {
				unset($newGraphItem['gitemid'], $newGraphItem['graphid']);

				foreach ($newGraphItem as $name => $value) {
					$this->screenitem['url']->setArgument('items['.$newGraphItem['itemid'].']['.$name.']', $value);
				}
			}

			$this->screenitem['url']->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name']);
			$this->screenitem['url'] = $this->screenitem['url']->getUrl();
		}

		// get time control
		$timeControlData = [
			'id' => $this->getDataId(),
			'containerid' => $containerId,
			'objDims' => $graphDims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		];

		$isDefault = false;
		if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || $this->screenitem['url'] === '') {
				$this->screenitem['url'] = 'chart6.php?graphid='.$resourceId.'&screenid='.$this->screenitem['screenid'];
				$isDefault = true;
			}

			$this->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_graphid($resourceId));

			$timeControlData['src'] = $this->screenitem['url'].'&width='.$this->screenitem['width']
				.'&height='.$this->screenitem['height'].'&legend='.$legend
				.'&graph3d='.$graph3d.$this->getProfileUrlParams();
			$timeControlData['src'] .= ($this->mode == SCREEN_MODE_EDIT)
				? '&period=3600&stime='.date(TIMESTAMP_FORMAT, time())
				: '&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'];
		}
		else {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || $this->screenitem['url'] === '') {
				$this->screenitem['url'] = 'chart2.php?graphid='.$resourceId.'&screenid='.$this->screenitem['screenid'];
				$isDefault = true;
			}

			if ($this->mode != SCREEN_MODE_EDIT && $graphId) {
				if ($this->mode == SCREEN_MODE_PREVIEW) {
					$timeControlData['loadSBox'] = 1;
				}
			}

			$timeControlData['src'] = $this->screenitem['url'].'&width='.$this->screenitem['width']
				.'&height='.$this->screenitem['height'].'&legend='.$legend.$this->getProfileUrlParams();
			$timeControlData['src'] .= ($this->mode == SCREEN_MODE_EDIT)
				? '&period=3600&stime='.date(TIMESTAMP_FORMAT, time())
				: '&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'];
		}

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			return 'timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '
				.CJs::encodeJson($timeControlData).')';
		}
		else {
			if ($this->mode == SCREEN_MODE_SLIDESHOW) {
				insert_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '
					.CJs::encodeJson($timeControlData).');'
				);
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '
					.CJs::encodeJson($timeControlData).');'
				);
			}

			if (($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_SLIDESHOW) || !$isDefault) {
				$item = new CDiv();
			}
			elseif ($this->mode == SCREEN_MODE_PREVIEW) {
				$item = new CLink(null, 'charts.php?graphid='.$resourceId.'&period='.$this->timeline['period'].
						'&stime='.$this->timeline['stimeNow']);
			}
			$item->setAttribute('id', $containerId);

			return $this->getOutput($item);
		}
	}
}
