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


/**
 * Pie chart widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\PieChart\Includes\{
	CWidgetFieldDataSet,
	CWidgetFieldDataSetView
};

$form = new CWidgetFormView($data);

$form_tabs = (new CTabView())
	->addTab('data_set', _('Data set'), getDatasetTab($form, $data['fields']),
		'pie-dataset'
	)
	->addTab('displaying_options', _('Displaying options'), getDisplayOptionsTab($form, $data['fields']),
		'pie-display-options'
	)
	->addTab('time_period', _('Time period'), getTimePeriodTab($form, $data['fields']),
		'pie-time-period'
	)
	->addTab('legend_tab', _('Legend'), getLegendTab($form, $data['fields']),
		'pie-legend'
	)
	->setSelected(0)
	->addClass('pie-chart-widget-config-tabs');

$form
	->addItem($form_tabs)
	->addJavaScript($form_tabs->makeJavascript())
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_pie_chart_form.init('.json_encode([
			'form_tabs_id' => $form_tabs->getId(),
			'color_palette' => CWidgetFieldDataSet::DEFAULT_COLOR_PALETTE,
			'templateid' => $data['templateid']
		], JSON_THROW_ON_ERROR).');')
	->show();

function getDatasetTab(CWidgetFormView $form, array $fields): array {
	$dataset_field = $form->registerField(new CWidgetFieldDataSetView($fields['ds']));

	return [
		(new CDiv($dataset_field->getView()))->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION),
		(new CDiv($dataset_field->getFooterView()))->addClass(ZBX_STYLE_LIST_ACCORDION_FOOT)
	];
}

function getDisplayOptionsTab(CWidgetFormView $form, array $fields): CDiv {
	$source_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['source']));
	$draw_type_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['draw_type']));
	$width_field = $form->registerField(new CWidgetFieldRangeControlView($fields['width']));
	$stroke_field = $form->registerField(new CWidgetFieldRangeControlView($fields['stroke']));
	$space_field = $form->registerField(new CWidgetFieldRangeControlView($fields['space']));
	$merge_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['merge']));
	$merge_percent_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['merge_percent']));
	$merge_color_field = $form->registerField(new CWidgetFieldColorView($fields['merge_color']));
	$total_show_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['total_show']));
	$value_size_type_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['value_size_type']));
	$value_size_input_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['value_size']));
	$decimal_places_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['decimal_places']));
	$units_show_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['units_show']));
	$units_field = $form->registerField(new CWidgetFieldTextBoxView($fields['units']));
	$value_bold_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['value_bold']));
	$value_color_field = $form->registerField(new CWidgetFieldColorView($fields['value_color']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					$source_field->getLabel(),
					new CFormField($source_field->getView())
				])
				->addItem([
					$draw_type_field->getLabel(),
					new CFormField($draw_type_field->getView())
				])
				->addItem([
					$width_field->getLabel()->setId('width_label'),
					(new CFormField([$width_field->getView(), ' %']))->setId('width_range')
				])
				->addItem([
					$stroke_field->getLabel()->setId('stroke_label'),
					(new CFormField($stroke_field->getView()))->setId('stroke_range')
				])
				->addItem([
					$space_field->getLabel(),
					new CFormField($space_field->getView())
				])
				->addItem([
					$merge_field->getLabel(),
					new CFormField([
						$merge_field->getView(),
						($merge_percent_field->getView())->setWidth(ZBX_TEXTAREA_NUMERIC_SMALL_WIDTH),
						' % ',
						$merge_color_field->getView()
					])
				])

		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$total_show_field->getLabel(),
					new CFormField($total_show_field->getView())
				])
				->addItem([
					$value_size_type_field->getLabel(),
					new CFormField([
						($value_size_type_field->getView())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						($value_size_input_field->getView())->setId('value_size_custom_input'),
						' %'
					])
				])
				->addItem([
					$decimal_places_field->getLabel(),
					new CFormField($decimal_places_field->getView())
				])
				->addItem([
					$units_show_field->getView(),
					(new CFormField(($units_field->getView())->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)))
				])
				->addItem([
					$value_bold_field->getLabel(),
					new CFormField($value_bold_field->getView())
				])
				->addItem([
					$value_color_field->getLabel(),
					new CFormField($value_color_field->getView())
				])->setId('show_total_fields')
		);
}

function getTimePeriodTab(CWidgetFormView $form, array $fields): CFormGrid {
	$time_period_field = (new CWidgetFieldTimePeriodView($fields['time_period']))
		->setDateFormat(ZBX_FULL_DATE_TIME)
		->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
		->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'));

	$form->registerField($time_period_field);

	$form_grid = new CFormGrid();

	foreach ($time_period_field->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
		$form_grid->addItem([
			$label,
			(new CFormField($view))->addClass($class)
		]);
	}

	return $form_grid;
}

function getLegendTab(CWidgetFormView $form, array $fields): CDiv {
	$show_legend_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend']));
	$show_value_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend_value']));
	$show_aggregation_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend_aggregation']));
	$legend_lines_mode_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['legend_lines_mode']));
	$legend_lines_field = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_lines']));
	$legend_columns_field = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_columns']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					$show_legend_field->getLabel(),
					new CFormField($show_legend_field->getView())
				])
				->addItem([
					$show_value_field->getLabel(),
					new CFormField($show_value_field->getView())
				])
				->addItem([
					$show_aggregation_field->getLabel(),
					new CFormField($show_aggregation_field->getView())
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$legend_lines_mode_field->getLabel(),
					new CFormField($legend_lines_mode_field->getView())
				])
				->addItem([
					$legend_lines_field->getLabel(),
					new CFormField($legend_lines_field->getView())
				])
				->addItem([
					$legend_columns_field->getLabel(),
					new CFormField($legend_columns_field->getView())
				])
		);
}
