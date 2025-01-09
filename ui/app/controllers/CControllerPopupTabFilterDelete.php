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


/**
 * Controller to delete tab filter by idx and idx2.
 */
class CControllerPopupTabFilterDelete extends CController {

	protected function checkInput() {
		$rules = [
			'idx' =>	'string|required',
			'idx2' =>	'int32|required'
		];

		$ret = $this->validateInput($rules);

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

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$idx = $this->getInput('idx');
		$idx2 = $this->getInput('idx2');

		(new CTabFilterProfile($idx, []))
			->read()
			->deleteTab((int) $idx2)
			->update();

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([])]));
	}
}
