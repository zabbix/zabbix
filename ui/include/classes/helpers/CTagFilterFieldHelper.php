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


class CTagFilterFieldHelper {

	public const FILTER_TYPE_TAG = 0;
	public const FILTER_TYPE_APM_ATTRIBUTE = 1;

	/**
	 * @param array  $data
	 * @param int    $data['evaltype']                (optional)
	 * @param array  $data['tags']                    (optional)
	 * @param string $data['tags'][]['tag']           if filter_type set to FILTER_TYPE_TAG
	 * @param string $data['tags'][]['key']           if filter_type set to FILTER_TYPE_APM_ATTRIBUTE
	 * @param int    $data['tags'][]['operator']
	 * @param string $data['tags'][]['value']
	 * @param array  $options
	 * @param string $options['tag_field_name']       (optional)
	 * @param string $options['evaltype_field_name']  (optional)
	 * @param string $options['filter_type']          (optional)
	 *
	 * @return CTable
	 */
	public static function getTagFilterField(array $data = [], array $options = []): CTable {
		$options += [
			'evaltype_field_name' => 'filter_evaltype'
		];

		$data += [
			'evaltype' => TAG_EVAL_TYPE_AND_OR,
			'tags' => []
		];

		$tags_table = (new CTable())->setId('filter-tags');

		$tags_table->addRow(
			(new CCol(
				(new CRadioButtonList($options['evaltype_field_name'], (int) $data['evaltype']))
					->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
					->addValue(_('Or'), TAG_EVAL_TYPE_OR)
					->setModern()
					->setId($options['evaltype_field_name'])
			))->setColSpan(4)
		);

		foreach (array_values($data['tags']) as $i => $tag) {
			$tags_table->addRow(self::getTemplate($options, $i, $tag));
		}

		$tags_table->addRow(
			(new CCol(
				(new CButton('tags_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->removeId()
			))->setColSpan(3)
		);

		return $tags_table;
	}

	/**
	 * Make tag filter field row template.
	 *
	 * @param array      $options
	 * @param string     $options['tag_field_name']  (optional)
	 * @param int        $options['filter_type']     (optional)
	 * @param string|int $index
	 * @param string     $data['tags'][]['tag']      if filter_type set to FILTER_TYPE_TAG
	 * @param string     $data['tags'][]['key']      if filter_type set to FILTER_TYPE_APM_ATTRIBUTE
	 * @param int        $data['tags'][]['operator']
	 * @param string     $data['tags'][]['value']
 *
	 * @return CRow
	 */
	public static function getTemplate(array $options = [], string|int $index = '#{rowNum}',
			array $tag = []): CRow {
		$options += [
			'tag_field_name' => 'filter_tags',
			'filter_type' => self::FILTER_TYPE_TAG
		];

		$name_placeholder = _('tag');
		$name_key = 'tag';

		if ($options['filter_type'] == self::FILTER_TYPE_APM_ATTRIBUTE) {
			$name_placeholder = _('key');
			$name_key = 'key';
		}

		return (new CRow([
			(new CTextBox($options['tag_field_name'].'['.$index.']['.$name_key.']',
				array_key_exists($name_key, $tag) ? $tag[$name_key] : ''
			))
				->setAttribute('placeholder', $name_placeholder)
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CSelect($options['tag_field_name'].'['.$index.'][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE),
			(new CTextBox($options['tag_field_name'].'['.$index.'][value]',
				array_key_exists('value', $tag) ? $tag['value'] : ''
			))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setId($options['tag_field_name'].'_'.$index.'_operator'),
			(new CCol(
				(new CButton($options['tag_field_name'].'['.$index.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass(ZBX_STYLE_FORM_ROW);
	}
}
