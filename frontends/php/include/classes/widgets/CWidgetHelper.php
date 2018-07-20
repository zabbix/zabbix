<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CWidgetHelper {

	/**
	 * Create CForm for widget configuration form.
	 *
	 * @return CForm
	 */
	public static function createForm() {
		return (new CForm('post'))
			->cleanItems()
			->setId('widget_dialogue_form')
			->setName('widget_dialogue_form');
	}

	/**
	 * Create CFormList for widget configuration form with default fields in it.
	 *
	 * @param string $dialogue_name
	 * @param string $type
	 * @param array $known_widget_types
	 * @param CWidgetFieldComboBox $field_rf_rate
	 *
	 * @return CFormList
	 */
	public static function createFormList($dialogue_name, $type, $known_widget_types, $field_rf_rate) {
		return (new CFormList())
			->addRow((new CLabel(_('Type'), 'type')),
				(new CComboBox('type', $type, 'updateWidgetConfigDialogue()', $known_widget_types))
			)
			->addRow(_('Name'),
				(new CTextBox('name',$dialogue_name))
					->setAttribute('placeholder', _('default'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
			->addRow(self::getLabel($field_rf_rate), self::getComboBox($field_rf_rate));
	}

	/**
	 * Creates label linked to the field.
	 *
	 * @param CWidgetField $field
	 *
	 * @return CLabel
	 */
	public static function getLabel($field) {
		return (new CLabel($field->getLabel(), $field->getName()))
			->setAsteriskMark(self::isAriaRequired($field));
	}

	/**
	 * Creates label linked to the multiselect field.
	 *
	 * @param CMultiSelect $field
	 *
	 * @return CLabel
	 */
	public static function getMultiselectLabel($field) {
		$field_name = $field->getName();

		if ($field instanceof CWidgetFieldItem) {
			$field_name .= ($field->isMultiple() ? '[]' : '');
		} else {
			$field_name .= '[]';
		}

		return (new CLabel($field->getLabel(), $field_name.'_ms'))->setAsteriskMark(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldComboBox $field
	 *
	 * @return CComboBox
	 */
	public static function getComboBox($field) {
		return (new CComboBox($field->getName(), $field->getValue(), $field->getAction(), $field->getValues()))
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldTextBox|CWidgetFieldUrl $field
	 *
	 * @return CTextBox
	 */
	public static function getTextBox($field) {
		return (new CTextBox($field->getName(), $field->getValue()))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldCheckBox $field
	 *
	 * @return array
	 */
	public static function getCheckBox($field) {
		return [new CVar($field->getName(), '0'), (new CCheckBox($field->getName()))
			->setChecked((bool) $field->getValue())
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
		];
	}

	/**
	 * @param CWidgetFieldGroup $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getGroup($field, $captions, $form_name) {
		$field_name = $field->getName().'[]';

		return (new CMultiSelect([
			'name' => $field_name,
			'object_name' => 'hostGroup',
			'data' => $captions,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form_name,
					'dstfld1' => zbx_formatDomId($field_name),
				]
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldHost $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getHost($field, $captions, $form_name) {
		$field_name = $field->getName().'[]';

		return (new CMultiSelect([
			'name' => $field_name,
			'object_name' => 'hosts',
			'data' => $captions,
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $form_name,
					'dstfld1' => zbx_formatDomId($field_name)
				]
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldItem $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getItem($field, $captions, $form_name) {
		$field_name = $field->getName().($field->isMultiple() ? '[]' : '');

		return (new CMultiSelect([
			'name' => $field_name,
			'object_name' => 'items',
			'multiple' => $field->isMultiple(),
			'data' => $captions,
			'popup' => [
				'parameters' => [
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'dstfrm' => $form_name,
						'dstfld1' => zbx_formatDomId($field_name)
					] + $field->getFilterParameters()
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldSelectResource $field
	 * @param array $caption
	 * @param string $form_name
	 *
	 * @return array
	 */
	public static function getSelectResource($field, $caption, $form_name) {
		return [
			(new CTextBox($field->getName().'_caption', $caption, true))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(self::isAriaRequired($field)),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson($field->getPopupOptions($form_name)).', null, this);')
		];
	}

	/**
	 * Creates CComboBox field without values, to later fill it by JS script.
	 *
	 * @param CWidgetFieldWidgetListComboBox $field
	 *
	 * @return CComboBox
	 */
	public static function getEmptyComboBox($field) {
		return (new CComboBox($field->getName(), [], $field->getAction(), []))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldNumericBox $field
	 *
	 * @return CNumericBox
	 */
	public static function getNumericBox($field) {
		return (new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength()))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldRadioButtonList $field
	 *
	 * @return CRadioButtonList
	 */
	public static function getRadioButtonList($field) {
		$radio_button_list = (new CRadioButtonList($field->getName(), $field->getValue()))
			->setModern($field->getModern())
			->setAriaRequired(self::isAriaRequired($field));

		foreach ($field->getValues() as $key => $value) {
			$radio_button_list->addValue($value, $key, null, $field->getAction());
		}

		return $radio_button_list;
	}

	/**
	 * @param CWidgetFieldSeverities $field
	 * @param array $config
	 *
	 * @return CList
	 */
	public static function getSeverities($field, $config) {
		$severities = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severities->addItem(
				(new CCheckBox($field->getName().'[]', $severity))
					->setLabel(getSeverityName($severity, $config))
					->setId($field->getName().'_'.$severity)
					->setChecked(in_array($severity, $field->getValue()))
			);
		}

		return $severities;
	}

	/**
	 * @param CWidgetFieldTags $field
	 *
	 * @return CTable
	 */
	public static function getTags($field) {
		$tags = $field->getValue();

		if (!$tags) {
			$tags = [['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']];
		}

		$tags_table = (new CTable())->setId('tags_table_'.$field->getName());
		$i = 0;

		foreach ($tags as $tag) {
			$tags_table->addRow([
				(new CTextBox($field->getName().'['.$i.'][tag]', $tag['tag']))
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field)),
				(new CRadioButtonList($field->getName().'['.$i.'][operator]', (int) $tag['operator']))
					->addValue(_('Like'), TAG_OPERATOR_LIKE)
					->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
					->setModern(true),
				(new CTextBox($field->getName().'['.$i.'][value]', $tag['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field)),
				(new CCol(
					(new CButton($field->getName().'['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP)
			], 'form_row');

			$i++;
		}

		$tags_table->addRow(
			(new CCol(
				(new CButton('tags_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			))->setColSpan(3)
		);

		return $tags_table;
	}

	/**
	 * JS Template for one tag line for Tags field
	 *
	 * @param CWidgetFieldTags $field
	 *
	 * @return string
	 */
	public static function getTagsTemplate($field) {
		return (new CRow([
			(new CTextBox($field->getName().'[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAriaRequired(self::isAriaRequired($field)),
			(new CRadioButtonList($field->getName().'[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Like'), TAG_OPERATOR_LIKE)
				->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox($field->getName().'[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAriaRequired(self::isAriaRequired($field)),
			(new CCol(
				(new CButton($field->getName().'[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString();
	}

	/**
	 * @param CWidgetField $field
	 *
	 * @return int
	 */
	public static function isAriaRequired($field) {
		return ($field->getFlags() & CWidgetField::FLAG_LABEL_ASTERISK);
	}
}
