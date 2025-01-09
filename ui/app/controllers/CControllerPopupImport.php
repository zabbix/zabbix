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


class CControllerPopupImport extends CController {

	protected function checkInput() {
		$fields = [
			'import' => 'in 1',
			'rules_preset' => 'required|in host,template,mediatype,map',
			'rules' => 'array'
		];

		$ret = $this->validateInput($fields);

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

	protected function checkPermissions() {
		$user_type = $this->getUserType();

		switch ($this->getInput('rules_preset')) {
			case 'map' :
				return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS);

			case 'host':
			case 'template':
				return ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN);

			case 'mediatype':
				return $user_type === USER_TYPE_SUPER_ADMIN;
		}
	}

	protected function doAction() {
		$user_type = $this->getUserType();

		// Adjust defaults for given rule preset, if specified.
		switch ($this->getInput('rules_preset')) {
			case 'host':
				$rules = [
					'host_groups' => ['updateExisting' => true],
					'hosts' => ['updateExisting' => true, 'createMissing' => true],
					'valueMaps' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'templateLinkage' => ['createMissing' => true, 'deleteMissing' => true],
					'items' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'discoveryRules' => ['updateExisting' => true, 'createMissing' => true,
						'deleteMissing' => true
					],
					'triggers' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'graphs' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'httptests' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true]
				];

				if ($user_type == USER_TYPE_SUPER_ADMIN) {
					$rules['host_groups']['createMissing'] = true;
				}
				break;

			case 'template':
				$rules = [
					'host_groups' => ['updateExisting' => true],
					'template_groups' => ['updateExisting' => true],
					'templates' => ['updateExisting' => true, 'createMissing' => true],
					'valueMaps' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'templateDashboards' => ['updateExisting' => true, 'createMissing' => true,
						'deleteMissing' => true
					],
					'templateLinkage' => ['createMissing' => true, 'deleteMissing' => true],
					'items' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'discoveryRules' => ['updateExisting' => true, 'createMissing' => true,
						'deleteMissing' => true
					],
					'triggers' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'graphs' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
					'httptests' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true]
				];

				if ($user_type == USER_TYPE_SUPER_ADMIN) {
					$rules['host_groups']['createMissing'] = true;
					$rules['template_groups']['createMissing'] = true;
				}
				break;

			case 'mediatype':
				$rules = [
					'mediaTypes' => ['updateExisting' => false, 'createMissing' => true]
				];
				break;

			case 'map':
				$rules = [
					'maps' => ['updateExisting' => true, 'createMissing' => true]
				];

				if ($user_type == USER_TYPE_SUPER_ADMIN) {
					$rules += [
						'images' => ['updateExisting' => false, 'createMissing' => true]
					];
				}
				break;
		}

		if ($this->hasInput('import')) {
			$request_rules = array_intersect_key($this->getInput('rules', []), $rules);
			$request_rules += array_fill_keys(array_keys($rules), []);
			$options = array_fill_keys(['updateExisting', 'createMissing', 'deleteMissing'], false);

			foreach ($request_rules as $rule_name => &$rule) {
				$rule = array_map('boolval', array_intersect_key($rule + $options, $rules[$rule_name]));
			}
			unset($rule);

			$result = false;

			if (!isset($_FILES['import_file'])) {
				error(_('No file was uploaded.'));
			}
			else {
				// CUploadFile throws exceptions, so we need to catch them
				try {
					$file = new CUploadFile($_FILES['import_file']);

					$result = API::Configuration()->import([
						'format' => CImportReaderFactory::fileExt2ImportFormat($file->getExtension()),
						'source' => $file->getContent(),
						'rules' => $request_rules
					]);
				}
				catch (Exception $e) {
					error($e->getMessage());
				}
			}

			$output = [];

			if ($result) {
				$output['success']['title'] = _('Imported successfully');

				if ($messages = get_and_clear_messages()) {
					$output['success']['messages'] = array_column($messages, 'message');
				}
			}
			else {
				$output['error'] = [
					'title' => _('Import failed'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				];
			}

			$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
		}
		else {
			$this->setResponse(new CControllerResponseData([
				'title' => _('Import'),
				'rules' => $rules,
				'rules_preset' => $this->getInput('rules_preset'),
				'advanced_config' => in_array($this->getInput('rules_preset'), ['host', 'template']),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
	}
}
