<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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


namespace Widgets\Graph\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldMultiSelectGraph,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldRadioButtonList
};

/**
 * Graph (classic) widget form.
 */
class WidgetForm extends CWidgetForm {

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
				&& !$this->getFieldValue('itemid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Item'), _('cannot be empty'));
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_GRAPH
				&& !$this->getFieldValue('graphid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Graph'), _('cannot be empty'));
		}

		return $errors;
	}

	public function addFields(): self {
		$this->addField(
			(new CWidgetFieldRadioButtonList('source_type', _('Source'), [
				ZBX_WIDGET_FIELD_RESOURCE_GRAPH => _('Graph'),
				ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH => _('Simple graph')
			]))
				->setDefault(ZBX_WIDGET_FIELD_RESOURCE_GRAPH)
		);

		$field_item = (new CWidgetFieldMultiSelectItem('itemid', _('Item'), $this->templateid))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setMultiple(false)
			->setFilterParameter('numeric', true);

		if ($this->templateid === null) {
			$field_item->setFilterParameter('with_simple_graph_items', true);
		}

		$this
			->addField($field_item)
			->addField(
				(new CWidgetFieldMultiSelectGraph('graphid', _('Graph'), $this->templateid))
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_legend', _('Show legend')))->setDefault(1)
			)
			->addField($this->templateid === null
				? new CWidgetFieldCheckBox('dynamic', _('Enable host selection'))
				: null
			);

		return $this;
	}
}
