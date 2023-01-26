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
	CWidgetFieldRadioButtonList
};

/**
 * Graph prototype widget form.
 */
class WidgetForm extends CWidgetForm {

	private const DEFAULT_COLUMNS_COUNT = 2;
	private const DEFAULT_ROWS_COUNT = 1;

	public function addFields(): self {
		$this->addField(
			(new CWidgetFieldRadioButtonList('source_type', _('Source'), [
				ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE => _('Graph prototype'),
				ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE => _('Simple graph prototype')
			]))
				->setDefault(ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE)
				->setAction('ZABBIX.Dashboard.reloadWidgetProperties()')
		);

		if (array_key_exists('source_type', $this->values)
				&& $this->values['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE) {

			$field_item_prototype = (new CWidgetFieldMultiSelectItemPrototype('itemid', _('Item prototype'),
				$this->templateid
			))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
				->setMultiple(false)
				->setFilterParameter('numeric', true);

			if ($this->templateid === null) {
				$field_item_prototype->setFilterParameter('with_simple_graph_item_prototypes', true);
			}

			$this->addField($field_item_prototype);
		}
		else {
			$this->addField(
				(new CWidgetFieldMultiSelectGraphPrototype('graphid', _('Graph prototype'), $this->templateid))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			);
		}

		$this
			->addField(
				(new CWidgetFieldCheckBox('show_legend', _('Show legend')))->setDefault(1)
			)
			->addField($this->templateid === null
				? new CWidgetFieldCheckBox('dynamic', _('Enable host selection'))
				: null
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
			);

		return $this;
	}
}
