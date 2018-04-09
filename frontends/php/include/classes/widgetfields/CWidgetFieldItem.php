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


class CWidgetFieldItem extends CWidgetField {

	private $multiple = true;

	private $filter_parameters = [
		'numeric' => false,
		'real_hosts' => true,
		'webitems' => true
	];

	/**
	 * Create widget field for Items selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_ITEM);
		$this->setDefault([]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Is field with multiple items or single.
	 *
	 * @return bool
	 */
	public function isMultiple() {
		return $this->multiple;
	}

	/**
	 * Set field to multiple items mode.
	 *
	 * @param bool $multiple
	 *
	 * @return CWidgetFieldItem
	 */
	public function setMultiple($multiple) {
		$this->multiple = $multiple;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFilterParameters() {
		return $this->filter_parameters;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return CWidgetFieldItem
	 */
	public function setFilterParameter($name, $value) {
		$this->filter_parameters[$name] = $value;

		return $this;
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);

		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && !$this->getValue()) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->getLabel(), _('cannot be empty'));
		}

		return $errors;
	}
}
