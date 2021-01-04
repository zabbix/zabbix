<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CSeverity extends CRadioButtonList {
	/**
	 * Options array.
	 *
	 * string $options['name']   HTML element name.
	 * int    $options['value']  Default selected value, default TRIGGER_SEVERITY_NOT_CLASSIFIED.
	 * bool   $options['all']    Is option 'All' available, default false.
	 * @property array
	 */
	private $options = [
		'all'	=> false,
		'value' => TRIGGER_SEVERITY_NOT_CLASSIFIED
	];

	/**
	 * @param array $options    Array of options.
	 * @param bool  $enabled    If set to false, radio buttons (severities) are marked as disabled.
	 */
	public function __construct(array $options = [], $enabled = true) {
		$this->options = $options + $this->options;

		parent::__construct($this->options['name'], $this->options['value']);

		$this->setModern(true);
		$this->setEnabled($enabled);
	}

	/**
	 * Add value.
	 *
	 * @param string $label      Input element label.
	 * @param string $value      Input element value.
	 * @param string $class      List item class name.
	 * @param string $on_change  Javascript handler for onchange event.
	 *
	 * @return CSeverity
	 */
	public function addValue($label, $value, $class = null, $on_change = null) {
		$this->values[] = [
			'name' => $label,
			'value' => $value,
			'id' => null,
			'class' => $class,
			'on_change' => $on_change
		];

		return $this;
	}

	public function toString($destroy = true) {
		if ($this->options['all']) {
			$this->addValue(_('all'), -1);
		}

		foreach (getSeverities() as $severity) {
			$this->addValue($severity['name'], $severity['value'], $severity['style']);
		}

		return parent::toString($destroy);
	}
}
