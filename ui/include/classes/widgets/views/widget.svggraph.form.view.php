<?php declare(strict_types = 0);
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

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.svggraph.form.view.js.php')];
$jq_templates = [];

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

$graph_preview = (new CDiv())
	->addClass(ZBX_STYLE_SVG_GRAPH_PREVIEW)
	->addItem((new CDiv())->setId('svg-graph-preview'));

$form_tabs = (new CTabView())
	->addTab('data_set', _('Data set'), getDatasetTab($fields, $jq_templates, $form->getName()),
		TAB_INDICATOR_GRAPH_DATASET
	)
	->addTab('displaying_options', _('Displaying options'), getDisplayOptionsTab($fields),
		TAB_INDICATOR_GRAPH_DISPLAY_OPTIONS
	)
	->addTab('time_period', _('Time period'), getTimePeriodTab($fields), TAB_INDICATOR_GRAPH_TIME)
	->addTab('axes', _('Axes'), getAxesTab($fields), TAB_INDICATOR_GRAPH_AXES)
	->addTab('legend_tab', _('Legend'), getLegendTab($fields, $scripts), TAB_INDICATOR_GRAPH_LEGEND)
	->addTab('problems', _('Problems'), getProblemsTab($fields, $scripts, $jq_templates, $form->getName()),
		TAB_INDICATOR_GRAPH_PROBLEMS
	)
	->addTab('overrides', _('Overrides'), getOverridesTab($fields, $scripts, $jq_templates, $form->getName()),
		TAB_INDICATOR_GRAPH_OVERRIDES
	)
	->addClass('graph-widget-config-tabs')
	->setSelected(0);
$scripts[] = $form_tabs->makeJavascript();

$form
	->addItem($form_grid)
	->addItem($graph_preview)
	->addItem($form_tabs);

$scripts[] = '
	widget_svggraph_form.init('.json_encode([
		'form_id' => $form->getId(),
		'form_tabs_id' => $form_tabs->getId(),
		'color_palette' => CWidgetFieldGraphDataSet::DEFAULT_COLOR_PALETTE
	]).');
';

return [
	'form' => $form,
	'scripts' => $scripts,
	'jq_templates' => $jq_templates
];

