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


use Zabbix\Widgets\Fields\CWidgetFieldTags;

class CWidgetFieldTagsView extends CWidgetFieldView {

	public function __construct(CWidgetFieldTags $field) {
		$this->field = $field;
	}

	public function getView(): CTable {
		$tags = $this->field->getValue();

		if (!$tags) {
			$tags = [CWidgetFieldTags::DEFAULT_TAG];
		}

		$view = (new CTable())
			->setId('tags_table_'.$this->field->getName())
			->addClass('table-tags')
			->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH);

		$i = 0;

		foreach ($tags as $tag) {
			$view->addItem($this->getRowTemplate($tag, $i));

			$i++;
		}

		$view->addRow(
			(new CCol(
				(new CButton('tags_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$this->isDisabled())
			))->setColSpan(3)
		);

		return $view;
	}

	public function getJavaScript(): string {
		return '
			jQuery("#tags_table_'.$this->field->getName().'")
				.dynamicRows({template: "#'.$this->field->getName().'-row-tmpl", allow_empty: true})
				.on("afteradd.dynamicRows", function() {
					const rows = this.querySelectorAll(".form_row");
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll("#tags_table_'.$this->field->getName().' .form_row").forEach(row => {
				new CTagFilterItem(row);
			});
		';
	}

	public function getTemplates(): array {
		return [
			new CTemplateTag($this->field->getName().'-row-tmpl', $this->getRowTemplate(CWidgetFieldTags::DEFAULT_TAG))
		];
	}

	private function getRowTemplate(array $tag, $row_num = '#{rowNum}'): CRow {
		return (new CRow([
			(new CTextBox($this->field->getName().'['.$row_num.'][tag]', $tag['tag']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAriaRequired($this->isRequired())
				->setEnabled(!$this->isDisabled() || $row_num === '#{rowNum}')
				->setAttribute('placeholder', _('tag')),
			(new CSelect($this->field->getName().'['.$row_num.'][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue($tag['operator'])
				->setFocusableElementId($this->field->getName().'-'.$row_num.'-operator-select')
				->setId($this->field->getName().'_'.$row_num.'_operator')
				->setDisabled($this->isDisabled() && $row_num !== '#{rowNum}'),
			(new CTextBox($this->field->getName().'['.$row_num.'][value]', $tag['value']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAriaRequired($this->isRequired())
				->setId($this->field->getName().'_'.$row_num.'_value')
				->setEnabled(!$this->isDisabled() || $row_num === '#{rowNum}')
				->setAttribute('placeholder', _('value')),
			(new CCol(
				(new CButton($this->field->getName().'['.$row_num.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->setEnabled(!$this->isDisabled() || $row_num === '#{rowNum}')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass('form_row');
	}
}
