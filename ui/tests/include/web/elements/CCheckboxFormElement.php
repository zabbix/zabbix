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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Form element with checkbox labels.
 */
class CCheckboxFormElement extends CFormElement {

	protected function getLabelCheckbox($label) {
		$for = $label->getAttribute('for');

		if ($for && substr($for, 0, 8) === 'visible_') {
			return $this->query('id', $for)->asCheckbox()->one(false);
		}

		return new CNullElement(['locator' => 'Form label checkbox']);
	}

	/**
	 * Get element field by label element.
	 *
	 * @param CElement $label     label element
	 *
	 * @return CElement|CNullElement
	 */
	public function getFieldByLabelElement($label) {
		try {
			$this->getLabelCheckbox($label)->check();
		}
		catch (\Exception $e) {
			// Code is not missing here.
		}

		return parent::getFieldByLabelElement($label);
	}

	/**
	 * Fill form fields with specific values.
	 *
	 * @param string $field   field name to filled in
	 * @param string $values  value to be put in field
	 *
	 * @return
	 */
	protected function setFieldValue($field, $values) {
		if ($values === null) {
			try {
				$this->getLabelCheckbox($this->getLabel($field))->uncheck();

				return;
			}
			catch (\Exception $e) {
				// Code is not missing here.
			}
		}

		return parent::setFieldValue($field, $values);
	}

	/**
	 * Check single form field value to have a specific value.
	 *
	 * @param string  $field              field name to filled checked
	 * @param mixed   $values             value to be checked in field
	 * @param boolean $raise_exception    flag to raise exceptions on error
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	protected function checkFieldValue($field, $values, $raise_exception = true) {
		if ($values === null) {
			try {
				return $this->getLabelCheckbox($this->getLabel($field))->checkValue(false, $raise_exception);
			}
			catch (\Exception $exception) {
				throw $exception;
			}
		}

		return parent::checkFieldValue($field, $values, $raise_exception);
	}
}
