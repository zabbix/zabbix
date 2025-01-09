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


class CControllerMacrosUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'macros' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS);
	}

	protected function doAction() {
		/** @var array $macros */
		$macros = $this->getInput('macros', []);
		foreach ($macros as &$macro) {
			$macro['macro'] = trim($macro['macro']);

			if (array_key_exists('value', $macro)) {
				$macro['value'] = trim($macro['value']);
			}

			$macro['description'] = trim($macro['description']);
		}
		unset($macro);

		foreach ($macros as $idx => $macro) {
			if (!array_key_exists('globalmacroid', $macro) && $macro['macro'] === ''
					&& (!array_key_exists('value', $macro) || $macro['value'] === '') && $macro['description'] === '') {
				unset($macros[$idx]);
			}
		}

		$db_macros = API::UserMacro()->get([
			'output' => ['globalmacroid', 'macro', 'value', 'type', 'description'],
			'globalmacro' => true,
			'preservekeys' => true
		]);

		$macros_to_update = [];
		foreach ($macros as $idx => $macro) {
			if (array_key_exists('globalmacroid', $macro) && array_key_exists($macro['globalmacroid'], $db_macros)) {
				$dbMacro = $db_macros[$macro['globalmacroid']];

				// Remove item from new macros array.
				unset($macros[$idx], $db_macros[$macro['globalmacroid']]);

				// If the macro is unchanged - skip it.
				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
					if (!array_key_exists('value', $macro)) {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}
				else {
					if ($dbMacro['type'] == ZBX_MACRO_TYPE_SECRET) {
						if ($dbMacro['macro'] === $macro['macro']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
					else {
						if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['value'] === $macro['value']
								&& $dbMacro['type'] == $macro['type']
								&& $dbMacro['description'] === $macro['description']) {
							continue;
						}
					}
				}

				$macros_to_update[] = $macro;
			}
		}

		$result = true;

		if ($macros_to_update || $db_macros || $macros) {
			DBstart();

			if ($macros_to_update) {
				$result = (bool) API::UserMacro()->updateGlobal($macros_to_update);
			}

			if ($db_macros) {
				$result = $result && (bool) API::UserMacro()->deleteGlobal(array_keys($db_macros));
			}

			if ($macros) {
				$result = $result && (bool) API::UserMacro()->createGlobal(array_values($macros));
			}

			$result = DBend($result);
		}

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'macros.edit'));
		if ($result) {
			CMessageHelper::setSuccessTitle(_('Macros updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update macros'));
			$form_data = $this->getInputAll();
			$form_data['macros'] = array_values($form_data['macros']);
			$response->setFormData($form_data);
		}

		$this->setResponse($response);
	}
}
