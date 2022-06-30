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


class CControllerPopupImport extends CController {

	protected function checkInput() {
		$fields = [
			'import' => 'in 1',
			'rules_preset' => 'in host,template,mediatype,map',
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

		switch ($this->getInput('rules_preset', '')) {
			case 'map' :
				return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS);

			case 'host':
			case 'template':
			case 'mediatype':
				return ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN);

			default:
				return false;
		}
	}

	protected function doAction() {
		$rules = [];

		// Adjust defaults for given rule preset, if specified.
		switch ($this->getInput('rules_preset')) {
			case 'host':
				$rules['host_groups'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['hosts'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['valueMaps'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['templateLinkage'] = ['createMissing' => true, 'deleteMissing' => false];
				$rules['items'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['discoveryRules'] = ['updateExisting' => true, 'createMissing' => true,
					'deleteMissing' => false
				];
				$rules['triggers'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['graphs'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['httptests'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				break;

			case 'template':
				$rules['host_groups'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['template_groups'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['templates'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['valueMaps'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['templateDashboards'] = ['updateExisting' => true, 'createMissing' => true,
					'deleteMissing' => false
				];
				$rules['templateLinkage'] = ['createMissing' => true, 'deleteMissing' => false];
				$rules['items'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['discoveryRules'] = ['updateExisting' => true, 'createMissing' => true,
					'deleteMissing' => false
				];
				$rules['triggers'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['graphs'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['httptests'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				break;

			case 'mediatype':
				$rules['mediaTypes'] = ['updateExisting' => false, 'createMissing' => true];
				break;

			case 'map':
				$rules['maps'] = [
					'updateExisting' => CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS),
					'createMissing' => CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS)
				];
				$rules['images'] = ['updateExisting' => false, 'createMissing' => true];
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
			} else {
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
				'user' => [
					'type' => $this->getUserType(),
					'can_edit_maps' => CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS),
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
	}
}
