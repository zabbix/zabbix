<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CControllerWidgetGraphView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>				'string',
			'uniqueid' =>			'required|string',
			'dashboardid' =>		'required|db dashboard.dashboardid',
			'fields' =>				'required|array',
			'dynamic_groupid' =>	'db groups.groupid', // TODO VM: probably not needed
			'dynamic_hostid' =>		'db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $fields
			 * @var id     $fields['graphid']
			 * @var int    $fields['dynamic']             (optional)
			 */
			// TODO VM: if fields are present, check that fields have enough data
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {

		// Default values
		$default = [
			'graphid' => null,
			'uniqueid' => $this->getInput('uniqueid'),
			'dynamic' => WIDGET_SIMPLE_ITEM,
			'dynamic_hostid' => '0'
		];

		$data = $this->getInput('fields');

		// TODO VM: for testing
		$data['width'] = '500';
		$data['height'] = '100';

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		// TODO VM: (?) By using uniqid() we are adding new graph after each reload. As a result, we will have A LOT of
		//			nonexisting graphs in timeControl.objectList array in gtlc.js
		// TODO VM: (?) By not using uniqueid(), we need to find a way to distinguish cases, when same graph is added
		//			in two widgets. We can't use widgetid, becasue for new widgets, widgetid is empty string => it can be same
		// TODO VM: (?) Other option is to update gtlc.js script, to delete nonexisting entries from timeControl.objectList,
		//			each time, we are adding new one. (together with using uniqueid())
		$dataid = 'graph_'.$data['uniqueid'];
		$containerid = 'graph_container_'.$data['uniqueid'];
		$profileIdx = 'web.dashbrd';
		$profileIdx2 = $this->getInput('dashboardid');
		$updateProfile = '1'; // TODO VM: there should be constant
		$graphDims = getGraphDims($data['graphid']);
		$graphDims['graphHeight'] = $data['height'];
		$graphDims['width'] = $data['width'];
		$graph = getGraphByGraphId($data['graphid']);
		$url = '';

		$timeline = calculateTime([
			'profileIdx' => $profileIdx,
			'profileIdx2' => $profileIdx2,
			'updateProfile' => $updateProfile,
			'period' => null,
			'stime' => null
		]);

		$time_control_data = [
			'id' => $dataid,
			'containerid' => $containerid,
			'objDims' => $graphDims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'periodFixed' => CProfile::get($profileIdx.'.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD,
			'reloadOnAdd' => 1
		];

		// data for flickerscreen
		$fs_data = [
			'id' => $dataid,
			'interval' => CWebUser::getRefresh(),
			'timeline' => $timeline,
			'resourcetype' => SCREEN_RESOURCE_GRAPH, // TODO VM: (?) flickerscreen works with screen resource types
			'profileIdx' => $profileIdx,
			'profileIdx2' => $profileIdx2,
			'updateProfile' => $updateProfile,
		];

		if ($data['dynamic'] == WIDGET_DYNAMIC_ITEM && $data['dynamic_hostid']) {
			// get host
			$hosts = API::Host()->get([
				'hostids' => $data['dynamic_hostid'],
				'output' => ['hostid', 'name']
			]);
			$host = reset($hosts);

			// get graph
			$graph = API::Graph()->get([
				'graphids' => $data['graphid'],
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
						$data['dynamic_hostid'],
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
						$data['dynamic_hostid'],
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
			$url = (in_array($graph['graphtype'], [GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED]))
				? 'chart7.php'
				: 'chart3.php';
			$url = new CUrl($url);

			foreach ($graph as $name => $value) {
				if ($name == 'width' || $name == 'height') {
					continue;
				}
				$url->setArgument($name, $value);
			}

			$newGraphItems = getSameGraphItemsForHost($graph['gitems'], $data['dynamic_hostid'], false);
			foreach ($newGraphItems as $newGraphItem) {
				unset($newGraphItem['gitemid'], $newGraphItem['graphid']);

				foreach ($newGraphItem as $name => $value) {
					$url->setArgument('items['.$newGraphItem['itemid'].']['.$name.']', $value);
				}
			}

			$url->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name']);
			$url = $url->getUrl();
		}

		$is_default = false; // TODO VM: (?) what it logicaly means?
		if (in_array($graphDims['graphtype'], [GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED])) {
			// $url may be empty, if it is dynamic graph, but no host is selected
			if ($data['dynamic'] == SCREEN_SIMPLE_ITEM || $url === '') { // TODO VM: WIDGET_SIMPLE_ITEM
				$url = 'chart6.php?graphid='.$data['graphid']/*.'&screenid='.$this->screenitem['screenid']*/;
				$is_default = true;
			}

			$timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_graphid($data['graphid']));

			$time_control_data['src'] = $url.'&width='.$data['width']
				.'&height='.$data['height'].'&legend='.$graph['show_legend']
				.'&graph3d='.$graph['show_3d'].'&updateProfile='.$updateProfile // TODO VM: $updateProfile must be int at this point
				.'&profileIdx='.$profileIdx.'&profileIdx2='.$profileIdx2;
//			$time_control_data['src'] .= ($this->mode == SCREEN_MODE_EDIT) // TODO VM (?): Implement as onEdit trigger.. or not.
//				? '&period=3600&stime='.date(TIMESTAMP_FORMAT, time())
//				: '&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'];
			// TODO VM: (?) was ..&stime='.$timeline['stimeNow'] - it means, graph will ALWAYS be "till now", not taking into account scrollbar
			$time_control_data['src'] .= '&period='.$timeline['period'].'&stime='.$timeline['stime'];
		}
		else {
			// $url may be empty, if it is dynamic graph, but no host is selected
			if ($data['dynamic'] == WIDGET_SIMPLE_ITEM || $url === '') {
				$url = 'chart2.php?graphid='.$data['graphid']/*.'&screenid='.$this->screenitem['screenid']*/;
				$is_default = true;
			}

//			if ($this->mode != SCREEN_MODE_EDIT && $graphId) { // TODO VM: implement edit part as trigger - or just hide the SBox in edit
//				if ($this->mode == SCREEN_MODE_PREVIEW) {
//					$time_control_data['loadSBox'] = 1;
//				}
//			}
			if ($data['graphid']) {
				$time_control_data['loadSBox'] = 1;
			}

			$time_control_data['src'] = $url.'&width='.$data['width']
				.'&height='.$data['height'].'&legend='.$graph['show_legend'].'&updateProfile='.$updateProfile // TODO VM: $updateProfile must be int at this point
				.'&profileIdx='.$profileIdx.'&profileIdx2='.$profileIdx2;
//			$time_control_data['src'] .= ($this->mode == SCREEN_MODE_EDIT) // TODO VM (?): Implement as onEdit trigger.. or not.
//				? '&period=3600&stime='.date(TIMESTAMP_FORMAT, time())
//				: '&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'];
			// TODO VM: (?) was ..&stime='.$timeline['stimeNow'] - it means, graph will ALWAYS be "till now", not taking into account scrollbar
			$time_control_data['src'] .= '&period='.$timeline['period'].'&stime='.$timeline['stime'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_GRAPH]),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'graph' => [
				'graphid' => $data['graphid'],
				'dataid' => $dataid,
				'containerid' => $containerid,
				'timestamp' => time()
			],
			'timeline' => $timeline,
			'time_control_data' => $time_control_data,
			'is_default' => $is_default,
			'fs_data' => $fs_data
		]));
	}
}
