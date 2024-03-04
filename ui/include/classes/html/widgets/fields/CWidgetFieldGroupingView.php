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


use Zabbix\Widgets\Fields\CWidgetFieldGrouping;

class CWidgetFieldGroupingView extends CWidgetFieldView {

	public function __construct(CWidgetFieldGrouping $field) {
		$this->field = $field;
	}

	public function getView(): CTable {
		$list_items = $this->field->getValue();

		$view = (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->setFooter(new CRow(
				new CCol(
					(new CButtonLink(_('Add')))
						->setId('add-row')
						->addClass('element-table-add')
				)
			));

		foreach ($list_items as $i => $list_item) {
			$view->addItem(
				$this->getRowTemplate($i, $list_item['attribute'], $list_item['tag_name'] ?: '')
			);
		}

		return $view;
	}

	public function getJavaScript(): string {
		$field_name = $this->field->getName();
		$tag_fields_json = json_encode($this->field->tag_fields);

		return "
			function updateVisibility(row) {
				const attribute = row.querySelector(`z-select`);
				const tag_name_input = row.querySelector('input[id^=\"".$field_name."_\"][id$=\"_tag_name\"]');
				const tag_fields = $tag_fields_json;

				tag_name_input.style.display = tag_fields.includes(Number(attribute.value)) ? '' : 'none';
			}

			jQuery('#".$field_name."-table').dynamicRows({template:'#".$field_name."-row-tmpl', allow_empty: true});

			document.querySelector('#".$field_name."-table').addEventListener('click', e => {
				if (e.target.classList.contains('element-table-add')) {
					const new_row = e.target.closest('tr').previousElementSibling;

					updateVisibility(new_row);

					new_row.querySelector(`z-select`).addEventListener('change', e => {
						updateVisibility(e.target.closest('tr'));
					})
				}
			});

			document.querySelectorAll('#".$field_name."-table .form_row').forEach(updateVisibility);
		";
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl', $this->getRowTemplate())
		];
	}

	private function getRowTemplate($row_num = '#{rowNum}', $attribute = '#{attribute}', $tag = '#{tag_name}'): CRow {
		return (new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan(((int) $row_num + 1).':'))->addClass('rowNum'),
			(new CDiv(
				(new CSelect($this->field->getName().'['.$row_num.'][attribute]'))
					->addOptions(CSelect::createOptionsFromArray($this->field->attributes))
					->setValue($attribute)
					->setFocusableElementId($this->field->getName().'-'.$row_num.'-attribute-select')
					->setId($this->field->getName().'_'.$row_num.'_attribute')
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			)),
			(new CDiv(
				(new CTextBox($this->field->getName().'['.$row_num.'][tag_name]', $tag, false))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAriaRequired($this->isRequired())
					->setId($this->field->getName().'_'.$row_num.'_tag_name')
					->setAttribute('placeholder', _('tag'))
			)),
			(new CDiv(
				(new CButton($this->field->getName().'['.$row_num.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))
		]))
			->addClass('form_row')
			->addClass(ZBX_STYLE_SORTABLE);
	}
}
