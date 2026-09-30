<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CApmAttrFilterFieldHelper {

	/**
	 * @param array  $data
	 *        int    $data['evaltype']                 (optional)
	 *        array  $data['attributes']               (optional)
	 *        string $data['attributes'][]['key']
	 *        int    $data['attributes'][]['operator']
	 *        string $data['attributes'][]['value']
	 * @param array  $options
	 *        string $options['attribute_field_name']  (optional)
	 *        string $options['evaltype_field_name']   (optional)
	 *
	 * @return CTable
	 */
	public static function getFilterField(array $data = [], array $options = []): CTable {
		$options += [
			'attribute_field_name' => 'filter_attributes',
			'evaltype_field_name' => 'filter_evaltype'
		];

		$data += [
			'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
			'attributes' => []
		];

		$attributes_table = (new CTable())
			->setId('filter-attributes')
			->addRow(
				(new CCol(
					(new CRadioButtonList($options['evaltype_field_name'], (int) $data['evaltype']))
						->addValue(_('And/Or'), CONDITION_EVAL_TYPE_AND_OR)
						->addValue(_('Or'), CONDITION_EVAL_TYPE_OR)
						->setModern()
						->setId($options['evaltype_field_name'])
				))->setColSpan(4)
			);

		foreach (array_values($data['attributes']) as $i => $attribute) {
			$attributes_table->addItem(self::getFilterFieldRow($i, $attribute, $options));
		}

		$attributes_table->addRow(
			(new CCol(
				(new CButton('attributes_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->removeId()
			))->setColSpan(3)
		);

		return $attributes_table;
	}

	/**
	 * Make attribute filter field row.
	 *
	 * @param string|int $index
	 * @param array      $attribute
	 *        string     $attribute['key']
	 *        int        $attribute['operator']
	 *        string     $attribute['value']
	 * @param array      $options
	 *        string     $options['attribute_field_name']  (optional)
	 *
	 * @return CRow
	 */
	public static function getFilterFieldRow(string|int $index, array $attribute, array $options = []): CRow {
		$options += [
			'attribute_field_name' => 'filter_attributes'
		];

		return (new CRow([
			(new CTextBox($options['attribute_field_name'].'['.$index.'][key]', $attribute['key']))
				->setAttribute('placeholder', _('key'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CSelect($options['attribute_field_name'].'['.$index.'][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_OPERATOR_EXISTS => _('Exists'),
					CONDITION_OPERATOR_EQUAL => _('Equals'),
					CONDITION_OPERATOR_LIKE => _('Contains'),
					CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist'),
					CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
					CONDITION_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue((int) $attribute['operator'])
				->setFocusableElementId(''.$options['attribute_field_name'].'-'.$index.'-operator-select')
				->setId($options['attribute_field_name'].'_'.$index.'_operator'),
			(new CTextBox(''.$options['attribute_field_name'].'['.$index.'][value]', $attribute['value']))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setId($options['attribute_field_name'].'_'.$index.'_value'),
			(new CCol(
				(new CButton($options['attribute_field_name'].'['.$index.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass(ZBX_STYLE_FORM_ROW);
	}
}
