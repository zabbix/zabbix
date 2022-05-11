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
 * Class containing methods for operations with widgets.
 */
abstract class CControllerWidget extends CController {

	/**
	 * @var int $type  Widget type WIDGET_*.
	 */
	private $type;

	/**
	 * @var array $validation_rules  Validation rules for input parameters.
	 */
	private $validation_rules = [];

	/**
	 * @var object $form  CWidgetForm object.
	 */
	private $form;

	/**
	 * Initialization function.
	 */
	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	/**
	 * Check user permissions.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	/**
	 * Set widget type.
	 *
	 * @param int $type  Widget type WIDGET_*.
	 *
	 * @return object
	 */
	protected function setType($type) {
		$this->type = $type;

		return $this;
	}

	protected function getContext(): string {
		return $this->hasInput('templateid')
			? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
			: CWidgetConfig::CONTEXT_DASHBOARD;
	}

	/**
	 * Set validation rules for input parameters.
	 *
	 * @param array $validation_rules  Validation rules for input parameters.
	 *
	 * @return object
	 */
	protected function setValidationRules(array $validation_rules) {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	/**
	 * Returns default widget name.
	 *
	 * @return string
	 */
	protected function getDefaultName() {
		return CWidgetConfig::getKnownWidgetTypes($this->getContext())[$this->type];
	}

	/**
	 * Validate input parameters.
	 *
	 * @return bool
	 */
	protected function checkInput() {
		$validation_rules = $this->validation_rules;

		if (CWidgetConfig::isWidgetTypeSupportedInContext($this->type, CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD)) {
			$validation_rules['templateid'] = 'db dashboard.templateid';
		}

		$ret = $this->validateInput($validation_rules);

		if ($ret) {
			$this->form = CWidgetConfig::getForm($this->type, $this->getInput('fields', '{}'),
				$this->hasInput('templateid') ? $this->getInput('templateid') : null
			);

			if ($errors = $this->form->validate()) {
				foreach ($errors as $error) {
					error($error);
				}

				$ret = false;
			}
		}

		if (!$ret) {
			$output = [
				'name' => $this->getDefaultName(),
				'messages' => getMessages()->toString()
			];

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * Returns form object.
	 *
	 * @return object
	 */
	protected function getForm() {
		return $this->form;
	}
}
