<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	 * @param int $view_mode (ZBX_WIDGET_VIEW_MODE_NORMAL|ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
	 * @param array $known_widget_types
	 * @param CWidgetFieldComboBox $field_rf_rate
	 *
	 * @return CFormList
	 */
	public static function createFormList($dialogue_name, $type, $view_mode, $known_widget_types, $field_rf_rate) {
		return (new CFormList())
			->addRow((new CLabel(_('Type'), 'type')), [
					(new CComboBox('type', $type, 'updateWidgetConfigDialogue()', $known_widget_types)),
					(new CDiv((new CCheckBox('show_header'))
						->setLabel(_('Show header'))
						->setLabelPosition(CCheckBox::LABEL_POSITION_LEFT)
						->setId('show_header')
						->setChecked($view_mode == ZBX_WIDGET_VIEW_MODE_NORMAL)
					))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
				]
			)
			->addRow(_('Name'),
				(new CTextBox('name', $dialogue_name))
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
		$combo_box = (new CComboBox($field->getName(), $field->getValue(), $field->getAction(), $field->getValues()))
			->setAriaRequired(self::isAriaRequired($field))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED));

		return $combo_box;
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
	 * @return CDiv
	 */
	public static function getHostPatternSelect($field, $form_name) {
		return (new CPatternSelect([
			'name' => $field->getName().'[]',
			'object_name' => 'hosts',
			'data' => $field->getValue(),
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
			->setAttribute('placeholder', $field->getPlaceholder())
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
					'real_hosts' => true,
					'enrich_parent_groups' => true
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
	 * @param CWidgetFieldIntegerBox $field
	 *
	 * @return CNumericBox
	 */
	public static function getIntegerBox($field) {
		return (new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength()))
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
	 * @param array $config
	 *
	 * @return CList
	 */
	public static function getSeverities($field, $config) {
		$severities = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

		if ($field->getOrientation() == CWidgetFieldSeverities::ORIENTATION_HORIZONTAL) {
			$severities->addClass(ZBX_STYLE_HOR_LIST);
		}

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severities->addItem(
				(new CCheckBox($field->getName().'[]', $severity))
					->setLabel(getSeverityName($severity, $config))
					->setId($field->getName().'_'.$severity)
					->setChecked(in_array($severity, $field->getValue()))
					->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
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
		$enabled = !($field->getFlags() & CWidgetField::FLAG_DISABLED);
		$i = 0;

		foreach ($tags as $tag) {
			$tags_table->addRow([
				(new CTextBox($field->getName().'['.$i.'][tag]', $tag['tag']))
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field))
					->setEnabled($enabled),
				(new CRadioButtonList($field->getName().'['.$i.'][operator]', (int) $tag['operator']))
					->addValue(_('Contains'), TAG_OPERATOR_LIKE)
					->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
					->setModern(true)
					->setEnabled($enabled),
				(new CTextBox($field->getName().'['.$i.'][value]', $tag['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAriaRequired(self::isAriaRequired($field))
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
			(new CRadioButtonList($field->getName().'[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Contains'), TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
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
	 * @param CWidgetFieldDatePicker $field
	 *
	 * @return CDateSelector
	 */
	public static function getDatePicker($field) {
		return (new CDateSelector($field->getName(), $field->getValue()))
			->setAriaRequired(self::isAriaRequired($field))
			->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED));
	}

	/**
	 * @param CWidgetFieldApplication $field
	 *
	 * @return array
	 */
	public static function getApplicationSelector($field) {
		return [
			(new CTextBox($field->getName(), $field->getValue()))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(self::isAriaRequired($field))
				->addClass('simple-textbox'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton($field->getName().'_select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick(
					'return PopUp("popup.generic", '.CJs::encodeJson($field->getFilterParameters()).', null, this);'
				)
		];
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
	 * @return array
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
							->setAttribute('placeholder', _('host pattern'))
							->setAriaRequired(self::isAriaRequired($field))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->addClass(ZBX_STYLE_COLUMN_50),
					(new CDiv(
						(new CPatternSelect([
							'name' => $field->getName().'['.$row_num.'][items][]',
							'object_name' => 'items',
							'data' => $value['items'],
							'multiple' => true,
							'popup' => [
								'parameters' => [
									'srctbl' => 'items',
									'srcfld1' => 'itemid',
									'real_hosts' => 1,
									'numeric' => 1,
									'webitems' => 1,
									'orig_names' => 1,
									'dstfrm' => $form_name,
									'dstfld1' => zbx_formatDomId($field->getName().'['.$row_num.'][items][]')
								]
							],
							'add_post_js' => false
						]))
							->setEnabled(!($field->getFlags() & CWidgetField::FLAG_DISABLED))
							->setAttribute('placeholder', _('host pattern'))
							->setAriaRequired(self::isAriaRequired($field))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->addClass(ZBX_STYLE_COLUMN_50)
				]))
					->addClass(ZBX_STYLE_COLUMN_95)
					->addClass(ZBX_STYLE_COLUMNS),

				(new CDiv(
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_REMOVE_BTN)
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
		$list = (new CList())
			->addClass(ZBX_STYLE_OVERRIDES_LIST)
			->setId('overrides');

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
						['name' => _('Base colour'), 'callback' => 'addOverride', 'args' => ['color', '']],

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
	 * @param string                    $form_name  Form name in which override field is located.
	 *
	 * @return string
	 */
	public static function getGraphOverrideJavascript($field, $form_name) {
		$scripts = [
			// Define it as function to avoid redundancy.
			'function initializeOverrides() {'.
				'jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_OPTIONS_LIST.'").overrides({'.
					'add: ".'.ZBX_STYLE_BTN_ALT.'",'.
					'options: "input[type=hidden]",'.
					'captions: '.CJs::encodeJson(self::getGraphOverrideOptionNames()).','.
					'makeName: function(option, row_id) {'.
						'return "'.$field->getName().'[" + row_id + "][" + option + "]";'.
					'},'.
					'makeOption: function(name) {'.
						'return name.match('.
							'/.*\[('.implode('|', CWidgetFieldGraphOverride::getOverrideOptions()).')\]/'.
						')[1];'.
					'},'.
					'override: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",'.
					'overridesList: ".'.ZBX_STYLE_OVERRIDES_LIST.'",'.
					'onUpdate: onGraphConfigChange,'.
					'menu: '.CJs::encodeJson(self::getGraphOverrideMenu()).
				'});'.
			'}',

			// Initialize dynamicRows.
			'jQuery("#overrides")'.
				'.dynamicRows({'.
					'template: "#overrides-row",'.
					'beforeRow: ".overrides-foot",'.
					'remove: ".'.ZBX_STYLE_REMOVE_BTN.'",'.
					'add: "#override-add",'.
					'row: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'"'.
				'})'.
				'.bind("afteradd.dynamicRows", function(event, options) {'.
					'var container = jQuery(".overlay-dialogue-body");'.
					'container.scrollTop(container[0].scrollHeight);'.

					'jQuery(".multiselect", jQuery("#overrides")).each(function() {'.
						'jQuery(this).multiSelect(jQuery(this).data("params"));'.
					'});'.
					'updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");'.
					'onGraphConfigChange();'.
				'})'.
				'.bind("afterremove.dynamicRows", function(event, options) {'.
					'updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");'.
					'onGraphConfigChange();'.
				'})'.
				'.bind("tableupdate.dynamicRows", function(event, options) {'.
					'updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");'.
					'initializeOverrides();'.
					'if (jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length > 1) {'.
						'jQuery("#overrides .drag-icon").removeClass("disabled");'.
						'jQuery("#overrides").sortable("enable");'.
					'}'.
					'else {'.
						'jQuery("#overrides .drag-icon").addClass("disabled");'.
						'jQuery("#overrides").sortable("disable");'.
					'}'.
				'});',

			// Initialize overrides UI control.
			'initializeOverrides();',

			// Initialize override pattern-selectors.
			'jQuery(".multiselect", jQuery("#overrides")).each(function() {'.
				'jQuery(this).multiSelect(jQuery(this).data("params"));'.
			'});',

			// Make overrides sortable.
			'if (jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length < 2) {'.
				'jQuery("#overrides .drag-icon").addClass("disabled");'.
			'}'.
			'jQuery("#overrides").sortable({'.
				'items: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",'.
				'containment: "parent",'.
				'handle: ".drag-icon",'.
				'tolerance: "pointer",'.
				'scroll: false,'.
				'cursor: IE ? "move" : "grabbing",'.
				'opacity: 0.6,'.
				'axis: "y",'.
				'disabled: function() {'.
					'return jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length < 2;'.
				'}(),'.
				'start: function() {'. // Workaround to fix wrong scrolling at initial sort.
					'jQuery(this).sortable("refreshPositions");'.
				'},'.
				'stop: onGraphConfigChange,'.
				'update: function() {'.
					'updateVariableOrder(jQuery("#overrides"), ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'", "or");'.
				'}'.
			'});'
		];

		return implode ('', $scripts);
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
	private static function getGraphDataSetLayout($field_name, array $value, $form_name, $row_num, $is_opened) {
		return (new CListItem([
			// Accordion head - data set selection fields and tools.
			(new CDiv([
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),
				(new CDiv([
					(new CDiv([
						(new CButton())
							->addClass(ZBX_STYLE_COLOR_PREVIEW_BOX)
							->addStyle('background-color: #'.$value['color'].';')
							->setAttribute('title', $is_opened ? _('Collapse') : _('Expand')),
						(new CPatternSelect([
							'name' => $field_name.'['.$row_num.'][hosts][]',
							'object_name' => 'hosts',
							'data' => $value['hosts'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'hosts',
									'srcfld1' => 'host',
									'dstfrm' => $form_name,
									'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][hosts][]')
								]
							],
							'add_post_js' => false
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					]))->addClass(ZBX_STYLE_COLUMN_50),
					(new CDiv(
						(new CPatternSelect([
							'name' => $field_name.'['.$row_num.'][items][]',
							'object_name' => 'items',
							'data' => $value['items'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'items',
									'srcfld1' => 'name',
									'real_hosts' => 1,
									'numeric' => 1,
									'webitems' => 1,
									'orig_names' => 1,
									'dstfrm' => $form_name,
									'dstfld1' => zbx_formatDomId($field_name.'['.$row_num.'][items][]')
								]
							],
							'add_post_js' => false
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->addClass(ZBX_STYLE_COLUMN_50),
				]))
					->addClass(ZBX_STYLE_COLUMN_95)
					->addClass(ZBX_STYLE_COLUMNS),
				(new CDiv([
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_REMOVE_BTN)
				]))->addClass(ZBX_STYLE_COLUMN_5)
			]))
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD)
				->addClass(ZBX_STYLE_COLUMNS),

			// Accordion body - data set configuration options.
			(new CDiv(
				(new CDiv([
					// Left column fields.
					(new CDiv(
						(new CFormList())
							->addRow(_('Base colour'),
								(new CColor($field_name.'['.$row_num.'][color]', $value['color']))
									->appendColorPickerJs(false)
							)
							->addRow(_('Draw'),
								(new CRadioButtonList($field_name.'['.$row_num.'][type]', (int) $value['type']))
									->addValue(_('Line'), SVG_GRAPH_TYPE_LINE)
									->addValue(_('Points'), SVG_GRAPH_TYPE_POINTS)
									->addValue(_('Staircase'), SVG_GRAPH_TYPE_STAIRCASE)
									->addValue(_('Bar'), SVG_GRAPH_TYPE_BAR)
									->onChange('changeDataSetDrawType(this)')
									->setModern(true)
							)
							->addRow(_('Width'),
								(new CRangeControl($field_name.'['.$row_num.'][width]', (int) $value['width']))
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
							->addRow(_('Point size'),
								(new CRangeControl($field_name.'['.$row_num.'][pointsize]', (int) $value['pointsize']))
									->setEnabled($value['type'] == SVG_GRAPH_TYPE_POINTS)
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(1)
									->setMax(10)
							)
							->addRow(_('Transparency'),
								(new CRangeControl($field_name.'['.$row_num.'][transparency]',
										(int) $value['transparency'])
									)
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
							->addRow(_('Fill'),
								(new CRangeControl($field_name.'['.$row_num.'][fill]', (int) $value['fill']))
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
						)
					)
						->addClass(ZBX_STYLE_COLUMN_50),

					// Right column fields.
					(new CDiv(
						(new CFormList())
							->addRow(_('Missing data'),
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
									->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_BAR]))
									->setModern(true)
							)
							->addRow(_('Y-axis'),
								(new CRadioButtonList($field_name.'['.$row_num.'][axisy]', (int) $value['axisy']))
									->addValue(_('Left'), GRAPH_YAXIS_SIDE_LEFT)
									->addValue(_('Right'), GRAPH_YAXIS_SIDE_RIGHT)
									->setModern(true)
							)
							->addRow(_('Time shift'),
								(new CTextBox($field_name.'['.$row_num.'][timeshift]', $value['timeshift']))
									->setAttribute('placeholder', _('none'))
									->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
							)
					))
						->addClass(ZBX_STYLE_COLUMN_50),
				]))
					->addClass(ZBX_STYLE_COLUMNS)
					->addClass(ZBX_STYLE_COLUMN_95)
			))
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_BODY)
				->addClass(ZBX_STYLE_COLUMNS)
		]))
			->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM)
			->addClass($is_opened ? ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED : ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED);
	}

	/**
	 * Return template used by dynamic rows in CWidgetFieldGraphDataSet field.
	 *
	 * @param CWidgetFieldGraphDataSet $field
	 * @param string                   $form_name   Form name in which data set field resides.
	 *
	 * @return string
	 */
	public static function getGraphDataSetTemplate($field, $form_name) {
		$value = ['color' => '#{color}'] + CWidgetFieldGraphDataSet::getDefaults();

		return self::getGraphDataSetLayout($field->getName(), $value, $form_name, '#{rowNum}', true)->toString();
	}

	/**
	 * @param CWidgetFieldGraphDataSet $field
	 *
	 * @return CList
	 */
	public static function getGraphDataSet($field, $form_name) {
		$list = (new CList())
			->addClass(ZBX_STYLE_LIST_VERTICAL_ACCORDION)
			->setId('data_sets');

		$values = $field->getValue();

		if (!$values) {
			$values[] = [];
		}

		$i = 0;

		foreach ($values as $value) {
			// Take default values for missing fields. This can happen if particular field is disabled.
			$value += CWidgetFieldGraphDataSet::getDefaults();

			$list->addItem(self::getGraphDataSetLayout($field->getName(), $value, $form_name, $i, $i == 0));

			$i++;
		}

		// Add 'Add' button under accordion.
		$list->addItem(
			(new CDiv(
				(new CButton('data_sets_add', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add new data set')]))
					->addClass(ZBX_STYLE_BTN_ALT)
					->setId('dataset-add')
			)),
			ZBX_STYLE_LIST_ACCORDION_FOOT
		);

		return $list;
	}

	/**
	 * Return javascript necessary to initialize CWidgetFieldGraphDataSet field.
	 *
	 * @return string
	 */
	public static function getGraphDataSetJavascript() {
		$scripts = [
			'function changeDataSetDrawType(obj) {'.
				'var row_num = obj.id.replace("ds_", "").replace("_type", "");'.
				'switch (jQuery(":checked", jQuery(obj)).val()) {'.
					'case "'.SVG_GRAPH_TYPE_POINTS.'":'.
						'jQuery("#ds_" + row_num + "_width").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_pointsize").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_fill").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", true);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", true);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", true);'.
						'break;'.
					'case "'.SVG_GRAPH_TYPE_BAR.'":'.
						'jQuery("#ds_" + row_num + "_width").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_pointsize").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_fill").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", true);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", true);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", true);'.
						'break;'.
					'default:'.
						'jQuery("#ds_" + row_num + "_width").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_pointsize").rangeControl("disable");'.
						'jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_fill").rangeControl("enable");'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", false);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", false);'.
						'jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", false);'.
						'break;'.
				'}'.
			'};',

			// Initialize dynamic rows.
			'jQuery("#data_sets")'.
				'.dynamicRows({'.
					'template: "#dataset-row",'.
					'beforeRow: ".'.ZBX_STYLE_LIST_ACCORDION_FOOT.'",'.
					'remove: ".'.ZBX_STYLE_REMOVE_BTN.'",'.
					'add: "#dataset-add",'.
					'row: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
					'dataCallback: function(data) {'.
						'data.color = function(num) {'.
							'var palete = '.CWidgetFieldGraphDataSet::DEFAULT_COLOR_PALETE.';'.
							'return palete[num % palete.length];'.
						'} (data.rowNum);'.
						'return data;'.
					'}'.
				'})'.
				'.bind("beforeadd.dynamicRows", function(event, options) {'.
					'jQuery("#data_sets").zbx_vertical_accordion("collapseAll");'.
				'})'.
				'.bind("afteradd.dynamicRows", function(event, options) {'.
					'var container = jQuery(".overlay-dialogue-body");'.
					'container.scrollTop(container[0].scrollHeight);'.

					'jQuery(".input-color-picker input").colorpicker({onUpdate: function(color) {'.
						'var ds = jQuery(this).closest(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'");'.
						'jQuery(".'.ZBX_STYLE_COLOR_PREVIEW_BOX.'", ds).css("background-color", "#"+color);'.
					'}, appendTo: "#overlay_dialogue"});'.

					'jQuery(".multiselect", jQuery("#data_sets")).each(function() {'.
						'jQuery(this).multiSelect(jQuery(this).data("params"));'.
					'});'.
					'updateVariableOrder(jQuery("#data_sets"), ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'", "ds");'.
					'onGraphConfigChange();'.
				'})'.
				'.bind("afterremove.dynamicRows", function(event, options) {'.
					'updateVariableOrder(jQuery("#data_sets"), ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'", "ds");'.
					'onGraphConfigChange();'.
				'})'.
				'.bind("tableupdate.dynamicRows", function(event, options) {'.
					'updateVariableOrder(jQuery("#data_sets"), ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'", "ds");'.
					'jQuery(".'.CRangeControl::ZBX_STYLE_CLASS.'[data-options]").rangeControl();'.
					'if (jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length > 1) {'.
						'jQuery("#data_sets .drag-icon").removeClass("disabled");'.
						'jQuery("#data_sets").sortable("enable");'.
					'}'.
					'else {'.
						'jQuery("#data_sets .drag-icon").addClass("disabled");'.
						'jQuery("#data_sets").sortable("disable");'.
					'}'.
				'});',

			// Intialize vertical accordion.
			'jQuery("#data_sets").zbx_vertical_accordion({'.
				'handler: ".'.ZBX_STYLE_COLOR_PREVIEW_BOX.'"'.
			'});',

			// Initialize rangeControl UI elements.
			'jQuery(".'.CRangeControl::ZBX_STYLE_CLASS.'", jQuery("#data_sets")).rangeControl();',

			// Expand dataset when click in pattern fields.
			'jQuery("#data_sets").on("click", "'.implode(', ', [
				'.'.ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED.' .'.CPatternSelect::ZBX_STYLE_CLASS,
				'.'.ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED.' .'.ZBX_STYLE_BTN_GREY
			]).'", function() {'.
				'var index = jQuery(this).closest(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").index();'.
				'jQuery("#data_sets").zbx_vertical_accordion("expandNth", index);'.
			'});',

			// Initialize pattern fields.
			'jQuery(".multiselect", jQuery("#data_sets")).each(function() {'.
				'jQuery(this).multiSelect(jQuery(this).data("params"));'.
			'});',

			// Initialize color-picker UI elements.
			'jQuery(".input-color-picker input").colorpicker({onUpdate: function(color){'.
				'var ds = jQuery(this).closest(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'");'.
				'jQuery(".'.ZBX_STYLE_COLOR_PREVIEW_BOX.'", ds).css("background-color", "#"+color);'.
			'}, appendTo: "#overlay_dialogue"});',

			// Initialize sortability.
			'if (jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length < 2) {'.
				'jQuery("#data_sets .drag-icon").addClass("disabled");'.
			'}'.
			'jQuery("#data_sets").sortable({'.
				'items: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
				'containment: "parent",'.
				'handle: ".drag-icon",'.
				'tolerance: "pointer",'.
				'scroll: false,'.
				'cursor: IE ? "move" : "grabbing",'.
				'opacity: 0.6,'.
				'axis: "y",'.
				'disabled: function() {'.
					'return jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length < 2;'.
				'}(),'.
				'start: function() {'. // Workaround to fix wrong scrolling at initial sort.
					'jQuery(this).sortable("refreshPositions");'.
				'},'.
				'stop: onGraphConfigChange,'.
				'update: function() {'.
					'updateVariableOrder(jQuery("#data_sets"), ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'", "ds");'.
				'}'.
			'});'
		];

		return implode ('', $scripts);
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
