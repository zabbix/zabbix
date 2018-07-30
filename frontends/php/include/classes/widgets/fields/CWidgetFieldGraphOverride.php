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
			'hosts'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'items'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'color'				=> ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'length' => 6],
			'type'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE])],
			'width'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'radius'			=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(1, 10))],
			'transparency'		=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'fill'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', range(0, 10))],
			'axisy'				=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
			'timeshift'			=> ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'length' => 255],
			'missingdatafunc'	=> ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO])],
			'order'				=> ['type' => API_INT32],
		]]);
		$this->setDefault([]);

		$this->override_options =
			['color', 'width', 'type', 'transparency', 'fill', 'radius', 'missingdatafunc', 'axisy', 'timeshift'];
	}

	public function getFieldLayout($value, $options) {
		$overrides_options_list = [];
		$fn = $this->getName();

		// No default values for overrides, but make sure that all fields are present.
		//$value = array_merge(['hosts' => '', 'items' => ''], $value);

		// Create override optins list.
		foreach ($this->override_options as $opt) {
			if (array_key_exists($opt, $value)) {
				$overrides_options_list[] =
					(new CInput('hidden', $fn.'['.$options['row_num'].']['.$opt.']', $value[$opt]));
			}
		}

		return [
			// Accordion head - data set selection fields and tools.
			(new CDiv([
				(new CVar($fn.'['.$options['row_num'].'][order]', $options['order_num'])),
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),

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
							'dstfrm' => $options['form_name'],
							'dstfld1' => $fn.'['.$options['row_num'].'][hosts]'
						]).', null, this);'
					),

				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),

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
							'srcfld1' => 'itemid',
							'reference' => 'name_expanded',
							'dstfrm' => $options['form_name'],
							'dstfld1' => $fn.'['.$options['row_num'].'][items]'
						]).', null, this);'
					),

				(new CButton())
					->setAttribute('title', _('Delete'))
					->addClass(ZBX_STYLE_BTN_TRASH)
					->addStyle('position: absolute; right: -30px; margin-top: 1px;')
			]))
				->addStyle('position: relative;'),

			// Configuration configuration options.
			(new CDiv([
				(new CList($overrides_options_list))
					->addItem((new CButton(null, (new CSpan())
								->addClass(ZBX_STYLE_PLUS_ICON)
								->addStyle('margin-right: 0px;')
						))
							->setAttribute('data-row', $options['row_num'])
							->addClass(ZBX_STYLE_BTN_ALT))
					->addClass(ZBX_STYLE_OVERRIDES_OPTIONS_LIST)
			]))
		];
	}

	public function setValue($value) {
		$this->value = (array) $value;

		// Sort data sets according order field.
		CArrayHelper::sort($this->value, ['order' => ZBX_SORT_UP]);

		foreach ($this->value as $index => $val) {
			// At least host or item pattern must be specified.
			if (!array_key_exists('hosts', $val) || $val['hosts'] === ''
					|| !array_key_exists('items', $val) || $val['items'] === '') {
				unset($this->value[$index]);
			}
		}

		return $this;
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);
		$values = $this->getValue();
		$color_validator = new CColorValidator();

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
					elseif ($option === 'timeshift' && timeUnitToSeconds($val, true) === null) {
						$errors[]
							= _s('Invalid parameter "%1$s": %2$s.', _('Time shift'), _('a time unit is expected'));
					}
					$options_set++;
				}

				if ($options_set == 0) {
					$errors[] = _s('Override options are not specified.');
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

	public function getOverrideOptionNames() {
		return [
			'width' => _('Width'),
			'type' => _('Draw'),
			'type'.SVG_GRAPH_TYPE_LINE => _('Line'),
			'type'.SVG_GRAPH_TYPE_POINTS => _('Points'),
			'type'.SVG_GRAPH_TYPE_STAIRCASE => _('Staircase'),
			'transparency' => _('Transparency'),
			'fill' => _('Fill'),
			'radius' => _('Radius'),
			'missingdatafunc' => _('Missing data'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_NONE => _('None'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_CONNECTED => _x('Connected', 'missing data function'),
			'missingdatafunc'.SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO => _x('Threat as 0', 'missing data function'),
			'axisy' => _('Y-axis'),
			'axisy'.GRAPH_YAXIS_SIDE_LEFT => _('Left'),
			'axisy'.GRAPH_YAXIS_SIDE_RIGHT => _('Right'),
			'timeshift' => _('Time shift')
		];
	}

	public function getOverrideMenu() {
		/**
		 * TODO miks: consider to make this shorter.
		 * E.g., instead of define 1,2,3...8,9,10, use 1..10 and create missing options in frontend.
		 */
		return [
			'sections' => [
				[
					'name' => _('ADD OVERRIDE'),
					'options' => [
						['name' => _('Base color'), 'callback' => 'addOverride', 'args' => ['color', '000000']],

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

						['name' => _('Radius').'/1', 'callback' => 'addOverride', 'args' => ['radius', 1]],
						['name' => _('Radius').'/2', 'callback' => 'addOverride', 'args' => ['radius', 2]],
						['name' => _('Radius').'/3', 'callback' => 'addOverride', 'args' => ['radius', 3]],
						['name' => _('Radius').'/4', 'callback' => 'addOverride', 'args' => ['radius', 4]],
						['name' => _('Radius').'/5', 'callback' => 'addOverride', 'args' => ['radius', 5]],
						['name' => _('Radius').'/6', 'callback' => 'addOverride', 'args' => ['radius', 6]],
						['name' => _('Radius').'/7', 'callback' => 'addOverride', 'args' => ['radius', 7]],
						['name' => _('Radius').'/8', 'callback' => 'addOverride', 'args' => ['radius', 8]],
						['name' => _('Radius').'/9', 'callback' => 'addOverride', 'args' => ['radius', 9]],
						['name' => _('Radius').'/10', 'callback' => 'addOverride', 'args' => ['radius', 10]],

						['name' => _('Missing data').'/'._('None'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_NONE]],
						['name' => _('Missing data').'/'._x('Connected', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_CONNECTED]],
						['name' => _('Missing data').'/'._x('Threat as 0', 'missing data function'), 'callback' => 'addOverride', 'args' => ['missingdatafunc', SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO]],

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
					'onChange: updateGraphPreview,'.
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
				'.bind("tableupdate.dynamicRows", function(event, options) {'.
					'initializeOverrides();'.
					'if (typeof updateGraphPreview === "function") {'.
						'updateGraphPreview();'.
					'}'.
				'});',

			// Initialize overrides UI control.
			'initializeOverrides();',

			// Make overrides sortable.
			'jQuery("#overrides").sortable({'.
				'items: ".'.ZBX_STYLE_OVERRIDES_LIST_ITEM.'",'.
				'containment: "parent",'.
				'handle: ".drag-icon",'.
				'tolerance: "pointer",'.
				'cursor: "move",'.
				'opacity: 0.6,'.
				'axis: "y",'.
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
	 * @return string
	 */
	public function getTemplate($form_name) {
		return (new CListItem(
			$this->getFieldLayout(
				['hosts' => '', 'items' => ''],
				['row_num' => '#{rowNum}', 'order_num' => '#{orderNum}', 'form_name' => $form_name]
			)
		))
			->addClass(ZBX_STYLE_OVERRIDES_LIST_ITEM)
			->toString();
	}

	public function getOverrideOptions() {
		return $this->override_options;
	}
}
