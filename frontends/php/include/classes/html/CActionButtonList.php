<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class CActionButtonList
 *
 * Implements wrapper to handle output of mass action buttons as used in list views.
 */
class CActionButtonList extends CObject {

	/**
	 * CSubmit instances.
	 *
	 * @var CSubmit[]
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
	 * @param string		$actionName				name of submit buttons
	 * @param string		$checkboxesName			Name of parameter into which checked checkboxes will be put in.
	 * @param array			$buttonsData			buttons data array
	 * @param string		$buttonsData['name']	button caption
	 * @param string		$buttonsData['confirm']	confirmation text (optional)
	 * @param string|null	$cookieNamePrefix		Prefix for cookie used for storing currently selected checkboxes.
	 */
	function __construct($actionName, $checkboxesName, array $buttonsData, $cookieNamePrefix = null) {
		$this->checkboxesName = $checkboxesName;
		$this->cookieNamePrefix = $cookieNamePrefix;

		foreach ($buttonsData as $action => $buttonData) {
			$this->buttons[$action] = (new CSubmit($actionName, $buttonData['name']))
				->addClass(ZBX_STYLE_BTN_ALT)
				->removeAttribute('id')
				->setAttribute('value', $action);

			if (array_key_exists('confirm', $buttonData)) {
				$this->buttons[$action]->setAttribute('confirm', $buttonData['confirm']);
			}
		}
	}

	/**
	 * Returns current element for showing how many checkboxes are selected. If currently no
	 * element exists, constructs and returns default one.
	 *
	 * @return CObject
	 */
	public function getSelectedCountElement() {
		if (!$this->selectedCountElement) {
			$this->selectedCountElement = (new CSpan('0 '._('selected')))
				->setId('selected_count')
				->addClass(ZBX_STYLE_SELECTED_ITEM_COUNT);
		}

		return $this->selectedCountElement;
	}

	/**
	 * Gets string representation of action button list.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		zbx_add_post_js('chkbxRange.pageGoName = '.CJs::encodeJson($this->checkboxesName).';');
		zbx_add_post_js('chkbxRange.prefix = '.CJs::encodeJson($this->cookieNamePrefix).';');

		$this->items[] = (new CDiv([$this->getSelectedCountElement(), $this->buttons]))
			->setId('action_buttons')
			->addClass(ZBX_STYLE_ACTION_BUTTONS);

		return parent::toString($destroy);
	}
}
