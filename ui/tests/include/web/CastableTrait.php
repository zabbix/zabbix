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
	 * Cast object to List element.
	 *
	 * @deprecated List element is present only in IPMI tab of host configuration form.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CListElement
	 */
	public function asList($options = []) {
		return $this->cast(CListElement::class, $options);
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
	 * Cast object to Grid form element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CGridFormElement
	 */
	public function asGridForm($options = []) {
		return $this->cast(CGridFormElement::class, $options);
	}

	/**
	 * Cast object to CheckboxForm element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CFormElement
	 */
	public function asCheckboxForm($options = []) {
		return $this->cast(CCheckboxFormElement::class, $options);
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
	 * Cast object to CompositeInput element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CCompositeInputElement
	 */
	public function asCompositeInput($options = []) {
		return $this->cast(CCompositeInputElement::class, $options);
	}

	/**
	 * Cast object to ColorPicker element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CColorPickerElement
	 */
	public function asColorPicker($options = []) {
		return $this->cast(CColorPickerElement::class, $options);
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
	 * Cast object to PopupMenu element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CPopupMenuElement
	 */
	public function asPopupMenu($options = []) {
		return $this->cast(CPopupMenuElement::class, $options);
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

	/**
	 * Cast object to PopupButton element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CPopupButtonElement
	 */
	public function asPopupButton($options = []) {
		return $this->cast(CPopupButtonElement::class, $options);
	}

	/**
	 * Cast object to InputGroup element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CInputGroupElement
	 */
	public function asInputGroup($options = []) {
		return $this->cast(CInputGroupElement::class, $options);
	}

	/**
	 * Cast object to Interface element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CHostInterfaceElement
	 */
	public function asHostInterfaceElement($options = []) {
		return $this->cast(CHostInterfaceElement::class, $options);
	}

	/**
	 * Cast object to FilterTab element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CFilterTabElement
	 */
	public function asFilterTab($options = []) {
		return $this->cast(CFilterTabElement::class, $options);
	}

	/**
	 * Cast object to MainMenu element.
	 *
	 * @param array $options    additional casting options
	 *
	 * @return CMainMenuElement
	 */
	public function asMainMenu($options = []) {
		return $this->cast(CMainMenuElement::class, $options);
	}
}
