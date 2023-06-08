<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Pie chart widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Zabbix\Widgets\Fields\CWidgetFieldPieChartDataSet;

$form = (new CWidgetFormView($data));

$form_tabs = (new CTabView())
	->addTab('data_set', _('Data set'), getDatasetTab($form, $data['fields']),
		TAB_INDICATOR_PIE_CHART_DATASET
	)
	->addTab('displaying_options', _('Displaying options'), getDisplayOptionsTab($form, $data['fields']),
		TAB_INDICATOR_PIE_CHART_DISPLAY_OPTIONS
	)
	->addTab('time_period', _('Time period'), getTimePeriodTab($form, $data['fields']),
		TAB_INDICATOR_PIE_CHART_TIME
	)
	->addTab('legend_tab', _('Legend'), getLegendTab($form, $data['fields']),
		TAB_INDICATOR_PIE_CHART_LEGEND
	)
	->setSelected(0)
	->addClass('pie-chart-widget-config-tabs');

$form
	->addItem($form_tabs)
	->addJavaScript($form_tabs->makeJavascript())
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_piechart_form.init('.json_encode([
			'form_tabs_id' => $form_tabs->getId(),
			'color_palette' => CWidgetFieldPieChartDataSet::DEFAULT_COLOR_PALETTE,
			'templateid' => $data['templateid']
		], JSON_THROW_ON_ERROR).');')
	->show();

function getDatasetTab(CWidgetFormView $form, array $fields): array {
	$dataset = $form->registerField(new CWidgetFieldPieChartDataSetView($fields['ds']));

	return [
		(new CDiv($dataset->getView()))->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION),
		(new CDiv($dataset->getFooterView()))->addClass(ZBX_STYLE_LIST_ACCORDION_FOOT)
	];
}

function getDisplayOptionsTab(CWidgetFormView $form, array $fields): CDiv {
	$source = $form->registerField(new CWidgetFieldRadioButtonListView($fields['source']));
	$draw = $form->registerField(new CWidgetFieldRadioButtonListView($fields['draw']));
	$width = $form->registerField(new CWidgetFieldRangeControlView($fields['width']));
	$stroke = $form->registerField(new CWidgetFieldRangeControlView($fields['stroke']));
	$sector_space = $form->registerField(new CWidgetFieldRangeControlView($fields['sector_space']));
	$merge_sectors = $form->registerField(new CWidgetFieldCheckBoxView($fields['merge_sectors']));
	$merge_percentage = $form->registerField(new CWidgetFieldIntegerBoxView($fields['merge_percentage']));
	$merge_color = $form->registerField(new CWidgetFieldColorView($fields['merge_color']));
	$show_total = $form->registerField(new CWidgetFieldCheckBoxView($fields['show_total']));
	$value_size = $form->registerField(new CWidgetFieldIntegerBoxView($fields['value_size']));
	$decimal_places = $form->registerField(new CWidgetFieldIntegerBoxView($fields['decimal_places']));
	$value_bold = $form->registerField(new CWidgetFieldCheckBoxView($fields['value_bold']));
	$value_color = $form->registerField(new CWidgetFieldColorView($fields['value_color']));
	$units_show = $form->registerField(new CWidgetFieldCheckBoxView($fields['units_show']));
	$units_value = $form->registerField(new CWidgetFieldTextBoxView($fields['units_value']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					$source->getLabel(),
					new CFormField($source->getView())
				])
				->addItem([
					$draw->getLabel(),
					new CFormField($draw->getView())
				])
				->addItem([
					$width->getLabel()->setId('width_label'),
					(new CFormField([$width->getView(), ' %']))->setId('width_range')
				])
				->addItem([
					$stroke->getLabel(),
					new CFormField($stroke->getView())
				])
				->addItem([
					$sector_space->getLabel(),
					new CFormField($sector_space->getView())
				])
				->addItem([
					$merge_sectors->getLabel(),
					(new CFormField([
						$merge_sectors->getView(), ($merge_percentage->getView())->setWidth(55), ' % ', $merge_color->getView()
					]))
				])

		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$show_total->getLabel(),
					new CFormField($show_total->getView())
				])
				->addItem([
					$value_size->getLabel(),
					(new CFormField([$value_size->getView(), ' %']))
				])
				->addItem([
					$decimal_places->getLabel(),
					new CFormField($decimal_places->getView())
				])
				->addItem([
					$units_show->getView(),
					(new CFormField(($units_value->getView())->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)))
				])
				->addItem([
					$value_bold->getLabel(),
					new CFormField($value_bold->getView())
				])
				->addItem([
					$value_color->getLabel(),
					new CFormField($value_color->getView())
				])->setId('show_total_fields')
		);
}

function getTimePeriodTab(CWidgetFormView $form, array $fields): CFormGrid {
	$chart_time = $form->registerField(new CWidgetFieldCheckBoxView($fields['chart_time']));
	$time_from = $form->registerField(
		(new CWidgetFieldDatePickerView($fields['time_from']))
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	);
	$time_to = $form->registerField(
		(new CWidgetFieldDatePickerView($fields['time_to']))
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	);

	return (new CFormGrid())
		->addItem([
			$chart_time->getLabel(),
			new CFormField($chart_time->getView())
		])
		->addItem([
			$time_from->getLabel(),
			new CFormField($time_from->getView())
		])
		->addItem([
			$time_to->getLabel(),
			new CFormField($time_to->getView())
		]);
}

function getLegendTab(CWidgetFormView $form, array $fields): CFormGrid {
	$legend = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend']));
	$legend_lines = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_lines']));
	$legend_columns = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_columns']));

	return (new CFormGrid())
		->addItem([
			$legend->getLabel(),
			new CFormField($legend->getView())
		])
		->addItem([
			$legend_lines->getLabel(),
			new CFormField($legend_lines->getView())
		])
		->addItem([
			$legend_columns->getLabel(),
			new CFormField($legend_columns->getView())
		]);
}
