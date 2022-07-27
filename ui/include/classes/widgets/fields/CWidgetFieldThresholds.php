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
	 * Create widget field for Tags selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'fields' => [
			'color'		=> ['type' => API_COLOR],
			'threshold'	=> ['type' => API_NUMERIC]
		]]);
		$this->setDefault([]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 *
	 * @return mixed
	 */
	public function getValue() {
		$value = parent::getValue();

		foreach ($value as $index => $val) {
			if ($val['threshold'] === '') {
				unset($value[$index]);
			}
		}

		return $value;
	}

	// TODO change the comment bellow
	/**
	 * Add dynamic row script.
	 *
	 * @return string
	 */
	public function getJavascript() {
		return '
			var thresholds_table = jQuery("#thresholds_table_'.$this->getName().'");

			thresholds_table
				.dynamicRows({template: "#thresholds-row-tmpl"})
				.on("afteradd.dynamicRows", function(opt) {
					var rows = this.querySelectorAll(".form_row");
					jQuery(".color-picker input", rows[rows.length - 1])
						.val(colorPalette.getNextColor())
						.colorpicker({
							appendTo: ".overlay-dialogue-body"
						});
					document.getElementById("tophosts-column-thresholds-warning").style.display =
					document.querySelector("#thresholds_table_thresholds")
						.querySelectorAll(".form_row")
						.length > 0 ? "" : "none";
				}).on("afterremove.dynamicRows", () => {
					document.getElementById("tophosts-column-thresholds-warning").style.display =
					document.querySelector("#thresholds_table_thresholds")
						.querySelectorAll(".form_row")
						.length > 0 ? "" : "none";
					});;
				';
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
