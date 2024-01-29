<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerPopupImport extends CController {

	protected function checkInput() {
		$fields = [
			'import' => 'in 1',
			'rules_preset' => 'required|in host,template,mediatype,map',
			'rules' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
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
					'groups' => ['updateExisting' => true],
					'hosts' => ['updateExisting' => true, 'createMissing' => true],
					'valueMaps' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'templateLinkage' => ['createMissing' => true, 'deleteMissing' => false],
					'items' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'discoveryRules' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'triggers' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'graphs' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'httptests' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false]
				];

				if ($user_type == USER_TYPE_SUPER_ADMIN) {
					$rules['groups']['createMissing'] = true;
				}
				break;

			case 'template':
				$rules = [
					'groups' => ['updateExisting' => true],
					'templates' => ['updateExisting' => true, 'createMissing' => true],
					'valueMaps' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'templateDashboards' => ['updateExisting' => true, 'createMissing' => true,
						'deleteMissing' => false
					],
					'templateLinkage' => ['createMissing' => true, 'deleteMissing' => false],
					'items' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'discoveryRules' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'triggers' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'graphs' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false],
					'httptests' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false]
				];

				if ($user_type == USER_TYPE_SUPER_ADMIN) {
					$rules['groups']['createMissing'] = true;
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
				$messages = CMessageHelper::getMessages();
				$output = ['title' => _('Imported successfully')];
				if (count($messages)) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				CMessageHelper::setErrorTitle(_('Import failed'));

				$output['errors'] = [
					'title' => CMessageHelper::getTitle(),
					'messages' => array_column(filter_messages(), 'message')
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$this->setResponse(new CControllerResponseData([
				'title' => _('Import'),
				'rules' => $rules,
				'rules_preset' => $this->getInput('rules_preset'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
	}
}
