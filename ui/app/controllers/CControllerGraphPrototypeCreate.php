<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerGraphPrototypeCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			'graphprototype.get', ['name' => '{name}', 'hostid' => '{hostid}']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'context' => ['string', 'required', 'in' => ['host', 'template']],
			'hostid' => ['db hosts.hostid', 'required'],
			'parent_discoveryid' => ['db items.itemid', 'required'],
			'name' => ['string', 'required', 'not_empty'],
			'width' => ['db graphs.width', 'required', 'min' => CGraphGeneralHelper::GRAPH_WIDTH_MIN,
				'max' => CGraphGeneralHelper::GRAPH_WIDTH_MAX
			],
			'height' => ['db graphs.height', 'required', 'min' => CGraphGeneralHelper::GRAPH_HEIGHT_MIN,
				'max' => CGraphGeneralHelper::GRAPH_HEIGHT_MAX
			],
			'graphtype' => ['db graphs.graphtype', 'required',
				'in' => [GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED, GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED]
			],
			'show_legend' => ['boolean'],
			'show_3d' => ['boolean'],
			'show_work_period' => ['boolean'],
			'show_triggers' => ['boolean'],
			'percent_left' => ['float', 'min' => 0, 'max' => 100, 'decimal_limit' => 4,
				'when' => ['graphtype', 'in' => [GRAPH_TYPE_NORMAL]]
			],
			'percent_right' => ['float', 'min' => 0, 'max' => 100, 'decimal_limit' => 4,
				'when' => ['graphtype', 'in' => [GRAPH_TYPE_NORMAL]]
			],
			'ymin_type' => ['db graphs.ymin_type',
				'in' => [GRAPH_YAXIS_TYPE_CALCULATED, GRAPH_YAXIS_TYPE_FIXED, GRAPH_YAXIS_TYPE_ITEM_VALUE]
			],
			'ymax_type' => ['db graphs.ymax_type',
				'in' => [GRAPH_YAXIS_TYPE_CALCULATED, GRAPH_YAXIS_TYPE_FIXED, GRAPH_YAXIS_TYPE_ITEM_VALUE]
			],
			'yaxismin' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class],
				'when' => ['ymin_type', 'in' => [GRAPH_YAXIS_TYPE_FIXED]]
			],
			'yaxismax' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class],
				'when' => ['ymax_type', 'in' => [GRAPH_YAXIS_TYPE_FIXED]]
			],
			'ymin_itemid' => ['db graphs.ymin_itemid', 'required',
				'when' => ['ymin_type', 'in' => [GRAPH_YAXIS_TYPE_ITEM_VALUE]]
			],
			'ymax_itemid' => ['db graphs.ymax_itemid', 'required',
				'when' => ['ymax_type', 'in' => [GRAPH_YAXIS_TYPE_ITEM_VALUE]]
			],
			'items' => ['objects', 'required', 'not_empty',
				'fields' => [
					'gitemid' => ['db graphs_items.gitemid'],
					'itemid' => ['db graphs_items.itemid', 'required'],
					'sortorder' => ['db graphs_items.sortorder', 'required'],
					'flags' => ['integer', 'required',
						'in' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE]
					],
					'type' => ['integer', 'required', 'in' => [GRAPH_ITEM_SIMPLE, GRAPH_TYPE_STACKED, GRAPH_ITEM_SUM]],
					'calc_fnc' => [
						['db graphs_items.calc_fnc', 'required',
							'in' => [CALC_FNC_MIN, CALC_FNC_AVG, CALC_FNC_MAX, CALC_FNC_ALL],
							'when' => ['../graphtype', 'in' => [GRAPH_TYPE_NORMAL]]
						],
						['db graphs_items.calc_fnc', 'required',
							'in' => [CALC_FNC_MIN, CALC_FNC_AVG, CALC_FNC_MAX],
							'when' => ['../graphtype', 'in' => [GRAPH_TYPE_STACKED]]
						],
						['db graphs_items.calc_fnc', 'required',
							'in' => [CALC_FNC_MIN, CALC_FNC_AVG, CALC_FNC_MAX, CALC_FNC_LST],
							'when' => ['../graphtype', 'in' => [GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED]]
						]
					],
					'drawtype' => ['db graphs_items.drawtype', 'required', 'in' => graph_item_drawtypes()],
					'yaxisside' => ['db graphs_items.yaxisside', 'required',
						'in' => [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT]
					],
					'color' => ['db graphs_items.color', 'required']
				],
				'count_values' => [
					[
						'field_rules' => ['flags', 'in' => [ZBX_FLAG_DISCOVERY_PROTOTYPE]],
						'min' => 1,
						'message' => _('At least one item must be a prototype')
					],
					[
						'field_rules' => ['type', 'in' => [GRAPH_ITEM_SUM]],
						'max' => 1,
						'message' => _('Cannot add more than one item with type "Graph sum"')
					]
				]
			],
			'discover' => ['db graphs.discover', 'required',
				'in' => [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add graph prototype'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$discovery_rule = API::DiscoveryRule()->get([
			'output' => ['itemid', 'hostid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		]);

		if (!$discovery_rule) {
			$discovery_rule = API::DiscoveryRulePrototype()->get([
				'output' => ['itemid', 'hostid'],
				'itemids' => $this->getInput('parent_discoveryid'),
				'editable' => true
			]);
		}

		if (!$discovery_rule) {
			return false;
		}

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$graph_prototype = $this->getInputAll();
		$graph_prototype['gitems'] = $graph_prototype['items'];
		unset($graph_prototype['items']);

		try {
			DBstart();

			$result = API::GraphPrototype()->create($graph_prototype);

			if ($result === false) {
				throw new Exception();
			}

			$result = DBend();
		}
		catch (Exception) {
			$result = false;

			DBend(false);
		}

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Graph prototype added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add graph prototype'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
