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


use Zabbix\Widgets\Fields\CWidgetFieldThresholds;

class CWidgetFieldThresholdsView extends CWidgetFieldView {

	public function __construct(CWidgetFieldThresholds $field) {
		$this->field = $field;
	}

	public function getView(): CDiv {
		$thresholds_table = (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->setHeader([
				'',
				_('Threshold'),
				(new CColHeader(''))->setWidth('100%')
			])
			->setFooter(new CRow(
				new CCol(
					(new CButtonLink(_('Add')))->addClass('element-table-add')
				)
			));

		foreach ($this->field->getValue() as $i => $threshold) {
			$thresholds_table->addRow(
				$this->getRowTemplate($i, $threshold['color'], $threshold['threshold'])
			);
		}

		return (new CDiv($thresholds_table))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	}

	public function getJavaScript(): string {
		return '
			var $thresholds_table = jQuery("#'.$this->field->getName().'-table");

			$thresholds_table
				.dynamicRows({template: "#'.$this->field->getName().'-row-tmpl", allow_empty: true})
				.on("afteradd.dynamicRows", function(opt) {
					const rows = this.querySelectorAll(".form_row");
					const colors = jQuery("#widget-dialogue-form")[0]
						.querySelectorAll(".'.ZBX_STYLE_COLOR_PICKER.' input");
					const used_colors = [];
					for (const color of colors) {
						if (color.value !== "" && color.name.includes("thresholds")) {
							used_colors.push(color.value);
						}
					}
					jQuery(".color-picker input", rows[rows.length - 1])
						.val(colorPalette.getNextColor(used_colors))
						.colorpicker({
							appendTo: ".overlay-dialogue-body"
						});
				});
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl', $this->getRowTemplate())
		];
	}

	public function getRowTemplate($row_num = '#{rowNum}', $color = '#{color}', $threshold = '#{threshold}'): CRow {
		return (new CRow([
			(new CColor($this->field->getName().'['.$row_num.'][color]', $color))->appendColorPickerJs(false),
			(new CTextBox($this->field->getName().'['.$row_num.'][threshold]', $threshold, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			(new CButton($this->field->getName().'['.$row_num.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row');
	}
}
