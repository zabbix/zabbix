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


use Widgets\ItemCard\Includes\CWidgetFieldItemSections;
use Widgets\ItemCard\Widget;

/**
 * Item card widget view.
 *
 * @var CView $this
 * @var array $data
 */

if ($data['error'] !== null) {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
elseif ($data['item']) {
	$item = $data['item'];
	$sections = [];

	foreach ($data['sections'] as $section) {
		switch ($section) {
			case CWidgetFieldItemSections::SECTION_DESCRIPTION:
				$sections[] = makeSectionDescription($item['description_expanded']);
				break;

			case CWidgetFieldItemSections::SECTION_ERROR_TEXT:
				$sections[] = makeSectionError($item['error']);
				break;

			case CWidgetFieldItemSections::SECTION_INTERVAL_AND_STORAGE:
				$sections[] = makeSectionIntervalAndStorage($item);
				break;

			case CWidgetFieldItemSections::SECTION_TYPE_OF_INFORMATION:
				$value_types = [
					ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
					ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
					ITEM_VALUE_TYPE_STR => _('Character'),
					ITEM_VALUE_TYPE_LOG => _('Log'),
					ITEM_VALUE_TYPE_TEXT => _('Text'),
					ITEM_VALUE_TYPE_BINARY => _('Binary')
				];

				$sections[] = makeSectionSingleParameter(_('Type of information'), $value_types[$item['value_type']]);
				break;

			case CWidgetFieldItemSections::SECTION_HOST_INTERFACE:
				$interface_data = $item['interfaces'] ? getHostInterface(reset($item['interfaces'])) : _('No data');

				$sections[] = makeSectionSingleParameter(_('Host interface'), $interface_data);
				break;

			case CWidgetFieldItemSections::SECTION_TYPE:
				$sections[] = makeSectionSingleParameter(_('Type'), item_type2str($item['type']));
				break;

			case CWidgetFieldItemSections::SECTION_TRIGGERS:
				$sections[] = makeSectionTriggers($data['triggers'], $item['hostid'], $data['trigger_parent_templates'],
					$data['allowed_ui_conf_templates'], $data['context'], $data['is_context_editable']
				);
				break;

			case CWidgetFieldItemSections::SECTION_HOST_INVENTORY:
				$sections[] = makeSectionSingleParameter(_('Host inventory'), $item['inventory_link'] != 0
					? getHostInventories()[$item['inventory_link']]['title']
					: ''
				);
				break;

			case CWidgetFieldItemSections::SECTION_LATEST_DATA:
				$sections[] = makeSectionLatestData($item, $data['context']);
				break;

			case CWidgetFieldItemSections::SECTION_TAGS:
				$sections[] = makeSectionTags($item['tags']);
				break;
		}
	}

	$body = (new CDiv([
		makeSectionsHeader($item, $data['context'], $data['is_context_editable'], $data['allowed_ui_conf_templates']),
		(new CDiv($sections))->addClass(Widget::ZBX_STYLE_SECTIONS)
	]))->addClass(Widget::ZBX_STYLE_CLASS);
}
else {
	$body = (new CDiv(_('No data found')))
		->addClass(ZBX_STYLE_NO_DATA_MESSAGE)
		->addClass(ZBX_ICON_SEARCH_LARGE);
}

(new CWidgetView($data))
	->addItem($body)
	->show();


function makeSectionsHeader(array $item, string $context, bool $show_path, bool $allowed_ui_conf_templates): CDiv {
	$item_status = '';
	$problems_indicator = '';
	$error_text = '';
	$item_discovery = '';

	if ($item['status'] == ITEM_STATUS_ACTIVE) {
		$problems = [];

		if ($item['error']) {
			$error_text = makeErrorIcon($item['error']);
		}

		$disable_source = $item['status'] == ITEM_STATUS_DISABLED && $item['discoveryData']
			? $item['discoveryData']['disable_source']
			: '';

		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['discoveryData']['status'] == ZBX_LLD_STATUS_LOST) {
			$item_discovery = getLldLostEntityIndicator(time(), $item['discoveryData']['ts_delete'],
				$item['discoveryData']['ts_disable'], $disable_source, $item['status'] == ITEM_STATUS_DISABLED,
				_('item')
			);
		}

		foreach ($item['problem_count'] as $severity => $count) {
			if ($count > 0) {
				$problems[] = (new CSpan($count))
					->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
					->addClass(CSeverityHelper::getStatusStyle($severity))
					->setTitle(CSeverityHelper::getName($severity));
			}
		}

		if ($problems) {
			$problems_indicator = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
				? new CLink(null,
					(new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->setArgument('hostids', [$item['hostid']])
						->setArgument('triggerids', $item['triggerids'])
						->setArgument('filter_set', '1')
				)
				: new CSpan();

			$problems_indicator
				->addClass(ZBX_STYLE_PROBLEM_ICON_LINK)
				->addItem($problems);
		}
	}
	else {
		$item_status = (new CDiv(_('Disabled')))->addClass(ZBX_STYLE_COLOR_NEGATIVE);
	}

	$path = [];

	if ($show_path) {
		if ($context === 'host') {
			$host_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'host.edit')
				->setArgument('hostid', $item['hostid'])
				->getUrl();

			$path[] = (new CLink($item['hosts'][0]['name'], $host_url))
				->setTitle($item['hosts'][0]['name'])
				->addClass('path-element');
		}

		$template = makeItemTemplatePrefix($item['itemid'], $item['parent_templates'], ZBX_FLAG_DISCOVERY_NORMAL,
			$allowed_ui_conf_templates, true
		);

		if ($template) {
			$template = reset($template);
			if ($path) {
				$path[] = '>';
			}

			$path[] = $template->addClass('path-element');
		}

		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			if ($path) {
				$path[] = '>';
			}

			if ($item['is_discovery_rule_editable']) {
				$path[] = (new CLink($item['discoveryRule']['name'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'item.prototype.list')
						->setArgument('parent_discoveryid', $item['discoveryRule']['itemid'])
						->setArgument('context', $context)
				))
					->setTitle($item['discoveryRule']['name'])
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_ORANGE);
			}
			else {
				$path[] = (new CSpan($item['discoveryRule']['name']))
					->setTitle($item['discoveryRule']['name'])
					->addClass(ZBX_STYLE_ORANGE);
			}
		}

		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			if ($path) {
				$path[] = '>';
			}

			if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
				$path[] = (new CDiv($item['master_item']['name']))
					->setTitle($item['master_item']['name'])
					->addClass('path-element');
			}
			else {
				$item_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'item.edit')
					->setArgument('context', $context)
					->setArgument('itemid', $item['master_itemid'])
					->getUrl();

				$path[] = (new CLink($item['master_item']['name'], $item_url))
					->setTitle($item['master_item']['name'])
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_TEAL)
					->addClass('path-element');
			}
		}
	}

	return (new CDiv([
		(new CDiv([
			(new CDiv([
				(new CLinkAction($item['name']))
					->setTitle($item['name'])
					->setMenuPopup(CMenuPopupHelper::getItem([
						'itemid' => $item['itemid'],
						'context' => $context,
						'backurl' => (new CUrl('zabbix.php'))
							->setArgument('action', 'latest.view')
							->setArgument('context', $context)
							->getUrl()
					])),
				$error_text,
				$item_discovery,
				$item_status
			]))->addClass('item-name'),
			$problems_indicator
		]))->addClass('section-item'),
		(new CDiv($path))->addClass('section-path')
	]))->addClass('sections-header');
}

