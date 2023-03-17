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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


namespace Widgets\TopHosts\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldColumnsList,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTags
};

use Widgets\TopHosts\Widget;

/**
 * Top hosts data widget form.
 */
class WidgetForm extends CWidgetForm {

	private const DEFAULT_HOSTS_COUNT = 10;
	private const DEFAULT_ORDER_COLUMN = 0;

	private array $field_column_values = [];

	protected function normalizeValues(array $values): array {
		$values = self::convertDottedKeys($values);

		if (array_key_exists('columnsthresholds', $values)) {
			foreach ($values['columnsthresholds'] as $column_index => $fields) {
				$values['columns'][$column_index]['thresholds'] = [];

				foreach ($fields as $field_key => $field_values) {
					foreach ($field_values as $value_index => $value) {
						$values['columns'][$column_index]['thresholds'][$value_index][$field_key] = $value;
					}
				}
			}
		}

		// Apply sortable changes to data.
		if (array_key_exists('sortorder', $values)) {
			if (array_key_exists('column', $values) && array_key_exists('columns', $values['sortorder'])) {
				// Fix selected column index when columns were sorted.
				$values['column'] = array_search($values['column'], $values['sortorder']['columns'], true);
			}

			foreach ($values['sortorder'] as $key => $sortorder) {
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

		if (array_key_exists('columns', $values)) {
			foreach ($values['columns'] as $key => $value) {
				if ($value['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
					$this->field_column_values[$key] = ($value['name'] === '') ? $value['item'] : $value['name'];
				}
			}
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('tags', '')
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Columns')))->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('order', _('Order'), [
					Widget::ORDER_TOP_N => _('Top N'),
					Widget::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(Widget::ORDER_TOP_N)
			)
			->addField(
				(new CWidgetFieldSelect('column', _('Order column'), $this->field_column_values))
					->setDefault($this->field_column_values
						? self::DEFAULT_ORDER_COLUMN
						: CWidgetFieldSelect::DEFAULT_VALUE
					)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldIntegerBox('count', _('Host count'), ZBX_MIN_WIDGET_LINES, ZBX_MAX_WIDGET_LINES))
					->setDefault(self::DEFAULT_HOSTS_COUNT)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}
}
