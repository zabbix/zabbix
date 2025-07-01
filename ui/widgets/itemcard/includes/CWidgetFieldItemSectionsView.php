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

use CButton,
	CButtonLink,
	CCol,
	CDiv,
	CRow,
	CSelect,
	CSpan,
	CTable,
	CTemplateTag,
	CWidgetFieldView;

class CWidgetFieldItemSectionsView extends CWidgetFieldView {

	public function __construct(CWidgetFieldItemSections $field) {
		$this->field = $field;
	}

	public function getView(): CDiv {
		return (new CDiv(
			(new CTable())
				->setId($this->field->getName().'-table')
				->setHeader(['', '', (new CCol(_('Name')))->addStyle('width: 100%;'), ''])
				->addClass(ZBX_STYLE_LIST_NUMBERED)
				->setFooter(new CRow(
					(new CCol(
						(new CButtonLink(_('Add')))
							->setId('add-row')
							->addClass('element-table-add')
					))->setColSpan(4)
				))
		))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');
	}

	public function getJavaScript(): string {
		return '
			CWidgetForm.addField(
				new ItemCard_CWidgetFieldItemSections('.json_encode([
					'name' => $this->field->getName(),
					'form_name' => $this->form_name,
					'value' => $this->field->getValue()
				]).')
			);
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl',
				(new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
					(new CSelect($this->field->getName().'[#{rowNum}]'))
						->addOptions(CSelect::createOptionsFromArray([
							CWidgetFieldItemSections::SECTION_DESCRIPTION => _('Description'),
							CWidgetFieldItemSections::SECTION_ERROR_TEXT => _('Error text'),
							CWidgetFieldItemSections::SECTION_INTERVAL_AND_STORAGE => _('Interval and storage'),
							CWidgetFieldItemSections::SECTION_LATEST_DATA => _('Latest data'),
							CWidgetFieldItemSections::SECTION_TYPE_OF_INFORMATION => _('Type of information'),
							CWidgetFieldItemSections::SECTION_TRIGGERS => _('Triggers'),
							CWidgetFieldItemSections::SECTION_HOST_INTERFACE => _('Host interface'),
							CWidgetFieldItemSections::SECTION_TYPE => _('Type'),
							CWidgetFieldItemSections::SECTION_HOST_INVENTORY => _('Host inventory'),
							CWidgetFieldItemSections::SECTION_TAGS => _('Tags')
						]))
						->setValue('#{section}')
						->setId($this->field->getName().'_#{rowNum}')
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
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
