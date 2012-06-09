<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFlickerfreeScreenGraph extends CFlickerfreeScreenItem {

	private $hostid;
	private $stime;

	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->hostid = get_request('hostid', 0);
		$this->stime = get_request('stime', null);
	}

	public function get() {
		$domGraphid = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$containerid = 'graph_cont_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$graphDims = getGraphDims($this->screenitem['resourceid']);
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];
		$graph = get_graph_by_graphid($this->screenitem['resourceid']);
		$graphid = $graph['graphid'];
		$legend = $graph['show_legend'];
		$graph3d = $graph['show_3d'];

		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			// get host
			$hosts = API::Host()->get(array(
				'hostids' => $this->hostid,
				'output' => array('hostid', 'host')
			));
			$host = reset($hosts);

			// get graph
			$graph = API::Graph()->get(array(
				'graphids' => $this->screenitem['resourceid'],
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => API_OUTPUT_REFER,
				'selectGraphItems' => API_OUTPUT_EXTEND
			));
			$graph = reset($graph);

			// if items from one host we change them, or set calculated if not exist on that host
			if (count($graph['hosts']) == 1) {
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
					$newDinamic = get_same_graphitems_for_host(
						array(array('itemid' => $graph['ymax_itemid'])),
						$this->hostid,
						false
					);
					$newDinamic = reset($newDinamic);

					if (isset($newDinamic['itemid']) && $newDinamic['itemid'] > 0) {
						$graph['ymax_itemid'] = $newDinamic['itemid'];
					}
					else {
						$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}

				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
					$newDinamic = get_same_graphitems_for_host(
						array(array('itemid' => $graph['ymin_itemid'])),
						$this->hostid,
						false
					);
					$newDinamic = reset($newDinamic);

					if (isset($newDinamic['itemid']) && $newDinamic['itemid'] > 0) {
						$graph['ymin_itemid'] = $newDinamic['itemid'];
					}
					else {
						$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}
			}

			// get url
			$url = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
				? 'chart7.php'
				: 'chart3.php';
			$url = new CUrl($url);

			foreach ($graph as $name => $value) {
				if ($name == 'width' || $name == 'height') {
					continue;
				}
				$url->setArgument($name, $value);
			}

			$newGraphItems = get_same_graphitems_for_host($graph['gitems'], $this->hostid, false);
			foreach ($newGraphItems as $newGraphItem) {
				unset($newGraphItem['gitemid'], $newGraphItem['graphid']);

				foreach ($newGraphItem as $name => $value) {
					$url->setArgument('items['.$newGraphItem['itemid'].']['.$name.']', $value);
				}
			}
			$url->setArgument('name', $host['host'].': '.$graph['name']);
			$url = $url->getUrl();
		}

		// get timecontroll
		$timecontrollData = array(
			'id' => $this->screenitem['resourceid'],
			'domid' => $domGraphid,
			'containerid' => $containerid,
			'objDims' => $graphDims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'loadScroll' => 0,
			'dynamic' => 0,
			'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		);

		$isDefault = false;
		if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || empty($url)) {
				$url = 'chart6.php?graphid='.$this->screenitem['resourceid'];
				$isDefault = true;
			}

			$timeline = array();
			$timeline['period'] = $this->effectiveperiod;
			$timeline['starttime'] = date('YmdHis', get_min_itemclock_by_graphid($this->screenitem['resourceid']));

			if (isset($this->stime)) {
				$timeline['usertime'] = date('YmdHis', zbxDateToTime($this->stime) + $timeline['period']);
			}

			$src = $url.'&width='.$this->screenitem['width'].'&height='.$this->screenitem['height'].'&legend='.$this->screenitem['legend'].'&graph3d='.$graph3d.'&period='.$this->effectiveperiod.url_param('stime');

			$timecontrollData['src'] = $src;
		}
		else {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || empty($url)) {
				$url = 'chart2.php?graphid='.$this->screenitem['resourceid'];
				$isDefault = true;
			}

			$src = $url.'&width='.$this->screenitem['width'].'&height='.$this->screenitem['height'].'&period='.$this->effectiveperiod.url_param('stime');

			$timeline = array();
			if ($this->mode != SCREEN_MODE_EDIT && !empty($graphid)) {
				$timeline['period'] = $this->effectiveperiod;
				$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

				if (!empty($this->stime)) {
					$timeline['usertime'] = date('YmdHis', zbxDateToTime($this->stime) + $timeline['period']);
				}
				if ($this->mode == SCREEN_MODE_PREVIEW) {
					$timecontrollData['loadSBox'] = 1;
				}
			}
			$timecontrollData['src'] = $src;
		}

		// process output
		if ($this->mode || !$isDefault) {
			$element = new CDiv();
		}
		else {
			$element = new CLink(null, $this->action);
		}
		$element->setAttribute('id', $containerid);

		$output = array($element);
		if ($this->mode == SCREEN_MODE_EDIT) {
			$output[] = BR();
			$output[] = new CLink(_('Change'), $this->action);
		}

		if ($this->mode == SCREEN_MODE_VIEW) {
			insert_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timecontrollData).');');
		}
		else {
			zbx_add_post_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timecontrollData).');');
		}

		return $output;
	}
}
