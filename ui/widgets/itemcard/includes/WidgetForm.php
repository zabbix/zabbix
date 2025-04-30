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


namespace Widgets\ItemCard\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldSparkline,
	CWidgetFieldTimePeriod
};

/**
 * Item card widget form.
 */
class WidgetForm extends CWidgetForm {

	public const SPARKLINE_DEFAULT = [
		'width'		=> 1,
		'fill'		=> 3,
		'color'		=> '42A5F5',
		'time_period' => [
			'data_source' => CWidgetFieldTimePeriod::DATA_SOURCE_DEFAULT,
			'from' => 'now-1h',
			'to' => 'now'
		],
		'history'	=> CWidgetFieldSparkline::DATA_SOURCE_AUTO
	];

	public function validate(bool $strict = false): array {
		$sections = $this->getFieldValue('sections');
		$sparkline_enabled = in_array(CWidgetFieldItemSections::SECTION_LATEST_DATA, $sections);
		$errors = [];

		foreach ($this->fields as $name => $field) {
			if (!$sparkline_enabled && $name === 'sparkline') {
				$field->setValue(CWidgetFieldSparkline::DEFAULT_VALUE);
			}
			else {
				$errors = array_merge($errors, $field->validate($strict));
			}
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('sections', $values) && is_array($values['sections'])
				&& in_array(CWidgetFieldItemSections::SECTION_LATEST_DATA, $values['sections'])) {
			$values['sparkline'] = array_key_exists('sparkline', $values)
				? array_replace(self::SPARKLINE_DEFAULT, $values['sparkline'])
				: self::SPARKLINE_DEFAULT;
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				new CWidgetFieldItemSections('sections', _('Show'))
			)
			->addField(
				(new CWidgetFieldSparkline('sparkline', _('Sparkline')))->setDefault(self::SPARKLINE_DEFAULT)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
