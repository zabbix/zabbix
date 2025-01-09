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
 * Graph widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\SvgGraph\Includes\{
	CWidgetFieldDataSet,
	CWidgetFieldDataSetView,
	CWidgetFieldOverrideView
};

$form = new CWidgetFormView($data);

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
		TAB_INDICATOR_GRAPH_TIME_PERIOD
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
	$simple_triggers = $form->registerField(new CWidgetFieldCheckBoxView($fields['simple_triggers']));
	$working_time = $form->registerField(new CWidgetFieldCheckBoxView($fields['working_time']));
	$percentile_left = $form->registerField(new CWidgetFieldCheckBoxView($fields['percentile_left']));
	$percentile_left_value = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['percentile_left_value']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);
	$percentile_right = $form->registerField(new CWidgetFieldCheckBoxView($fields['percentile_right']));
	$percentile_right_value = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['percentile_right_value']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);

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
					$simple_triggers->getLabel(),
					new CFormField($simple_triggers->getView())
				])
				->addItem([
					$working_time->getLabel(),
					new CFormField($working_time->getView())
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$percentile_left->getLabel(),
					new CFormField([
						$percentile_left->getView(),
						$percentile_left_value->getView()
					])
				])
				->addItem([
					$percentile_right->getLabel(),
					new CFormField([
						$percentile_right->getView(),
						$percentile_right_value->getView()
					])
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
	$lefty = $form->registerField(new CWidgetFieldCheckBoxView($fields['lefty']));
	$lefty_scale = $form->registerField(new CWidgetFieldSelectView($fields['lefty_scale']));
	$lefty_min = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['lefty_min']))->setPlaceholder(_('calculated'))
	);
	$lefty_max = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['lefty_max']))->setPlaceholder(_('calculated'))
	);
	$lefty_units = $form->registerField(new CWidgetFieldSelectView($fields['lefty_units']));
	$lefty_static_units = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['lefty_static_units']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);
	$righty = $form->registerField(new CWidgetFieldCheckBoxView($fields['righty']));
	$righty_scale = $form->registerField(new CWidgetFieldSelectView($fields['righty_scale']));
	$righty_min = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['righty_min']))->setPlaceholder(_('calculated'))
	);
	$righty_max = $form->registerField(
		(new CWidgetFieldNumericBoxView($fields['righty_max']))->setPlaceholder(_('calculated'))
	);
	$righty_units = $form->registerField(new CWidgetFieldSelectView($fields['righty_units']));
	$righty_static_units = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['righty_static_units']))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	);
	$axisx = $form->registerField(new CWidgetFieldCheckBoxView($fields['axisx']));

	return (new CDiv())
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_3)
		->addItem(
			(new CFormGrid())
				->addItem([
					$lefty->getLabel(),
					new CFormField($lefty->getView())
				])
				->addItem([
					$lefty_scale->getLabel(),
					new CFormField($lefty_scale->getView())
				])
				->addItem([
					$lefty_min->getLabel(),
					new CFormField($lefty_min->getView())
				])
				->addItem([
					$lefty_max->getLabel(),
					new CFormField($lefty_max->getView())
				])
				->addItem([
					$lefty_units->getLabel(),
					new CFormField([
						$lefty_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$lefty_static_units->getView()
					])
				])
		)
		->addItem(
			(new CFormGrid())
				->addItem([
					$righty->getLabel(),
					new CFormField($righty->getView())
				])
				->addItem([
					$righty_scale->getLabel(),
					new CFormField($righty_scale->getView())
				])
				->addItem([
					$righty_min->getLabel(),
					new CFormField($righty_min->getView())
				])
				->addItem([
					$righty_max->getLabel(),
					new CFormField($righty_max->getView())
				])
				->addItem([
					$righty_units->getLabel(),
					new CFormField([
						$righty_units->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$righty_static_units->getView()
					])
				])
		)
		->addItem(
			(new CFormGrid())->addItem([
				$axisx->getLabel(),
				new CFormField($axisx->getView())
			])
		);
}

function getLegendTab(CWidgetFormView $form, array $fields): CDiv {
	$legend = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend']));
	$legend_statistic = $form->registerField(new CWidgetFieldCheckBoxView($fields['legend_statistic']));
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
					$legend_statistic->getLabel(),
					new CFormField($legend_statistic->getView())
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

function getProblemsTab(CWidgetFormView $form, array $fields): CFormGrid {
	$show_problems = $form->registerField(new CWidgetFieldCheckBoxView($fields['show_problems']));
	$graph_item_problems = $form->registerField(new CWidgetFieldCheckBoxView($fields['graph_item_problems']));
	$problemhosts = array_key_exists('problemhosts', $fields)
		? $form->registerField(
			(new CWidgetFieldPatternSelectHostView($fields['problemhosts']))->setPlaceholder(_('host patterns'))
		)
		: null;
	$severities = $form->registerField(new CWidgetFieldSeveritiesView($fields['severities']));
	$problem_name = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['problem_name']))->setPlaceholder(_('problem pattern'))
	);
	$evaltype = $form->registerField(new CWidgetFieldRadioButtonListView($fields['evaltype']));
	$tags = $form->registerField(new CWidgetFieldTagsView($fields['tags']));

	return (new CFormGrid())
		->addItem([
			$show_problems->getLabel(),
			new CFormField($show_problems->getView())
		])
		->addItem([
			$graph_item_problems->getLabel(),
			new CFormField($graph_item_problems->getView())
		])
		->addItem($problemhosts !== null
			? [
				$problemhosts->getLabel(),
				new CFormField($problemhosts->getView())
			]
			: null
		)
		->addItem([
			$severities->getLabel(),
			new CFormField($severities->getView())
		])
		->addItem([
			$problem_name->getLabel(),
			new CFormField($problem_name->getView())
		])
		->addItem([
			$evaltype->getLabel(),
			new CFormField($evaltype->getView())
		])
		->addItem(
			new CFormField($tags->getView())
		);
}

function getOverridesTab(CWidgetFormView $form, array $fields): CFormGrid {
	$overrides = $form->registerField(new CWidgetFieldOverrideView($fields['or']));

	return (new CFormGrid())->addItem([
		$overrides->getLabel(),
		new CFormField($overrides->getView())
	]);
}
