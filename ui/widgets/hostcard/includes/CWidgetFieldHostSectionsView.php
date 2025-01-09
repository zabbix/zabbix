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


namespace Widgets\HostCard\Includes;

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

class CWidgetFieldHostSectionsView extends CWidgetFieldView {

	public function __construct(CWidgetFieldHostSections $field) {
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
			document.forms["'.$this->form_name.'"].fields["'.$this->field->getName().'"] =
				new CWidgetFieldHostSections('.json_encode([
					'field_name' => $this->field->getName(),
					'field_value' => $this->field->getValue()
				]).');
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
							CWidgetFieldHostSections::SECTION_HOST_GROUPS => _('Host groups'),
							CWidgetFieldHostSections::SECTION_DESCRIPTION => _('Description'),
							CWidgetFieldHostSections::SECTION_MONITORING => _('Monitoring'),
							CWidgetFieldHostSections::SECTION_AVAILABILITY => _('Availability'),
							CWidgetFieldHostSections::SECTION_MONITORED_BY => _('Monitored by'),
							CWidgetFieldHostSections::SECTION_TEMPLATES => _('Templates'),
							CWidgetFieldHostSections::SECTION_INVENTORY => _('Inventory'),
							CWidgetFieldHostSections::SECTION_TAGS => _('Tags')
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
