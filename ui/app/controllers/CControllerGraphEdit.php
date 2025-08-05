<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerGraphEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>			'required|in '.implode(',', ['host', 'template']),
			'hostid' =>				'db hosts.hostid',
			'graphid' =>			'db graphs.graphid',
			'name' =>				'string',
			'width' =>				'db graphs.width',
			'height' => 			'db graphs.height',
			'graphtype' =>			'db graphs.graphtype|in '.implode(',', [
				GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED, GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED
			]),
			'show_legend' =>		'db graphs.show_legend|in 0,1',
			'show_3d' =>			'db graphs.show_3d|in 0,1',
			'show_work_period' =>	'db graphs.show_work_period|in 0,1',
			'show_triggers' =>		'db graphs.show_triggers|in 0,1',
			'percent_left' =>		'string',
			'percent_right' =>		'string',
			'ymin_type' =>			'db graphs.ymin_type|in '.implode(',', [
				GRAPH_YAXIS_TYPE_CALCULATED, GRAPH_YAXIS_TYPE_FIXED, GRAPH_YAXIS_TYPE_ITEM_VALUE
			]),
			'ymax_type' =>			'db graphs.ymax_type|in '.implode(',', [
				GRAPH_YAXIS_TYPE_CALCULATED, GRAPH_YAXIS_TYPE_FIXED, GRAPH_YAXIS_TYPE_ITEM_VALUE
			]),
			'yaxismin' =>			'string',
			'yaxismax' =>			'string',
			'ymin_itemid' =>		'db graphs.ymin_itemid',
			'ymax_itemid' =>		'db graphs.ymax_itemid',
			'items' =>				'array',
			'clone' =>				'in 1',
			'visible' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if ($this->hasInput('graphid')) {
			$graphid = $this->getInput('graphid');

			if ($graphid) {
				$graph = (bool) API::Graph()->get([
					'output' => [],
					'graphids' => $graphid,
					'editable' => true
				]);

				if (!$graph) {
					return false;
				}
			}
		}

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction() {
		$data = [
			'graphid' => $this->getInput('graphid', 0),
			'hostid' => $this->getInput('hostid', 0),
			'context' => $this->getInput('context'),
			'readonly' => false,
			'discovered' => false,
			'discovered_prototype' => false
		];

		if ($data['graphid'] != 0) {
			$options = [
				'output' => ['name', 'width', 'height', 'ymin_type', 'ymax_type', 'ymin_itemid', 'yaxismin',
					'ymax_itemid', 'yaxismax', 'show_work_period', 'show_triggers', 'graphtype', 'show_legend',
					'show_3d', 'percent_left', 'percent_right', 'templateid', 'flags'
				],
				'selectHosts' => ['hostid'],
				'graphids' => $data['graphid'],
				'selectDiscoveryRule' => ['itemid', 'name'],
				'selectDiscoveryData' => ['parent_graphid']
			];

			$graph = API::Graph()->get($options);
			$graph = reset($graph);

			if ($graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
				$db_parent = API::GraphPrototype()->get([
					'graphids' => $graph['discoveryData']['parent_graphid'],
					'selectDiscoveryRule' => ['itemid'],
					'selectDiscoveryRulePrototype' => ['itemid'],
					'nopermissions' => true
				]);
				$db_parent = reset($db_parent);

				$parent_lld = $db_parent['discoveryRule'] ?: $db_parent['discoveryRulePrototype'];
				$graph['discoveryData']['lldruleid'] = $parent_lld['itemid'];
			}

			$data += $graph;

			$data['discovered'] = $graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED;
			$data['yaxismin'] = sprintf('%.'.ZBX_FLOAT_DIG.'G', $graph['yaxismin']);
			$data['yaxismax'] = sprintf('%.'.ZBX_FLOAT_DIG.'G', $graph['yaxismax']);

			if ($data['hostid'] == 0) {
				$host = reset($graph['hosts']);
				$data['hostid'] = $host['hostid'];
			}

			$data['templates'] = makeGraphTemplatesHtml($graph['graphid'],
				getGraphParentTemplates([$graph], ZBX_FLAG_DISCOVERY_NORMAL), ZBX_FLAG_DISCOVERY_NORMAL,
				CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);

			$data['items'] = API::GraphItem()->get([
				'output' => ['gitemid', 'graphid', 'itemid', 'type', 'drawtype', 'yaxisside', 'calc_fnc', 'color',
					'sortorder'
				],
				'graphids' => $data['graphid'],
				'sortfield' => 'gitemid'
			]);
		}
		else {
			$data['name'] = $this->getInput('name', '');
			$data['graphtype'] = $this->getInput('graphtype', GRAPH_TYPE_NORMAL);
			$data['templates'] = [];

			if ($data['graphtype'] == GRAPH_TYPE_PIE || $data['graphtype'] == GRAPH_TYPE_EXPLODED) {
				$data['width'] = $this->getInput('width', 400);
				$data['height'] = $this->getInput('height', 300);
			}
			else {
				$data['width'] = $this->getInput('width', 900);
				$data['height'] = $this->getInput('height', 200);
			}

			$data['percent_left'] = $this->getInput('percent_left', 0);
			$data['percent_right'] = $this->getInput('percent_right', 0);
			$data['ymin_type'] = $this->getInput('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$data['ymax_type'] = $this->getInput('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$data['yaxismin'] = $this->getInput('yaxismin', 0);
			$data['yaxismax'] = $this->getInput('yaxismax', 100);
			$data['ymin_itemid'] = $this->getInput('ymin_itemid', 0);
			$data['ymax_itemid'] = $this->getInput('ymax_itemid', 0);
			$data['visible'] = $this->getInput('visible', []);

			if (array_key_exists('percent_left', $data['visible'])) {
				$data['percent_left'] = $this->getInput('percent_left', 0);
			}

			if (array_key_exists('percent_right', $data['visible'])) {
				$data['percent_right'] = $this->getInput('percent_right', 0);
			}

			if ($this->hasInput('clone')) {
				$data['show_work_period'] = $this->hasInput('show_work_period');
				$data['show_triggers'] = $this->hasInput('show_triggers');
				$data['show_legend'] = $this->hasInput('show_legend');
				$data['show_3d'] = $this->hasInput('show_3d');
			}
			else {
				$data['show_work_period'] = $this->getInput('show_work_period', 1);
				$data['show_triggers'] = $this->getInput('show_triggers', 1);
				$data['show_legend'] = $this->getInput('show_legend', 1);
				$data['show_3d'] = $this->getInput('show_3d', 0);
			}

			$gitems = [];

			foreach ($this->getInput('items', []) as $gitem) {
				if ((array_key_exists('itemid', $gitem) && ctype_digit($gitem['itemid']))
						&& (array_key_exists('type', $gitem) && ctype_digit($gitem['type']))
						&& (array_key_exists('drawtype', $gitem) && ctype_digit($gitem['drawtype']))) {
					$gitems[] = $gitem;
				}
			}

			$data['items'] = $gitems;
		}

		if ($data['ymax_itemid'] || $data['ymin_itemid']) {
			$options = [
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'selectHosts' => ['name'],
				'itemids' => [$data['ymax_itemid'], $data['ymin_itemid']],
				'webitems' => true,
				'preservekeys' => true
			];

			$data['yaxis_items'] = API::Item()->get($options);
		}

		// items
		if ($data['items']) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'itemids' => array_column($data['items'], 'itemid'),
				'webitems' => true,
				'preservekeys' => true
			]);

			if ($items) {
				foreach ($data['items'] as &$item) {
					$host = reset($items[$item['itemid']]['hosts']);

					$item['host'] = $host['name'];
					$item['hostid'] = $items[$item['itemid']]['hostid'];
					$item['name'] = $items[$item['itemid']]['name'];
					$item['flags'] = $items[$item['itemid']]['flags'];
				}
				unset($item);
			}
		}

		// Set ymin_item_name.
		$data['ymin_item_name'] = '';
		$data['ymax_item_name'] = '';

		if ($data['ymin_itemid'] != 0 || $data['ymax_itemid'] != 0) {
			$items = API::Item()->get([
				'output' => ['itemid', 'name'],
				'selectHosts' => ['name'],
				'itemids' => array_filter([$data['ymin_itemid'], $data['ymax_itemid']]),
				'webitems' => true,
				'preservekeys' => true
			]);

			if ($data['ymin_itemid'] != 0 && array_key_exists($data['ymin_itemid'], $items)) {
				$item = $items[$data['ymin_itemid']];
				$data['ymin_item_name'] = $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
			}

			if ($data['ymax_itemid'] != 0 && array_key_exists($data['ymax_itemid'], $items)) {
				$item = $items[$data['ymax_itemid']];
				$data['ymax_item_name'] = $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
			}
		}

		$data['items'] = array_values($data['items']);
		$item_count = count($data['items']);

		for ($i = 0; $i < $item_count - 1;) {
			$next = $i + 1;

			while (!array_key_exists($next, $data['items']) && $next < ($item_count - 1)) {
				$next++;
			}

			if ($data['items'][$next] && $data['items'][$i]['sortorder'] == $data['items'][$next]['sortorder']) {
				for ($j = $next; $j < $item_count; $j++) {
					if ($data['items'][$j - 1]['sortorder'] >= $data['items'][$j]['sortorder']) {
						$data['items'][$j]['sortorder']++;
					}
				}
			}

			$i = $next;
		}

		CArrayHelper::sort($data['items'], ['sortorder']);
		$data['items'] = array_values($data['items']);

		$data += [
			'is_template' => $data['hostid'] == 0 ? false : isTemplate($data['hostid']),
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
