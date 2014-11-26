<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class CGoButtonGroup
 *
 * Implements wrapper to handle output of mass action buttons as used in list views.
 */
class CGoButtonGroup extends CObject {

	/**
	 * CGoButton instances.
	 *
	 * @var CGoButton[]
	 */
	protected $buttons;

	/**
	 * Name of parameter which will hold values of checked checkboxes.
	 *
	 * @var string
	 */
	protected $checkboxesName;

	/**
	 * Prefix for cookies used for remembering which checkboxes have been checked when navigating between pages.
	 *
	 * @var string|null
	 */
	protected $cookieNamePrefix = null;

	/**
	 * Element that is used to show number of selected checkboxes.
	 *
	 * @var CObject
	 */
	protected $selectedCountElement = null;

	/**
	 * @param string      $actionName        name of submit buttons
	 * @param string      $checkboxesName    name of paramerer into which checked checkboxes will be put in
	 * @param array       $buttonsData       buttons data array - can be:
	 *                                       - a string and is used as caption,
	 *                                       - an array with 2 elements - first is used as caption, second is
	 *                                         used as confirmation text
	 * @param string|null $cookieNamePrefix  prefix for cookie used for storing currently selected checkboxes.
	 */
	function __construct($actionName, $checkboxesName, array $buttonsData, $cookieNamePrefix = null) {
		$this->checkboxesName = $checkboxesName;
		$this->cookieNamePrefix = $cookieNamePrefix;

		foreach ($buttonsData as $actionValue => $buttonData) {
			if ($buttonData) {
				$this->buttons[$actionValue] = new CGoButton(
					$actionName,
					$actionValue,
					is_array($buttonData) ? $buttonData[0] : $buttonData,
					is_array($buttonData) && isset($buttonData[1]) ? $buttonData[1] : false
				);
			}
		}
	}

	/**
	 * Sets custom element to be used to show how many checkboxes are selected.
	 * @param CObject $selectedCountSpan
	 */
	public function setSelectedCountElement(CObject $selectedCountSpan) {
		$this->selectedCountElement = $selectedCountSpan;
	}

	/**
	 * Returns current element for showing how many checkboxes are selected. If currently no
	 * element exists, constructs and returns default one.
	 *
	 * @return CObject
	 */
	public function getSelectedCountElement() {
		if (!$this->selectedCountElement) {
			$this->selectedCountElement = new CSpan('0 '._('selected'), null, 'selectedCount');
		}

		return $this->selectedCountElement;
	}

	/**
	 * Gets string representation of go button group.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		zbx_add_post_js('chkbxRange.pageGoName = '.CJs::encodeJson($this->checkboxesName).';');
		zbx_add_post_js('chkbxRange.prefix = '.CJs::encodeJson($this->cookieNamePrefix).';');

		$this->items[] = $this->getSelectedCountElement()->toString($destroy);

		foreach ($this->buttons as $button) {
			$this->items[] = $button->toString($destroy);
		}

		return parent::toString($destroy);
	}

	/**
	 * Returns all go buttons.
	 *
	 * @return CGoButton[]
	 */
	public function getButtons() {
		return $this->buttons;
	}

	/**
	 * Returns go button for action value.
	 *
	 * @param string|int $actionValue
	 *
	 * @return CGoButton|null
	 */
	public function getButton($actionValue) {
		return isset($this->buttons[$actionValue])
			? $this->buttons[$actionValue]
			: null;
	}

	/**
	 * Sets go buttons.
	 *
	 * @param CGoButton[] $buttons
	 */
	public function setButtons(array $buttons) {
		$this->buttons = $buttons;
	}
}
