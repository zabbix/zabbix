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


use Zabbix\Core\CWidget;

use Zabbix\Widgets\CWidgetForm;

/**
 * Class containing methods for operations with widgets.
 */
abstract class CControllerDashboardWidgetView extends CController {

	protected ?CWidget $widget;

	private array $validation_rules = [];

	private CWidgetForm $form;

	/**
	 * Initialization function.
	 */
	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	/**
	 * Set validation rules for input parameters.
	 *
	 * @param array $validation_rules  Validation rules for input parameters.
	 */
	protected function setValidationRules(array $validation_rules): object {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	/**
	 * Check user permissions.
	 */
	protected function checkPermissions() {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	/**
	 * Validate input parameters.
	 *
	 * @return bool
	 */
	protected function checkInput() {
		$this->widget = APP::ModuleManager()->getModuleByActionName($this->getAction());

		$validation_rules = $this->validation_rules;

		if ($this->widget->isSupportedInTemplate()) {
			$validation_rules['templateid'] = 'db dashboard.templateid';
		}

		$ret = $this->validateInput($validation_rules);

		if ($ret) {
			$this->form = $this->widget->getForm($this->getInput('fields', []),
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
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function getForm(): CWidgetForm {
		return $this->form;
	}
}
