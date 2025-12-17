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
		$header_row = [
			'',
			_('Threshold'),
			(new CColHeader(''))->setWidth('100%')
		];

		$thresholds_table = (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(ZBX_STYLE_TABLE_FORMS)
			->setHeader($header_row)
			->setFooter(new CRow(
				(new CCol(
					(new CButtonLink(_('Add')))->addClass('element-table-add')
				))->setColSpan(count($header_row))
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
			CWidgetForm.addField(
				new CWidgetFieldThresholds('.json_encode([
					'name' => $this->field->getName(),
					'form_name' => $this->form_name
				]).')
			);
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl', $this->getRowTemplate())
		];
	}

	public function getRowTemplate($row_num = '#{rowNum}', $color = '#{color}', $threshold = '#{threshold}'): CRow {
		return (new CRow([
			(new CColorPicker($this->field->getName().'['.$row_num.'][color]'))->setColor($color),
			(new CTextBox($this->field->getName().'['.$row_num.'][threshold]', $threshold, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			(new CButton($this->field->getName().'['.$row_num.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row');
	}
}
