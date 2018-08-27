<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['known_widget_types'], $fields['rf_rate']
);

$form->addItem($form_list);

$scripts = [];
$jq_templates = [];
$form_name = $form->getName();

// Create graph preview box.
$form->addItem(
	(new CDiv(
		(new CDiv())->setId('svg-graph-preview')
	))->addClass(ZBX_STYLE_SVG_GRAPH_PREVIEW)
);

// Stick preview to the top of configuration window when scroll.
$scripts[] =
	'jQuery(".overlay-dialogue-body").on("scroll", function() {'.
		'if (jQuery("#svg-graph-preview").length) {'.
			'var $dialogue_body = jQuery(this),'.
				'$preview_container = jQuery(".'.ZBX_STYLE_SVG_GRAPH_PREVIEW.'");'.
				'jQuery("#svg-graph-preview").css("top",'.
					'($preview_container.offset().top < $dialogue_body.offset().top && $dialogue_body.height() > 500)'.
						' ? $dialogue_body.offset().top - $preview_container.offset().top'.
						' : 0'.
				');'.
		'}'.
		'else {'.
			'jQuery(".overlay-dialogue-body").off("scroll");'.
		'}'.
	'})';

$scripts[] =
	'function updateGraphPreview() {'.
		'var $preview = jQuery("#svg-graph-preview"),'.
			'url = new Curl("zabbix.php"),'.
			'data = {'.
				'uniqueid: 0,'.
				'preview: 1,'.
				'content_width: $preview.width(),'.
				'content_height: $preview.height() - 10,'.
				'fields: JSON.stringify(jQuery("#'.$form->getId().'").serializeJSON())'.
			'};'.
		'url.setArgument("action", "widget.svggraph.view");'.

		'jQuery.ajax({'.
			'url: url.getUrl(),'.
			'method: "POST",'.
			'data: data,'.
			'dataType: "json",'.
			'success: function(r) {'.
				'if (typeof r.body !== "undefined" && typeof r.errors === "undefined") {'.
					'$preview.html(jQuery(r.body)).attr("unselectable", "on").css("user-select", "none");'.
				'}'.
			'}'.
		'})'.
	'}'.
	'updateGraphPreview();';

// Create 'Data set' tab.
$tab_data_set = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['ds']), CWidgetHelper::getGraphDataSet($fields['ds'], $form_name));
$scripts[] = $fields['ds']->getJavascript($form_name);
$jq_templates['dataset-row'] = $fields['ds']->getTemplate($form_name);

// Create 'Display options' tab.
$tab_display_opt = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['source']),
		CWidgetHelper::getRadioButtonList($fields['source'], $form_name)
	);

// Create 'Time period' tab.
$tab_time_period = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['graph_time']), CWidgetHelper::getCheckBox($fields['graph_time']))
	->addRow(CWidgetHelper::getLabel($fields['time_from']), CWidgetHelper::getDatePicker($fields['time_from']))
	->addRow(CWidgetHelper::getLabel($fields['time_to']), CWidgetHelper::getDatePicker($fields['time_to']));
$scripts[] = $fields['time_from']->getJavascript($form_name, 'updateGraphPreview();');
$scripts[] = $fields['time_to']->getJavascript($form_name, 'updateGraphPreview();');

// Create 'Axes' tab.
$tab_axes = (new CFormList())->addRow('',
	(new CDiv([
		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['lefty']), CWidgetHelper::getCheckBox($fields['lefty']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_min']), CWidgetHelper::getTextBox($fields['lefty_min']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_max']), CWidgetHelper::getTextBox($fields['lefty_max']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_units']), [
				CWidgetHelper::getComboBox($fields['lefty_units']),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				CWidgetHelper::getTextBox($fields['lefty_static_units'])
			])
			->addClass(ZBX_STYLE_COLUMN_33),

		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['righty']), CWidgetHelper::getCheckBox($fields['righty']))
			->addRow(CWidgetHelper::getLabel($fields['righty_min']), CWidgetHelper::getTextBox($fields['righty_min']))
			->addRow(CWidgetHelper::getLabel($fields['righty_max']), CWidgetHelper::getTextBox($fields['righty_max']))
			->addRow(CWidgetHelper::getLabel($fields['righty_units']), [
				CWidgetHelper::getComboBox($fields['righty_units']),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
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
$tab_legend = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['legend']), CWidgetHelper::getCheckBox($fields['legend']))
	->addRow(CWidgetHelper::getLabel($fields['legend_lines']), CWidgetHelper::getRangeControl($fields['legend_lines']));
$scripts[] = 'jQuery("[name=legend_lines]").rangeControl();';

// Add 'Problems' tab.
$tab_problems = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['show_problems']), CWidgetHelper::getCheckBox($fields['show_problems']))
	->addRow(CWidgetHelper::getLabel($fields['graph_item_problems']),
		CWidgetHelper::getCheckBox($fields['graph_item_problems'])
	)
	->addRow(CWidgetHelper::getLabel($fields['problemhosts']),
		CWidgetHelper::getHostsPatternTextBox($fields['problemhosts'], $form_name)
	)
	->addRow(CWidgetHelper::getLabel($fields['severities']),
		CWidgetHelper::getSeverities($fields['severities'], $data['config'])
	)
	->addRow(CWidgetHelper::getLabel($fields['problem_name']), CWidgetHelper::getTextBox($fields['problem_name']))
	->addRow(CWidgetHelper::getLabel($fields['evaltype']), CWidgetHelper::getRadioButtonList($fields['evaltype']))
	->addRow(CWidgetHelper::getLabel($fields['tags']), CWidgetHelper::getTags($fields['tags']));

$scripts[] = 'jQuery("#problemhosts").autoGrowTextarea({maxHeight: 100});';
$scripts[] = $fields['tags']->getJavascript();
$jq_templates['tag-row'] = CWidgetHelper::getTagsTemplate($fields['tags']);

// Create 'Overrides' tab.
$tab_overrides = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['or']), CWidgetHelper::getGraphOverride($fields['or'], $form_name));

$scripts[] = $fields['or']->getJavascript($form_name);
$jq_templates['overrides-row'] = $fields['or']->getTemplate($form_name);

// Create CTabView.
$form_tabs = (new CTabView())
	->addTab('data_set',  _('Data set'), $tab_data_set)
	->addTab('display_options',  _('Display options'), $tab_display_opt)
	->addTab('time_period',  _('Time period'), $tab_time_period)
	->addTab('axes',  _('Axes'), $tab_axes)
	->addTab('legend',  _('Legend'), $tab_legend)
	->addTab('problems',  _('Problems'), $tab_problems)
	->addTab('overrides',  _('Overrides'), $tab_overrides)
	->addClass('graph-widget-config-tabs') // Add special style used for graph widget tabs only.
	->onTabChange('jQuery.colorpicker("hide");')
	->setSelected(0);

// Add CTabView to form.
$form->addItem($form_tabs);
$scripts[] = $form_tabs->makeJavascript();

$scripts[] = 'jQuery("#'.$form_tabs->getId().'").on("change", "input, textarea", updateGraphPreview);';

return [
	'form' => $form,
	'scripts' => $scripts,
	'js_includes' => [
		'js/class.coverride.js',
		'js/class.cverticalaccordion.js',
		'js/class.crangecontrol.js',
		'js/colorpicker.js'
	],
	'jq_templates' => $jq_templates
];
