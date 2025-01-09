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


namespace Widgets\ItemHistory\Includes;

use CWidgetsData;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTimePeriod
};

/**
 * Plain text widget form.
 */
class WidgetForm extends CWidgetForm {

	public const LAYOUT_HORIZONTAL = 0;
	public const LAYOUT_VERTICAL = 1;

	public const NEW_VALUES_TOP = 0;
	public const NEW_VALUES_BOTTOM = 1;

	public const COLUMN_HEADER_OFF = 0;
	public const COLUMN_HEADER_HORIZONTAL = 1;
	public const COLUMN_HEADER_VERTICAL = 2;

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		// Apply sortable changes to data.
		if (array_key_exists('sort_order', $values)) {
			if (array_key_exists('column', $values) && array_key_exists('columns', $values['sort_order'])) {
				// Fix selected column index when columns were sorted.
				$values['column'] = array_search($values['column'], $values['sort_order']['columns'], true);
			}

			foreach ($values['sort_order'] as $key => $sortorder) {
				if (!array_key_exists($key, $values)) {
					continue;
				}

				$sorted = [];

				foreach ($sortorder as $index) {
					$sorted[] = $values[$key][$index];
				}

				$values[$key] = $sorted;
			}
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					self::LAYOUT_HORIZONTAL => _('Horizontal'),
					self::LAYOUT_VERTICAL => _('Vertical')
				]))->setDefault(self::LAYOUT_HORIZONTAL)
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Items')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES, ZBX_MAX_WIDGET_LINES))
					->setDefault(ZBX_DEFAULT_WIDGET_LINES)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			)
			->addField(
				(new CWidgetFieldRadioButtonList('sortorder', _('New values'), [
					self::NEW_VALUES_TOP => _('Top'),
					self::NEW_VALUES_BOTTOM => _('Bottom')
				]))->setDefault(self::NEW_VALUES_TOP)
			)
			->addField(
				new CWidgetFieldCheckBox('show_timestamp', _('Show timestamp'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_column_header', _('Show column header'), [
					self::COLUMN_HEADER_OFF => _('Off'),
					self::COLUMN_HEADER_HORIZONTAL => _('Horizontal'),
					self::COLUMN_HEADER_VERTICAL => _('Vertical')
				]))->setDefault(self::COLUMN_HEADER_VERTICAL)
			)
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
}
