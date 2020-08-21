<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


$left_column = (new CFormList())
	->addRow(_('Show'),
		(new CRadioButtonList('show', (int) $data['show']))
			->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM, 'show_1#{uniqid}')
			->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM, 'show_2#{uniqid}')
			->addValue(_('History'), TRIGGERS_OPTION_ALL, 'show_3#{uniqid}')
			->setId('show_#{uniqid}')
			->setModern(true)
	)
	->addRow((new CLabel(_('Host groups'), 'groupids__ms')),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('groups', $data) ? $data['groups'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'groupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)->setId('groupids_#{uniqid}')
	)
	->addRow((new CLabel(_('Hosts'), 'hostids__ms')),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => array_key_exists('hosts', $data) ? $data['hosts'] : [],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'groupids_'
				],
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)->setId('hostids_#{uniqid}')
	)
	->addRow(_('Application'), [
		(new CTextBox('application', $data['application']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('application_select', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
	])
	->addRow((new CLabel(_('Triggers'), 'triggerids__ms')),
		(new CMultiSelect([
			'name' => 'triggerids[]',
			'object_name' => 'triggers',
			'data' => array_key_exists('triggers', $data) ? $data['triggers'] : [],
			'popup' => [
				'filter_preselect_fields' => [
					'hosts' => 'hostids_'
				],
				'parameters' => [
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'triggerids_',
					'monitored_hosts' => true,
					'with_monitored_triggers' => true,
					'noempty' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)->setId('triggerids_#{uniqid}')
	)
	->addRow(_('Problem'),
		(new CTextBox('name', $data['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Severity'),
		(new CSeverityCheckBoxList('severities'))
			->setUniqid('#{uniqid}')
			->setChecked($data['severities'])
	);

$filter_age = (new CNumericBox('age', $data['age'], 3, false, false, false))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
if ($data['age_state'] == 0) {
	$filter_age->setAttribute('disabled', 'disabled');
}

$left_column
	->addRow(_('Age less than'), [
		(new CCheckBox('age_state'))->setChecked($data['age_state'] == 1),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$filter_age,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('days')
	]);

$filter_inventory = $data['inventory'];
if (!$filter_inventory) {
	$filter_inventory = [['field' => '', 'value' => '']];
}

$filter_inventory_table = new CTable();
$filter_inventory_table->setId('filter-inventory_#{uniqid}');
$inventories = array_column(getHostInventories(), 'title', 'db_field');
$i = 0;
foreach ($filter_inventory as $field) {
	$filter_inventory_table->addRow([
		new CComboBox('inventory['.$i.'][field]', $field['field'], null, $inventories),
		(new CTextBox('inventory['.$i.'][value]', $field['value']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('inventory['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_inventory_table->addRow(
	(new CCol(
		(new CButton('inventory_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$filter_tags = $data['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = new CTable();
$filter_tags_table->setId('filter-tags_#{uniqid}');

$filter_tags_table->addRow(
	(new CCol(
		(new CRadioButtonList('evaltype', (int) $data['evaltype']))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
			->addValue(_('Or'), TAG_EVAL_TYPE_OR)
			->setModern(true)
	))->setColSpan(4)
);

$i = 0;
foreach ($filter_tags as $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CRadioButtonList('tags['.$i.'][operator]', (int) $tag['operator']))
			->addValue(_('Contains'), TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
			->setModern(true),
		(new CTextBox('tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$tag_format_line = (new CHorList())
	->addItem((new CRadioButtonList('show_tags', (int) $data['show_tags']))
			->addValue(_('None'), PROBLEMS_SHOW_TAGS_NONE)
			->addValue(PROBLEMS_SHOW_TAGS_1, PROBLEMS_SHOW_TAGS_1)
			->addValue(PROBLEMS_SHOW_TAGS_2, PROBLEMS_SHOW_TAGS_2)
			->addValue(PROBLEMS_SHOW_TAGS_3, PROBLEMS_SHOW_TAGS_3)
			->setModern(true)
			->setId('show_tags_#{uniqid}')
	)
	->addItem((new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN))
	->addItem(new CLabel(_('Tag name')))
	->addItem((new CRadioButtonList('tag_name_format', (int) $data['tag_name_format']))
			->addValue(_('Full'), PROBLEMS_TAG_NAME_FULL)
			->addValue(_('Shortened'), PROBLEMS_TAG_NAME_SHORTENED)
			->addValue(_('None'), PROBLEMS_TAG_NAME_NONE)
			->setModern(true)
			->setEnabled((int) $data['show_tags'] !== PROBLEMS_SHOW_TAGS_NONE)
	);

$right_column = (new CFormList())
	->addRow(_('Host inventory'), $filter_inventory_table)
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Show tags'), $tag_format_line)
	->addRow(_('Tag display priority'),
		(new CTextBox('tag_priority', $data['tag_priority']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setAttribute('placeholder', _('comma-separated list'))
			->setEnabled((int) $data['show_tags'] !== PROBLEMS_SHOW_TAGS_NONE)
	)
	->addRow(_('Show operational data'), [
		(new CRadioButtonList('show_opdata', (int) $data['show_opdata']))
			->addValue(_('None'), OPERATIONAL_DATA_SHOW_NONE)
			->addValue(_('Separately'), OPERATIONAL_DATA_SHOW_SEPARATELY)
			->addValue(_('With problem name'), OPERATIONAL_DATA_SHOW_WITH_PROBLEM)
			->setModern(true)
			->setEnabled($data['compact_view'] == 0)
	])
	->addRow(_('Show suppressed problems'), [
		(new CCheckBox('show_suppressed'))
			->setChecked($data['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE),
		(new CDiv([
			(new CLabel(_('Show unacknowledged only'), 'unacknowledged'))
				->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('unacknowledged'))
				->setChecked($data['unacknowledged'] == 1)
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	])
	->addRow(_('Compact view'), [
		(new CCheckBox('compact_view'))
			->setChecked($data['compact_view'] == 1)
			->setId('compact_view_#{uniqid}'),
		(new CDiv([
			(new CLabel(_('Show timeline'), 'show_timeline'))->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('show_timeline'))
				->setChecked($data['show_timeline'] == 1)
				->setEnabled($data['compact_view'] == 0)
				->setId('show_timeline_#{uniqid}'),
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	])
	->addRow(_('Show details'), [
		(new CCheckBox('details'))
			->setChecked($data['details'] == 1)
			->setEnabled($data['compact_view'] == 0),
		(new CDiv([
			(new CLabel(_('Highlight whole row'), 'highlight_row'))->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('highlight_row'))
				->setChecked($data['highlight_row'] == 1)
				->setEnabled($data['compact_view'] == 1)
		]))
			->addClass(ZBX_STYLE_FILTER_HIGHLIGHT_ROW_CB)
			->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	]);

// $filter = (new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'problem.view')))
// 	->setProfile($data['profileIdx'])
// 	->setActiveTab($data['active_tab'])
// 	->addFormItem((new CVar('action', 'problem.view'))->removeId());

// if ($data['show'] == TRIGGERS_OPTION_ALL) {
// 	$filter->addTimeSelector($screen->timeline['from'], $screen->timeline['to']);
// }

$template = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem([
		(new CDiv($left_column))->addClass(ZBX_STYLE_CELL),
		(new CDiv($right_column))->addClass(ZBX_STYLE_CELL)
	]);
$template = (new CForm('get'))
	->cleanItems()
	->addItem($template)
	->addVar('filter_name', '#{filter_name}')
	->addVar('filter_show_counter', '#{filter_show_counter}')
	->addVar('filter_custom_time', '#{filter_custom_time}');

if (array_key_exists('render_html', $data)) {
	/**
	 * Render HTML to prevent filter flickering after initial page load. PHP created content will be replaced by
	 * javascript with additional event handling (dynamic rows, etc.) when page will be fully loaded and javascript
	 * executed.
	 */
	$template->show();

	return;
}

(new CScriptTemplate('filter-monitoring-problem'))
	->setAttribute('data-template', 'monitoring.problem.filter')
	->addItem($template)
	->show();

?>
<script type="text/javascript">
let template = document.querySelector('[data-template="monitoring.problem.filter"]');

function render(data, container) {
	// Host groups multiselect.
	$('#groupids_' + data.uniqid, container).multiSelectHelper({
		id: 'groupids_' + data.uniqid,
		object_name: 'hostGroup',
		name: 'groupids[]',
		data: data.groups||[],
		popup: {
			parameters: {
				srctbl: 'host_groups',
				srcfld1: 'groupid',
				dstfrm: 'zbx_filter',
				dstfld1: 'groupids_' + data.uniqid,
				multiselect: 1,
				noempty: 1,
				real_hosts: 1,
				enrich_parent_groups: 1
			}
		}
	});

	// Hosts multiselect.
	$('#hostids_' + data.uniqid, container).multiSelectHelper({
		id: 'hostids_' + data.uniqid,
		object_name: 'hosts',
		name: 'hostids_[]',
		data: data.groups_multiselect||[],
		popup: {
			filter_preselect_fields: {
				hostgroups: 'groupids_' + data.uniqid
			},
			parameters: {
				srctbl: 'hosts',
				srcfld1: 'hostid',
				dstfrm: 'zbx_filter',
				dstfld1: 'hostids_' + data.uniqid,
			}
		}
	});

	// Application
	$('[name="application_select"]').on('click', function() {
		let options = {
				srctbl: 'applications',
				srcfld1: 'name',
				dstfrm: 'zbx_filter',
				dstfld1: 'application',
				with_applications: '1',
				real_hosts: '1'
			};

		PopUp("popup.generic", $.extend(options, getFirstMultiselectValue("hostids_" + data.uniqid)), null, this);
	});

	// Triggers multiselect.
	$('#triggerids_' + data.uniqid, container).multiSelectHelper({
		id: 'triggerids_' + data.uniqid,
		object_name: 'triggers',
		name: 'triggerids[]',
		data: data.triggers||[],
		popup: {
			filter_preselect_fields: {
				hosts: 'hostids_' + data.uniqid
			},
			parameters: {
				srctbl: 'triggers',
				srcfld1: 'triggerid',
				dstfrm: 'zbx_filter',
				dstfld1: 'triggerids_' + data.uniqid,
				multiselect: 1,
				noempty: 1,
				monitored_hosts: 1,
				with_monitored_triggers: 1
			}
		}
	});

	$('#filter-inventory_' + data.uniqid, container).dynamicRows({template: '#filter-inventory-row'});
	$('#filter-tags_' + data.uniqid, container).dynamicRows({template: '#filter-tag-row-tmpl'});

	$('#show_' + data.uniqid, container).change(function() {
		var	filter_show = $('input[name="show"]:checked', container).val();

		$('[name="age"]', container).closest('li').toggle(filter_show == <?= TRIGGERS_OPTION_RECENT_PROBLEM ?>
			|| filter_show == <?= TRIGGERS_OPTION_IN_PROBLEM ?>);
	}).trigger('change');

	$('[name="age_state"]').change(function() {
		$('[name="age"]').prop('disabled', !$(this).is(':checked'));
	});

	$('[name="compact_view"]', container).change(function() {
		let checked = $(this).is(':checked');

		$('[name="show_timeline"],[name="details"],[name="show_opdata"]', container).prop('disabled', checked);
		$('[name="highlight_row"]', container).prop('disabled', !checked);
	});

	$('[name="show_tags"]').change(function () {
		let disabled = (this.value == <?= PROBLEMS_SHOW_TAGS_NONE ?>);

		$('[name="tag_priority"]', container).prop('disabled', disabled);
		$('[name="tag_name_format"]', container).prop('disabled', disabled);
	});
}

function expand(data, container) {

}

template.onRender = render;

// Tab filter item events handlers.
template.addEventListener(TABFILTERITEM_EVENT_RENDER, function (ev) {
	render.call(ev.detail, ev.detail._data, ev.detail._content_container);
});
template.addEventListener(TABFILTERITEM_EVENT_EXPAND, function (ev) {
	expand.call(ev.detail, ev.detail._data, ev.detail._content_container);
});
</script>
