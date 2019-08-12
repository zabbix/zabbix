<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/**
 * Trait for objects that can be casted to web elements.
 */
trait CastableTrait {

	/**
	 * Cast object to base Element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CElement
	 */
	public function asElement($options = []) {
		return $this->cast(CElement::class, $options);
	}

	/**
	 * Cast object to Checkbox element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CCheckboxElement
	 */
	public function asCheckbox($options = []) {
		return $this->cast(CCheckboxElement::class, $options);
	}

	/**
	 * Cast object to CheckboxList element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CCheckboxElement
	 */
	public function asCheckboxList($options = []) {
		return $this->cast(CCheckboxListElement::class, $options);
	}

	/**
	 * Cast object to Dashboard element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CDashboardElement
	 */
	public function asDashboard($options = []) {
		return $this->cast(CDashboardElement::class, $options);
	}

	/**
	 * Cast object to Dropdown element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CDropdownElement
	 */
	public function asDropdown($options = []) {
		return $this->cast(CDropdownElement::class, $options);
	}

	/**
	 * Cast object to Form element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CFormElement
	 */
	public function asForm($options = []) {
		return $this->cast(CFormElement::class, $options);
	}

	/**
	 * Cast object to Message element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CMessageElement
	 */
	public function asMessage($options = []) {
		return $this->cast(CMessageElement::class, $options);
	}

	/**
	 * Cast object to Multiselect element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CMultiselectElement
	 */
	public function asMultiselect($options = []) {
		return $this->cast(CMultiselectElement::class, $options);
	}

	/**
	 * Cast object to OverlayDialog element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return COverlayDialogElement
	 */
	public function asOverlayDialog($options = []) {
		return $this->cast(COverlayDialogElement::class, $options);
	}

	/**
	 * Cast object to Table element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CTableElement
	 */
	public function asTable($options = []) {
		return $this->cast(CTableElement::class, $options);
	}

	/**
	 * Cast object to TableRow element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CTableRowElement
	 */
	public function asTableRow($options = []) {
		return $this->cast(CTableRowElement::class, $options);
	}

	/**
	 * Cast object to Widget element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CWidgetElement
	 */
	public function asWidget($options = []) {
		return $this->cast(CWidgetElement::class, $options);
	}

	/**
	 * Cast object to SegmentedRadio element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CSegmentedRadioElement
	 */
	public function asSegmentedRadio($options = []) {
		return $this->cast(CSegmentedRadioElement::class, $options);
	}

	/**
	 * Cast object to Range Control element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CRangeControlElement
	 */
	public function asRangeControl($options = []) {
		return $this->cast(CRangeControlElement::class, $options);
	}

	/**
	 * Cast object to MultifieldTable element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CMultifieldTableElement
	 */
	public function asMultifieldTable($options = []) {
		return $this->cast(CMultifieldTableElement::class, $options);
	}

	/**
	 * Cast object to base RemoteWebElement.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return RemoteWebElement
	 */
	public function asBaseType($options = []) {
		return $this->cast(RemoteWebElement::class, $options);
	}

	/**
	 * Cast object to Multiline element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CMultilineElement
	 */
	public function asMultiline($options = []) {
		return $this->cast(CMultilineElement::class, $options);
	}
}
