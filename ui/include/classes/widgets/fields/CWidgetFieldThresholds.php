<?php
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


class CWidgetFieldThresholds extends CWidgetField {

	/**
	 * Create widget field for Thresholds selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
			'color'		=> ['type' => API_COLOR, 'flags' => API_REQUIRED],
			'threshold'	=> ['type' => API_NUMERIC, 'flags' => API_REQUIRED]
		]]);
		$this->setDefault([]);
	}

	public function validate($strict = false): array {
		$errors = parent::validate();

		if ($errors) {
			return $errors;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$thresholds = [];

		foreach ($this->getValue() as $threshold) {
			$threshold['threshold'] = trim($threshold['threshold']);

			if ($threshold['threshold'] !== ''
					&& $number_parser->parse($threshold['threshold']) == CParser::PARSE_SUCCESS) {
				$thresholds[] = $threshold + ['value' => $number_parser->calcValue()];
			}
		}

		CArrayHelper::sort($thresholds, ['value']);

		$thresholds = array_values($thresholds);

		$this->setValue($thresholds);

		return $errors;
	}

	public function setValue($value) {
		return parent::setValue((array) $value);
	}

	/**
	 * Add dynamic row script.
	 *
	 * @return string
	 */
	public function getJavascript() {
		return '
			var thresholds_table = jQuery("#thresholds_table_'.$this->getName().'");

			thresholds_table
				.dynamicRows({template: "#'.$this->getName().'"})
				.on("afteradd.dynamicRows", function(opt) {
					const rows = this.querySelectorAll(".form_row");
					jQuery(".color-picker input", rows[rows.length - 1])
						.val(colorPalette.getNextColor())
						.colorpicker({
							appendTo: ".overlay-dialogue-body"
						});
				});
		';
	}

	/**
	 * @param array $widget_fields   reference to Array of widget fields.
	 */
	public function toApi(array &$widget_fields = []) {
		$value = $this->getValue();

		foreach ($value as $index => $val) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.color.'.$index,
				'value' => $val['color']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.threshold.'.$index,
				'value' => $val['threshold']
			];
		}
	}
}
