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
	CMacrosResolverHelper,
	CParser,
	CRoleHelper,
	CSettingsHelper,
	CSimpleIntervalParser,
	CSvgGraph,
	CTagHelper,
	CUpdateIntervalParser,
	CWebUser,
	Manager;

use Widgets\ItemCard\Includes\CWidgetFieldItemSections;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

class WidgetView extends CControllerDashboardWidgetView {

	private const SECTION_MAX_WIDTH = 600;
	private const SECTION_MIN_WIDTH = 300;

	protected function doAction(): void {
		$item = $this->getItem();

		$context = $this->isTemplateDashboard() && !$this->fields_values['override_hostid'] ? 'template' : 'host';

		$triggers = [];
		$trigger_parent_templates = [];

		if ($item && $item['triggers']
				&& in_array(CWidgetFieldItemSections::SECTION_TRIGGERS, $this->fields_values['sections'])) {
			$triggers = $this->getAndPrepareItemTriggers($item['triggerids'], $context);

			if ($triggers) {
				$trigger_parent_templates = getTriggerParentTemplates($triggers, ZBX_FLAG_DISCOVERY_NORMAL);
			}
		}

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'sections' => $this->fields_values['sections'],
			'context' => $context,
			'is_context_editable' => $context === 'host'
				? CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				: CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'error' => null,
			'item' => $item,
			'triggers' => $triggers,
			'trigger_parent_templates' => $trigger_parent_templates,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getItem(): ?array {
		$options = [
			'output' => ['itemid', 'type', 'hostid', 'key_', 'delay', 'history', 'trends', 'status', 'value_type',
				'units', 'templateid', 'flags', 'description', 'inventory_link', 'master_itemid', 'state', 'error'
			],
			'selectHosts' => ['hostid', 'name'],
			'selectTemplates' => ['templateid', 'name'],
			'selectDiscoveryRule' => ['itemid', 'name', 'templateid', 'flags'],
			'selectDiscoveryData' => ['parent_itemid', 'status', 'ts_delete', 'ts_disable', 'disable_source'],
			'selectTriggers' => ['triggerid'],
			'webitems' => true
		];

		if (in_array(CWidgetFieldItemSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$options += [
				'selectValueMap' => ['mappings']
			];
		}

		if (in_array(CWidgetFieldItemSections::SECTION_HOST_INTERFACE, $this->fields_values['sections'])) {
			$options += [
				'selectInterfaces' => ['interfaceid', 'type', 'ip', 'dns', 'port', 'useip']
			];
		}

		if (in_array(CWidgetFieldItemSections::SECTION_TAGS, $this->fields_values['sections'])) {
			$options += [
				'selectTags' => ['tag', 'value'],
				'selectInheritedTags' => ['tag', 'value']
			];
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

		$item['triggerids'] = array_column($item['triggers'], 'triggerid');
		$item['problem_count'] = $this->getItemProblemCount($item);
		$item['parent_templates'] = getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL);

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

			if ($db_master_items) {
				if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
					$db_master_items = CArrayHelper::renameObjectsKeys($db_master_items, ['name_resolved' => 'name']);
				}

				$item['master_item'] = reset($db_master_items);
			}
		}

		[$item] = CMacrosResolverHelper::resolveItemKeys([$item]);
		[$item] = CMacrosResolverHelper::resolveItemDescriptions([$item]);
		[$item] = CMacrosResolverHelper::resolveTimeUnitMacros([$item], ['delay', 'history', 'trends']);

		if (in_array(CWidgetFieldItemSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$this->getItemValue($item);
		}

		if (in_array(CWidgetFieldItemSections::SECTION_INTERVAL_AND_STORAGE, $this->fields_values['sections'])) {
			$this->prepareItemDelay($item);
		}

		if (in_array(CWidgetFieldItemSections::SECTION_INTERVAL_AND_STORAGE, $this->fields_values['sections'])
				|| in_array(CWidgetFieldItemSections::SECTION_LATEST_DATA, $this->fields_values['sections'])) {
			$this->prepareItemHistoryAndTrends($item);
		}

		if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			unset($item['discoveryRule']);
		}
		else {
			$item['parent_lld'] = $item['discoveryRule'];
			$item['is_discovery_rule_editable'] = (bool) API::DiscoveryRule()->get([
				'output' => [],
				'itemids' => $item['discoveryRule']['itemid'],
				'editable' => true
			]);
		}

		if (in_array(CWidgetFieldItemSections::SECTION_TAGS, $this->fields_values['sections'])) {
			CTagHelper::mergeOwnAndInheritedTagsForObject($item);

			CArrayHelper::sort($item['tags'], ['tag', 'value']);
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

		if ($source === 'history') {
			switch ($item['value_type']) {
				case ITEM_VALUE_TYPE_LOG:
					$output = ['value', 'clock', 'ns', 'timestamp'];
					break;
				case ITEM_VALUE_TYPE_BINARY:
					$output = ['clock', 'ns'];
					break;
				default:
					$output = ['value', 'clock', 'ns'];
					break;
			}

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
				'output' => ['value_avg', 'clock'],
				'history' => $item['value_type'],
				'itemids' => $item['itemid'],
				'time_from' => $history_period,
				'time_till' => time(),
				'sortfield' => ['clock', 'ns'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => 1
			]);

			$db_items_values = CArrayHelper::renameObjectsKeys($db_items_values, ['value_avg' => 'value']);
		}

		$item['last_value'] = $db_items_values
			? reset($db_items_values)
			: null;

		if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			$content_width = $this->getInput('contents_width', 0);

			$item['sparkline'] = $this->getSparkline($item, [
				'width'		=> $this->fields_values['sparkline']['width'],
				'fill'		=> $this->fields_values['sparkline']['fill'],
				'color'		=> $this->fields_values['sparkline']['color'],
				'history'	=> $this->fields_values['sparkline']['history'],
				'from'		=> $this->fields_values['sparkline']['time_period']['from_ts'],
				'to'		=> $this->fields_values['sparkline']['time_period']['to_ts'],
				'contents_width'	=> ceil(
					max([self::SECTION_MIN_WIDTH, min([self::SECTION_MAX_WIDTH, $content_width])]) / 3
				)
			]);
		}
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

