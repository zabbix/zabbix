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
 * Graph widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Zabbix\Widgets\Fields\CWidgetFieldGraphDataSet;

$form = (new CWidgetFormView($data));

$preview = (new CDiv())
	->addClass(ZBX_STYLE_SVG_GRAPH_PREVIEW)
	->addItem((new CDiv())->setId('svg-graph-preview'));

$form_tabs = (new CTabView())
	->addTab('data_set', _('Data set'), getDatasetTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_DATASET
	)
	->addTab('displaying_options', _('Displaying options'), getDisplayOptionsTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_DISPLAY_OPTIONS
	)
	->addTab('time_period', _('Time period'), getTimePeriodTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_TIME
	)
	->addTab('axes', _('Axes'), getAxesTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_AXES
	)
	->addTab('legend_tab', _('Legend'), getLegendTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_LEGEND
	)
	->addTab('problems', _('Problems'), getProblemsTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_PROBLEMS
	)
	->addTab('overrides', _('Overrides'), getOverridesTab($form, $data['fields']),
		TAB_INDICATOR_GRAPH_OVERRIDES
	)
	->addClass('graph-widget-config-tabs')
	->setSelected(0);

$form
	->addItem([$preview, $form_tabs])
	->addJavaScript($form_tabs->makeJavascript())
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_svggraph_form.init('.json_encode([
		'form_tabs_id' => $form_tabs->getId(),
		'color_palette' => CWidgetFieldGraphDataSet::DEFAULT_COLOR_PALETTE
	], JSON_THROW_ON_ERROR).');')
	->show();

function getDatasetTab(CWidgetFormView $form, array $fields): array {
	$dataset = new CWidgetFieldGraphDataSetView($fields['ds']);

	return $form->makeCustomField($dataset, [
		(new CDiv($dataset->getView()))->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION),
		(new CDiv($dataset->getFooterView()))->addClass(ZBX_STYLE_LIST_ACCORDION_FOOT)
	]);
}

function getDisplayOptionsTab(CWidgetFormView $form, array $fields): CDiv {
	$percentile_left = new CWidgetFieldCheckBoxView($fields['percentile_left']);
	$percentile_left_value = (new CWidgetFieldTextBoxView($fields['percentile_left_value']))
		->setPlaceholder(_('value'))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

	$percentile_right = new CWidgetFieldCheckBoxView($fields['percentile_right']);
	$percentile_right_value = (new CWidgetFieldTextBoxView($fields['percentile_right_value']))
		->setPlaceholder(_('value'))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			new CFormGrid([
				$form->makeCustomField(
					new CWidgetFieldRadioButtonListView($fields['source'])
				),

				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['simple_triggers'])
				),

				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['working_time'])
				)
			])
		)
		->addItem(
			new CFormGrid([
				$form->makeCustomField($percentile_left, [
					$percentile_left->getLabel(),
					new CFormField([
						$percentile_left->getView(),
						$percentile_left_value->getView()
					])
				]),

				$form->makeCustomField($percentile_right, [
					$percentile_right->getLabel(),
					new CFormField([
						$percentile_right->getView(),
						$percentile_right_value->getView()
					])
				])
			])
		);
}

function getTimePeriodTab(CWidgetFormView $form, array $fields): CFormGrid {
	return new CFormGrid([
		$form->makeCustomField(
			new CWidgetFieldCheckBoxView($fields['graph_time'])
		),

		$form->makeCustomField(
			(new CWidgetFieldDatePickerView($fields['time_from']))
				->setDateFormat(ZBX_FULL_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
		),

		$form->makeCustomField(
			(new CWidgetFieldDatePickerView($fields['time_to']))
				->setDateFormat(ZBX_FULL_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
		)
	]);
}

function getAxesTab(CWidgetFormView $form, array $fields): CDiv {
	$lefty_units = new CWidgetFieldSelectView($fields['lefty_units']);
	$lefty_static_units = (new CWidgetFieldTextBoxView($fields['lefty_static_units']))
		->setPlaceholder(_('value'))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

	$righty_units = new CWidgetFieldSelectView($fields['righty_units']);
	$righty_static_units = (new CWidgetFieldTextBoxView($fields['righty_static_units']))
		->setPlaceholder(_('value'))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_3)
		->addItem(
			new CFormGrid([
				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['lefty'])
				),

				$form->makeCustomField(
					(new CWidgetFieldNumericBoxView($fields['lefty_min']))->setPlaceholder(_('calculated'))
				),

				$form->makeCustomField(
					(new CWidgetFieldNumericBoxView($fields['lefty_max']))->setPlaceholder(_('calculated'))
				),

				$form->makeCustomField($lefty_units, [
					$lefty_units->getLabel(),
					new CFormField([
						$lefty_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$lefty_static_units->getView()
					])
				])
			])
		)
		->addItem(
			new CFormGrid([
				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['righty'])
				),

				$form->makeCustomField(
					(new CWidgetFieldNumericBoxView($fields['righty_min']))->setPlaceholder(_('calculated'))
				),

				$form->makeCustomField(
					(new CWidgetFieldNumericBoxView($fields['righty_max']))->setPlaceholder(_('calculated'))
				),

				$form->makeCustomField($righty_units, [
					$righty_units->getLabel(),
					new CFormField([
						$righty_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$righty_static_units->getView()
					])
				])
			])
		)
		->addItem(
			new CFormGrid(
				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['axisx'])
				)
			)
		);
}

function getLegendTab(CWidgetFormView $form, array $fields): CDiv {
	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		->addItem(
			new CFormGrid([
				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['legend'])
				),

				$form->makeCustomField(
					new CWidgetFieldCheckBoxView($fields['legend_statistic'])
				)
			])
		)
		->addItem(
			new CFormGrid([
				$form->makeCustomField(
					new CWidgetFieldRangeControlView($fields['legend_lines'])
				),

				$form->makeCustomField(
					new CWidgetFieldRangeControlView($fields['legend_columns'])
				)
			])
		);
}

function getProblemsTab(CWidgetFormView $form, array $fields): CFormGrid {
	return new CFormGrid([
		$form->makeCustomField(
			new CWidgetFieldCheckBoxView($fields['show_problems'])
		),

		$form->makeCustomField(
			new CWidgetFieldCheckBoxView($fields['graph_item_problems'])
		),

		$form->makeCustomField(
			(new CWidgetFieldHostPatternSelectView($fields['problemhosts']))->setPlaceholder(_('host pattern'))
		),

		$form->makeCustomField(
			new CWidgetFieldSeveritiesView($fields['severities'])
		),

		$form->makeCustomField(
			(new CWidgetFieldTextBoxView($fields['problem_name']))->setPlaceholder(_('problem pattern'))
		),

		$form->makeCustomField(
			new CWidgetFieldRadioButtonListView($fields['evaltype'])
		),

		$form->makeCustomField(
			new CWidgetFieldTagsView($fields['tags'])
		)
	]);
}

function getOverridesTab(CWidgetFormView $form, array $fields): CFormGrid {
	return new CFormGrid(
		$form->makeCustomField(
			new CWidgetFieldGraphOverrideView($fields['or'])
		)
	);
}
