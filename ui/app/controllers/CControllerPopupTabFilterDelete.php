<?php declare(strict_types = 1);
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
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
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
