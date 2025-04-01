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


class CControllerGraphUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'graphid' =>			'fatal|required|db graphs.graphid',
			'context' =>			'required|in '.implode(',', ['host', 'template']),
			'hostid' =>				'required|db hosts.hostid',
			'name' =>				'required|db graphs.name|not_empty',
			'width' =>				'required|db graphs.width|not_empty|ge 20|le 65535',
			'height' => 			'required|db graphs.height|not_empty|ge 20|le 65535',
			'graphtype' =>			'required|db graphs.graphtype|in '.implode(',', [
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
			'items' =>				'required|array'
		];

		$ret = $this->validateInput($fields);

		$graphtype = $this->getInput('graphtype');

		if ($ret && $graphtype == GRAPH_TYPE_NORMAL) {
			$ret = $this->validatePercentFields();
		}

		if ($ret && ($graphtype == GRAPH_TYPE_NORMAL || $graphtype == GRAPH_TYPE_STACKED)) {
			$ret = $this->validateYAxisFields();
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update graph'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if ($this->getInput('hostid') && !isWritableHostTemplates([$this->getInput('hostid')])) {
			return false;
		}

		$graph = (bool) API::Graph()->get([
			'output' => [],
			'graphids' => $this->getInput('graphid'),
			'editable' => true
		]);

		if (!$graph) {
			return false;
		}

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		try {
			$gitems = [];

			foreach ($this->getInput('items', []) as $gitem) {
				if (array_key_exists('type', $gitem) && ctype_digit($gitem['type'])
						&& array_key_exists('drawtype', $gitem) && ctype_digit($gitem['drawtype'])) {
					if (array_key_exists('gitemid', $gitem) && !$gitem['gitemid']) {
						unset($gitem['gitemid']);
					}

					$gitems[] = $gitem;
				}
			}

			$graph = [
				'graphid' => $this->getInput('graphid'),
				'name' => $this->getInput('name'),
				'width' => $this->getInput('width'),
				'height' => $this->getInput('height'),
				'ymin_type' => $this->getInput('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED),
				'ymax_type' => $this->getInput('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED),
				'yaxismin' => $this->getInput('yaxismin', 0),
				'yaxismax' => $this->getInput('yaxismax', 100),
				'show_work_period' => $this->getInput('show_work_period', 0),
				'show_triggers' => $this->getInput('show_triggers', 0),
				'graphtype' => $this->getInput('graphtype'),
				'show_legend' => $this->getInput('show_legend', 0),
				'show_3d' => $this->getInput('show_3d', 0),
				'percent_left' => $this->getInput('percent_left', 0),
				'percent_right' => $this->getInput('percent_right', 0),
				'gitems' => $gitems
			];

			if ($graph['graphtype'] == GRAPH_TYPE_NORMAL || $graph['graphtype'] == GRAPH_TYPE_STACKED) {
				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['ymin_itemid'] = $this->getInput('ymin_itemid');
				}
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['ymax_itemid'] = $this->getInput('ymax_itemid');
				}
			}

			DBstart();

			$result = API::Graph()->update($graph);

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
			$output['success']['title'] = _('Graph updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update graph'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function validateYAxisFields(): bool {
		$fields = [];
		$ymin_type = $this->getInput('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
		$ymax_type = $this->getInput('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);

		if ($ymin_type == GRAPH_YAXIS_TYPE_FIXED) {
			$fields['yaxismin'] = 'required|string|not_empty';
		}
		elseif ($ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$fields['ymin_itemid'] = 'required|db graphs.ymin_itemid|not_empty';
		}

		if ($ymax_type == GRAPH_YAXIS_TYPE_FIXED) {
			$fields['yaxismax'] = 'required|string|not_empty';
		}
		elseif ($ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$fields['ymax_itemid'] = 'required|db graphs.ymax_itemid|not_empty';
		}

		$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

		foreach ($validator->getAllErrors() as $error) {
			error($error);
		}

		foreach (['yaxismin', 'yaxismax'] as $field) {
			if ($this->hasInput($field)) {
				$value = $this->getInput($field);

				if ($value !== '' && !is_numeric($value)) {
					error(_s('Incorrect value for field "%1$s": %2$s.', $field, _('a number is expected')));

					return false;
				}
			}
		}

		return !$validator->isErrorFatal() && !$validator->isError();
	}

	private function validatePercentFields(): bool {
		foreach (['percent_left', 'percent_right'] as $field) {
			if ($this->hasInput($field)) {
				if (!is_numeric($this->getInput($field))) {
					error(_s('Incorrect value for field "%1$s": %2$s.', $field, _('a number is expected')));

					return false;
				}

				if (!CGraphHelper::validateNumberRangeWithPrecision($this->getInput($field), 0, 100, 4)) {
					error(_s('Incorrect value for field "%1$s": %2$s.', $field,
						_s('value must be between "%1$s" and "%2$s", and have no more than "%3$s" digits after the decimal point',
							'0', '100', '4'
						)
					));

					return false;
				}
			}
		}

		return true;
	}
}
