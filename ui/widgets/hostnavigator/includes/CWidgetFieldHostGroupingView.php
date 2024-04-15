<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\HostNavigator\Includes;

use CButton,
	CButtonLink,
	CCol,
	CDiv,
	CRow,
	CSelect,
	CSpan,
	CTable,
	CTemplateTag,
	CTextBox,
	CWidgetFieldView;

class CWidgetFieldHostGroupingView extends CWidgetFieldView {

	public const ZBX_STYLE_HOST_GROUPING = 'host-grouping';

	public function __construct(CWidgetFieldHostGrouping $field) {
		$this->field = $field;
	}

	public function getView(): CTable {
		return (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(self::ZBX_STYLE_HOST_GROUPING)
			->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
			->addClass(ZBX_STYLE_LIST_NUMBERED)
			->setFooter(new CRow(
				(new CCol(
					(new CButtonLink(_('Add')))
						->setId('add-row')
						->addClass('element-table-add')
				))->setColSpan(4)
			));
	}

	public function getJavaScript(): string {
		return '
			document.forms["'.$this->form_name.'"].fields["'.$this->field->getName().'"] =
				new CWidgetFieldHostGrouping('.json_encode([
					'field_name' => $this->field->getName(),
					'field_value' => $this->field->getValue(),
					'max_rows' => CWidgetFieldHostGrouping::MAX_ROWS
				]).');
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl',
				(new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
					(new CSelect($this->field->getName().'[#{rowNum}][attribute]'))
						->addOptions(CSelect::createOptionsFromArray([
							CWidgetFieldHostGrouping::GROUP_BY_HOST_GROUP => _('Host group'),
							CWidgetFieldHostGrouping::GROUP_BY_TAG_VALUE => _('Tag value'),
							CWidgetFieldHostGrouping::GROUP_BY_SEVERITY => _('Severity')
						]))
						->setValue('#{attribute}')
						->setId($this->field->getName().'_#{rowNum}_attribute')
						->setFocusableElementId($this->field->getName().'-#{rowNum}-attribute-select')
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
					(new CCol(
						(new CTextBox($this->field->getName().'[#{rowNum}][tag_name]', '#{tag_name}', false))
							->setId($this->field->getName().'_#{rowNum}_tag_name')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->setAttribute('placeholder', _('tag'))
					))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
					(new CDiv(
						(new CButton($this->field->getName().'[#{rowNum}][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					))
				]))->addClass('form_row')
			)
		];
	}
}
