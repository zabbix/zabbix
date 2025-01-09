<?php declare(strict_types = 0);
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


class CControllerTemplateDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'templateids' =>	'required|array_db hosts.hostid',
			'clear' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$templateids = $this->getInput('templateids');
		$result = false;

		$writeble_templates = API::Template()->get([
			'output' => [],
			'templateids' => $templateids,
			'editable' => true,
			'countOutput' => true
		]);

		try {
			DBstart();

			if ($writeble_templates != count($templateids)) {
				error(_('No permissions to referred object or it does not exist!'));
				throw new Exception();
			}

			if (!$this->hasInput('clear')) {
				$hosts = API::Host()->get([
					'output' => [],
					'templateids' => $templateids,
					'preservekeys' => true
				]);

				if ($hosts) {
					$result = API::Host()->massRemove([
						'hostids' => array_keys($hosts),
						'templateids' => $templateids
					]);

					if (!$result) {
						throw new Exception();
					}
				}

				$templates = API::Template()->get([
					'output' => [],
					'parentTemplateids' => $templateids,
					'preservekeys' => true
				]);

				if ($templates) {
					$result = API::Template()->massRemove([
						'templateids' => array_keys($templates),
						'templateids_link' => $templateids
					]);

					if (!$result) {
						throw new Exception();
					}
				}
			}

			$result = API::Template()->delete($templateids);

			$result = DBend($result);
		}
		catch (Exception $e) {
			DBend(false);
		}

		if (!$result) {
			$templates = API::Template()->get([
				'output' => [],
				'templateids' => $templateids,
				'editable' => true,
				'preservekeys' => true
			]);

			$output['keepids'] = array_column($templates , 'hostid');
		}

		$templates_count = count($templateids);

		if ($result) {
			$success = ['title' => _n('Template deleted', 'Templates deleted', $templates_count)];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$success['action'] = 'delete';
			$output['success'] = $success;
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete template', 'Cannot delete templates', $templates_count));

			$output['error'] = [
				'title' => CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));

	}
}
