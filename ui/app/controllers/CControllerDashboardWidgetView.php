<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


use Zabbix\Core\CWidget;

use Zabbix\Widgets\CWidgetForm;

/**
 * Class containing methods for operations with widgets.
 */
class CControllerDashboardWidgetView extends CController {

	protected ?CWidget $widget;
	protected CWidgetForm $form;

	protected array $validation_rules = [];
	protected array $fields_values = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'array',
			'templateid' => 'db dashboard.templateid'
		]);
	}

	protected function setValidationRules(array $validation_rules): self {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	protected function addValidationRules(array $validation_rules): self {
		$this->validation_rules = array_merge($this->validation_rules, $validation_rules);

		return $this;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function checkInput(): bool {
		$this->widget = APP::ModuleManager()->getActionModule();

		$ret = $this->validateInput($this->validation_rules);

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

		if ($ret) {
			$this->fields_values = $this->form->getFieldsValues();
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function getForm(): CWidgetForm {
		return $this->form;
	}

	protected function doAction(): void {
		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	protected function isTemplateDashboard(): bool {
		return $this->hasInput('templateid');
	}
}
