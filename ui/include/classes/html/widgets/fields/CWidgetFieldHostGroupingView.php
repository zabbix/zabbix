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


use Zabbix\Widgets\Fields\CWidgetFieldHostGrouping;

use Widgets\HostNavigator\Includes\WidgetForm;

class CWidgetFieldHostGroupingView extends CWidgetFieldView {

	public function __construct(CWidgetFieldHostGrouping $field) {
		$this->field = $field;
	}

	public function getView(): CTable {
		return (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
			->addClass('list-numbered')
			->setFooter(new CRow(
				(new CCol(
					(new CButtonLink(_('Add')))
						->setId('add-row')
						->addClass('element-table-add')
				))->setColSpan(4)
			));
	}

	public function getJavaScript(): string {
		$field_name = $this->field->getName();
		$tag_value_attribute = json_encode(WidgetForm::GROUP_BY_TAG_VALUE);

		return '
			// Toggles tag name field visibility
			function updateRowFieldVisibility(row) {
				const attribute = row.querySelector("[name$=\"[attribute]\"]");
				const tag_name_input = row.querySelector("input[name$=\"[tag_name]\"]");

				tag_name_input.style.display = attribute.value == '.$tag_value_attribute.' ? "" : "none";
				tag_name_input.disabled = attribute.value != '.$tag_value_attribute.';
			}

			jQuery("#'.$field_name.'-table")
				.dynamicRows({
					template: "#'.$field_name.'-row-tmpl",
					allow_empty: true,
					rows: '.json_encode($this->field->getValue()).',
					sortable: true,
					sortable_options: {
						target: "tbody",
						selector_handle: ".'.ZBX_STYLE_DRAG_ICON.'",
						freeze_end: 1
					}
				})
				.on("afteradd.dynamicRows", e => {
					updateRowFieldVisibility([...e.target.querySelectorAll(".form_row")].at(-1));
				})
				.on("tableupdate.dynamicRows", (e) => {
					e.target.querySelectorAll(".form_row").forEach((row, index) => {
						for (const field of row.querySelectorAll("[name^=\"'.$field_name.'[\"]")) {
							field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
						}
					});
				});

			document.querySelector("#'.$field_name.'-table").addEventListener("change", e => {
				if (e.target.matches("[name$=\"[attribute]\"]")) {
					updateRowFieldVisibility(e.target.closest(".form_row"));
				}
			});

			// Update initial row field visibility
			document.querySelectorAll("#'.$field_name.'-table .form_row").forEach(updateRowFieldVisibility);
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl',
				(new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CSpan(':'))->addClass('list-numbered-item'),
					(new CSelect($this->field->getName().'[#{rowNum}][attribute]'))
						->addOptions(CSelect::createOptionsFromArray([
							WidgetForm::GROUP_BY_HOST_GROUP => _('Host group'),
							WidgetForm::GROUP_BY_TAG_VALUE => _('Tag value'),
							WidgetForm::GROUP_BY_SEVERITY => _('Severity')
						]))
						->setValue('#{attribute}')
						->setFocusableElementId($this->field->getName().'-#{rowNum}-attribute-select')
						->setId($this->field->getName().'_#{rowNum}_attribute')
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
