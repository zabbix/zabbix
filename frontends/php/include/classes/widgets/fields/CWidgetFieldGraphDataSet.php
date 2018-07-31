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


class CWidgetFieldGraphDataSet extends CWidgetField {

	public $color_palete;

	/**
	 * Create widget field for Data set selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'fields' => [
			'hosts'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'items'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'color'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 6],
			'type'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE])],
			'width'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'radius'			=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(1, 10))],
			'transparency'		=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(0, 10))],
			'fill'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(0, 10))],
			'axisy'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
			'timeshift'			=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'missingdatafunc'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO])],
			'order'				=> ['type' => API_INT32, 'flags' => API_REQUIRED]
		]]);
		$this->setFlags(parent::FLAG_NOT_EMPTY);
		$this->setDefault([]);

		// Specify color palete for data-set colors.
		$this->color_palete = [
			'ff465c','b0af07','0ec9ac','524bbc','ed1248','d1e754','2ab5ff','385cc7','ec1594','bae37d',
			'6ac8ff','ee2b29','3ca20d','6f4bbc','00a1ff','f3601b','1cae59','45cfdb','894bbc','6d6d6d'
		];
	}

	public function getDefault() {
		return [
			'hosts' => '',
			'items' => '',
			'color' => $this->color_palete[0],
			'type' => SVG_GRAPH_TYPE_LINE,
			'width' => 1,
			'radius' => 3,
			'transparency' => 5,
			'fill' => 3,
			'axisy' => GRAPH_YAXIS_SIDE_LEFT,
			'timeshift' => '',
			'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE
		];
	}

	public function getFieldLayout($value, $options) {
		$fn = $this->getName();

		// Take default values for missing fields. This can happen if particular field is disabled.
		$value = array_merge($this->getDefault(), $value);

		return [
			// Accordion head - data set selection fields and tools.
			(new CDiv([
				(new CVar($fn.'['.$options['row_num'].'][order]', $options['order_num'])),
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),
				(new CDiv([
					(new CDiv([
						(new CDiv($options['letter_id']))
							->addClass(ZBX_STYLE_COLOR_PREVIEW_BOX)
							->addStyle('background-color: #'.$value['color'].';'),
						(new CTextBox($fn.'['.$options['row_num'].'][hosts]', $value['hosts']))
							->setAttribute('placeholder', _('(hosts pattern)'))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->addClass(ZBX_STYLE_PATTERNSELECT),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick('return PopUp("popup.generic", '.
								CJs::encodeJson([
									'srctbl' => 'hosts',
									'srcfld1' => 'host',
									'reference' => 'name',
									'multiselect' => 1,
									'dstfrm' => $options['form_name'],
									'dstfld1' => $fn.'['.$options['row_num'].'][hosts]'
								]).', null, this);'
							)
					]))->addClass(ZBX_STYLE_COLUMN_50),
					(new CDiv([
						(new CTextBox($fn.'['.$options['row_num'].'][items]', $value['items']))
							->setAttribute('placeholder', _('(items pattern)'))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->addClass(ZBX_STYLE_PATTERNSELECT),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick('return PopUp("popup.generic", '.
								CJs::encodeJson([
									'srctbl' => 'items',
									'srcfld1' => 'name',
									'reference' => 'name_expanded',
									'multiselect' => 1,
									'dstfrm' => $options['form_name'],
									'dstfld1' => $fn.'['.$options['row_num'].'][items]',
								]).', null, this);'
							)
					]))->addClass(ZBX_STYLE_COLUMN_50),
				]))
					->addClass(ZBX_STYLE_COLUMN_95)
					->addClass(ZBX_STYLE_COLUMNS),
				(new CDiv([
					(new CButton())
						->setAttribute('title', $options['is_opened'] ? _('Collapse') : _('Expand'))
						->addClass(ZBX_STYLE_BTN_GEAR),
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_BTN_TRASH)
				]))
					->addStyle('margin-left: -15px;')
					->addClass(ZBX_STYLE_COLUMN_5)
			]))
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD)
				->addClass(ZBX_STYLE_COLUMNS),

			// Accordion body - data set configuration options.
			(new CDiv(
				(new CDiv([
					// Left column fields.
					(new CDiv(
						(new CFormList())
							->addRow(_('Base color'),
								// Replaced to simple input field, because we don't have usable color picker now.
								(new CTextBox($fn.'['.$options['row_num'].'][color]', $value['color'], false, 6))
								//(new CColor($fn.'['.$options['row_num'].'][color]', $value['color']))
								//	->appendColorPickerJs(false)
							)
							->addRow(_('Draw'),
								(new CRadioButtonList($fn.'['.$options['row_num'].'][type]', (int) $value['type']))
									->addValue(_('Line'), SVG_GRAPH_TYPE_LINE)
									->addValue(_('Points'), SVG_GRAPH_TYPE_POINTS)
									->addValue(_('Staircase'), SVG_GRAPH_TYPE_STAIRCASE)
									->onChange(
										'var row_num = this.id.replace("'.$fn.'_","").replace("_type","");'.
										'switch (jQuery(":checked", jQuery(this)).val()) {'.
											'case "'.SVG_GRAPH_TYPE_LINE.'":'.
												'jQuery("[name=\"ds["+row_num+"][width]\"]").closest("li").show();'.
												'jQuery("[name=\"ds["+row_num+"][width]\"]").rangeControl("enable");'.
												'jQuery("[name=\"ds["+row_num+"][radius]\"]").rangeControl("disable");'.
												'break;'.
											'case "'.SVG_GRAPH_TYPE_POINTS.'":'.
												//'jQuery("[name=\"ds["+row_num+"][width]\"]").rangeControl("disable");'.
												'jQuery("[name=\"ds["+row_num+"][width]\"]").closest("li").hide();'.
												'jQuery("[name=\"ds["+row_num+"][radius]\"]").rangeControl("enable");'.
												'break;'.
											'case "'.SVG_GRAPH_TYPE_STAIRCASE.'":'.
												'jQuery("[name=\"ds["+row_num+"][width]\"]").rangeControl("enable");'.
												'jQuery("[name=\"ds["+row_num+"][radius]\"]").rangeControl("disable");'.
												'break;'.
										'}'
									)
									->setModern(true)
							)
							->addRow(_('Width'),
								(new CRangeControl($fn.'['.$options['row_num'].'][width]', (int) $value['width']))
									->setEnabled($value['type'] != SVG_GRAPH_TYPE_POINTS)
									->addClass('range-control')
									->setAttribute('maxlength', 2)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
							->addRow(_('Radius'),
								(new CRangeControl($fn.'['.$options['row_num'].'][radius]', (int) $value['radius']))
									->setEnabled($value['type'] == SVG_GRAPH_TYPE_POINTS)
									->addClass('range-control')
									->setAttribute('maxlength', 2)
									->setStep(1)
									->setMin(1)
									->setMax(10)
							)
							->addRow(_('Transparency'),
								(new CRangeControl($fn.'['.$options['row_num'].'][transparency]', (int) $value['transparency']))
									->addClass('range-control')
									->setAttribute('maxlength', 2)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
							->addRow(_('Fill'),
								(new CRangeControl($fn.'['.$options['row_num'].'][fill]', (int) $value['fill']))
									->addClass('range-control')
									->setAttribute('maxlength', 2)
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
								(new CRadioButtonList($fn.'['.$options['row_num'].'][missingdatafunc]', (int) $value['missingdatafunc']))
									->addValue(_('None'), SVG_GRAPH_MISSING_DATA_NONE)
									->addValue(_x('Connected', 'missing data function'), SVG_GRAPH_MISSING_DATA_CONNECTED)
									->addValue(_x('Threat as 0', 'missing data function'), SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO)
									->setModern(true)
							)
							->addRow(_('Y-axis'),
								(new CRadioButtonList($fn.'['.$options['row_num'].'][axisy]', (int) $value['axisy']))
									->addValue(_('Left'), GRAPH_YAXIS_SIDE_LEFT)
									->addValue(_('Right'), GRAPH_YAXIS_SIDE_RIGHT)
									->setModern(true)
							)
							->addRow(_('Time shift'),
								(new CTextBox($fn.'['.$options['row_num'].'][timeshift]', $value['timeshift']))
									->setAttribute('placeholder', _('(none)'))
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
		];
	}

	public function setValue($value) {
		$this->value = (array) $value;

		// Sort data sets according order field.
		CArrayHelper::sort($this->value, [['field' => 'order', 'order' => ZBX_SORT_UP]]);

		foreach ($this->value as $index => $val) {
			/**
			 * Host pattern, item pattern and color are all mandatory fields.
			 *
			 * Data sets with unspecified host pattern and item pattern are deleted. If at least one is specified, error
			 * message tells that both fields are mandatory.
			 *
			 * Color is not validated here, because it makes wrong error message later (e.g., if color is not specified,
			 * error message says that data set is empty).
			 */
			if ((!array_key_exists('hosts', $val) || $val['hosts'] === '')
					&& (!array_key_exists('items', $val) || $val['items'] === '')) {
				unset($this->value[$index]);
			}
		}

		return $this;
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);
		$values = $this->getValue();

		// At least on data set is mandatory.
		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && !$values) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Data set'), _('cannot be empty'));
		}

		// Validate host and item pattern fields.
		if (!$errors && $strict) {
			foreach ($values as $val) {
				if (!array_key_exists('hosts', $val) || $val['hosts'] === '') {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Hosts pattern'), _('cannot be empty'));
				}
				elseif (!array_key_exists('items', $val) || $val['items'] === '') {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Items pattern'), _('cannot be empty'));
				}
			}
		}

		// Validate timeshift values.
		if (!$errors && $strict) {
			foreach ($values as $val) {
				if (array_key_exists('timeshift', $val) && $val['timeshift'] !== ''
						&& timeUnitToSeconds($val['timeshift'], true) === null) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Time shift'), _('a time unit is expected'));
				}
			}
		}

		// Validate color.
		if (!$errors && $strict) {
			$color_validator = new CColorValidator();

			foreach ($values as $val) {
				if (!array_key_exists('color', $val) || !$color_validator->validate($val['color'])) {
					$errors[] = _s('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).',
						$val['color']);
				}
			}
		}

		return $errors;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions.
	 * Reference is needed here to avoid array merging in CWidgetForm::fieldsToApi method. With large number of widget
	 * fields it causes significant performance decrease.
	 *
	 * @param array $widget_fields   reference to Array of widget fields.
	 */
	public function toApi(array &$widget_fields = []) {
		$value = $this->getValue();

		foreach ($value as $index => $val) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_STR,
				'name' => $this->name.'.hosts.'.$index,
				'value' => $val['hosts']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_STR,
				'name' => $this->name.'.items.'.$index,
				'value' => $val['items']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_STR,
				'name' => $this->name.'.color.'.$index,
				'value' => $val['color']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.type.'.$index,
				'value' => $val['type']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.transparency.'.$index,
				'value' => $val['transparency']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.fill.'.$index,
				'value' => $val['fill']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.axisy.'.$index,
				'value' => $val['axisy']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_STR,
				'name' => $this->name.'.timeshift.'.$index,
				'value' => $val['timeshift']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.missingdatafunc.'.$index,
				'value' => $val['missingdatafunc']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.order.'.$index,
				'value' => $val['order']
			];

			if (array_key_exists('width', $val)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.width.'.$index,
					'value' => $val['width']
				];
			}
			if (array_key_exists('radius', $val)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.radius.'.$index,
					'value' => $val['radius']
				];
			}
		}
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @return string
	 */
	public function getJavascript($form_name) {
		$scripts = [
			// Initialize dynamic rows.
			'jQuery("#data_sets")'.
				'.dynamicRows({'.
					'template: "#dataset-row",'.
					'beforeRow: ".'.ZBX_STYLE_LIST_ACCORDION_FOOT.'",'.
					'remove: ".'.ZBX_STYLE_BTN_TRASH.'",'.
					'add: "#dataset-add",'.
					'row: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
					'dataCallback: function(data) {'.
						'data.formulaId = num2letter(data.rowNum);'.
						'data.color= function(num) {'.
							'var palete = '.CJs::encodeJson($this->color_palete).';'.
							'return palete[num % palete.length];'.
						'}(data.rowNum);'.
						'data.orderNum = data.rowNum + 1;'.
						'return data;'.
					'}'.
				'})'.
				'.bind("beforeadd.dynamicRows", function(event, options) {'.
					'jQuery("#data_sets").zbx_vertical_accordion("collapseAll");'.
				'})'.
				'.bind("afteradd.dynamicRows", function(event, options) {'.
					'var container = jQuery(".overlay-dialogue-body");'.
					'container.scrollTop(container[0].scrollHeight);'.
				'})'.
				'.bind("tableupdate.dynamicRows", function(event, options) {'.
					'jQuery(".range-control[data-options]").rangeControl();'.
					'if (typeof updateGraphPreview === "function") {'.
						'updateGraphPreview();'.
					'}'.
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
			'jQuery("#data_sets").zbx_vertical_accordion({handler: ".'.ZBX_STYLE_BTN_GEAR.'"});',

			// Initialize rangeControl UI elements.
			'jQuery(".range-control").rangeControl();',

			// Initialize sortability.
			'if (jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length < 2) {'.
				'jQuery("#data_sets .drag-icon").addClass("disabled");'.
			'}'.
			'jQuery("#data_sets").sortable({'.
				'items: ".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'",'.
				'containment: "parent",'.
				'handle: ".drag-icon",'.
				'tolerance: "pointer",'.
				'cursor: "move",'.
				'opacity: 0.6,'.
				'axis: "y",'.
				'disabled: function() {'.
					'return jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length < 2;'.
				'},'.
				'update: function() {'.
					'jQuery("input[type=hidden]", jQuery("#data_sets")).filter(function() {'.
						'return jQuery(this).attr("name").match(/.*\[\d+\]\[order\]/);'.
					'}).each(function(i) {'.
						'jQuery(this).val(i + 1);'.
					'});'.
				'}'.
			'});'
		];

		return implode ('', $scripts);
	}

	/**
	 * Return template used by dynamic rows.
	 *
	 * @return string
	 */
	public function getTemplate($form_name) {
		return (new CListItem(
			$this->getFieldLayout(
				array_merge(
					$this->getDefault(),
					['color' => '#{color}']
				), [
					'row_num' => '#{rowNum}',
					'order_num' => '#{orderNum}',
					'letter_id' => '#{formulaId}',
					'form_name' => $form_name,
					'is_opened' => true
				]
			)
		))
			->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM)
			->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED)
			->toString();
	}
}
