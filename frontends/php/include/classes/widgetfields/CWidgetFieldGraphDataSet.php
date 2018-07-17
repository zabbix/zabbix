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
			'missingdatafunc'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_THREAT_AS_ZERRO])]
		]]);
		$this->setFlags(parent::FLAG_NOT_EMPTY);
		$this->setDefault([]);
	}

	public function getDefault() {
		return [
			'hosts' => '',
			'items' => '',
			'color' => '0062fd',
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
				(new CDiv())
					->addClass(ZBX_STYLE_DRAG_ICON)
					->addStyle('position: absolute; margin-left: -25px;'),
				(new CDiv([
					(new CDiv([
						(new CDiv($options['letter_id']))
							->addClass(ZBX_STYLE_COLOR_PREVIEW_BOX)
							->addStyle('background-color: #'.$value['color'].';'),
						(new CTextBox($fn.'['.$options['row_num'].'][hosts]', $value['hosts']))
							->setAttribute('placeholder', _('(pattern)'))
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
							)
					]))->addClass(ZBX_STYLE_COLUMNS_6),
					(new CDiv([
						(new CTextBox($fn.'['.$options['row_num'].'][items]', $value['items']))
							->setAttribute('placeholder', _('(pattern)'))
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
									'dstfrm' => $options['form_name'],
									'dstfld1' => $fn.'['.$options['row_num'].'][items]',
								]).', null, this);'
							)
					]))->addClass(ZBX_STYLE_COLUMNS_6),
				]))
					->addClass(ZBX_STYLE_COLUMNS_11)
					->addClass(ZBX_STYLE_COLUMNS),
				(new CDiv([
					(new CButton())
						->setAttribute('title', $options['is_opened'] ? _('Collapse') : _('Expand'))
						->addClass(ZBX_STYLE_BTN_GEAR),
					(new CButton())
						->setAttribute('title', _('Delete'))
						->addClass(ZBX_STYLE_BTN_TRASH)
				]))->addClass(ZBX_STYLE_COLUMNS_1)
			]))
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD)
				->addClass(ZBX_STYLE_COLUMNS),

			// Accordion body - data set configuration options.
			(new CDiv([
				// Left column fields.
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
								'var row_num = this.id.replace("'.$fn.'_","").replace("_type","");'.
								'switch(jQuery(":checked", jQuery(this)).val()) {'.
									'case "'.SVG_GRAPH_TYPE_LINE.'":'.
										'jQuery("#'.$fn.'_"+row_num+"_width").prop("disabled", false);'.
										'jQuery("#'.$fn.'_"+row_num+"_radius").prop("disabled", true);'.
										'break;'.
									'case "'.SVG_GRAPH_TYPE_POINTS.'":'.
										'jQuery("#'.$fn.'_"+row_num+"_width").prop("disabled", true);'.
										'jQuery("#'.$fn.'_"+row_num+"_radius").prop("disabled", false);'.
										'break;'.
									'case "'.SVG_GRAPH_TYPE_STAIRCASE.'":'.
										'jQuery("#'.$fn.'_"+row_num+"_width").prop("disabled", false);'.
										'jQuery("#'.$fn.'_"+row_num+"_radius").prop("disabled", true);'.
										'break;'.
								'}'
							)
							->setModern(true)
					)
					->addRow(_('Width'),
						(new CInput('range', $fn.'['.$options['row_num'].'][width]', (int) $value['width']))
							->setEnabled($value['type'] != SVG_GRAPH_TYPE_POINTS)
							->setAttribute('min', 0)
							->setAttribute('max', 10)
					)
					->addRow(_('Radius'),
						(new CInput('range', $fn.'['.$options['row_num'].'][radius]', (int) $value['radius']))
							->setEnabled($value['type'] == SVG_GRAPH_TYPE_POINTS)
							->setAttribute('min', 1)
							->setAttribute('max', 10)
					)
					->addRow(_('Transparency'),
						(new CInput('range', $fn.'['.$options['row_num'].'][transparency]', (int) $value['transparency']))
							->setAttribute('min', 0)
							->setAttribute('max', 10)
					)
					->addRow(_('Fill'), (new CInput('range', $fn.'['.$options['row_num'].'][fill]', (int) $value['fill']))
							->setAttribute('min', 0)
							->setAttribute('max', 10)
					)
					->addClass(ZBX_STYLE_COLUMNS_6),

				// Right column fields.
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
					->addClass(ZBX_STYLE_COLUMNS_6)
			]))
				->addClass(ZBX_STYLE_LIST_ACCORDION_ITEM_BODY)
				->addClass(ZBX_STYLE_COLUMNS_11)
				->addClass(ZBX_STYLE_COLUMNS)
		];
	}

	public function setValue($value) {
		$this->value = (array) $value;

		foreach ($this->value as $index => $val) {
			// At least host or item pattern must be specified.
			if ((!array_key_exists('hosts', $val) || $val['hosts'] === '')
					&& (!array_key_exists('items', $val) || $val['items'] === '')) {
				unset($this->value[$index]);
			}
		}

		return $this;
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);

		// At least on data set is mandatory.
		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && !$this->getValue()) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Data set'), _('cannot be empty'));
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
}
