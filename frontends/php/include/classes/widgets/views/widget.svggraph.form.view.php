<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	$data['dialogue']['view_mode'], $data['known_widget_types'], $fields['rf_rate']
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
	'function onLeftYChange() {'.
		'var on = (!jQuery("#lefty").is(":disabled") && jQuery("#lefty").is(":checked"));'.
		'if (jQuery("#lefty").is(":disabled") && !jQuery("#lefty").is(":checked")) {'.
			'jQuery("#lefty").prop("checked", true);'.
		'}'.
		'jQuery("#lefty_min, #lefty_max, #lefty_units").prop("disabled", !on);'.
		'jQuery("#lefty_static_units").prop("disabled",'.
			'(!on || jQuery("#lefty_units").val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"));'.
	'}'.
	'function onRightYChange() {'.
		'var on = (!jQuery("#righty").is(":disabled") && jQuery("#righty").is(":checked"));'.
		'if (jQuery("#righty").is(":disabled") && !jQuery("#righty").is(":checked")) {'.
			'jQuery("#righty").prop("checked", true);'.
		'}'.
		'jQuery("#righty_min, #righty_max, #righty_units").prop("disabled", !on);'.
		'jQuery("#righty_static_units").prop("disabled",'.
			'(!on || jQuery("#righty_units").val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"));'.
	'}';

$scripts[] =
	'function onGraphConfigChange() {'.
		// Update graph preview.
		'var $preview = jQuery("#svg-graph-preview"),'.
			'$form = jQuery("#'.$form->getId().'"),'.
			'url = new Curl("zabbix.php"),'.
			'data = {'.
				'uniqueid: 0,'.
				'preview: 1,'.
				'content_width: $preview.width(),'.
				'content_height: $preview.height() - 10'.
			'};'.
		'url.setArgument("action", "widget.svggraph.view");'.

		// Enable/disable fields for Y axis.
		'if (this.id !== "lefty" && this.id !== "righty") {'.
			'var axes_used = {'.GRAPH_YAXIS_SIDE_LEFT.':0, '.GRAPH_YAXIS_SIDE_RIGHT.':0};'.

			'jQuery("[type=radio]", $form).each(function() {'.
				'if (jQuery(this).attr("name").match(/ds\[\d+\]\[axisy\]/) && jQuery(this).is(":checked")) {'.
					'axes_used[jQuery(this).val()]++;'.
				'}'.
			'});'.
			'jQuery("[type=hidden]", $form).each(function() {'.
				'if (jQuery(this).attr("name").match(/or\[\d+\]\[axisy\]/)) {'.
					'axes_used[jQuery(this).val()]++;'.
				'}'.
			'});'.

			'jQuery("#lefty").prop("disabled", !axes_used['.GRAPH_YAXIS_SIDE_LEFT.']);'.
			'jQuery("#righty").prop("disabled", !axes_used['.GRAPH_YAXIS_SIDE_RIGHT.']);'.

			'onLeftYChange();'.
			'onRightYChange();'.
		'}'.

		'var form_fields = $form.serializeJSON();'.
		'if ("ds" in form_fields) {'.
			'for (var i in form_fields.ds) {'.
				'form_fields.ds[i] = jQuery.extend({"hosts":[], "items":[]}, form_fields.ds[i]);'.
			'}'.
		'}'.
		'if ("or" in form_fields) {'.
			'for (var i in form_fields.or) {'.
				'form_fields.or[i] = jQuery.extend({"hosts":[], "items":[]}, form_fields.or[i]);'.
			'}'.
		'}'.
		'data.fields = JSON.stringify(form_fields);'.

		'jQuery.ajax({'.
			'url: url.getUrl(),'.
			'method: "POST",'.
			'data: data,'.
			'dataType: "json",'.
			'success: function(r) {'.
				'$form.prev(".msg-bad").remove();'.
				'if (typeof r.messages !== "undefined") {'.
					'jQuery(r.messages).insertBefore($form);'.
				'}'.
				'if (typeof r.body !== "undefined") {'.
					'$preview.html(jQuery(r.body)).attr("unselectable", "on").css("user-select", "none");'.
				'}'.
			'}'.
		'});'.
	'}';

