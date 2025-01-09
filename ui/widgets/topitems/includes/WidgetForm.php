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


namespace Widgets\TopItems\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldPatternSelectHost,
	CWidgetFieldPatternSelectItem,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTags
};

/**
 * Top items data widget form.
 */
class WidgetForm extends CWidgetForm {

	public const LAYOUT_HORIZONTAL = 0;
	public const LAYOUT_VERTICAL = 1;

	public const ORDER_TOP_N = 2;
	public const ORDER_BOTTOM_N = 3;

	public const PROBLEMS_ALL = 0;
	public const PROBLEMS_UNSUPPRESSED = 1;
	public const PROBLEMS_NONE = 2;

	public const COLUMN_HEADER_OFF = 0;
	public const COLUMN_HEADER_HORIZONTAL = 1;
	public const COLUMN_HEADER_VERTICAL = 2;

	public const ORDERBY_HOST_NAME = 0;
	public const ORDERBY_HOST = 1;
	public const ORDERBY_ITEM_NAME = 2;
	public const ORDERBY_ITEM_VALUE = 3;

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('host_tags_evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('host_tags')
			)
			->addField(
				(new CWidgetFieldRadioButtonList('problems', _('Show problems'), [
					self::PROBLEMS_ALL => _('All'),
					self::PROBLEMS_UNSUPPRESSED => _('Unsuppressed'),
					self::PROBLEMS_NONE => _('None')
				]))->setDefault(self::PROBLEMS_UNSUPPRESSED)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					self::LAYOUT_HORIZONTAL => _('Horizontal'),
					self::LAYOUT_VERTICAL => _('Vertical')
				]))->setDefault(self::LAYOUT_HORIZONTAL)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_column_header', _('Show column header'), [
					self::COLUMN_HEADER_OFF => _('Off'),
					self::COLUMN_HEADER_HORIZONTAL => _('Horizontal'),
					self::COLUMN_HEADER_VERTICAL => _('Vertical')
				]))->setDefault(self::COLUMN_HEADER_VERTICAL)
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Items')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)

			// Advanced configuration fields - host ordering.
			->addField(
				(new CWidgetFieldRadioButtonList('host_ordering_order_by', _('Order by'), [
					self::ORDERBY_HOST_NAME => _('Host name'),
					self::ORDERBY_ITEM_VALUE => _('Item value')
				]))
					->setDefault(self::ORDERBY_HOST_NAME)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldPatternSelectItem('host_ordering_item', _('Item')))
					->prefixLabel(_('Host ordering'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('host_ordering_order', _('Order'), [
					self::ORDER_TOP_N => _('Top N'),
					self::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(self::ORDER_TOP_N)
			)
			->addField(
				(new CWidgetFieldIntegerBox('host_ordering_limit', _('Limit'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->prefixLabel(_('Host ordering'))
					->setDefault(10)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)

			// Advanced configuration fields - item ordering.
			->addField(
				(new CWidgetFieldRadioButtonList('item_ordering_order_by', _('Order by'), [
					self::ORDERBY_ITEM_VALUE => _('Item value'),
					self::ORDERBY_ITEM_NAME => _('Item name'),
					self::ORDERBY_HOST => _('Host')
				]))
					->setDefault(self::ORDERBY_ITEM_VALUE)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldPatternSelectHost('item_ordering_host', _('Host')))
					->prefixLabel(_('Item ordering'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('item_ordering_order', _('Order'), [
					self::ORDER_TOP_N => _('Top N'),
					self::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(self::ORDER_TOP_N)
			)
			->addField(
				(new CWidgetFieldIntegerBox('item_ordering_limit', _('Limit'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->prefixLabel(_('Item ordering'))
					->setDefault(10)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}

	public function validate(bool $strict = false): array {
		if ($this->getField('host_ordering_order_by')->getValue() == self::ORDERBY_ITEM_VALUE) {
			$this->getField('host_ordering_item')->setFlags(CWidgetField::FLAG_NOT_EMPTY);
		}

		if ($this->getField('item_ordering_order_by')->getValue() == self::ORDERBY_HOST) {
			$this->getField('item_ordering_host')->setFlags(CWidgetField::FLAG_NOT_EMPTY);
		}

		return parent::validate($strict);
	}
}
