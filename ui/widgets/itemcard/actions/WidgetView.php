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


namespace Widgets\ItemCard\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CHousekeepingHelper,
	CItemHelper,
	CParser,
	CRoleHelper,
	CSettingsHelper,
	CSimpleIntervalParser,
	CSvgGraph,
	CWebUser,
	Manager;

use Widgets\ItemCard\Includes\CWidgetFieldSections;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

class WidgetView extends CControllerDashboardWidgetView
{

	private const SECTION_MAX_WIDTH = 600;
	private const SECTION_MIN_WIDTH = 300;

	protected function doAction(): void {
		$item = $this->getItem();

		$trigger_parent_templates = [];

		if (array_key_exists('triggers', $item) && $item['triggers']) {
			$trigger_parent_templates = getTriggerParentTemplates($item['triggers'], ZBX_FLAG_DISCOVERY_NORMAL);
		}

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'sections' => $this->fields_values['sections'],
			'context' => $this->isTemplateDashboard() && !$this->fields_values['override_hostid'] ? 'template' : 'host',
			'error' => null,
			'item' => $item,
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'item_parent_templates' => getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL),
			'trigger_parent_templates' => $trigger_parent_templates,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getItem(): ?array {
		$options = [
			'output' => ['itemid', 'hostid', 'templateid', 'error', 'flags', 'master_itemid', 'type', 'state',
				'status'
			],
			'selectHosts' => ['hostid', 'name'],
			'selectTemplates' => ['templateid', 'name'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectItemDiscovery' => ['itemdiscoveryid', 'itemid', 'parent_itemid', 'status', 'ts_delete', 'ts_disable',
				'disable_source'
			],
			'webitems' => true
		];

		if (in_array(CWidgetFieldSections::SECTION_DESCRIPTION, $this->fields_values['sections'])) {
			$options['output'][] = 'description';
		}

		if (in_array(CWidgetFieldSections::SECTION_METRICS, $this->fields_values['sections'])) {
			$options['output'] = array_merge($options['output'], ['delay', 'key_']);
		}

		if (in_array(CWidgetFieldSections::SECTION_METRICS, $this->fields_values['sections'])
				|| in_array(CWidgetFieldSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$options['output'] = array_merge($options['output'], ['history', 'trends']);
		}

		if (in_array(CWidgetFieldSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$options['output'][] = 'units';
			$options += [
				'selectValueMap' => ['mappings']
			];
		}

		if (in_array(CWidgetFieldSections::SECTION_TYPE_OF_INFORMATION, $this->fields_values['sections'])
				|| in_array(CWidgetFieldSections::SECTION_METRICS, $this->fields_values['sections'])
				|| in_array(CWidgetFieldSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$options['output'][] = 'value_type';
		}

		if (in_array(CWidgetFieldSections::SECTION_HOST_INTERFACE, $this->fields_values['sections'])) {
			$options += [
				'selectInterfaces' => ['interfaceid', 'type', 'ip', 'dns', 'port', 'useip'],
			];
		}

		if (in_array(CWidgetFieldSections::SECTION_TRIGGERS, $this->fields_values['sections'])) {
			$options += [
				'selectTriggers' => ['triggerid']
			];
		}

		if (in_array(CWidgetFieldSections::SECTION_TAGS, $this->fields_values['sections'])) {
			$options += [
				'selectTags' => ['tag', 'value']
			];
		}

		if (in_array(CWidgetFieldSections::SECTION_HOST_INVENTORY, $this->fields_values['sections'])) {
			$options['output'][] = 'inventory_link';
		}

		$options['output'][] = $this->isTemplateDashboard() && !$this->fields_values['override_hostid']
			? 'name'
			: 'name_resolved';

		if ($this->fields_values['override_hostid']) {
			$db_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $this->fields_values['itemid']
			]);

			if (!$db_items) {
				return null;
			}

			$options += [
				'hostids' => $this->fields_values['override_hostid'],
				'filter' => ['key_' => $db_items[0]['key_']]
			];
		}
		else {
			$options['itemids'] = $this->fields_values['itemid'];
		}

		$db_items = API::Item()->get($options);

		if (!$db_items) {
			return null;
		}

		if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
			$db_items = CArrayHelper::renameObjectsKeys($db_items, ['name_resolved' => 'name']);
		}

		$item = reset($db_items);

		if ($item['master_itemid']) {
			$output = ['itemid', 'type'];

			$output[] = $this->isTemplateDashboard() && !$this->fields_values['override_hostid']
				? 'name'
				: 'name_resolved';

			$db_master_items = API::Item()->get([
				'output' => $output,
				'itemids' => $item['master_itemid'],
				'webitems' => true
			]);

			if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
				$db_master_items = CArrayHelper::renameObjectsKeys($db_master_items, ['name_resolved' => 'name']);
			}

			if ($db_master_items) {
				$item['master_item'] = reset($db_master_items);
			}
		}

