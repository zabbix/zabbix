<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CTagFilterFieldHelper {

	/**
	 * @param array  $data
	 * @param int    $data['evaltype']                (optional)
	 * @param array  $data['tags']                    (optional)
	 * @param string $data['tags'][]['tag']
	 * @param int    $data['tags'][]['operator']
	 * @param string $data['tags'][]['value']
	 * @param array  $options
	 * @param string $options['tag_field_name']       (optional)
	 * @param string $options['evaltype_field_name']  (optional)
	 *
	 * @return CTable
	 */
	public static function getTagFilterField(array $data = [], array $options = []): CTable {
		$options += [
			'tag_field_name' => 'filter_tags',
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
					->setModern(true)
					->setId($options['evaltype_field_name'])
			))->setColSpan(4)
		);

		foreach (array_values($data['tags']) as $i => $tag) {
			$tags_table->addRow([
				(new CTextBox($options['tag_field_name'].'['.$i.'][tag]', $tag['tag']))
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
				(new CSelect($options['tag_field_name'].'['.$i.'][operator]'))
					->addOptions(CSelect::createOptionsFromArray([
						TAG_OPERATOR_EXISTS => _('Exists'),
						TAG_OPERATOR_EQUAL => _('Equals'),
						TAG_OPERATOR_LIKE => _('Contains'),
						TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
						TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
						TAG_OPERATOR_NOT_LIKE => _('Does not contain')
					]))
					->setValue((int) $tag['operator'])
					->setFocusableElementId($options['tag_field_name'].'-'.$i.'-operator-select')
					->setId($options['tag_field_name'].'_'.$i.'_operator'),
				(new CTextBox($options['tag_field_name'].'['.$i.'][value]', $tag['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setId($options['tag_field_name'].'_'.$i.'_value'),
				(new CCol(
					(new CButton($options['tag_field_name'].'['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
						->removeId()
				))->addClass(ZBX_STYLE_NOWRAP)
			], 'form_row');
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
	 * Make empty tag filter field row template.
	 *
	 * @param array  $options
	 * @param string $options['tag_field_name']  (optional)
	 *
	 * @return string
	 */
	public static function getTemplate(array $options = []): string {
		$options += [
			'tag_field_name' => 'filter_tags'
		];

		return (new CRow([
			(new CTextBox($options['tag_field_name'].'[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->removeId(),
			(new CSelect($options['tag_field_name'].'[#{rowNum}][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(TAG_OPERATOR_LIKE),
			(new CTextBox($options['tag_field_name'].'[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->removeId(),
			(new CCol(
				(new CButton($options['tag_field_name'].'[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString();
	}
}