function getGraphDataSetItemRow(): string {
	return (new CRow([
		(new CCol(
			(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
		))
			->addClass('table-col-handle')
			->addClass(ZBX_STYLE_TD_DRAG_ICON),
		(new CCol(
			(new CColor('ds[#{dsNum}][color][]', '#{color}', 'items_#{dsNum}_#{rowNum}_color'))
				->appendColorPickerJs(false)
		))->addClass('table-col-color'),
		(new CCol(new CSpan('#{rowNum}:')))->addClass('table-col-no'),
		(new CCol(
			(new CLink('#{name}'))
				->setId('items_#{dsNum}_#{rowNum}_name')
				->addClass('js-click-expend')
		))->addClass('table-col-name'),
		(new CCol([
			(new CButton('button', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove'),
			(new CVar('ds[#{dsNum}][itemids][]', '#{itemid}', 'items_#{dsNum}_#{rowNum}_input'))
		]))
			->addClass('table-col-action')
			->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('sortable')
		->addClass('single-item-table-row')
		->toString();
}

function getDatasetTab(array $fields, array &$jq_templates, string $form_name): CFormGrid {
	$jq_templates['dataset-single-item-tmpl'] = CWidgetHelper::getGraphDataSetTemplate($fields['ds'], $form_name,
		CWidgetHelper::DATASET_TYPE_SINGLE_ITEM
	);
	$jq_templates['dataset-pattern-item-tmpl'] = CWidgetHelper::getGraphDataSetTemplate($fields['ds'], $form_name,
		CWidgetHelper::DATASET_TYPE_PATTERN_ITEM
	);
	$jq_templates['dataset-item-row-tmpl'] = getGraphDataSetItemRow();

	return (new CFormGrid())
		->addItem([
			CWidgetHelper::getLabel($fields['ds']),
			(new CFormField(CWidgetHelper::getGraphDataSet($fields['ds'], $form_name)))
				->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION),
			(new CFormField(CWidgetHelper::getGraphDataSetFooter()))->addClass(ZBX_STYLE_LIST_ACCORDION_FOOT)
		]);
}

function getDisplayOptionsTab(array $fields): CDiv {
	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['source']),
					new CFormField(CWidgetHelper::getRadioButtonList($fields['source']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['simple_triggers']),
					new CFormField(CWidgetHelper::getCheckBox($fields['simple_triggers']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['working_time']),
					new CFormField(CWidgetHelper::getCheckBox($fields['working_time']))
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['percentile_left']),
					new CFormField([
						CWidgetHelper::getCheckBox($fields['percentile_left']),
						CWidgetHelper::getTextBox($fields['percentile_left_value'])
					])
				])
				->addItem([
					CWidgetHelper::getLabel($fields['percentile_right']),
					new CFormField([
						CWidgetHelper::getCheckBox($fields['percentile_right']),
						CWidgetHelper::getTextBox($fields['percentile_right_value'])
					])
				])
		);
}

function getTimePeriodTab(array $fields): CFormGrid {
	return (new CFormGrid())
		->addItem([
			CWidgetHelper::getLabel($fields['graph_time']),
			new CFormField(CWidgetHelper::getCheckBox($fields['graph_time']))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['time_from']),
			new CFormField(
				CWidgetHelper::getDatePicker($fields['time_from'])
					->setDateFormat(ZBX_FULL_DATE_TIME)
					->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			)
		])
		->addItem([
			CWidgetHelper::getLabel($fields['time_to']),
			new CFormField(
				CWidgetHelper::getDatePicker($fields['time_to'])
					->setDateFormat(ZBX_FULL_DATE_TIME)
					->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			)
		]);
}

function getAxesTab(array $fields): CDiv {
	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_3)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['lefty']),
					new CFormField(CWidgetHelper::getCheckBox($fields['lefty']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['lefty_min']),
					new CFormField(CWidgetHelper::getNumericBox($fields['lefty_min']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['lefty_max']),
					new CFormField(CWidgetHelper::getNumericBox($fields['lefty_max']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['lefty_units']),
					new CFormField([
						CWidgetHelper::getSelect($fields['lefty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						CWidgetHelper::getTextBox($fields['lefty_static_units'])
					])
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['righty']),
					new CFormField(CWidgetHelper::getCheckBox($fields['righty']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['righty_min']),
					new CFormField(CWidgetHelper::getNumericBox($fields['righty_min']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['righty_max']),
					new CFormField(CWidgetHelper::getNumericBox($fields['righty_max']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['righty_units']),
					new CFormField([
						CWidgetHelper::getSelect($fields['righty_units'])->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						CWidgetHelper::getTextBox($fields['righty_static_units'])
					])
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['axisx']),
					new CFormField(CWidgetHelper::getCheckBox($fields['axisx']))
				])
		);
}

function getLegendTab(array $fields, array &$scripts): CDiv {
	$field_legend_lines = CWidgetHelper::getRangeControl($fields['legend_lines']);
	$field_legend_columns = CWidgetHelper::getRangeControl($fields['legend_columns']);

	$scripts[] = $field_legend_lines->getPostJS();
	$scripts[] = $field_legend_columns->getPostJS();

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['legend']),
					new CFormField(CWidgetHelper::getCheckBox($fields['legend']))
				])
				->addItem([
					CWidgetHelper::getLabel($fields['legend_statistic']),
					new CFormField(CWidgetHelper::getCheckBox($fields['legend_statistic']))
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					CWidgetHelper::getLabel($fields['legend_lines']),
					new CFormField($field_legend_lines)
				])
				->addItem([
					CWidgetHelper::getLabel($fields['legend_columns']),
					new CFormField($field_legend_columns)
				])
		);
}

function getProblemsTab(array $fields, array &$scripts, array &$jq_templates, string $form_name): CFormGrid {
	$scripts[] = $fields['problemhosts']->getJavascript();
	$scripts[] = $fields['tags']->getJavascript();
	$jq_templates['tag-row-tmpl'] = CWidgetHelper::getTagsTemplate($fields['tags']);

	return (new CFormGrid())
		->addItem([
			CWidgetHelper::getLabel($fields['show_problems']),
			new CFormField(CWidgetHelper::getCheckBox($fields['show_problems']))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['graph_item_problems']),
			new CFormField(CWidgetHelper::getCheckBox($fields['graph_item_problems']))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['problemhosts']),
			new CFormField(CWidgetHelper::getHostPatternSelect($fields['problemhosts'], $form_name))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['severities']),
			new CFormField(CWidgetHelper::getSeverities($fields['severities']))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['problem_name']),
			new CFormField(CWidgetHelper::getTextBox($fields['problem_name']))
		])
		->addItem([
			CWidgetHelper::getLabel($fields['evaltype']),
			new CFormField(CWidgetHelper::getRadioButtonList($fields['evaltype']))
		])
		->addItem(new CFormField(CWidgetHelper::getTags($fields['tags'])));
}

function getOverridesTab(array $fields, array &$scripts, array &$jq_templates, string $form_name): CFormGrid {
	$scripts[] = CWidgetHelper::getGraphOverrideJavascript($fields['or']);
	$jq_templates['overrides-row'] = CWidgetHelper::getGraphOverrideTemplate($fields['or'], $form_name);

	return (new CFormGrid())
		->addItem([
			CWidgetHelper::getLabel($fields['or']),
			new CFormField(CWidgetHelper::getGraphOverride($fields['or'], $form_name))
		]);
}
