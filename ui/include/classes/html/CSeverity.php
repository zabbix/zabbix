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


class CSeverity extends CRadioButtonList {
	/**
	 * @param string   $name     HTML element name.
	 * @param int|null $value    Default selected value, default TRIGGER_SEVERITY_NOT_CLASSIFIED.
	 * @param bool     $enabled  If set to false, radio buttons (severities) are marked as disabled.
	 */
	public function __construct(string $name, ?int $value = TRIGGER_SEVERITY_NOT_CLASSIFIED, bool $enabled = true) {
		parent::__construct($name, $value);

		$this->setModern(true);
		$this->setEnabled($enabled);
	}

	/**
	 * Add value.
	 *
	 * @param string $label           Input element label.
	 * @param string|int $value       Input element value.
	 * @param string|null $class      List item class name.
	 * @param string|null $on_change  Javascript handler for onchange event.
	 *
	 * @return CSeverity
	 */
	public function addValue($label, $value, $class = null, $on_change = null): self {
		$this->values[] = [
			'name' => $label,
			'value' => $value,
			'id' => null,
			'class' => $class,
			'on_change' => $on_change
		];

		return $this;
	}

	public function toString($destroy = true): string {
		foreach (CSeverityHelper::getSeverities() as $severity) {
			$this->addValue($severity['name'], $severity['value'], $severity['style']);
		}

		return parent::toString($destroy);
	}
}
