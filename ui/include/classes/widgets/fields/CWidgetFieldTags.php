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


class CWidgetFieldTags extends CWidgetField {

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault([])
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
				'operator'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
				'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
			]]);
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 */
	public function getValue() {
		$value = parent::getValue();

		foreach ($value as $index => $val) {
			if ($val['tag'] === '' && $val['value'] === '') {
				unset($value[$index]);
			}
		}

		return $value;
	}

	public function setValue($value): self {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Add dynamic row script and fix the distance between AND/OR buttons and tag inputs below them.
	 *
	 * @return string
	 */
	public function getJavaScript(): string {
		return '
			jQuery("#tags_table_'.$this->getName().'")
				.dynamicRows({template: "#tag-row-tmpl"})
				.on("afteradd.dynamicRows", function() {
					const rows = this.querySelectorAll(".form_row");
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll("#tags_table_'.$this->getName().' .form_row").forEach(row => {
				new CTagFilterItem(row);
			});
		';
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		foreach ($value as $index => $val) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.tag.'.$index,
				'value' => $val['tag']
			];
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.operator.'.$index,
				'value' => $val['operator']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.value.'.$index,
				'value' => $val['value']
			];
		}
	}
}
