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


/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('gtlc.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('apm.metric.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$csrf_token = CCsrfTokenHelper::get('apm');
$reset_url  = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter = (new CFilter())
	->setId('apm_metric_filter')
	->setResetUrl($reset_url)
	->setProfile($data['filter_options']['idx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addTimeSelector(
		$data['filter_options']['timeselector']['from'],
		$data['filter_options']['timeselector']['to'],
		$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
	)
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Metric name'), 'filter_metric_name')),
				new CFormField(
					(new CTextBox('filter_metric_name', $data['filter']['metric_name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Type'), 'filter_types')),
				new CFormField(
					(new CCheckBoxList('filter_types'))
						->setUniqid('#{uniqid}')
						->setOptions(CApmMetricHelper::getTypes())
						->setChecked($data['filter']['types'])
						->setColumns(2)
						->setVertical()
						->showTitles()
				)
			])
			->addItem([
				(new CLabel(_('Service name'), 'filter_service_name')),
				new CFormField(
					(new CTextBox('filter_service_name', $data['filter']['service_name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Scope name'), 'filter_scope_name')),
				new CFormField(
					(new CTextBox('filter_scope_name', $data['filter']['scope_name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Attributes'), 'filter_attributes')),
				new CFormField(
					CApmAttrFilterFieldHelper::getFilterField([
						'evaltype' => $data['filter']['attributes_evaltype'],
						'attributes' => $data['filter']['attributes'] ?: [
							['key' => '', 'value' => '', 'operator' => CONDITION_OPERATOR_LIKE]
						]
					],
					[
						'evaltype_field_name' => 'filter_attributes_evaltype',
						'attribute_field_name' => 'filter_attributes'
					])
				)
			])
			->addItem([
				(new CLabel(_('Resource attributes'), 'filter_resource_attributes')),
				new CFormField(
					CApmAttrFilterFieldHelper::getFilterField([
						'evaltype' => $data['filter']['resource_attributes_evaltype'],
						'attributes' => $data['filter']['resource_attributes'] ?: [
							['key' => '', 'value' => '', 'operator' => CONDITION_OPERATOR_LIKE]
						]
					],
					[
						'evaltype_field_name' => 'filter_resource_evaltype',
						'attribute_field_name' => 'filter_resource_attributes'
					])->setId('filter-resource-attributes')
				)
			])
			->addItem([
				(new CLabel(_('Scope attributes'), 'filter_scope_attributes')),
				new CFormField(
					CApmAttrFilterFieldHelper::getFilterField([
						'evaltype' => $data['filter']['scope_attributes_evaltype'],
						'attributes' => $data['filter']['scope_attributes'] ?: [
							['key' => '', 'value' => '', 'operator' => CONDITION_OPERATOR_LIKE]
						]
					],
					[
						'evaltype_field_name' => 'filter_scope_evaltype',
						'attribute_field_name' => 'filter_scope_attributes'
					])->setId('filter-scope-attributes')
				)
			])
	]);

$form = (new CForm())
	->setName('metrics')
	->addItem(
		(new CDataTable())->setId('datatable-metrics')
	);

$html_page = (new CHtmlPage())
	->setTitle(_('Metrics'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::APM_METRIC_VIEW))
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	)
	->setSplitView(
		(new CSplitView())
			->setAttribute('min', '10%')
			->setAttribute('max', '90%')
			->setAttribute('position', $data['side_drawer_position'])
	)
	->addItem([$filter, $form]);

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	$html_page->addItem((new CPre())->addClass(ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH));
}

$html_page->show();

(new CTemplateTag('filter-attributes-row-tmpl'))
	->addItem(
		CApmAttrFilterFieldHelper::getFilterFieldRow('#{rowNum}', [
			'key' => '#{key}',
			'value' => '#{value}',
			'operator' => CONDITION_OPERATOR_LIKE
		],
		[
			'attribute_field_name' => 'filter_attributes'
		])
	)
	->show();

(new CTemplateTag('filter-resource-attributes-row-tmpl'))
	->addItem(
		CApmAttrFilterFieldHelper::getFilterFieldRow('#{rowNum}', [
			'key' => '#{key}',
			'value' => '#{value}',
			'operator' => CONDITION_OPERATOR_LIKE
		],
		[
			'attribute_field_name' => 'filter_resource_attributes'
		])
	)
	->show();

(new CTemplateTag('filter-scope-attributes-row-tmpl'))
	->addItem(
		CApmAttrFilterFieldHelper::getFilterFieldRow('#{rowNum}', [
			'key' => '#{key}',
			'value' => '#{value}',
			'operator' => CONDITION_OPERATOR_LIKE
		],
		[
			'attribute_field_name' => 'filter_scope_attributes'
		])
	)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'csrf_token' => $csrf_token,
		'default_sort_field' => $data['default_sort_field'],
		'default_sort_order' => $data['default_sort_order'],
		'filter' => $data['filter'],
		'filter_options' => $data['filter_options'],
		'metric_types' => CApmMetricHelper::getTypes(),
		'page' => $data['page'],
		'refresh_interval' => $data['refresh_interval'],
		'filter_validation_rules' => $data['filter_validation_rules'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs'],
		'side_drawer_position' => $data['side_drawer_position']
	]).');
'))
	->setOnDocumentReady()
	->show();
