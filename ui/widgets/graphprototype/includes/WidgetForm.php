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


namespace Widgets\GraphPrototype\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGraphPrototype,
	CWidgetFieldMultiSelectItemPrototype,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTimePeriod
};

use CWidgetsData;

/**
 * Graph prototype widget form.
 */
class WidgetForm extends CWidgetForm {

	private const DEFAULT_COLUMNS_COUNT = 2;
	private const DEFAULT_ROWS_COUNT = 1;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE
				&& !$this->getFieldValue('itemid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Item prototype'), _('cannot be empty'));
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE
				&& !$this->getFieldValue('graphid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Graph prototype'), _('cannot be empty'));
		}

		return $errors;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source_type', _('Source'), [
					ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE => _('Graph prototype'),
					ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE => _('Simple graph prototype')
				]))
					->setDefault(ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE)
			)
			->addField(
				(new CWidgetFieldMultiSelectItemPrototype('itemid', _('Item prototype')))
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldMultiSelectGraphPrototype('graphid', _('Graph prototype')))
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
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
			)
			->addField(
				(new CWidgetFieldCheckBox('show_legend', _('Show legend')))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldIntegerBox('columns', _('Columns'), 1, DASHBOARD_MAX_COLUMNS))
					->setDefault(self::DEFAULT_COLUMNS_COUNT)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldIntegerBox('rows', _('Rows'), 1, DASHBOARD_MAX_ROWS))
					->setDefault(self::DEFAULT_ROWS_COUNT)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
