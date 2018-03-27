<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerTrigDescUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'triggerid' => 'db triggers.triggerid',
			'comments' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (bool) API::Trigger()->get([
			'output' => [],
			'triggerids' => $this->getInput('triggerid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$result = API::Trigger()->update([
			'triggerid' => $this->getInput('triggerid'),
			'comments' => $this->getInput('comments')
		]);

		if (!$result) {
			error(_('Cannot update description'));
		}

		$output = [];
		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))
		);
	}
}
