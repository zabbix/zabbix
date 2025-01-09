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


namespace Widgets\PieChart\Includes;

use CWidgetsData;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldColor,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldTextBox,
	CWidgetFieldTimePeriod
};

/**
 * Pie chart widget form view.
 */
class WidgetForm extends CWidgetForm {

	public const DATA_SOURCE_AUTO = 0;
	public const DATA_SOURCE_HISTORY = 1;
	public const DATA_SOURCE_TRENDS = 2;

	public const DRAW_TYPE_DOUGHNUT = 1;
	public const DRAW_TYPE_PIE = 0;

	public const LEGEND_ON = 1;

	public const LEGEND_COLUMNS_MAX = 4;
	public const LEGEND_COLUMNS_MIN = 1;

	public const LEGEND_LINES_MAX = 10;
	public const LEGEND_LINES_MIN = 1;

	public const LEGEND_LINES_MODE_FIXED = 0;
	public const LEGEND_LINES_MODE_VARIABLE = 1;

	public const MERGE_COLOR_DEFAULT = '768D99';
	public const MERGE_PERCENT_MAX = 10;
	public const MERGE_PERCENT_MIN = 1;

	public const SPACE_DEFAULT = 1;
	public const SPACE_MAX = 10;
	public const SPACE_MIN = 0;

	private const STROKE_DEFAULT = 0;
	private const STROKE_MAX = 10;
	private const STROKE_MIN = 0;

	public const VALUE_DECIMALS_DEFAULT = 2;
	public const VALUE_DECIMALS_MAX = 6;
	public const VALUE_DECIMALS_MIN = 0;

	public const VALUE_SIZE_CUSTOM = 1;
	public const VALUE_SIZE_AUTO = 0;
	public const VALUE_SIZE_DEFAULT = 20;
	public const VALUE_SIZE_MAX = 100;
	public const VALUE_SIZE_MIN = 1;

	public const WIDTH_DEFAULT = 50;
	public const WIDTH_MIN = 20;
	public const WIDTH_STEP = 10;

	private bool $legend_on = true;

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

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
			->initLegendFields()
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}

	private function initDataSetFields(): self {
		return $this->addField(
			(new CWidgetFieldDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	private function initDisplayingOptionsFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source', _('History data selection'), [
					self::DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
					self::DATA_SOURCE_HISTORY => _('History'),
					self::DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(self::DATA_SOURCE_AUTO)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('draw_type', _('Draw'), [
					self::DRAW_TYPE_PIE => _('Pie'),
					self::DRAW_TYPE_DOUGHNUT => _('Doughnut')
				]))->setDefault(self::DRAW_TYPE_PIE)
			)
			->addField(
				(new CWidgetFieldRangeControl('width', _('Width'),
					self::WIDTH_MIN, self::WIDTH_DEFAULT, self::WIDTH_STEP
				))
					->setDefault(self::WIDTH_DEFAULT)
			)
			->addField(
				(new CWidgetFieldRangeControl('stroke', _('Stroke width'),
					self::STROKE_MIN, self::STROKE_MAX
				))
					->setDefault(self::STROKE_DEFAULT)
			)
			->addField(
				(new CWidgetFieldRangeControl('space', _('Space between sectors'),
					self::SPACE_MIN, self::SPACE_MAX
				))
					->setDefault(self::SPACE_DEFAULT)
			)
			->addField(
				new CWidgetFieldCheckBox('merge', null, _('Merge sectors smaller than '))
			)
			->addField(
				(new CWidgetFieldIntegerBox('merge_percent', null,
					self::MERGE_PERCENT_MIN, self::MERGE_PERCENT_MAX
				))
					->setDefault(self::MERGE_PERCENT_MIN)
			)
			->addField(
				(new CWidgetFieldColor('merge_color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('total_show', _('Show total value')))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('value_size_type', _('Size'), [
					self::VALUE_SIZE_AUTO => _('Auto'),
					self::VALUE_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::VALUE_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_size', null,
					self::VALUE_SIZE_MIN, self::VALUE_SIZE_MAX
				))
					->setDefault(self::VALUE_SIZE_DEFAULT)
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'),
					self::VALUE_DECIMALS_MIN, self::VALUE_DECIMALS_MAX
				))
					->setDefault(self::VALUE_DECIMALS_DEFAULT)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', null, _('Units')))
			)
			->addField(
				(new CWidgetFieldTextBox('units'))->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldCheckBox('value_bold', _('Bold')))
			)
			->addField(
				new CWidgetFieldColor('value_color', _('Color'))
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

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(self::LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_value', _('Show value')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_aggregation', _('Show aggregation function')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
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
				(new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
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
}
