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


class CWidgetFieldWidgetSelect extends CWidgetField {

	private $search_by_value;

	/**
	 * Field that creates a selection of widgets in current dashboard, filtered by given key of widget array.
	 *
	 * @param string $name         Name of field in config form and widget['fields'] array.
	 * @param string $label        Field label in config form.
	 * @param mixed  $search_type  Value that will be searched in widgets.
	 */
	public function __construct($name, $label, $search_by_value) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->search_by_value = $search_by_value;
	}

	/**
	 * Set additional flags, which can be used in configuration form.
	 *
	 * @param int $flags
	 *
	 * @return $this
	 */
	public function setFlags($flags) {
		parent::setFlags($flags);

		if ($flags & self::FLAG_NOT_EMPTY) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules(null);
		}

		return $this;
	}

	/**
	 * JS code, that should be executed, to fill the select element with values and select current one.
	 *
	 * @return string
	 */
	public function getJavascript() {
		return '
			var filter_select = document.getElementById("'.$this->getName().'");

			filter_select.addOption('.json_encode(['label' => _('Select widget'), 'value' => '-1']).');
			filter_select.selectedIndex = 0;

			ZABBIX.Dashboard.getSelectedDashboardPage().getWidgets().forEach((widget) => {
				if (widget.getType() === "'.$this->search_by_value.'") {
					filter_select.addOption({label: widget.getHeaderName(), value: widget.getFields().reference});
					if (widget.getFields().reference === "'.$this->getValue().'") {
						filter_select.value = "'.$this->getValue().'";
					}
				}
			});
		';
	}

	public function setValue($value) {
		if ($value === '' || ctype_alnum($value)) {
			$this->value = $value;
		}

		return $this;
	}

	public function setAction($action) {
		throw new RuntimeException(sprintf('Method is not implemented: "%s".', __METHOD__));
	}

	public function getAction() {
		throw new RuntimeException(sprintf('Method is not implemented: "%s".', __METHOD__));
	}
}
