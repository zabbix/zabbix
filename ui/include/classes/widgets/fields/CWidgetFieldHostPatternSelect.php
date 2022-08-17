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


class CWidgetFieldHostPatternSelect extends CWidgetField {

	private string $placeholder = '';

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault([]);

		/*
		 * Set validation rules bypassing a parent::setSaveType to skip validation of length.
		 * Save type is set in self::toApi method for each string field separately.
		 */
		$this->setValidationRules(['type' => API_STRINGS_UTF8]);
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		if ($value !== $this->default) {
			foreach ($value as $num => $val) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$num,
					'value' => $val
				];
			}
		}
	}

	public function getPlaceholder(): string {
		return $this->placeholder;
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function getJavaScript(): string {
		$field_id = zbx_formatDomId($this->getName().'[]');

		return 'jQuery("#'.$field_id.'").multiSelect(jQuery("#'.$field_id.'").data("params"));';
	}
}