	protected function getItemProblemCount(array $item): array {
		$problem_count = array_fill_keys([TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION,
			TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER
		], 0);

		if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
			$triggers = API::Trigger()->get([
				'triggerids' => $item['triggerids'],
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
				$problem_count[$problem['severity']]++;
			}
		}

		return $problem_count;
	}

	protected function prepareItemDelay(array &$item): void {
		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		$item['custom_intervals'] = [];
		$item['delay_has_errors'] = false;

		if (in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
				|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strpos($item['key_expanded'], 'mqtt.get') === 0)) {
			$item['delay'] = '';
		}
		elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item['delay'] = $update_interval_parser->getDelay();

			if ($item['delay'][0] === '{') {
				$item['delay_has_errors'] = true;
			}

			$item['custom_intervals'] = $update_interval_parser->getIntervals();
		}
		else {
			$item['delay_has_errors'] = true;
		}
	}

	protected function prepareItemHistoryAndTrends(array &$item): void {
		$simple_interval_parser = new CSimpleIntervalParser();

		$item['history_has_errors'] = false;
		$item['trends_has_errors'] = false;

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
			$hk_history = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY);

			$item['history'] = $hk_history;
			$item['keep_history'] = timeUnitToSeconds($hk_history);
		}
		elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
			$item['keep_history'] = timeUnitToSeconds($item['history']);
		}
		else {
			$item['keep_history'] = 0;
			$item['history_has_errors'] = true;
		}

		if ($item['history'] == 0) {
			$item['history'] = '';
		}

		if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
				$hk_trends = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS);

				$item['trends'] = $hk_trends;
				$item['keep_trends'] = timeUnitToSeconds($hk_trends);
			}
			elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
				$item['keep_trends'] = timeUnitToSeconds($item['trends']);
			}
			else {
				$item['keep_trends'] = 0;
				$item['trends_has_errors'] = true;
			}

			if ($item['trends'] == 0) {
				$item['trends'] = '';
			}
		}
		else {
			$item['trends'] = '';
			$item['keep_trends'] = 0;
		}
	}

	protected function getAndPrepareItemTriggers(array $triggerids, string $context): array {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression',
				'priority', 'status', 'state', 'error', 'templateid', 'flags'
			],
			'selectHosts' => ['hostid', 'name', 'host'],
			'triggerids' => $triggerids,
			'preservekeys' => true
		]);

		if (!$triggers) {
			return [];
		}

		$expanded_trigger_description = CMacrosResolverHelper::resolveTriggerNames($triggers);

		foreach ($expanded_trigger_description as $key => $trigger) {
			$triggers[$key]['description_expanded'] = $trigger['description'];
		}

		return CMacrosResolverHelper::resolveTriggerExpressions($triggers, [
			'html' => true,
			'sources' => ['expression', 'recovery_expression'],
			'context' => $context
		]);
	}
}
