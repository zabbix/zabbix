<?php declare(strict_types = 0);
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


class CWidgetHelper {

	public const DATASET_TYPE_SINGLE_ITEM = 0;
	public const DATASET_TYPE_PATTERN_ITEM = 1;

	/**
	 * Create CForm for widget configuration form.
	 *
	 * @return CForm
	 */
	public static function createForm(): CForm {
		return (new CForm('post'))
			->cleanItems()
			->setId('widget-dialogue-form')
			->setName('widget_dialogue_form')
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FORM);
	}

	/**
	 * Create CFormGrid for widget configuration form with default fields in it.
	 *
	 * @param string                  $name
	 * @param string                  $type
	 * @param int                     $view_mode  ZBX_WIDGET_VIEW_MODE_NORMAL | ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
	 * @param array                   $known_widget_types
	 * @param CWidgetFieldSelect|null $field_rf_rate
	 *
	 * @return CFormGrid
	 */
	public static function createFormGrid(string $name, string $type, int $view_mode, array $known_widget_types,
			?CWidgetFieldSelect $field_rf_rate): CFormGrid {

		$deprecated_widget_types = array_intersect_key($known_widget_types,
			array_flip(CWidgetConfig::DEPRECATED_WIDGETS)
		);

		$widget_types_select = (new CSelect('type'))
			->setFocusableElementId('label-type')
			->setId('type')
			->setValue($type)
			->setAttribute('autofocus', 'autofocus')
			->addOptions(CSelect::createOptionsFromArray(
				array_diff_key($known_widget_types, $deprecated_widget_types))
			);

		if ($deprecated_widget_types) {
			$widget_types_select->addOptionGroup(
				(new CSelectOptionGroup(_('Deprecated')))
					->addOptions(CSelect::createOptionsFromArray($deprecated_widget_types))
			);
		}

		return (new CFormGrid())
			->addItem([
				new CLabel(_('Type'), 'label-type'),
				new CFormField(array_key_exists($type, $deprecated_widget_types)
					? [$widget_types_select, ' ', makeWarningIcon(_('Widget is deprecated.'))]
					: $widget_types_select
				)
			])
			->addItem(
				(new CFormField(
					(new CCheckBox('show_header'))
						->setLabel(_('Show header'))
						->setLabelPosition(CCheckBox::LABEL_POSITION_LEFT)
						->setId('show_header')
						->setChecked($view_mode == ZBX_WIDGET_VIEW_MODE_NORMAL)
				))->addClass('form-field-show-header')
			)
			->addItem([
				new CLabel(_('Name'), 'name'),
				new CFormField(
					(new CTextBox('name', $name))
						->setAttribute('placeholder', _('default'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
			])
			->addItem($field_rf_rate !== null
				? [
					self::getLabel($field_rf_rate),
					new CFormField(self::getSelect($field_rf_rate))
				]
				: null
			);
	}

	/**
	 * Creates label linked to the field.
	 *
	 * @param CWidgetField $field
	 * @param string       $class	Custom CSS class for label.
	 * @param mixed        $hint	Hint box text.
	 *
	 * @return CLabel
	 */
	public static function getLabel($field, $class = null, $hint = null) {
		$help_icon = ($hint !== null)
			? makeHelpIcon($hint)
			: null;

		if ($field instanceof CWidgetFieldSelect) {
			return (new CLabel([$field->getLabel(), $help_icon], 'label-'.$field->getName()))
				->setAsteriskMark(self::isAriaRequired($field))
				->addClass($class);
		}

		return (new CLabel([$field->getLabel(), $help_icon], $field->getName()))
			->setAsteriskMark(self::isAriaRequired($field))
			->addClass($class);
	}

	/**
	 * @param CWidgetFieldSelect $field
	 *
	 * @return CSelect
	 */
	public static function getSelect($field) {
		return (new CSelect($field->getName()))
			->setId($field->getName())
			->setFocusableElementId('label-'.$field->getName())
			->setValue($field->getValue())
			->addOptions(CSelect::createOptionsFromArray($field->getValues()))
			->setDisabled($field->getFlags() & CWidgetField::FLAG_DISABLED)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldTextArea $field
	 *
	 * @return CTextArea
	 */
	public static function getTextArea($field) {
		return (new CTextArea($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setAdaptiveWidth($field->getWidth());
	}

	/**
	 * @param CWidgetFieldTextBox $field
	 *
	 * @return CTextBox
	 */
	public static function getTextBox($field) {
		return (new CTextBox($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setAttribute('placeholder', $field->getPlaceholder())
			->setWidth($field->getWidth());
	}

	/**
	 * @param CWidgetFieldLatLng $field
	 *
	 * @return CTextBox
	 */
	public static function getLatLngZoomBox($field) {
		return (new CTextBox($field->getName(), $field->getValue()))
			->setAttribute('placeholder', $field->getPlaceholder())
			->setWidth($field->getWidth());
	}

	/**
	 * @param CWidgetFieldUrl $field
	 *
	 * @return CTextBox
	 */
	public static function getUrlBox($field) {
		return (new CTextBox($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	}

	/**
	 * @param CWidgetFieldRangeControl $field
	 *
	 * @return CRangeControl
	 */
	public static function getRangeControl($field) {
		return (new CRangeControl($field->getName(), (int) $field->getValue()))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setStep($field->getStep())
			->setMin($field->getMin())
			->setMax($field->getMax());
	}

	/**
	 * @param CWidgetFieldHostPatternSelect  $field      Widget field object.
	 * @param string                         $form_name  HTML form element name.
	 *
	 * @return CPatternSelect
	 */
	public static function getHostPatternSelect($field, $form_name) {
		return (new CPatternSelect([
			'name' => $field->getName().'[]',
			'object_name' => 'hosts',
			'data' => $field->getValue(),
			'placeholder' => $field->getPlaceholder(),
			'wildcard_allowed' => 1,
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $form_name,
					'dstfld1' => zbx_formatDomId($field->getName().'[]')
				]
			],
			'add_post_js' => false
		]))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setAriaRequired(self::isAriaRequired($field))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	}

	/**
	 * @param CWidgetFieldCheckBox $field
	 *
	 * @return array
	 */
	public static function getCheckBox($field) {
		return [(new CVar($field->getName(), '0'))->removeId(), (new CCheckBox($field->getName()))
			->setChecked((bool) $field->getValue())
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setLabel($field->getCaption())
			->onChange($field->getAction())
		];
	}

	/**
	 * @param CWidgetFieldColor $field
	 * @param bool              $use_default  Tell the Color picker whether to use Default color feature or not.
	 *
	 * @return CColor
	 */
	public static function getColor($field, $use_default = false) {
		// appendColorPickerJs(false), because the script responsible for it is in widget.item.form.view.
		$color_picker = (new CColor($field->getName(), $field->getValue()))->appendColorPickerJs(false);
		if ($use_default) {
			$color_picker->enableUseDefault();
		}
		return $color_picker;
	}

	/**
	 * Creates label linked to the multiselect field.
	 *
	 * @param CWidgetFieldMs $field
	 *
	 * @return CLabel
	 */
	public static function getMultiselectLabel($field) {
		$field_name = $field->getName();

		if ($field instanceof CWidgetFieldMs) {
			$field_name .= ($field->isMultiple() ? '[]' : '');
		}
		else {
			$field_name .= '[]';
		}

		return (new CLabel($field->getLabel(), $field_name.'_ms'))
			->setAsteriskMark(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldMs $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	private static function getMultiselectField($field, $captions, $form_name, $object_name, $popup_options) {
		$field_name = $field->getName().($field->isMultiple() ? '[]' : '');
		$options = [
			'name' => $field_name,
			'object_name' => $object_name,
			'multiple' => $field->isMultiple(),
			'data' => $captions,
			'popup' => [
				'parameters' => [
					'dstfrm' => $form_name,
					'dstfld1' => zbx_formatDomId($field_name)
				] + $popup_options
			],
			'add_post_js' => false
		];

		if ($field instanceof CWidgetFieldMsHost && $field->filter_preselect_host_group_field) {
			$options['popup']['filter_preselect_fields']['hostgroups'] = $field->filter_preselect_host_group_field;
		}

		return (new CMultiSelect($options))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldMsGroup $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getGroup($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'hostGroup', [
			'srctbl' => 'host_groups',
			'srcfld1' => 'groupid',
			'real_hosts' => true,
			'enrich_parent_groups' => true
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsHost $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getHost($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'hosts', [
			'srctbl' => 'hosts',
			'srcfld1' => 'hostid'
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsItem $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getItem($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'items', [
			'srctbl' => 'items',
			'srcfld1' => 'itemid'
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsGraph $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getGraph($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'graphs', [
			'srctbl' => 'graphs',
			'srcfld1' => 'graphid',
			'srcfld2' => 'name',
			'with_graphs' => true
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsItemPrototype $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getItemPrototype($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'item_prototypes', [
			'srctbl' => 'item_prototypes',
			'srcfld1' => 'itemid'
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsGraphPrototype $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getGraphPrototype($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'graph_prototypes', [
			'srctbl' => 'graph_prototypes',
			'srcfld1' => 'graphid',
			'srcfld2' => 'name',
			'with_graph_prototypes' => true
		] + $field->getFilterParameters());
	}

	/**
	 * @param CWidgetFieldMsService $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getService($field, $captions, $form_name) {
		return (new CMultiSelect([
			'name' => $field->getName().($field->isMultiple() ? '[]' : ''),
			'object_name' => 'services',
			'multiple' => $field->isMultiple(),
			'data' => $captions,
			'custom_select' => true,
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldMsSla $field
	 * @param array $captions
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	public static function getSla($field, $captions, $form_name) {
		return self::getMultiselectField($field, $captions, $form_name, 'sla', [
				'srctbl' => 'sla',
				'srcfld1' => 'slaid'
			] + $field->getFilterParameters());
	}

	public static function getSelectResource($field, $caption, $form_name) {
		return [
			(new CTextBox($field->getName().'_caption', $caption, true))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(self::isAriaRequired($field)),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic", '.json_encode($field->getPopupOptions($form_name)).',
					{dialogue_class: "modal-popup-generic"}
				);')
		];
	}

	/**
	 * Creates select field without values, to later fill it by JS script.
	 *
	 * @param CWidgetFieldWidgetSelect $field
	 *
	 * @return CSelect
	 */
	public static function getEmptySelect($field) {
		return (new CSelect($field->getName()))
			->setFocusableElementId('label-'.$field->getName())
			->setId($field->getName())
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldIntegerBox $field
	 *
	 * @return CNumericBox
	 */
	public static function getIntegerBox(CWidgetFieldIntegerBox $field): CNumericBox {
		return (new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength(), false,
			($field->getFlags() & CWidgetField::FLAG_NOT_EMPTY) == 0
		))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired(self::isAriaRequired($field));
	}

	/**
	 * @param CWidgetFieldNumericBox $field
	 *
	 * @return CTextBox
	 */
	public static function getNumericBox($field) {
		return (new CTextBox($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setAttribute('placeholder', $field->getPlaceholder())
			->setWidth($field->getWidth());
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
			$radio_button_list
				->addValue($value, $key, null, $field->getAction())
				->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED));
		}

		return $radio_button_list;
	}

	/**
	 * @param CWidgetFieldSeverities $field
	 *
	 * @return CCheckBoxList
	 */
	public static function getSeverities($field) {
		return (new CCheckBoxList($field->getName()))
			->setOptions(CSeverityHelper::getSeverities())
			->setChecked($field->getValue())
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setColumns(3)
			->setVertical();
	}

	/**
	 * @param CWidgetFieldCheckBoxList $field
	 * @param array                    $list        Option list array.
	 * @param array                    $class_list  List of additional CSS classes.
	 *
	 * @return CList
	 */
	public static function getCheckBoxList($field, array $list, array $class_list = []) {
		$checkbox_list = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);
		if ($class_list) {
			foreach ($class_list as $class) {
				$checkbox_list->addClass($class);
			}
		}

		foreach ($list as $key => $label) {
			$checkbox_list->addItem(
				(new CCheckBox($field->getName().'[]', $key))
					->setLabel($label)
					->setId($field->getName().'_'.$key)
					->setChecked(in_array($key, $field->getValue()))
					->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
			);
		}

		return $checkbox_list;
	}

	/**
	 * @param CWidgetFieldColumnsList $field  Widget columns field.
	 *
	 * @return CDiv
	 */
	public static function getWidgetColumns(CWidgetFieldColumnsList $field) {
		$columns = $field->getValue();
		$header = [
			'',
			(new CColHeader(_('Name')))->addStyle('width: 39%'),
			(new CColHeader(_('Data')))->addStyle('width: 59%'),
			_('Action')
		];
		$row_actions = [
			(new CButton('edit', _('Edit')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId(),
			(new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		];
		$table = (new CTable())
			->setId('list_'.$field->getName())
			->setHeader($header);
		$enabled = !($field->getFlags() & CWidgetField::FLAG_DISABLED);

		foreach ($columns as $column_index => $column) {
			$column_data = [new CVar('sortorder['.$field->getName().'][]', $column_index)];

			foreach ($column as $key => $value) {
				$column_data[] = new CVar($field->getName().'['.$column_index.']['.$key.']', $value);
			}

			$label = array_key_exists('item', $column) ? $column['item'] : '';

			if ($column['data'] == CWidgetFieldColumnsList::DATA_HOST_NAME) {
				$label = new CTag('em', true, _('Host name'));
			}
			else if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$label = new CTag('em', true, $column['text']);
			}

			$table->addRow((new CRow([
				(new CCol((new CDiv)
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('top: 0px;')
				))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CDiv($column['name']))->addClass('text'),
				(new CDiv($label))->addClass('text'),
				(new CList(array_merge($row_actions, [$column_data])))->addClass(ZBX_STYLE_HOR_LIST)
			]))->addClass('sortable'));
		}

		$table->addRow(
			(new CCol(
				(new CButton('add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled($enabled)
			))->setColSpan(count($header))
		);

		return $table;
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

		$tags_table = (new CTable())
			->setId('tags_table_'.$field->getName())
			->addClass('table-tags')
			->addClass('table-initial-width');

		$enabled = !($field->getFlags() & CWidgetField::FLAG_DISABLED);
		$i = 0;

		foreach ($tags as $tag) {
			$zselect_operator = (new CSelect($field->getName().'['.$i.'][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue($tag['operator'])
				->setFocusableElementId($field->getName().'-'.$i.'-operator-select')
				->setId($field->getName().'_'.$i.'_operator');

			if (!$enabled) {
				$zselect_operator->setDisabled();
			}

			$tags_table->addRow([
				(new CTextBox($field->getName().'['.$i.'][tag]', $tag['tag']))
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field))
					->setEnabled($enabled),
				$zselect_operator,
				(new CTextBox($field->getName().'['.$i.'][value]', $tag['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field))
					->setId($field->getName().'_'.$i.'_value')
					->setEnabled($enabled),
				(new CCol(
					(new CButton($field->getName().'['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
						->setEnabled($enabled)
				))->addClass(ZBX_STYLE_NOWRAP)
			], 'form_row');

			$i++;
		}

		$tags_table->addRow(
			(new CCol(
				(new CButton('tags_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled($enabled)
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
			(new CSelect($field->getName().'[#{rowNum}][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(TAG_OPERATOR_LIKE)
				->setFocusableElementId($field->getName().'-#{rowNum}-operator-select')
				->setId($field->getName().'_#{rowNum}_operator'),
			(new CTextBox($field->getName().'[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAriaRequired(self::isAriaRequired($field))
				->setId($field->getName().'_#{rowNum}_value'),
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
	 * @param CWidgetFieldDatePicker $field
	 *
	 * @return CDateSelector
	 */
	public static function getDatePicker(CWidgetFieldDatePicker $field): CDateSelector {
		return (new CDateSelector($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setMaxLength(DB::getFieldLength('widget_field', 'value_str'))
			->setEnabled(($field->getFlags() & CWidgetField::FLAG_DISABLED) == 0);
	}

	/**
	 * Function returns array containing HTML objects filled with given values. Used to generate HTML in widget
	 * overrides field.
	 *
	 * @param CWidgetFieldGraphOverride  $field
	 * @param array                      $value      Values to fill in particular data set row. See self::setValue() for
	 *                                               detailed description.
	 * @param string                     $form_name  Name of form in which data set fields resides.
	 * @param int|string                 $row_num    Unique data set numeric identifier. Used to make unique field names.
	 *
	 * @return CListItem
	 */
	public static function getGraphOverrideLayout($field, array $value, $form_name, $row_num) {
		$inputs = [];

		// Create override optins list.
		foreach (CWidgetFieldGraphOverride::getOverrideOptions() as $option) {
			if (array_key_exists($option, $value)) {
				$inputs[] = (new CVar($field->getName().'['.$row_num.']['.$option.']', $value[$option]));
			}
		}

		return (new CListItem([
			/**
			 * First line: host pattern field, item pattern field.
			 * Contains also drag and drop button and delete button.
			 */
			(new CDiv([
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),
				(new CDiv([
					(new CDiv(
						(new CPatternSelect([
							'name' => $field->getName().'['.$row_num.'][hosts][]',
							'object_name' => 'hosts',
							'data' => $value['hosts'],
							'placeholder' => _('host pattern'),
							'wildcard_allowed' => 1,
							'popup' => [
								'parameters' => [
									'srctbl' => 'hosts',
									'srcfld1' => 'hostid',
									'dstfrm' => $form_name,
									'dstfld1' => zbx_formatDomId($field->getName().'['.$row_num.'][hosts][]')
								]
							],
							'add_post_js' => false
						]))
							->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
							->setAriaRequired(self::isAriaRequired($field))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->addClass(ZBX_STYLE_COLUMN_50),
					(new CDiv(
						(new CPatternSelect([
							'name' => $field->getName().'['.$row_num.'][items][]',
							'object_name' => 'items',
							'data' => $value['items'],
							'placeholder' => _('item pattern'),
							'multiple' => true,
							'wildcard_allowed' => 1,
							'popup' => [
								'parameters' => [
									'srctbl' => 'items',
									'srcfld1' => 'itemid',
									'real_hosts' => 1,
									'numeric' => 1,
									'dstfrm' => $form_name,
									'dstfld1' => zbx_formatDomId($field->getName().'['.$row_num.'][items][]')
								]
							],
							'add_post_js' => false
						]))
							->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
							->setAriaRequired(self::isAriaRequired($field))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->addClass(ZBX_STYLE_COLUMN_50)
				]))
					->addClass(ZBX_STYLE_COLUMNS)
					->addClass(ZBX_STYLE_COLUMNS_NOWRAP)
					->addClass(ZBX_STYLE_COLUMN_95),

				(new CDiv(
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_BTN_REMOVE)
						->removeId()
				))
					->addClass(ZBX_STYLE_COLUMN_5)
			]))
				->addClass(ZBX_STYLE_COLUMNS),

			// Selected override options.
			(new CList($inputs))
				->addClass(ZBX_STYLE_OVERRIDES_OPTIONS_LIST)
				->addItem((new CButton(null, (new CSpan())
							->addClass(ZBX_STYLE_PLUS_ICON)
							->addStyle('margin-right: 0px;')
					))
						->setAttribute('data-row', $row_num)
						->addClass(ZBX_STYLE_BTN_ALT)
				)
		]))
			->addClass(ZBX_STYLE_OVERRIDES_LIST_ITEM);
	}

	/**
	 * Return template used by dynamic rows in CWidgetFieldGraphOverride field.
	 *
	 * @param CWidgetFieldGraphOverride $field
	 * @param string                    $form_name  Form name in which override field is located.
	 *
	 * @return string
	 */
	public static function getGraphOverrideTemplate($field, $form_name) {
		$value = CWidgetFieldGraphOverride::getDefaults();

		return self::getGraphOverrideLayout($field, $value, $form_name, '#{rowNum}')->toString();
	}

	/**
	 * @param CWidgetFieldGraphOverride $field
	 *
	 * @return CList
	 */
	public static function getGraphOverride($field, $form_name) {
		$list = (new CList())->addClass(ZBX_STYLE_OVERRIDES_LIST);

		$values = $field->getValue();

		if (!$values) {
			$values = [];
		}

		$i = 0;

		foreach ($values as $override) {
			$list->addItem(self::getGraphOverrideLayout($field, $override, $form_name, $i));

			$i++;
		}

		// Add 'Add' button under the list.
		$list->addItem(
			(new CDiv(
				(new CButton('override_add', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add new override')]))
					->addClass(ZBX_STYLE_BTN_ALT)
					->setId('override-add')
			)),
			'overrides-foot'
		);

		return $list;
	}

	/**
	 * Function returns array containing string values used as titles for override options.
	 *
	 * @return array
	 */
	private static function getGraphOverrideOptionNames() {
		return [
			'width' => _('Width'),
			'type' => _('Draw'),
			'type'.SVG_GRAPH_TYPE_LINE => _('Line'),
			'type'.SVG_GRAPH_TYPE_POINTS => _('Points'),
			'type'.SVG_GRAPH_TYPE_STAIRCASE => _('Staircase'),
			'type'.SVG_GRAPH_TYPE_BAR => _('Bar'),
			'transparency' => _('Transparency'),
			'fill' => _('Fill'),
			'pointsize' => _('Point size'),
			'missingdatafunc' => _('Missing data'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_NONE => _('None'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_CONNECTED => _x('Connected', 'missing data function'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO => _x('Treat as 0', 'missing data function'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_LAST_KNOWN => _x('Last known', 'missing data function'),
			'axisy' => _('Y-axis'),
			'axisy'.GRAPH_YAXIS_SIDE_LEFT => _('Left'),
			'axisy'.GRAPH_YAXIS_SIDE_RIGHT => _('Right'),
			'timeshift' => _('Time shift')
		];
	}

	/**
	 * Function returns array used to construct override field menu of available override options.
	 *
	 * @return array
	 */
	private static function getGraphOverrideMenu() {
		return [
			'sections' => [
				[
					'name' => _('ADD OVERRIDE'),
					'options' => [
						['name' => _('Base color'), 'callback' => 'addOverride', 'args' => ['color', '']],

						['name' => _('Width').'/0', 'callback' => 'addOverride', 'args' => ['width', 0]],
						['name' => _('Width').'/1', 'callback' => 'addOverride', 'args' => ['width', 1]],
						['name' => _('Width').'/2', 'callback' => 'addOverride', 'args' => ['width', 2]],
						['name' => _('Width').'/3', 'callback' => 'addOverride', 'args' => ['width', 3]],
						['name' => _('Width').'/4', 'callback' => 'addOverride', 'args' => ['width', 4]],
						['name' => _('Width').'/5', 'callback' => 'addOverride', 'args' => ['width', 5]],
						['name' => _('Width').'/6', 'callback' => 'addOverride', 'args' => ['width', 6]],
						['name' => _('Width').'/7', 'callback' => 'addOverride', 'args' => ['width', 7]],
						['name' => _('Width').'/8', 'callback' => 'addOverride', 'args' => ['width', 8]],
						['name' => _('Width').'/9', 'callback' => 'addOverride', 'args' => ['width', 9]],
						['name' => _('Width').'/10', 'callback' => 'addOverride', 'args' => ['width', 10]],

						['name' => _('Draw').'/'._('Line'), 'callback' => 'addOverride', 'args' => ['type', SVG_GRAPH_TYPE_LINE]],
						['name' => _('Draw').'/'._('Points'), 'callback' => 'addOverride', 'args' => ['type', SVG_GRAPH_TYPE_POINTS]],
						['name' => _('Draw').'/'._('Staircase'), 'callback' => 'addOverride', 'args' => ['type', SVG_GRAPH_TYPE_STAIRCASE]],
						['name' => _('Draw').'/'._('Bar'), 'callback' => 'addOverride', 'args' => ['type', SVG_GRAPH_TYPE_BAR]],

						['name' => _('Transparency').'/0', 'callback' => 'addOverride', 'args' => ['transparency', 0]],
						['name' => _('Transparency').'/1', 'callback' => 'addOverride', 'args' => ['transparency', 1]],
						['name' => _('Transparency').'/2', 'callback' => 'addOverride', 'args' => ['transparency', 2]],
						['name' => _('Transparency').'/3', 'callback' => 'addOverride', 'args' => ['transparency', 3]],
						['name' => _('Transparency').'/4', 'callback' => 'addOverride', 'args' => ['transparency', 4]],
						['name' => _('Transparency').'/5', 'callback' => 'addOverride', 'args' => ['transparency', 5]],
						['name' => _('Transparency').'/6', 'callback' => 'addOverride', 'args' => ['transparency', 6]],
						['name' => _('Transparency').'/7', 'callback' => 'addOverride', 'args' => ['transparency', 7]],
						['name' => _('Transparency').'/8', 'callback' => 'addOverride', 'args' => ['transparency', 8]],
						['name' => _('Transparency').'/9', 'callback' => 'addOverride', 'args' => ['transparency', 9]],
						['name' => _('Transparency').'/10', 'callback' => 'addOverride', 'args' => ['transparency', 10]],

						['name' => _('Fill').'/0', 'callback' => 'addOverride', 'args' => ['fill', 0]],
						['name' => _('Fill').'/1', 'callback' => 'addOverride', 'args' => ['fill', 1]],
						['name' => _('Fill').'/2', 'callback' => 'addOverride', 'args' => ['fill', 2]],
						['name' => _('Fill').'/3', 'callback' => 'addOverride', 'args' => ['fill', 3]],
						['name' => _('Fill').'/4', 'callback' => 'addOverride', 'args' => ['fill', 4]],
						['name' => _('Fill').'/5', 'callback' => 'addOverride', 'args' => ['fill', 5]],
						['name' => _('Fill').'/6', 'callback' => 'addOverride', 'args' => ['fill', 6]],
						['name' => _('Fill').'/7', 'callback' => 'addOverride', 'args' => ['fill', 7]],
						['name' => _('Fill').'/8', 'callback' => 'addOverride', 'args' => ['fill', 8]],
						['name' => _('Fill').'/9', 'callback' => 'addOverride', 'args' => ['fill', 9]],
						['name' => _('Fill').'/10', 'callback' => 'addOverride', 'args' => ['fill', 10]],

						['name' => _('Point size').'/1', 'callback' => 'addOverride', 'args' => ['pointsize', 1]],
						['name' => _('Point size').'/2', 'callback' => 'addOverride', 'args' => ['pointsize', 2]],
						['name' => _('Point size').'/3', 'callback' => 'addOverride', 'args' => ['pointsize', 3]],
						['name' => _('Point size').'/4', 'callback' => 'addOverride', 'args' => ['pointsize', 4]],
						['name' => _('Point size').'/5', 'callback' => 'addOverride', 'args' => ['pointsize', 5]],
						['name' => _('Point size').'/6', 'callback' => 'addOverride', 'args' => ['pointsize', 6]],
						['name' => _('Point size').'/7', 'callback' => 'addOverride', 'args' => ['pointsize', 7]],
						['name' => _('Point size').'/8', 'callback' => 'addOverride', 'args' => ['pointsize', 8]],
						['name' => _('Point size').'/9', 'callback' => 'addOverride', 'args' => ['pointsize', 9]],
						['name' => _('Point size').'/10', 'callback' => 'addOverride', 'args' => ['pointsize', 10]],

						['name' => _('Missing data').'/'._('None'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_NONE]],
						['name' => _('Missing data').'/'._x('Connected', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_CONNECTED]],
						['name' => _('Missing data').'/'._x('Treat as 0', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO]],
						['name' => _('Missing data').'/'._x('Last known', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_LAST_KNOWN]],

						['name' => _('Y-axis').'/'._('Left'), 'callback' => 'addOverride', 'args' => ['axisy', GRAPH_YAXIS_SIDE_LEFT]],
						['name' => _('Y-axis').'/'._('Right'), 'callback' => 'addOverride', 'args' => ['axisy', GRAPH_YAXIS_SIDE_RIGHT]],

						['name' => _('Time shift'), 'callback' => 'addOverride', 'args' => ['timeshift']]
					]
				]
			]
		];
	}

	/**
	 * Return javascript necessary to initialize CWidgetFieldGraphOverride field.
	 *
	 * @param CWidgetFieldGraphOverride $field
	 *
	 * @return string
	 */
	public static function getGraphOverrideJavascript($field) {
		return '
			// Define it as function to avoid redundancy.
			function initializeOverrides() {
				jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_OPTIONS_LIST.'").overrides({
					add: ".'.ZBX_STYLE_BTN_ALT.'",
					options: "input[type=hidden]",
					captions: '.json_encode(self::getGraphOverrideOptionNames()).',
					makeName: function(option, row_id) {
						return "'.$field->getName().'[" + row_id + "][" + option + "]";
					},
					makeOption: function(name) {
						return name.match(
							/.*\[('.implode('|', CWidgetFieldGraphOverride::getOverrideOptions()).')\]/
						)[1];
					},
					override: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",
					overridesList: ".'.ZBX_STYLE_OVERRIDES_LIST.'",
					onUpdate: () => widget_svggraph_form.onGraphConfigChange(),
					menu: '.json_encode(self::getGraphOverrideMenu()).'
				});
			}

			// Initialize dynamicRows.
			jQuery("#overrides")
				.dynamicRows({
					template: "#overrides-row",
					beforeRow: ".overrides-foot",
					remove: ".'.ZBX_STYLE_BTN_REMOVE.'",
					add: "#override-add",
					row: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'"
				})
				.bind("afteradd.dynamicRows", function(event, options) {
					const container = jQuery(".overlay-dialogue-body");

					container.scrollTop(Math.max(container.scrollTop(),
						jQuery("#widget-dialogue-form")[0].scrollHeight - container.height()
					));

					jQuery(".multiselect", jQuery("#overrides")).each(function() {
						jQuery(this).multiSelect(jQuery(this).data("params"));
					});

					widget_svggraph_form.updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");
					widget_svggraph_form.onGraphConfigChange();
				})
				.bind("afterremove.dynamicRows", function(event, options) {
					widget_svggraph_form.updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");
					widget_svggraph_form.onGraphConfigChange();
				})
				.bind("tableupdate.dynamicRows", function(event, options) {
					widget_svggraph_form.updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");
					initializeOverrides();
					if (jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length > 1) {
						jQuery("#overrides .drag-icon").removeClass("disabled");
						jQuery("#overrides").sortable("enable");
					}
					else {
						jQuery("#overrides .drag-icon").addClass("disabled");
						jQuery("#overrides").sortable("disable");
					}
				});

			// Initialize overrides UI control.
			initializeOverrides();

			// Initialize override pattern-selectors.
			jQuery(".multiselect", jQuery("#overrides")).each(function() {
				jQuery(this).multiSelect(jQuery(this).data("params"));
			});

			// Make overrides sortable.
			if (jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length < 2) {
				jQuery("#overrides .drag-icon").addClass("disabled");
			}

			jQuery("#overrides").sortable({
				items: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",
				containment: "parent",
				handle: ".drag-icon",
				tolerance: "pointer",
				scroll: false,
				cursor: "grabbing",
				opacity: 0.6,
				axis: "y",
				disabled: function() {
					return jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length < 2;
				}(),
				start: function() { // Workaround to fix wrong scrolling at initial sort.
					jQuery(this).sortable("refreshPositions");
				},
				stop: () => widget_svggraph_form.onGraphConfigChange(),
				update: function() {
					widget_svggraph_form.updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");
				}
			});
		';
	}

	/**
	 * Function returns array containing HTML objects filled with given values. Used to generate HTML row in widget
	 * data set field.
	 *
	 * @param string     $field_name
	 * @param array      $value      Values to fill in particular data set row. See self::setValue() for detailed
	 *                               description.
	 * @param string     $form_name  Name of form in which data set fields resides.
	 * @param int|string $row_num    Unique data set numeric identifier. Used to make unique field names.
	 * @param bool       $is_opened  Either accordion row is made opened or closed.
	 *
	 * @return CListItem
	 */
	private static function getGraphDataSetLayout($field_name, array $value, $form_name, $row_num, $is_opened,
			int $dataset_type = CWidgetHelper::DATASET_TYPE_PATTERN_ITEM) {
		$dataset_head = [
			new CDiv((new CSimpleButton('&nbsp;'))->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE)),
			new CVar($field_name.'['.$row_num.'][dataset_type]', $dataset_type, '')
		];

		if ($dataset_type == self::DATASET_TYPE_PATTERN_ITEM) {
			$dataset_head = array_merge($dataset_head, [
				(new CColor($field_name.'['.$row_num.'][color]', $value['color']))->appendColorPickerJs(false),
				(new CPatternSelect([
					'name' => $field_name.'['.$row_num.'][hosts][]',
					'object_name' => 'hosts',
					'data' => $value['hosts'],
					'placeholder' => _('host pattern'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'host',
							'dstfrm' => $form_name,
							'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][hosts][]')
						]
					],
					'add_post_js' => false
				]))
					->addClass('js-hosts-multiselect')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				(new CPatternSelect([
					'name' => $field_name.'['.$row_num.'][items][]',
					'object_name' => 'items',
					'data' => $value['items'],
					'placeholder' => _('item pattern'),
					'wildcard_allowed' => 1,
					'popup' => [
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'name',
							'real_hosts' => 1,
							'numeric' => 1,
							'dstfrm' => $form_name,
							'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][items][]')
						]
					],
					'add_post_js' => false
				]))
					->addClass('js-items-multiselect')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			]);
		}
		else {
			$item_rows = [];
			foreach($value['itemids'] as $i => $itemid) {
				$item_name = array_key_exists($itemid, $value['item_names'])
					? $value['item_names'][$itemid]
					: '';

				$item_rows[] = (new CRow([
					(new CCol(
						(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
					))
						->addClass('table-col-handle')
						->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CCol(
						(new CColor($field_name.'['.$row_num.'][color][]', $value['color'][$i],
							'items_'.$row_num.'_'.($i + 1).'_color'
						))->appendColorPickerJs(false)
					))->addClass('table-col-color'),
					(new CCol(new CSpan(($i + 1).':')))->addClass('table-col-no'),
					(new CCol(
						(new CLink($item_name))
							->setId('items_'.$row_num.'_'.($i + 1).'_name')
							->addClass('js-click-expend')
					))->addClass('table-col-name'),
					(new CCol([
						(new CButton('button', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove'),
						new CVar($field_name.'['.$row_num.'][itemids][]', $itemid,
							'items_'.$row_num.'_'.($i + 1).'_input'
						)
					]))
						->addClass('table-col-action')
						->addClass(ZBX_STYLE_NOWRAP)
				]))
					->addClass(ZBX_STYLE_SORTABLE)
					->addClass('single-item-table-row');
			}

			$empty_msg_block = (new CDiv(_('No item selected.')))->addClass('no-items-message');

			$items_list = (new CTable())
				->addClass('single-item-table')
				->setAttribute('data-set', $row_num)
				->setColumns([
					(new CTableColumn())->addClass('table-col-handle'),
					(new CTableColumn())->addClass('table-col-color'),
					(new CTableColumn())->addClass('table-col-no'),
					(new CTableColumn(_('Name')))->addClass('table-col-name'),
					(new CTableColumn(_('Action')))->addClass('table-col-action')
				])
				->addItem([
					$item_rows,
					(new CTag('tfoot', true))
						->addItem(
							(new CCol(
								(new CList())
									->addClass(ZBX_STYLE_INLINE_FILTER_FOOTER)
									->addItem(
										(new CSimpleButton(_('Add')))
											->addClass(ZBX_STYLE_BTN_LINK)
											->addClass('js-add-item')
									)
							))->setColSpan(5)
						)
				]);

			$dataset_head = array_merge($dataset_head, [
				(new CDiv([$empty_msg_block, $items_list]))->addClass('items-list table-forms-separator')
			]);
		}

		$dataset_head[] = (new CDiv(
			(new CButton())
				->setAttribute('title', _('Delete'))
				->addClass(ZBX_STYLE_BTN_REMOVE)
				->removeId()
		))->addClass('dataset-actions');

		return (new CListItem([
			(new CDiv())
				->addClass(ZBX_STYLE_DRAG_ICON)
				->addClass(ZBX_STYLE_SORTABLE_DRAG_HANDLE)
				->addClass('js-main-drag-icon'),
			(new CDiv())
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD)
				->addClass('dataset-head')
				->addItem($dataset_head),
			(new CDiv())
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_BODY)
				->addClass('dataset-body')
				->addItem([
					(new CFormGrid())
						->addItem([
							new CLabel(_('Draw')),
							new CFormField(
								(new CRadioButtonList($field_name.'['.$row_num.'][type]', (int) $value['type']))
									->addClass('js-type')
									->addValue(_('Line'), SVG_GRAPH_TYPE_LINE)
									->addValue(_('Points'), SVG_GRAPH_TYPE_POINTS)
									->addValue(_('Staircase'), SVG_GRAPH_TYPE_STAIRCASE)
									->addValue(_('Bar'), SVG_GRAPH_TYPE_BAR)
									->setModern(true)
							)
						])
						->addItem([
							new CLabel(_('Stacked'), $field_name.'['.$row_num.'][stacked]'),
							new CFormField([
								(new CVar($field_name.'['.$row_num.'][stacked]', '0'))->removeId(),
								(new CCheckBox($field_name.'['.$row_num.'][stacked]'))
									->addClass('js-stacked')
									->setChecked((bool) $value['stacked'])
									->setEnabled($value['type'] != SVG_GRAPH_TYPE_POINTS)
							])
						])
						->addItem([
							new CLabel(_('Width')),
							new CFormField(
								(new CRangeControl($field_name.'['.$row_num.'][width]', (int) $value['width']))
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
						])
						->addItem([
							new CLabel(_('Point size')),
							new CFormField(
								(new CRangeControl($field_name.'['.$row_num.'][pointsize]', (int) $value['pointsize']))
									->setEnabled($value['type'] == SVG_GRAPH_TYPE_POINTS)
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(1)
									->setMax(10)
							)
						])
						->addItem([
							new CLabel(_('Transparency')),
							new CFormField(
								(new CRangeControl($field_name.'['.$row_num.'][transparency]',
									(int) $value['transparency'])
								)
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
						])
						->addItem([
							new CLabel(_('Fill')),
							new CFormField(
								(new CRangeControl($field_name.'['.$row_num.'][fill]', (int) $value['fill']))
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
						]),
					(new CFormGrid())
						->addItem([
							new CLabel(_('Missing data')),
							new CFormField(
								(new CRadioButtonList($field_name.'['.$row_num.'][missingdatafunc]',
									(int) $value['missingdatafunc'])
								)
									->addValue(_('None'), SVG_GRAPH_MISSING_DATA_NONE)
									->addValue(_x('Connected', 'missing data function'),
										SVG_GRAPH_MISSING_DATA_CONNECTED
									)
									->addValue(_x('Treat as 0', 'missing data function'),
										SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO
									)
									->addValue(_x('Last known', 'missing data function'),
										SVG_GRAPH_MISSING_DATA_LAST_KNOWN
									)
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setModern(true)
							)
						])
						->addItem([
							new CLabel(_('Y-axis')),
							new CFormField(
								(new CRadioButtonList($field_name.'['.$row_num.'][axisy]', (int) $value['axisy']))
									->addValue(_('Left'), GRAPH_YAXIS_SIDE_LEFT)
									->addValue(_('Right'), GRAPH_YAXIS_SIDE_RIGHT)
									->setModern(true)
							)
						])
						->addItem([
							new CLabel(_('Time shift'), $field_name.'['.$row_num.'][timeshift]'),
							new CFormField(
								(new CTextBox($field_name.'['.$row_num.'][timeshift]', $value['timeshift']))
									->setAttribute('placeholder', _('none'))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
						->addItem([
							new CLabel(_('Aggregation function'),
								'label-'.$field_name.'_'.$row_num.'_aggregate_function'
							),
							new CFormField(
								(new CSelect($field_name.'['.$row_num.'][aggregate_function]'))
									->setId($field_name.'_'.$row_num.'_aggregate_function')
									->setFocusableElementId('label-'.$field_name.'_'.$row_num.'_aggregate_function')
									->setValue((int) $value['aggregate_function'])
									->addOptions(CSelect::createOptionsFromArray([
										AGGREGATE_NONE => graph_item_aggr_fnc2str(AGGREGATE_NONE),
										AGGREGATE_MIN => graph_item_aggr_fnc2str(AGGREGATE_MIN),
										AGGREGATE_MAX => graph_item_aggr_fnc2str(AGGREGATE_MAX),
										AGGREGATE_AVG => graph_item_aggr_fnc2str(AGGREGATE_AVG),
										AGGREGATE_COUNT => graph_item_aggr_fnc2str(AGGREGATE_COUNT),
										AGGREGATE_SUM => graph_item_aggr_fnc2str(AGGREGATE_SUM),
										AGGREGATE_FIRST => graph_item_aggr_fnc2str(AGGREGATE_FIRST),
										AGGREGATE_LAST => graph_item_aggr_fnc2str(AGGREGATE_LAST)
									]))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
						->addItem([
							new CLabel(_('Aggregation interval'), $field_name.'['.$row_num.'][aggregate_interval]'),
							new CFormField(
								(new CTextBox($field_name.'['.$row_num.'][aggregate_interval]',
									$value['aggregate_interval']
								))
									->setEnabled($value['aggregate_function'] != AGGREGATE_NONE)
									->setAttribute('placeholder', GRAPH_AGGREGATE_DEFAULT_INTERVAL)
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
						->addItem([
							new CLabel(_('Aggregate')),
							new CFormField(
								(new CRadioButtonList($field_name.'['.$row_num.'][aggregate_grouping]',
									(int) $value['aggregate_grouping'])
								)
									->addValue(_('Each item'), GRAPH_AGGREGATE_BY_ITEM)
									->addValue(_('Data set'), GRAPH_AGGREGATE_BY_DATASET)
									->setEnabled($value['aggregate_function'] != AGGREGATE_NONE)
									->setModern(true)
							)
						])
						->addItem([
							new CLabel(_('Approximation'),
								'label-'.$field_name.'_'.$row_num.'_approximation'
							),
							new CFormField(
								(new CSelect($field_name.'['.$row_num.'][approximation]'))
									->setId($field_name.'_'.$row_num.'_approximation')
									->setFocusableElementId('label-'.$field_name.'_'.$row_num.'_approximation')
									->setValue((int) $value['approximation'])
									->addOptions(CSelect::createOptionsFromArray([
										APPROXIMATION_ALL => [
											'label' => _('all'),
											'disabled' => ($value['type'] != SVG_GRAPH_TYPE_LINE || (bool) $value['stacked'])
										],
										APPROXIMATION_MIN => _('min'),
										APPROXIMATION_AVG => _('avg'),
										APPROXIMATION_MAX => _('max')
									]))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
						])
				])
		]))
			->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM)
			->addClass(ZBX_STYLE_SORTABLE_ITEM)
			->addClass($is_opened ? ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED : ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED)
			->setAttribute('data-set', $row_num)
			->setAttribute('data-type', $dataset_type);
	}

	/**
	 * Return template used by dynamic rows in CWidgetFieldGraphDataSet field.
	 *
	 * @param CWidgetFieldGraphDataSet $field
	 * @param string                   $form_name     Form name in which data set field resides.
	 * @param int                      $dataset_type
	 *
	 * @return string
	 */
	public static function getGraphDataSetTemplate($field, $form_name, int $dataset_type) {
		$value = ['color' => '#{color}'] +  CWidgetFieldGraphDataSet::getDefaults();

		return self::getGraphDataSetLayout($field->getName(), $value, $form_name, '#{rowNum}', true, $dataset_type)->toString();
	}

	/**
	 * @param CWidgetFieldGraphDataSet $field
	 *
	 * @return CList
	 */
	public static function getGraphDataSet($field, $form_name) {
		$list = (new CList())
			->setId('data_sets')
			->addClass(ZBX_STYLE_SORTABLE_LIST);

		$values = $field->getValue();

		if (!$values) {
			$values[] = CWidgetFieldGraphDataSet::getDefaults();
		}

		// Get item names for single item datasets.
		$itemids = array_merge(...array_column($values, 'itemids'));
		if ($itemids) {
			$names = self::getItemNames($itemids);
		}

		foreach ($values as $i => $value) {
			if ($value['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				$value['item_names'] = $names;
			}

			$list->addItem(
				self::getGraphDataSetLayout($field->getName(), $value, $form_name, $i, $i == 0, $value['dataset_type'])
			);
		}

		return $list;
	}

	public static function getGraphDataSetFooter() {
		return (new CList())
			->addClass(ZBX_STYLE_BTN_SPLIT)
			->addItem([
				(new CButton(null, [
					(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON),
					_('Add new data set')
				]))
					->setId('dataset-add')
					->addClass(ZBX_STYLE_BTN_ALT),
				(new CButton(null, '&#8203;'))
					->setId('dataset-menu')
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON)
			]);
	}

	private static function getItemNames(array $itemids): array {
		$names = [];

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name'],
			'selectHosts' => ['hostid', 'name'],
			'webitems' => true,
			'itemids' => $itemids,
			'preservekeys' => true
		]);

		if (!$items) {
			return $names;
		}

		foreach ($items as $item) {
			$hosts = array_column($item['hosts'], 'name', 'hostid');
			$names[$item['itemid']] = $hosts[$item['hostid']].NAME_DELIMITER.$item['name'];
		}

		return $names;
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
