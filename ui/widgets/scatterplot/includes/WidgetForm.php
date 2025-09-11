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


namespace Widgets\ScatterPlot\Includes;

use CNumberParser,
	CParser,
	CWidgetsData;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldNumericBox,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldSelect,
	CWidgetFieldTextBox,
	CWidgetFieldTimePeriod
};

/**
 * Scatter plot widget form view.
 */
class WidgetForm extends CWidgetForm {

	public const LEGEND_ON = 1;
	public const LEGEND_AGGREGATION_ON = 1;

	public const LEGEND_LINES_MODE_FIXED = 0;
	public const LEGEND_LINES_MODE_VARIABLE = 1;

	public const LEGEND_LINES_MIN = 1;
	public const LEGEND_LINES_MAX = 10;

	public const LEGEND_COLUMNS_MIN = 1;
	public const LEGEND_COLUMNS_MAX = 4;

	private bool $x_axis_on = true;
	private bool $x_axis_units_static = false;
	private bool $y_axis_on = true;
	private bool $y_axis_units_static = false;

	private bool $legend_on = true;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		$number_parser_w_suffix = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		// Validate Min/Max values in Axes tab.
		if ($this->getFieldValue('x_axis') == SVG_GRAPH_AXIS_ON) {
			$x_axis_min = $number_parser_w_suffix->parse($this->getFieldValue('x_axis_min')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			$x_axis_max = $number_parser_w_suffix->parse($this->getFieldValue('x_axis_max')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			if ($x_axis_min !== null && $x_axis_max !== null && $x_axis_min >= $x_axis_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('X axis').': '._('Max'),
					_('X axis MAX value must be greater than X axis MIN value')
				);
			}
		}

		if ($this->getFieldValue('y_axis') == SVG_GRAPH_AXIS_ON) {
			$y_axis_min = $number_parser_w_suffix->parse($this->getFieldValue('y_axis_min')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			$y_axis_max = $number_parser_w_suffix->parse($this->getFieldValue('y_axis_max')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			if ($y_axis_min !== null && $y_axis_max !== null && $y_axis_min >= $y_axis_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Y axis').': '._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('x_axis', $values)) {
			$this->x_axis_on = $values['x_axis'] == SVG_GRAPH_AXIS_ON;
		}

		if (!$this->x_axis_on) {
			unset($values['x_axis_min'], $values['x_axis_max'], $values['x_axis_units']);
		}

		if (array_key_exists('x_axis_units', $values)) {
			$this->x_axis_units_static = $values['x_axis_units'] == SVG_GRAPH_AXIS_UNITS_STATIC;
		}

		if (!$this->x_axis_on || !$this->x_axis_units_static) {
			unset($values['x_axis_static_units']);
		}

		if (array_key_exists('y_axis', $values)) {
			$this->y_axis_on = $values['y_axis'] == SVG_GRAPH_AXIS_ON;
		}

		if (!$this->y_axis_on) {
			unset($values['y_axis_min'], $values['y_axis_max'], $values['y_axis_units']);
		}

		if (array_key_exists('y_axis_units', $values)) {
			$this->y_axis_units_static = $values['y_axis_units'] == SVG_GRAPH_AXIS_UNITS_STATIC;
		}

		if (!$this->y_axis_on || !$this->y_axis_units_static) {
			unset($values['y_axis_static_units']);
		}

		if (array_key_exists('legend', $values)) {
			$this->legend_on = $values['legend'] == self::LEGEND_ON;
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->initDataSetFields()
			->initDisplayingOptionsFields()
			->initTimePeriodFields()
			->initAxesFields()
			->initLegendFields()
			->initThresholdsFields()
			->addField(new CWidgetFieldMultiSelectOverrideHost());
	}

	private function initDataSetFields(): self {
		return $this->addField(
			(new CWidgetFieldDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	private function initDisplayingOptionsFields(): self {
		return $this->addField(
				(new CWidgetFieldRadioButtonList('source', _('History data selection'), [
					SVG_GRAPH_DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_DATA_SOURCE_HISTORY => _('History'),
					SVG_GRAPH_DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(SVG_GRAPH_DATA_SOURCE_AUTO)
			);
	}

	private function initTimePeriodFields(): self {
		return $this
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}

	private function initAxesFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('x_axis', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldNumericBox('x_axis_min', _('Min')))
					->prefixLabel(_('X axis'))
					->setFlags(!$this->x_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('x_axis_max', _('Max')))
					->prefixLabel(_('X axis'))
					->setFlags(!$this->x_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldSelect('x_axis_units', _('Units'), [
					SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
				]))
					->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
					->setFlags(!$this->x_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTextBox('x_axis_static_units'))
					->setFlags(!$this->x_axis_on || !$this->x_axis_units_static ? CWidgetField::FLAG_DISABLED : 0x00)
					->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldCheckBox('y_axis', _('Y-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldSelect('y_axis_scale', _('Scale'), [
					SVG_GRAPH_AXIS_SCALE_LINEAR => _('Linear'),
					SVG_GRAPH_AXIS_SCALE_LOGARITHMIC => _('Logarithmic')
				]))->setDefault(SVG_GRAPH_AXIS_SCALE_LINEAR)
			)
			->addField(
				(new CWidgetFieldNumericBox('y_axis_min', _('Min')))
					->prefixLabel(_('Y axis'))
					->setFlags(!$this->y_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('y_axis_max', _('Max')))
					->prefixLabel(_('Y axis'))
					->setFlags(!$this->y_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldSelect('y_axis_units', _('Units'), [
					SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
				]))
					->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
					->setFlags(!$this->y_axis_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTextBox('y_axis_static_units', null))
					->setFlags(!$this->y_axis_on || !$this->y_axis_units_static ? CWidgetField::FLAG_DISABLED : 0x00)
					->setMaxLength(255)
			);
	}

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(self::LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_aggregation', _('Show aggregation function')))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('legend_lines_mode', _('Rows'), [
					self::LEGEND_LINES_MODE_FIXED => _('Fixed'),
					self::LEGEND_LINES_MODE_VARIABLE => _('Variable')
				]))
					->setDefault(self::LEGEND_LINES_MODE_FIXED)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_lines',  _('Number of rows'),
					self::LEGEND_LINES_MIN, self::LEGEND_LINES_MAX
				))
					->setDefault(self::LEGEND_LINES_MIN)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_columns', _('Number of columns'),
					self::LEGEND_COLUMNS_MIN, self::LEGEND_COLUMNS_MAX
				))
					->setDefault(self::LEGEND_COLUMNS_MAX)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			);
	}

	private function initThresholdsFields(): self {
		return $this
			->addField(new CWidgetFieldCheckBox('interpolation', '', _('Color interpolation')))
			->addField(new CWidgetFieldAxisThresholds('thresholds'));
	}
}
