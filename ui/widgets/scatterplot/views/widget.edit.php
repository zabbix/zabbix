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
 * Scatter plot widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\ScatterPlot\Includes\{
	CWidgetFieldAxisThresholdsView,
	CWidgetFieldDataSet,
	CWidgetFieldDataSetView};

$form = new CWidgetFormView($data);

$form_tabs = (new CTabView())
	->addTab('data_set', _('Data set'), getDatasetTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_DATASET
	)
	->addTab('displaying_options', _('Displaying options'), getDisplayOptionsTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_DISPLAY_OPTIONS
	)
	->addTab('time_period', _('Time period'), getTimePeriodTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_TIME_PERIOD
	)
	->addTab('axes', _('Axes'), getAxesTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_AXES
	)
	->addTab('legend_tab', _('Legend'), getLegendTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_LEGEND
	)
	->addTab('thresholds_tab', _('Thresholds'), getThresholdsTab($form, $data['fields']),
		TAB_INDICATOR_SCATTER_PLOT_THRESHOLDS
	)
	->addClass('scatter-plot-widget-config-tabs')
	->setSelected(0);

$form
	->addItem($form_tabs)
	->addJavaScript($form_tabs->makeJavascript())
	->includeJsFile('widget.edit.js.php')
	->initFormJs('widget_form.init('.json_encode([
		'form_tabs_id' => $form_tabs->getId(),
		'color_palette' => CWidgetFieldDataSet::DEFAULT_COLOR_PALETTE,
		'templateid' => $data['templateid']
	], JSON_THROW_ON_ERROR).');')
	->show();

function getDatasetTab(CWidgetFormView $form, array $fields): array {
	$dataset = $form->registerField(new CWidgetFieldDataSetView($fields['ds']));

	return [
		(new CDiv($dataset->getView()))->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION),
		(new CDiv($dataset->getFooterView()))->addClass(ZBX_STYLE_LIST_ACCORDION_FOOT)
	];
}

function getDisplayOptionsTab(CWidgetFormView $form, array $fields): CDiv {
	$source = $form->registerField(new CWidgetFieldRadioButtonListView($fields['source']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addItem(
			(new CFormGrid())
				->addItem([
					$source->getLabel(),
					new CFormField($source->getView())
				])
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

function getAxesTab(CWidgetFormView $form, array $fields): CDiv {
	$x_axis = $form->registerField(new CWidgetFieldCheckBoxView($fields['x_axis']));
	$x_axis_min = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['x_axis_min']))->setPlaceholder(_('calculated'))
	);
	$x_axis_max = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['x_axis_max']))->setPlaceholder(_('calculated'))
	);
	$x_axis_units = $form->registerField(new CWidgetFieldSelectView($fields['x_axis_units']));
	$x_axis_static_units = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['x_axis_static_units']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);
	$y_axis = $form->registerField(new CWidgetFieldCheckBoxView($fields['y_axis']));
	$y_axis_min = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['y_axis_min']))->setPlaceholder(_('calculated'))
	);
	$y_axis_max = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['y_axis_max']))->setPlaceholder(_('calculated'))
	);
	$y_axis_units = $form->registerField(new CWidgetFieldSelectView($fields['y_axis_units']));
	$y_axis_static_units = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['y_axis_static_units']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_3)
		->addItem(
			(new CFormGrid())
				->addItem([
					$x_axis->getLabel(),
					new CFormField($x_axis->getView())
				])
				->addItem([
					$x_axis_min->getLabel(),
					new CFormField($x_axis_min->getView())
				])
				->addItem([
					$x_axis_max->getLabel(),
					new CFormField($x_axis_max->getView())
				])
				->addItem([
					$x_axis_units->getLabel(),
					new CFormField([
						$x_axis_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$x_axis_static_units->getView()
					])
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$y_axis->getLabel(),
					new CFormField($y_axis->getView())
				])
				->addItem([
					$y_axis_min->getLabel(),
					new CFormField($y_axis_min->getView())
				])
				->addItem([
					$y_axis_max->getLabel(),
					new CFormField($y_axis_max->getView())
				])
				->addItem([
					$y_axis_units->getLabel(),
					new CFormField([
						$y_axis_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$y_axis_static_units->getView()
					])
				])
		);
}

function getLegendTab(CWidgetFormView $form, array $fields): CDiv {
	$legend = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend']));
	$legend_aggregation = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend_aggregation']));
	$legend_lines_mode_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['legend_lines_mode']));
	$legend_lines = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_lines']));
	$legend_columns = $form->registerField(new CWidgetFieldRangeControlView($fields['legend_columns']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			(new CFormGrid())
				->addItem([
					$legend->getLabel(),
					new CFormField($legend->getView())
				])
				->addItem([
					$legend_aggregation->getLabel(),
					new CFormField($legend_aggregation->getView())
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$legend_lines_mode_field->getLabel(),
					new CFormField($legend_lines_mode_field->getView())
				])
				->addItem([
					$legend_lines->getLabel(),
					new CFormField($legend_lines->getView())
				])
				->addItem([
					$legend_columns->getLabel(),
					new CFormField($legend_columns->getView())
				])
		);
}

function getThresholdsTab(CWidgetFormView $form, array $fields): CTag {
	$interpolation = $form->registerField(new CWidgetFieldCheckBoxView($fields['interpolation']));
	$thresholds = $form->registerField(new CWidgetFieldAxisThresholdsView($fields['thresholds']));

	return (new CDiv())
		->addClass('thresholds-tab-grid')
		->addItem([
			$interpolation->getView(),
			$thresholds->getView()
		]);
}