$scripts[] =
	/**
	 * This function needs to change element names in "Data set" or "Overrides" controls after reordering elements.
	 *
	 * @param obj           "Data set" or "Overrides" element.
	 * @param row_selector  jQuery selector for rows.
	 * @param var_prefix    Prefix for the variables, which will be renamed.
	 */
	'function updateVariableOrder(obj, row_selector, var_prefix) {'.
		'jQuery.each([10000, 0], function(index, value) {'.
			'jQuery(row_selector, obj).each(function(i) {'.
				'jQuery(".multiselect[data-params]", this).each(function() {'.
					'var name = jQuery(this).multiSelect("getOption", "name");'.
					'if (name !== null) {'.
						'jQuery(this).multiSelect("modify", {'.
							'name: name.replace(/([a-z]+\[)\d+(\]\[[a-z]+\])/, "$1" + (value + i) + "$2")'.
						'});'.
					'}'.
				'});'.

				'jQuery(\'[name^="\' + var_prefix + \'["]\', this).filter(function() {'.
					'return jQuery(this).attr("name").match(/[a-z]+\[\d+\]\[[a-z]+\]/);'.
				'}).each(function() {'.
					'jQuery(this).attr("name", '.
						'jQuery(this).attr("name").replace(/([a-z]+\[)\d+(\]\[[a-z]+\])/, "$1" + (value + i) + "$2")'.
					');'.
				'});'.
			'});'.
		'});'.
	'}';

// Create 'Data set' tab.
$tab_data_set = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['ds']), CWidgetHelper::getGraphDataSet($fields['ds'], $form_name));
$scripts[] = CWidgetHelper::getGraphDataSetJavascript();
$jq_templates['dataset-row'] = CWidgetHelper::getGraphDataSetTemplate($fields['ds'], $form_name);

// Create 'Displaying options' tab.
$tab_displaying_opt = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['source']),
		CWidgetHelper::getRadioButtonList($fields['source'], $form_name)
	);

// Create 'Time period' tab.
$tab_time_period = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['graph_time']), CWidgetHelper::getCheckBox($fields['graph_time']))
	->addRow(CWidgetHelper::getLabel($fields['time_from']), CWidgetHelper::getDatePicker($fields['time_from']))
	->addRow(CWidgetHelper::getLabel($fields['time_to']), CWidgetHelper::getDatePicker($fields['time_to']));

// Create 'Axes' tab.
$tab_axes = (new CFormList())->addRow('',
	(new CDiv([
		(new CFormList())
			->addRow(CWidgetHelper::getLabel($fields['lefty']), CWidgetHelper::getCheckBox($fields['lefty']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_min']), CWidgetHelper::getNumericBox($fields['lefty_min']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_max']), CWidgetHelper::getNumericBox($fields['lefty_max']))
			->addRow(CWidgetHelper::getLabel($fields['lefty_units']), [
				CWidgetHelper::getComboBox($fields['lefty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
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
				CWidgetHelper::getComboBox($fields['righty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
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
		CWidgetHelper::getHostPatternSelect($fields['problemhosts'], $form_name)
	)
	->addRow(CWidgetHelper::getLabel($fields['severities']),
		CWidgetHelper::getSeverities($fields['severities'], $data['config'])
	)
	->addRow(CWidgetHelper::getLabel($fields['problem_name']), CWidgetHelper::getTextBox($fields['problem_name']))
	->addRow(CWidgetHelper::getLabel($fields['evaltype']), CWidgetHelper::getRadioButtonList($fields['evaltype']))
	->addRow(CWidgetHelper::getLabel($fields['tags']), CWidgetHelper::getTags($fields['tags']));

$scripts[] = $fields['problemhosts']->getJavascript();
$scripts[] = $fields['tags']->getJavascript();
$jq_templates['tag-row-tmpl'] = CWidgetHelper::getTagsTemplate($fields['tags']);

// Create 'Overrides' tab.
$tab_overrides = (new CFormList())
	->addRow(CWidgetHelper::getLabel($fields['or']), CWidgetHelper::getGraphOverride($fields['or'], $form_name));

$scripts[] = CWidgetHelper::getGraphOverrideJavascript($fields['or'], $form_name);
$jq_templates['overrides-row'] = CWidgetHelper::getGraphOverrideTemplate($fields['or'], $form_name);

// Create CTabView.
$form_tabs = (new CTabView())
	->addTab('data_set',  _('Data set'), $tab_data_set)
	->addTab('displaying_options',  _('Displaying options'), $tab_displaying_opt)
	->addTab('time_period',  _('Time period'), $tab_time_period)
	->addTab('axes',  _('Axes'), $tab_axes)
	->addTab('legendtab',  _('Legend'), $tab_legend)
	->addTab('problems',  _('Problems'), $tab_problems)
	->addTab('overrides',  _('Overrides'), $tab_overrides)
	->addClass('graph-widget-config-tabs') // Add special style used for graph widget tabs only.
	->onTabChange('jQuery.colorpicker("hide");')
	->setSelected(0);

// Add CTabView to form.
$form->addItem($form_tabs);
$scripts[] = $form_tabs->makeJavascript();

$scripts[] = 'jQuery("#'.$form_tabs->getId().'").on("change", "input, select, .multiselect", onGraphConfigChange);';
$scripts[] = 'onGraphConfigChange();';

return [
	'form' => $form,
	'scripts' => $scripts,
	'jq_templates' => $jq_templates
];
