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


/**
 * Class for data set widget field used in Graph widget configuration Data set tab.
 */
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
			'hosts'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'items'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'color'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 6],
			'type'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE])],
			'width'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'pointsize'			=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(1, 10))],
			'transparency'		=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(0, 10))],
			'fill'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'axisy'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
			'timeshift'			=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 10],
			'missingdatafunc'	=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO])],
			'order'				=> ['type' => API_INT32, 'flags' => API_REQUIRED]
		]]);
		$this->setFlags(parent::FLAG_NOT_EMPTY);
		$this->setDefault([]);

		/**
		 * Predefined colors for data-sets.
		 * Used in 2 ways:
		 * - each next data set takes next sequential value from palette;
		 * - validator looks if submitted color code is taken from this palette to determine when user has specified its
		 *   own color. When color is specified by user, it is assumed that data set is not in original state anymore
		 *   and validator will not erase this data set but requires to fill all mandatory fields.
		 */
		$this->color_palete = [
			'FF465C','B0AF07','0EC9AC','524BBC','ED1248','D1E754','2AB5FF','385CC7','EC1594','BAE37D',
			'6AC8FF','EE2B29','3CA20D','6F4BBC','00A1FF','F3601B','1CAE59','45CFDB','894BBC','6D6D6D'
		];
	}

	/**
	 * Default values filled in newly created data set or used as unspecified values.
	 *
	 * @return array
	 */
	public function getDefault() {
		return [
			'hosts' => '',
			'items' => '',
			'color' => $this->color_palete[0],
			'type' => SVG_GRAPH_TYPE_LINE,
			'width' => SVG_GRAPH_DEFAULT_WIDTH,
			'pointsize' => SVG_GRAPH_DEFAULT_POINTSIZE,
			'transparency' => SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'fill' => SVG_GRAPH_DEFAULT_FILL,
			'axisy' => GRAPH_YAXIS_SIDE_LEFT,
			'timeshift' => '',
			'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE
		];
	}

	/**
	 * Function returns array containing HTML objects filled with given values. Used to generate HTML row in widget
	 * data set field.
	 *
	 * @param array    $value              Values to fill in particular data set row. See self::setValue() for detailed
	 *                                     description.
	 * @param array    $options            Calculated options of particular override.
	 * @param int      $options[row_num]   Unique data set numeric identifier. Used to make unique field names.
	 * @param int      $options[order_num] Sequential order number.
	 * @param string   $options[form_name] Name of form in which data set fields resides.
	 * @param bool     $options[is_opened] Either accordion row is made opened or closed.
	 *
	 * @return array
	 */
	public function getFieldLayout(array $value, array $options) {
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
						(new CDiv())
							->addClass(ZBX_STYLE_COLOR_PREVIEW_BOX)
							->addStyle('background-color: #'.$value['color'].';')
							->setAttribute('title', $options['is_opened'] ? _('Collapse') : _('Expand')),
						(new CTextArea($fn.'['.$options['row_num'].'][hosts]', $value['hosts'], ['rows' => 1]))
							->setAttribute('placeholder', _('host pattern'))
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
						(new CTextArea($fn.'['.$options['row_num'].'][items]', $value['items'], ['rows' => 1]))
							->setAttribute('placeholder', _('item pattern'))
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->addClass(ZBX_STYLE_PATTERNSELECT),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton(null, _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
							->onClick('return PopUp("popup.generic", '.
								CJs::encodeJson([
									'srctbl' => 'items',
									'srcfld1' => 'itemid',
									'srcfld2' => 'name',
									'reference' => 'name_expanded',
									'multiselect' => 1,
									'real_hosts' => 1,
									'numeric' => 1,
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
					->addStyle('margin-left: -25px;')
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
								(new CColor($fn.'['.$options['row_num'].'][color]', $value['color']))
									->appendColorPickerJs(false)
							)
							->addRow(_('Draw'),
								(new CRadioButtonList($fn.'['.$options['row_num'].'][type]', (int) $value['type']))
									->addValue(_('Line'), SVG_GRAPH_TYPE_LINE)
									->addValue(_('Points'), SVG_GRAPH_TYPE_POINTS)
									->addValue(_('Staircase'), SVG_GRAPH_TYPE_STAIRCASE)
									->onChange(
										'var rnum = this.id.replace("'.$fn.'_","").replace("_type","");'.
										'if (jQuery(":checked", jQuery(this)).val() == "'.SVG_GRAPH_TYPE_POINTS.'") {'.
											'jQuery("#ds_"+rnum+"_width").rangeControl("disable");'.
											'jQuery("#ds_"+rnum+"_fill").rangeControl("disable");'.
											'jQuery("#ds_"+rnum+"_pointsize").rangeControl("enable");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_0").attr("disabled", "disabled");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_1").attr("disabled", "disabled");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_2").attr("disabled", "disabled");'.
										'}'.
										'else {'.
											'jQuery("[name=\"ds["+rnum+"][width]\"]").rangeControl("enable");'.
											'jQuery("[name=\"ds["+rnum+"][fill]\"]").rangeControl("enable");'.
											'jQuery("[name=\"ds["+rnum+"][pointsize]\"]").rangeControl("disable");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_0").removeAttr("disabled");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_1").removeAttr("disabled");'.
											'jQuery("#ds_"+rnum+"_missingdatafunc_2").removeAttr("disabled");'.
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
							->addRow(_('Point size'),
								(new CRangeControl($fn.'['.$options['row_num'].'][pointsize]',
										(int) $value['pointsize'])
									)
									->setEnabled($value['type'] == SVG_GRAPH_TYPE_POINTS)
									->addClass('range-control')
									->setAttribute('maxlength', 2)
									->setStep(1)
									->setMin(1)
									->setMax(10)
							)
							->addRow(_('Transparency'),
								(new CRangeControl($fn.'['.$options['row_num'].'][transparency]',
										(int) $value['transparency'])
									)
									->addClass('range-control')
									->setAttribute('maxlength', 2)
									->setStep(1)
									->setMin(0)
									->setMax(10)
							)
							->addRow(_('Fill'),
								(new CRangeControl($fn.'['.$options['row_num'].'][fill]', (int) $value['fill']))
									->setEnabled($value['type'] != SVG_GRAPH_TYPE_POINTS)
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
								(new CRadioButtonList($fn.'['.$options['row_num'].'][missingdatafunc]',
										(int) $value['missingdatafunc'])
									)
									->addValue(_('None'), SVG_GRAPH_MISSING_DATA_NONE)
									->addValue(_x('Connected', 'missing data function'),
										SVG_GRAPH_MISSING_DATA_CONNECTED
									)
									->addValue(_x('Treat as 0', 'missing data function'),
										SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO
									)
									->setEnabled($value['type'] != SVG_GRAPH_TYPE_POINTS)
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
									->setAttribute('placeholder', _('none'))
									->setAttribute('maxlength', 10)
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

	/**
	 * Set data set row values.
	 *
	 * @param array  $value                  Values filled in particular override.
	 * @param string $value[order]           Number by which overrides are sorted.
	 * @param string $value[hosts]           Host pattern.
	 * @param string $value[items]           Item pattern.
	 * @param string $value[color]           Data set color option.
	 * @param string $value[type]            Data set type option.
	 * @param string $value[width]           Data set width option.
	 * @param string $value[pointsize]       Data set pointsize option.
	 * @param string $value[transparency]    Data set transparency option.
	 * @param string $value[fill]            Data set fill option.
	 * @param string $value[axisy]           Data set axisy option.
	 * @param string $value[timeshift]       Data set timeshift option.
	 * @param string $value[missingdatafunc] Data set missingdatafunc option.
	 *
	 * @return object $this
	 */
	public function setValue($value) {
		$this->value = (array) $value;

		// Sort data sets according order field.
		CArrayHelper::sort($this->value, [['field' => 'order', 'order' => ZBX_SORT_UP]]);

		// Data sets with unchanged default values are removed. Color is changed if matches none of predefined colors.
		$defaults = $this->getDefault();
		foreach ($this->value as $index => $val) {
			// Values received from frontend are strings. Values received from database comes as arrays.
			$hosts = array_key_exists('hosts', $val)
				? (is_array($val['hosts']) ? implode(', ', $val['hosts']) : $val['hosts'])
				: '';
			$items = array_key_exists('items', $val)
				? (is_array($val['items']) ? implode(', ', $val['items']) : $val['items'])
				: '';

			if ($hosts === '' && $items === '' && in_array(strtoupper($val['color']), $this->color_palete)
					&& $defaults['type'] == $val['type'] && $defaults['transparency'] == $val['transparency']
					&& $defaults['axisy'] == $val['axisy'] && $defaults['timeshift'] == $val['timeshift']
					&& ($defaults['type'] != SVG_GRAPH_TYPE_POINTS
						&& $defaults['missingdatafunc'] == $val['missingdatafunc'])
					&& (($defaults['type'] != SVG_GRAPH_TYPE_POINTS && $defaults['width'] == $val['width']
							&& $defaults['fill'] == $val['fill'])
						|| ($defaults['type'] == SVG_GRAPH_TYPE_POINTS || $defaults['pointsize'] == $val['pointsize'])
					)) {
				unset($this->value[$index]);
			}
			else {
				$this->value[$index]['hosts'] = $hosts;
				$this->value[$index]['items'] = $items;
			}
		}

		return $this;
	}

	/**
	 * Function makes field specific validation for values set using self::setValue().
	 *
	 * @param  bool $strict    Either to make a strict validation.
	 *
	 * @return array $errors   List of errors found during validation.
	 */
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
					$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('host pattern'), _('Data set'),
						_('cannot be empty')
					);
					break;
				}
				elseif (!array_key_exists('items', $val) || $val['items'] === '') {
					$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('item pattern'), _('Data set'),
						_('cannot be empty')
					);
					break;
				}
			}
		}

		// Validate timeshift values.
		if (!$errors && $strict) {
			foreach ($values as $val) {
				if (array_key_exists('timeshift', $val) && $val['timeshift'] !== '') {
					$timeshift = timeUnitToSeconds($val['timeshift'], true);
					if ($timeshift === null // invalid
						|| bccomp(ZBX_MIN_TIMESHIFT, $timeshift) == 1 // exceeds min timeshift
						|| bccomp(ZBX_MAX_TIMESHIFT, $timeshift) == -1 // exceeds max timeshift
					) {
						$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('Time shift'),
							_('Data set'), _('a time unit is expected')
						);
					}
					break;
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
					break;
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
			// Hosts and items fields are stored as arrays to bypass length limit.
			foreach (CWidgetHelper::splitPatternIntoParts($val['hosts']) as $num => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.hosts.'.$index.'.'.$num,
					'value' => $pattern_item
				];
			}
			foreach (CWidgetHelper::splitPatternIntoParts($val['items']) as $num => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.items.'.$index.'.'.$num,
					'value' => $pattern_item
				];
			}
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
			if (array_key_exists('pointsize', $val)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.pointsize.'.$index,
					'value' => $val['pointsize']
				];
			}
			if (array_key_exists('fill', $val)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.fill.'.$index,
					'value' => $val['fill']
				];
			}
			if (array_key_exists('missingdatafunc', $val)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.missingdatafunc.'.$index,
					'value' => $val['missingdatafunc']
				];
			}
		}
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @param string $form_name   Form name in which data set field resides.
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

					'jQuery(".input-color-picker input").colorpicker({onUpdate: function(color){'.
						'var ds = jQuery(this).closest(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'");'.
						'jQuery(".'.ZBX_STYLE_COLOR_PREVIEW_BOX.'", ds).css("background-color", "#"+color);'.
					'}, appendTo: "#overlay_dialogue"});'.

					'jQuery("textarea", jQuery("#data_sets"))'.
						'.filter(function() {return this.id.match(/ds_\d+_hosts/);})'.
						'.each(function() {'.
							'var itemsId = jQuery(this).attr("id").replace("_hosts", "_items"),'.
								'hostsId = jQuery(this).attr("id");'.
							'jQuery(this).autoGrowTextarea({pair: "#"+itemsId, maxHeight: 100});'.
							'jQuery("#"+itemsId).autoGrowTextarea({pair: "#"+hostsId, maxHeight: 100});'.
						'});'.
				'})'.
				'.bind("afterremove.dynamicRows", function(event, options) {'.
					'updateGraphPreview();'.
				'})'.
				'.bind("tableupdate.dynamicRows", function(event, options) {'.
					'jQuery(".range-control[data-options]").rangeControl();'.
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
				'handler: ".'.ZBX_STYLE_BTN_GEAR.', .'.ZBX_STYLE_COLOR_PREVIEW_BOX.'"'.
			'});',

			// Initialize rangeControl UI elements.
			'jQuery(".range-control", jQuery("#data_sets")).rangeControl();',

			// Expand dataset when click in pattern fields.
			'jQuery("#data_sets").on("click", "'.implode(', ', [
				'.'.ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED.' .'.ZBX_STYLE_PATTERNSELECT,
				'.'.ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED.' .'.ZBX_STYLE_BTN_GREY
			]).'", function() {'.
				'var num = jQuery(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'")'.
					'.index(jQuery(this).closest(".'.ZBX_STYLE_LIST_ACCORDION_ITEM.'"));'.
				'jQuery("#data_sets").zbx_vertical_accordion("expandNth", num);'.
			'});',

			// Initialize textarea autogrow.
			'jQuery("textarea", jQuery("#data_sets"))'.
				'.filter(function() {return this.id.match(/ds_\d+_hosts/);})'.
				'.each(function() {'.
					'var itemsId = jQuery(this).attr("id").replace("_hosts", "_items"),'.
						'hostsId = jQuery(this).attr("id");'.
					'jQuery(this).autoGrowTextarea({pair: "#"+itemsId, maxHeight: 100});'.
					'jQuery("#"+itemsId).autoGrowTextarea({pair: "#"+hostsId, maxHeight: 100});'.
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
				'cursor: "move",'.
				'opacity: 0.6,'.
				'axis: "y",'.
				'disable: function() {'.
					'return jQuery("#data_sets .'.ZBX_STYLE_LIST_ACCORDION_ITEM.'").length < 2;'.
				'},'.
				'start: function() {'. // Workaround to fix wrong scrolling at initial sort.
					'jQuery(this).sortable("refreshPositions");'.
				'},'.
				'stop: function() {'.
					'updateGraphPreview();'.
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
	 * @param string $form_name   Form name in which data set field resides.
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
