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

	public const THRESHOLDS_TABLE_ID = '%s-table';
	public const THRESHOLDS_ROW_TMPL_ID = '%s-row-tmpl';

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

	public function setValue($value) {
		$thresholds = [];

		foreach ($value as $threshold) {
			$threshold['threshold'] = trim($threshold['threshold']);

			if ($threshold['threshold'] !== '') {
				$thresholds[] = $threshold;
			}
		}

		return parent::setValue($thresholds);
	}

	public function validate($strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$thresholds = [];

		foreach ($this->getValue() as $threshold) {
			if ($number_parser->parse($threshold['threshold']) == CParser::PARSE_SUCCESS) {
				$thresholds[] = $threshold + ['threshold_value' => $number_parser->calcValue()];
			}
		}

		CArrayHelper::sort($thresholds, ['threshold_value']);

		$thresholds = array_values($thresholds);

		$this->setValue($thresholds);

		return [];
	}

	public function getJavascript() {
		return '
			var thresholds_table = jQuery("#'.sprintf(self::THRESHOLDS_TABLE_ID, $this->getName()).'");

			thresholds_table
				.dynamicRows({template: "#'.sprintf(self::THRESHOLDS_ROW_TMPL_ID, $this->getName()).'"})
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
