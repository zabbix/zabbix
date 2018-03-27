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


class CControllerPopupTrigDescView extends CController {
	private $trigger;

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'triggerid' =>	'db triggers.triggerid',
			'success'	=>	'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$trigger = API::Trigger()->get([
			'output' => API_OUTPUT_EXTEND,
			'triggerids' => $this->getInput('triggerid')
		]);

		if (!$trigger) {
			return false;
		}

		$this->trigger = reset($trigger);

		return true;
	}

	protected function doAction() {
		$trigger_editable = API::Trigger()->get([
			'output' => [],
			'triggerids' => $this->trigger['triggerid'],
			'filter' => [
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL
			],
			'editable' => true
		]);

		if ($this->hasInput('success')) {
			info(_('Description updated'));
		}

		$data = [
			'title' => _('Trigger description'),
			'trigger' => $this->trigger,
			'isTriggerEditable' => (boolean) $trigger_editable,
			'isCommentExist' => ($this->trigger['comments'] !== ''),
			'resolved' => CMacrosResolverHelper::resolveTriggerDescription($this->trigger)
		];

		if (($messages = getMessages($this->hasInput('success'))) !== null) {
			$data['messages'] = $messages;
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