function makeSectionDescription(string $description): CDiv {
	return (new CDiv(
		(new CDiv($description))
			->addClass(ZBX_STYLE_LINE_CLAMP)
			->setTitle($description)
	))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-description');
}

function makeSectionError(string $error): CDiv {
	return (new CDiv(
		(new CDiv($error))
			->addClass(ZBX_STYLE_LINE_CLAMP)
			->addClass(ZBX_STYLE_COLOR_NEGATIVE)
			->setTitle($error)
	))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-error');
}

function makeSectionSingleParameter(string $section_name, string $section_body): CDiv {
	return (new CDiv([
		(new CDiv($section_name))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($section_body))
			->setTitle($section_body)
			->addClass(Widget::ZBX_STYLE_SECTION_BODY)
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-single-parameter');
}

function makeSectionTriggers(array $item_triggers, string $hostid, array $trigger_parent_templates,
		bool $allowed_ui_conf_templates, string $context, bool $is_context_editable): CDiv {
	$triggers = [];

	$i = 0;
	$template_count = count($item_triggers);
	$hint_trigger = [];

	$hint_table = (new CTableInfo())->setHeader([_('Severity'), _('Name'), _('Expression'), _('Status')]);

	foreach ($item_triggers as $trigger) {
		$triggers[] = (new CSpan([
			(new CSpan($trigger['description_expanded']))
				->addClass('trigger-name')
				->setTitle($trigger['description_expanded']),
			++$i < $template_count ? (new CSpan(', '))->addClass('delimiter') : null
		]))->addClass('trigger');

		$hint_trigger[] = $trigger['description_expanded'];

		if ($is_context_editable) {
			$trigger_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'trigger.edit')
				->setArgument('triggerid', $trigger['triggerid'])
				->setArgument('hostid', $hostid)
				->setArgument('context', $context)
				->getUrl();

			$trigger_description = new CLink($trigger['description'], $trigger_url);
		}
		else {
			$trigger_description = new CSpan($trigger['description']);
		}

		$hint_table->addRow([
			CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
			[
				makeTriggerTemplatePrefix($trigger['triggerid'], $trigger_parent_templates,
					ZBX_FLAG_DISCOVERY_NORMAL, $allowed_ui_conf_templates
				),
				$trigger_description
			],
			(new CDiv(
				$trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
					? [
						_('Problem'), ': ', $trigger['expression'], BR(),
						_('Recovery'), ': ', $trigger['recovery_expression']
					]
					: $trigger['expression']
			))->addClass(ZBX_STYLE_WORDBREAK),
			(new CSpan(triggerIndicator($trigger['status'], $trigger['state'])))
				->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		]);
	}

	if ($triggers) {
		$triggers[] = (new CLink(_('more')))
			->addClass(ZBX_STYLE_LINK_ALT)
			->setHint(implode(', ', $hint_trigger), ZBX_STYLE_HINTBOX_WRAP);
	}

	return (new CDiv([
		(new CDiv([
			(new CLinkAction(_('Triggers')))->setHint($hint_table),
			CViewHelper::showNum($hint_table->getNumRows())
		]))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($triggers))
			->addClass(Widget::ZBX_STYLE_SECTION_BODY)
			->addClass('triggers')
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-triggers');
}

function makeSectionIntervalAndStorage(array $item): CDiv {
	$help_icon = null;

	if ($item['custom_intervals']) {
		$table = (new CTableInfo())->setHeader([_('Type'), _('Interval'), _('Period')]);

		foreach ($item['custom_intervals'] as $custom_interval) {
			$table->addRow($custom_interval['type'] == ITEM_DELAY_FLEXIBLE
				? [_('Flexible'), $custom_interval['update_interval'], $custom_interval['time_period']]
				: [_('Scheduling'), $custom_interval['interval'], '']
			);
		}

		$help_icon = (new CButtonIcon(ZBX_ICON_ALERT_WITH_CONTENT))
			->setAttribute('data-content', '?')
			->setHint($table, ZBX_STYLE_HINTBOX_WRAP);
	}

	return (new CDiv([
		(new CDiv([
			(new CDiv(_('Interval')))->addClass('column-header'),
			(new CDiv([
				(new CSpan($item['delay']))
					->setTitle($item['delay'])
					->addClass($item['delay_has_errors'] ? ZBX_STYLE_COLOR_NEGATIVE : null),
				$help_icon
			]))->addClass('column-value')
		]))->addClass('column'),
		(new CDiv(
			(new CDiv([
				(new CDiv(_('History')))->addClass('column-header'),
				(new CSpan($item['history']))
					->setTitle($item['history'])
					->addClass('column-value')
					->addClass($item['history_has_errors'] ? ZBX_STYLE_COLOR_NEGATIVE : null)
			]))->addClass('column')
		))->addClass('center-column'),
		(new CDiv([
			(new CDiv(_('Trends')))->addClass('column-header'),
			(new CSpan($item['trends']))
				->setTitle($item['trends'])
				->addClass('column-value')
				->addClass($item['trends_has_errors'] ? ZBX_STYLE_COLOR_NEGATIVE : null)
		]))->addClass('right-column')
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-interval-and-storage');
}

function makeSectionLatestData(array $item, string $context): CDiv {
	$last_check_column_value = '';
	$last_value_column_value = '';

	$action_column = (new CDiv())->addClass('right-column');
	$action_column_value = (new CDiv())->addClass('column-value');

	$item_value = $item['last_value'];

	if ($context === 'host') {
		if ($item_value !== null) {
			$last_check_column_value = (new CSpan(zbx_date2age($item_value['clock'])))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $item_value['clock']), '', true, '', 0);

			if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
				$last_value_column_value = italic(_('binary value'))->addClass(ZBX_STYLE_GREY);

				$action_column_value = (new CButton(null, _('Show')))
					->addClass('btn-thumbnail')
					->addClass('js-show-binary');

				$action_column
					->setAttribute('data-itemid', $item['itemid'])
					->setAttribute('data-key_', $item['key_'])
					->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns']);
			}
			else {
				$last_value_column_value = (new CSpan(formatHistoryValue($item_value['value'], $item, false)))
					->addClass(ZBX_STYLE_CURSOR_POINTER)
					->setHint(
						(new CDiv(mb_substr($item_value['value'], 0, ZBX_HINTBOX_CONTENT_LIMIT)))
							->addClass(ZBX_STYLE_HINTBOX_RAW_DATA)
							->addClass(ZBX_STYLE_HINTBOX_WRAP),
						'', true, '', 0
					);
			}
		}

		if ($item['value_type'] != ITEM_VALUE_TYPE_BINARY) {
			$is_numeric = $item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64;

			if ($item['keep_history'] != 0 || $item['keep_trends'] != 0) {
				$action_column->addItem(
					(new CDiv(
						(new CLink($is_numeric ? _('Graph') : _('History'), (new CUrl('history.php'))
							->setArgument('action', $is_numeric ? HISTORY_GRAPH : HISTORY_VALUES)
							->setArgument('itemids[]', $item['itemid'])
						))
					))->addClass('column-header')
				);
			}

			if ($is_numeric) {
				$action_column_value->addItem(
					(new CSparkline())
						->setColor('#'.$item['sparkline']['color'])
						->setLineWidth($item['sparkline']['width'])
						->setFill($item['sparkline']['fill'])
						->setValue($item['sparkline']['value'])
						->setTimePeriodFrom($item['sparkline']['from'])
						->setTimePeriodTo($item['sparkline']['to'])
				);
			}
		}
	}
	else {
		$last_value_column_value = _('No data');
	}

	$action_column->addItem($action_column_value);

	return (new CDiv([
		(new CDiv([
			(new CDiv(_('Last check')))->addClass('column-header'),
			(new CDiv($last_check_column_value))->addClass('column-value')
		]))->addClass('column'),
		(new CDiv(
			(new CDiv([
				(new CDiv(_('Last value')))->addClass('column-header'),
				(new CDiv($last_value_column_value))->addClass('column-value')
			]))->addClass('column'),
		))->addClass('center-column'),
		$action_column
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-latest-data');
}

function makeSectionTags(array $item_tags): CDiv {
	$tags = [];

	foreach ($item_tags as $tag) {
		$tag = $tag['tag'].($tag['value'] === '' ? '' : ': '.$tag['value']);

		$tags[] = (new CSpan($tag))
			->addClass(ZBX_STYLE_TAG)
			->setHint($tag);
	}

	if ($tags) {
		$tags[] = (new CButtonIcon(ZBX_ICON_MORE))->setHint($tags, ZBX_STYLE_HINTBOX_WRAP.' '.ZBX_STYLE_TAGS_WRAPPER);
	}

	return (new CDiv(
		(new CDiv($tags))->addClass('tags')->addClass(ZBX_STYLE_TAGS_WRAPPER)
	))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-tags');
}
