<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CWidgetFieldItemId extends CWidgetField
{
	protected $caption;
	protected $caption_name;

	/**
	 * Create widget field for Item selection
	 * @param string $name field name in form
	 * @param string $label label for the field in form
	 * @param int $default default Item Id value
	 * @param string $caption_name name of caption field in form
	 *
	 * @return CWidgetFieldItemId
	 */
	public function __construct($name, $label, $default = 0, $caption_name = 'caption') {
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_ITEM);
		$this->caption = '';
		$this->caption_name = $caption_name;
	}

	public function setValue($value) {
		if (in_array($value, [null, 0, '0'])) {
			$this->value = null;
		} else {
			$this->value = (int)$value;
		}
		return $this;
	}

	public function setCaption($value) {
		$this->caption = $value;
		return $this;
	}

	/**
	 * Get caption text
	 * @param bool $with_calculate get caption text from Item Id
	 *
	 * @return string
	 */
	public function getCaption($with_calculate = false) {
		$caption = $this->caption;
		if ($with_calculate === true) {
			$caption = $this->calculateCaption();
		}
		return $caption;
	}

	public function getCaptionName() {
		return $this->caption_name;
	}

	public function validate() {
		$errors = parent::validate();
		if ($this->required === true && $this->value === 0) {
			$errors[] = _s('Field \'%s\' is required', $this->label);
		}

		return $errors;
	}

	// TODO VM: (?) in screens it was done only when time type is HOST. This way that check is omited.
	// (but logically it should work same as well)
	protected function calculateCaption() {
		$caption = $this->caption;
		if ($this->caption === '' && $this->value !== null && $this->value > 0) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'key_', 'name'],
				'selectHosts' => ['name'],
				'itemids' => $this->value,
				'webitems' => true
			]);

			if ($items) {
				$items = CMacrosResolverHelper::resolveItemNames($items);

				$item = reset($items);
				$host = reset($item['hosts']);
				$caption = $host['name'].NAME_DELIMITER.$item['name_expanded'];
			}
		}
		return $caption;
	}
}
