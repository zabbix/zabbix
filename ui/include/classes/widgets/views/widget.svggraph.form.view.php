<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * SVG graph widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$form->addItem($form_list);

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.svggraph.form.view.js.php')];
$jq_templates = [];

// Create graph preview box.
$form->addItem(
	(new CDiv(
		(new CDiv())->setId('svg-graph-preview')
	))->addClass(ZBX_STYLE_SVG_GRAPH_PREVIEW)
);

// Create 'Data set' tab.
$tab_data_set = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['ds']), CWidgetHelper::getGraphDataSet($fields['ds'], $form->getName()));
$scripts[] = CWidgetHelper::getGraphDataSetJavascript();
$jq_templates['dataset-row'] = CWidgetHelper::getGraphDataSetTemplate($fields['ds'], $form->getName());

// Create 'Displaying options' tab.
$tab_displaying_opt = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['source']),
		CWidgetHelper::getRadioButtonList($fields['source'])
	);

// Create 'Time period' tab.
$tab_time_period = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['graph_time']), CWidgetHelper::getCheckBox($fields['graph_time']))
	->addRow(
		CWidgetHelper::getLabel($fields['time_from']),
		CWidgetHelper::getDatePicker($fields['time_from'])
			->setDateFormat(DATE_TIME_FORMAT_SECONDS)
			->setPlaceholder(DATE_TIME_FORMAT_SECONDS_PLACEHOLDER)
	)
	->addRow(
		CWidgetHelper::getLabel($fields['time_to']),
		CWidgetHelper::getDatePicker($fields['time_to'])
			->setDateFormat(DATE_TIME_FORMAT_SECONDS)
			->setPlaceholder(DATE_TIME_FORMAT_SECONDS_PLACEHOLDER)
	);

// Create 'Axes' tab.
$tab_axes = (new CFormList())->addRow('',
	(new CDiv([
		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['lefty']), CWidgetHelper::getCheckBox($fields['lefty']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_min']), CWidgetHelper::getNumericBox($fields['lefty_min']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_max']), CWidgetHelper::getNumericBox($fields['lefty_max']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_units']), [
				CWidgetHelper::getSelect($fields['lefty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				CWidgetHelper::getTextBox($fields['lefty_static_units'])
			])
			->addClass(ZBX_STYLE_COLUMN_33),

		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['righty']), CWidgetHelper::getCheckBox($fields['righty']))
			->addRow(CWidgetHelper::getLabel($fields['righty_min']),
				CWidgetHelper::getNumericBox($fields['righty_min'])
			)
			->addRow(CWidgetHelper::getLabel($fields['righty_max']),
				CWidgetHelper::getNumericBox($fields['righty_max'])
			)
			->addRow(CWidgetHelper::getLabel($fields['righty_units']), [
				CWidgetHelper::getSelect($fields['righty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				CWidgetHelper::getTextBox($fields['righty_static_units'])
			])
			->addClass(ZBX_STYLE_COLUMN_33),

		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['axisx']), CWidgetHelper::getCheckBox($fields['axisx']))
			->addClass(ZBX_STYLE_COLUMN_33)
	]))
		->addClass(ZBX_STYLE_COLUMNS)
);

// Create 'Legend' tab.
$field_legend_lines = CWidgetHelper::getRangeControl($fields['legend_lines']);
$tab_legend = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['legend']), CWidgetHelper::getCheckBox($fields['legend']))
	->addRow(CWidgetHelper::getLabel($fields['legend_lines']), $field_legend_lines);
$scripts[] = $field_legend_lines->getPostJS();

// Add 'Problems' tab.
$tab_problems = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['show_problems']), CWidgetHelper::getCheckBox($fields['show_problems']))
	->addRow(CWidgetHelper::getLabel($fields['graph_item_problems']),
		CWidgetHelper::getCheckBox($fields['graph_item_problems'])
	)
	->addRow(CWidgetHelper::getLabel($fields['problemhosts']),
		CWidgetHelper::getHostPatternSelect($fields['problemhosts'], $form->getName())
	)
	->addRow(CWidgetHelper::getLabel($fields['severities']),
		CWidgetHelper::getSeverities($fields['severities'])
	)
	->addRow(CWidgetHelper::getLabel($fields['problem_name']), CWidgetHelper::getTextBox($fields['problem_name']))
	->addRow(CWidgetHelper::getLabel($fields['evaltype']), CWidgetHelper::getRadioButtonList($fields['evaltype']))
	->addRow(CWidgetHelper::getLabel($fields['tags']), CWidgetHelper::getTags($fields['tags']));

$scripts[] = $fields['problemhosts']->getJavascript();
$scripts[] = $fields['tags']->getJavascript();
$jq_templates['tag-row-tmpl'] = CWidgetHelper::getTagsTemplate($fields['tags']);

// Create 'Overrides' tab.
$tab_overrides = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['or']), CWidgetHelper::getGraphOverride($fields['or'], $form->getName()));

$scripts[] = CWidgetHelper::getGraphOverrideJavascript($fields['or']);
$jq_templates['overrides-row'] = CWidgetHelper::getGraphOverrideTemplate($fields['or'], $form->getName());

// Create CTabView.
$form_tabs = (new CTabView())
	->addTab('data_set',  _('Data set'), $tab_data_set, TAB_INDICATOR_GRAPH_DATASET)
	->addTab('displaying_options',  _('Displaying options'), $tab_displaying_opt, TAB_INDICATOR_GRAPH_OPTIONS)
	->addTab('time_period',  _('Time period'), $tab_time_period, TAB_INDICATOR_GRAPH_TIME)
	->addTab('axes',  _('Axes'), $tab_axes)
	->addTab('legendtab',  _('Legend'), $tab_legend, TAB_INDICATOR_GRAPH_LEGEND)
	->addTab('problems',  _('Problems'), $tab_problems, TAB_INDICATOR_GRAPH_PROBLEMS)
	->addTab('overrides',  _('Overrides'), $tab_overrides, TAB_INDICATOR_GRAPH_OVERRIDES)
	->addClass('graph-widget-config-tabs') // Add special style used for graph widget tabs only.
	->onTabChange('jQuery.colorpicker("hide");jQuery(window).trigger("resize");')
	->setSelected(0);

// Add CTabView to form.
$form->addItem($form_tabs);
$scripts[] = $form_tabs->makeJavascript();

$form->addItem(
	(new CScriptTag('
		widget_svggraph_form.init('.json_encode([
			'form_id' => $form->getId(),
			'form_tabs_id' =>$form_tabs->getId()
		]).');
	'))->setOnDocumentReady()
);

return [
	'form' => $form,
	'scripts' => $scripts,
	'jq_templates' => $jq_templates
];
