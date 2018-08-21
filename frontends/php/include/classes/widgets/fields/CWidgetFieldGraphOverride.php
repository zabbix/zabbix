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
 * Class for override widget field used in Graph widget configuration overrides tab.
 */
class CWidgetFieldGraphOverride extends CWidgetField {

	protected $override_options = [];

	/**
	 * Create widget field for Graph Override selection.
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
			'color'				=> ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'length' => 6],
			'type'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE])],
			'width'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'pointsize'			=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(1, 10))],
			'transparency'		=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'fill'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'axisy'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
			'timeshift'			=> ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'length' => 10],
			'missingdatafunc'	=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO])],
			'order'				=> ['type' => API_INT32]
		]]);

		/**
		 * No default values for overrides but let's set an empty field here because extended class have no default
		 * 'default' value.
		 */
		$this->setDefault([]);

		// Supported override options.
		$this->override_options
			= ['color', 'width', 'type', 'transparency', 'fill', 'pointsize', 'missingdatafunc', 'axisy', 'timeshift'];
	}

	/**
	 * Function returns array containing HTML objects filled with given values. Used to generate HTML in widget
	 * overrides field.
	 *
	 * @param array $value              Values to fill in particular override row. See self::setValue() for detailed
	 *                                  description.
	 * @param array $options            Calculated options of particular override.
	 * @param int   $options[row_num]   Unique override numeric identifier. Used to make unique field names.
	 * @param int   $options[order_num] Sequential order number.
	 *
	 * @return array
	 */
	public function getFieldLayout(array $value, array $options) {
		$overrides_options_list = [];
		$fn = $this->getName();

		// Create override optins list.
		foreach ($this->override_options as $opt) {
			if (array_key_exists($opt, $value)) {
				$overrides_options_list[] =
					(new CInput('hidden', $fn.'['.$options['row_num'].']['.$opt.']', $value[$opt]));
			}
		}

		return [
			/**
			 * First line: host pattern field, item pattern field.
			 * Contains also hidden order field, drag and drop button and delete button.
			 */
			(new CDiv([
				(new CVar($fn.'['.$options['row_num'].'][order]', $options['order_num'])),
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),
				(new CDiv([
					(new CDiv([
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
					]))
						->addClass(ZBX_STYLE_COLUMN_50),
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
									'reference' => 'name_expanded',
									'multiselect' => 1,
									'real_hosts' => 1,
									'numeric' => 1,
									'dstfrm' => $options['form_name'],
									'dstfld1' => $fn.'['.$options['row_num'].'][items]'
								]).', null, this);'
							)
					]))
						->addClass(ZBX_STYLE_COLUMN_50)
				]))
					->addClass(ZBX_STYLE_COLUMN_95)
					->addClass(ZBX_STYLE_COLUMNS),

				(new CDiv(
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_BTN_TRASH)
				))
					->addClass(ZBX_STYLE_COLUMN_5)
			]))
				->addClass(ZBX_STYLE_COLUMNS),

			// Selected override options.
			(new CList($overrides_options_list))
				->addClass(ZBX_STYLE_OVERRIDES_OPTIONS_LIST)
				->addItem((new CButton(null, (new CSpan())
							->addClass(ZBX_STYLE_PLUS_ICON)
							->addStyle('margin-right: 0px;')
					))
						->setAttribute('data-row', $options['row_num'])
						->addClass(ZBX_STYLE_BTN_ALT)
				)
		];
	}

	/**
	 * Set override field value.
	 *
	 * @param array  $value                  Values filled in particular override.
	 * @param string $value[order]           Number by which overrides are sorted.
	 * @param string $value[hosts]           Host pattern.
	 * @param string $value[items]           Item pattern.
	 * @param string $value[color]           (optional) Override color option.
	 * @param string $value[width]           (optional) Override width option.
	 * @param string $value[type]            (optional) Override type option.
	 * @param string $value[transparency]    (optional) Override transparency option.
	 * @param string $value[fill]            (optional) Override fill option.
	 * @param string $value[pointsize]       (optional) Override pointsize option.
	 * @param string $value[missingdatafunc] (optional) Override missingdatafunc option.
	 * @param string $value[axisy]           (optional) Override axisy option.
	 * @param string $value[timeshift]       (optional) Override timeshift option.
	 *
	 * @return object $this
	 */
	public function setValue($value) {
		$this->value = (array) $value;

		// Sort data sets according order field.
		CArrayHelper::sort($this->value, [['field' => 'order', 'order' => ZBX_SORT_UP]]);

		// Delete empty fields.
		foreach ($this->value as $index => $val) {
			$hosts = array_key_exists('hosts', $val)
				? (is_array($val['hosts']) ? implode(', ', $val['hosts']) : $val['hosts'])
				: '';
			$items = array_key_exists('items', $val)
				? (is_array($val['items']) ? implode(', ', $val['items']) : $val['items'])
				: '';

			$is_hosts_specified = ($hosts !== '');
			$is_items_specified = ($items !== '');
			$is_options_specified = (bool) array_intersect(array_keys($val), $this->override_options);

			if (!$is_hosts_specified && !$is_items_specified && !$is_options_specified) {
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
		$color_validator = new CColorValidator();

		// Validate host and item pattern fields.
		if (!$errors && $strict) {
			foreach ($values as $val) {
				if (!array_key_exists('hosts', $val) || $val['hosts'] === '') {
					$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('host pattern'),
						_('Overrides'), _('cannot be empty')
					);
					break;
				}
				elseif (!array_key_exists('items', $val) || $val['items'] === '') {
					$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('item pattern'),
						_('Overrides'), _('cannot be empty')
					);
					break;
				}
			}
		}

		// Validate options.
		if (!$errors && $strict) {
			foreach ($values as $override) {
				$options_set = 0;
				foreach ($override as $option => $val) {
					if (!in_array($option, $this->override_options)) {
						continue;
					}

					if ($option === 'color' && !$color_validator->validate($val)) {
						$errors[]
							= _s('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).', $val);
					}
					elseif ($option === 'timeshift') {
						$timeshift = timeUnitToSeconds($val, true);
						if ($timeshift === null // invalid
							|| bccomp(ZBX_MIN_TIMESHIFT, $timeshift) == 1 // exceeds min timeshift
							|| bccomp(ZBX_MAX_TIMESHIFT, $timeshift) == -1 // exceeds max timeshift
						) {
							$errors[]
								= _s('Invalid parameter "%1$s": %2$s.', _('Time shift'), _('a time unit is expected'));
						}
					}
					$options_set++;
				}

				if ($options_set == 0) {
					$errors[] = _s('Override options are not specified.');
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
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.order.'.$index,
				'value' => $val['order']
			];

			foreach ($this->override_options as $opt) {
				if (array_key_exists($opt, $val)) {
					$widget_fields[] = [
						'type' => ($opt === 'color' || $opt === 'timeshift')
							? ZBX_WIDGET_FIELD_TYPE_STR
							: ZBX_WIDGET_FIELD_TYPE_INT32,
						'name' => $this->name.'.'.$opt.'.'.$index,
						'value' => $val[$opt]
					];
				}
			}
		}
	}

	/**
	 * Function returns array containing string values used as titles for override options.
	 *
	 * @return array
	 */
	public function getOverrideOptionNames() {
		return [
			'width' => _('Width'),
			'type' => _('Draw'),
			'type'.SVG_GRAPH_TYPE_LINE => _('Line'),
			'type'.SVG_GRAPH_TYPE_POINTS => _('Points'),
			'type'.SVG_GRAPH_TYPE_STAIRCASE => _('Staircase'),
			'transparency' => _('Transparency'),
			'fill' => _('Fill'),
			'pointsize' => _('Point size'),
			'missingdatafunc' => _('Missing data'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_NONE => _('None'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_CONNECTED => _x('Connected', 'missing data function'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO => _x('Treat as 0', 'missing data function'),
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
	public function getOverrideMenu() {
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
						['name' => _('Missing data').'/'._x('Treat as 0', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERRO]],

						['name' => _('Y-axis').'/'._('Left'), 'callback' => 'addOverride', 'args' => ['axisy', GRAPH_YAXIS_SIDE_LEFT]],
						['name' => _('Y-axis').'/'._('Right'), 'callback' => 'addOverride', 'args' => ['axisy', GRAPH_YAXIS_SIDE_RIGHT]],

						['name' => _('Time shift'), 'callback' => 'addOverride', 'args' => ['timeshift']]
					]
				]
			]
		];
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @param string $form_name   Form name in which override field is located.
	 *
	 * @return string
	 */
	public function getJavascript($form_name) {
		$scripts = [
			// Define it as function to avoid redundancy.
			'function initializeOverrides() {'.
				'jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_OPTIONS_LIST.'").overrides({'.
					'add: ".'.ZBX_STYLE_BTN_ALT.'",'.
					'options: "input[type=hidden]",'.
					'captions: '.CJs::encodeJson($this->getOverrideOptionNames()).','.
					'makeName: function(option) {return "'.$this->getName().'["+this.rowId+"]["+option+"]";},'.
					'makeOption: function(name) {'.
						'return name.match(/.*\[('.implode('|', $this->getOverrideOptions()).')\]/)[1];},'.
					'onUpdate: updateGraphPreview,'.
					'menu: '.CJs::encodeJson($this->getOverrideMenu()).
				'});'.
			'}',

			// Initialize dynamicRows.
			'jQuery("#overrides")'.
				'.dynamicRows({'.
					'template: "#overrides-row",'.
					'beforeRow: ".overrides-foot",'.
					'remove: ".'.ZBX_STYLE_BTN_TRASH.'",'.
					'add: "#override-add",'.
					'row: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",'.
					'dataCallback: function(data) {'.
						'data.orderNum = data.rowNum + 1;'.
						'return data;'.
					'}'.
				'})'.
				'.bind("afteradd.dynamicRows", function(event, options) {'.
					'var container = jQuery(".overlay-dialogue-body");'.
					'container.scrollTop(container[0].scrollHeight);'.

					// Initialize textarea autogrow.
					'jQuery("textarea", jQuery("#overrides"))'.
						'.filter(function() {return this.id.match(/or_\d+_hosts/);})'.
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

			// Initialize textarea autogrow.
			'jQuery("textarea", jQuery("#overrides"))'.
				'.filter(function() {return this.id.match(/or_\d+_hosts/);})'.
				'.each(function() {'.
					'var itemsId = jQuery(this).attr("id").replace("_hosts", "_items"),'.
						'hostsId = jQuery(this).attr("id");'.
					'jQuery(this).autoGrowTextarea({pair: "#"+itemsId, maxHeight: 100});'.
					'jQuery("#"+itemsId).autoGrowTextarea({pair: "#"+hostsId, maxHeight: 100});'.
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
				'cursor: "move",'.
				'opacity: 0.6,'.
				'axis: "y",'.
				'disable: function() {'.
					'return jQuery("#overrides .'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'").length < 2;'.
				'},'.
				'start: function() {'. // Workaround to fix wrong scrolling at initial sort.
					'jQuery(this).sortable("refreshPositions");'.
				'},'.
				'stop: function() {'.
					'updateGraphPreview();'.
				'},'.
				'update: function() {'.
					'jQuery("input[type=hidden]", jQuery("#overrides")).filter(function() {'.
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
	 * @param string $form_name   Form name in which override field is located.
	 *
	 * @return string
	 */
	public function getTemplate($form_name) {
		return (new CListItem(
			$this->getFieldLayout(
				[
					'hosts' => '',
					'items' => ''
				],
				[
					'row_num' => '#{rowNum}',
					'order_num' => '#{orderNum}',
					'form_name' => $form_name
				]
			)
		))
			->addClass(ZBX_STYLE_OVERRIDES_LIST_ITEM)
			->toString();
	}

	/**
	 * Returns array of supported override options.
	 *
	 * @return array
	 */
	public function getOverrideOptions() {
		return $this->override_options;
	}
}
