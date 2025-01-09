<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	protected $checkboxes_name;

	/**
	 * Prefix for sessionStorage used for remembering which checkboxes have been checked when navigating between pages.
	 *
	 * @var string|null
	 */
	protected $name_prefix = null;

	/**
	 * Element that is used to show number of selected checkboxes.
	 *
	 * @var CObject
	 */
	protected $selected_count_element = null;

	/**
	 * @param string       $action_name			Name of submit buttons.
	 * @param string       $checkboxes_name		Name of parameter into which checked checkboxes will be put in.
	 * @param array        $buttons_data		Buttons data array.
	 * @param string|null  $name_prefix			Prefix for sessionStorage used for storing currently selected
	 *                                          checkboxes.
	 *
	 * $buttons_data = [[
	 * 		'name' =>				(string)	Button caption.
	 * 		'confirm_singular' =>	(string)	Confirmation text in the singular (optional). If this is provided,
	 *                                  		'confirm_plural' also must be provided.
	 * 		'confirm_plural' =>		(string)	Confirmation text in the plural (optional). If this is provided,
	 *                                  		'confirm_singular' also must be provided.
	 * 		'redirect' =>			(string)	Redirect URL (optional).
	 * 		'csrf_token' =>			(string)	CSRF token (optional).
	 * 		'disabled' =>			(bool)		Set button state disabled (optional).
	 * 		'attributes' =>			(array)		Set additional HTML attributes where array key is attribute name array
	 * 											value is the attribute value.
	 * 		'content' =>			(CTag)		A HTML tag. For example a CButton wrapped in CList object.
	 * ]]
	 */
	function __construct($action_name, $checkboxes_name, array $buttons_data, $name_prefix = null) {
		$this->checkboxes_name = $checkboxes_name;
		$this->name_prefix = $name_prefix;

		foreach ($buttons_data as $action => $button_data) {
			if (array_key_exists('content', $button_data)) {
				$button = $button_data['content'];
			}
			else {
				$button = (new CSubmit($action_name, $button_data['name']))
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id');

				if (array_key_exists('attributes', $button_data) && is_array($button_data['attributes'])
						&& $button_data['attributes']) {
					foreach ($button_data['attributes'] as $attr_name => $attr_value) {
						$button->setAttribute($attr_name, $attr_value);
					}
				}

				if (array_key_exists('redirect', $button_data)) {
					$on_click_action = 'const form = this.closest("form");' .
						/*
						 * Save the original form action
						 * Function getAttribute()/setAttribute() is used instead of .action, because there are many
						 * buttons with name 'action' and .action selects these buttons.
						 */
						'if (!form.dataset.action) {
							form.dataset.action = form.getAttribute("action");
						}
						form.setAttribute("action", this.dataset.redirect);';

					$button
						// Removing parameters not to conflict with the redirecting URL.
						->removeAttribute('name')
						->removeAttribute('value')
						->setAttribute('data-redirect', $button_data['redirect']);
				}
				else {
					$on_click_action = 'const form = this.closest("form");'.
						// Restore the original form action, if previously saved.
						'if (form.dataset.action) {
							form.setAttribute("action", form.dataset.action);
						}';

					$button
						->setAttribute('value', $action);
				}

				if (array_key_exists('csrf_token', $button_data)) {
					$on_click_action .= 'create_var(form,"'.CSRF_TOKEN_NAME.'", "'.
						$button_data['csrf_token'].'", false);';
				}

				$button->onClick($on_click_action);

				if (array_key_exists('disabled', $button_data)) {
					$button
						->setEnabled(!$button_data['disabled'])
						->setAttribute('data-disabled', $button_data['disabled']);
				}

				if (array_key_exists('confirm_singular', $button_data)
						&& array_key_exists('confirm_plural', $button_data)) {
					$button->setAttribute('confirm_singular', $button_data['confirm_singular']);
					$button->setAttribute('confirm_plural', $button_data['confirm_plural']);
				}
			}

			$this->buttons[$action] = $button;
		}
	}

	/**
	 * Returns current element for showing how many checkboxes are selected. If currently no
	 * element exists, constructs and returns default one.
	 *
	 * @return CObject
	 */
	public function getSelectedCountElement() {
		if (!$this->selected_count_element) {
			$this->selected_count_element = (new CSpan('0 '._('selected')))
				->setId('selected_count')
				->addClass(ZBX_STYLE_SELECTED_ITEM_COUNT);
		}

		return $this->selected_count_element;
	}

	/**
	 * Gets string representation of action button list.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		zbx_add_post_js('chkbxRange.pageGoName = '.json_encode($this->checkboxes_name).';');
		zbx_add_post_js('chkbxRange.prefix = '.json_encode($this->name_prefix).';');

		$this->items[] = (new CDiv([$this->getSelectedCountElement(), $this->buttons]))
			->setId('action_buttons')
			->addClass(ZBX_STYLE_ACTION_BUTTONS);

		return parent::toString($destroy);
	}
}
