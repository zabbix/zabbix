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

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source_type', _('Source'), [
					ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE => _('Graph prototype'),
					ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE => _('Simple graph prototype')
				]))
					->setDefault(ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE)
					->setAction('ZABBIX.Dashboard.reloadWidgetProperties()')
			)
			->addField(array_key_exists('source_type', $this->values)
					&& $this->values['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE
				? (new CWidgetFieldMultiSelectItemPrototype('itemid', _('Item prototype')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
				: (new CWidgetFieldMultiSelectGraphPrototype('graphid', _('Graph prototype')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
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
				(new CWidgetFieldIntegerBox('rows', _('Rows'), 1,
					floor(DASHBOARD_WIDGET_MAX_ROWS / DASHBOARD_WIDGET_MIN_ROWS)
				))
					->setDefault(self::DEFAULT_ROWS_COUNT)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