		$item['problem_count'] = array_fill_keys([TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION,
			TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER
		], 0);

		if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
			$triggers = API::Trigger()->get([
				'itemids' => $this->fields_values['itemid'],
				'skipDependent' => true,
				'monitored' => true,
				'preservekeys' => true
			]);

			$problems = API::Problem()->get([
				'output' => ['severity'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectids' => array_keys($triggers),
				'symptom' => false
			]);

			foreach ($problems as $problem) {
				$item['problem_count'][$problem['severity']]++;
			}
		}

		if (in_array(CWidgetFieldSections::SECTION_TRIGGERS, $this->fields_values['sections'])) {
			$item['triggers'] = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression',
					'priority', 'status', 'state', 'error', 'templateid', 'flags'
				],
				'selectHosts' => ['hostid', 'name', 'host'],
				'itemids' => $this->fields_values['itemid'],
				'preservekeys' => true
			]);
		}

		if (in_array(CWidgetFieldSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$this->getItemValue($item);
		}

		return $item;
	}

	protected function getItemValue(array &$item): void {
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			[$db_item] = CItemHelper::addDataSource([$item], time() - $history_period);
			$source = $db_item['source'];
		}
		else {
			$source = 'history';
		}

		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_LOG:
				$output = ['itemid', 'value', 'clock', 'ns', 'timestamp'];
				break;
			case ITEM_VALUE_TYPE_BINARY:
				$output = ['itemid', 'clock', 'ns'];
				break;
			default:
				$output = ['itemid', 'value', 'clock', 'ns'];
				break;
		}

		if ($source === 'history') {
			$db_items_values = API::History()->get([
				'output' => $output,
				'history' => $item['value_type'],
				'itemids' => $item['itemid'],
				'time_from' => $history_period,
				'time_till' => time(),
				'sortfield' => ['clock', 'ns'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => 1
			]);
		}
		else {
			$db_items_values = API::Trend()->get([
				'output' => $output,
				'history' => $item['value_type'],
				'itemids' => $item['itemid'],
				'time_from' => $history_period,
				'time_till' => time(),
				'sortfield' => ['clock', 'ns'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => 1
			]);
		}

		$item['last_value'] = $db_items_values
			? reset($db_items_values)
			: null;

		$simple_interval_parser = new CSimpleIntervalParser();

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
			$keep_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
		}
		elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
			$keep_history = timeUnitToSeconds($item['history']);
		}
		else {
			$keep_history = 0;
		}

		$item['keep_history'] = $keep_history;

		if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
				$keep_trends = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
			}
			elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
				$keep_trends = timeUnitToSeconds($item['trends']);
			}
			else {
				$keep_trends = 0;
			}

			$content_width = $this->getInput('contents_width', 0);

			$item['sparkline'] = $this->getSparkline($item, [
				'width'		=> $this->fields_values['sparkline']['width'],
				'fill'		=> $this->fields_values['sparkline']['fill'],
				'color'		=> $this->fields_values['sparkline']['color'],
				'history'	=> $this->fields_values['sparkline']['history'],
				'from'		=> $this->fields_values['sparkline']['time_period']['from_ts'],
				'to'		=> $this->fields_values['sparkline']['time_period']['to_ts'],
				'contents_width'	=> ceil(max([self::SECTION_MIN_WIDTH, min([self::SECTION_MAX_WIDTH, $content_width])]) / 3)
			]);
		}
		else {
			$keep_trends = 0;
		}

		$item['keep_trends'] = $keep_trends;
	}

	protected function getSparkline(array $sparkline_item, array $options): array {
		$sparkline = [
			'width'		=> $options['width'],
			'fill'		=> $options['fill'],
			'color'		=> $options['color'],
			'history'	=> $options['history'],
			'from'		=> $options['from'],
			'to'		=> $options['to'],
			'value'		=> []
		];

		if ($options['history'] == CWidgetFieldSparkline::DATA_SOURCE_AUTO) {
			[$sparkline_item] = CItemHelper::addDataSource([$sparkline_item], $options['from']);
		}
		else {
			$sparkline_item['source'] = $options['history'] == CWidgetFieldSparkline::DATA_SOURCE_TRENDS
				? 'trends'
				: 'history';
		}

		$data = Manager::History()->getGraphAggregationByWidth([$sparkline_item], $options['from'], $options['to'],
			$options['contents_width']
		);

		if ($data) {
			$points = array_column(reset($data)['data'], 'avg', 'clock');
			/**
			 * Postgres may return entries in mixed 'clock' order, getMissingData for calculations
			 * requires order by 'clock'.
			 */
			ksort($points);
			$points += CSvgGraph::getMissingData($points, SVG_GRAPH_MISSING_DATA_NONE);
			ksort($points);

			foreach ($points as $ts => $value) {
				$sparkline['value'][] = [$ts, $value];
			}
		}

		return $sparkline;
	}
}
