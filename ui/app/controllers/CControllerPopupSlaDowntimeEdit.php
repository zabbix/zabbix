<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupSlaDowntimeEdit extends CController {

	protected function checkInput() {
		$fields = [
			'row_index' =>			'ge 0',
			'name' =>				'string',
			'start_time' =>			'range_time',
			'duration_days' =>		'ge 0',
			'duration_hours' =>		'in '.implode(',', range(0, 23)),
			'duration_minutes' =>	'in '.implode(',', range(0, 59))
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
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$data['form'] = $this->getInputAll();
		unset($data['form']['row_index']);

		if (implode('', $data['form']) === '') {
			$buttons = [
				[
					'title' => _('Add'),
					'class' => 'js-add',
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'sla_edit.downtime.submit();'
				]
			];
		}
		else {
			$buttons = [
				[
					'title' => _('Update'),
					'class' => 'js-update',
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'sla_edit.downtime.submit();'
				]
			];
		}

		$data['form'] += [
			'name' => '',
			'start_time' => '',
			'duration_days' => 0,
			'duration_hours' => 0,
			'duration_minutes' => 0,
			'row_index' => $this->getInput('row_index')
		];

		$data = array_merge($data, [
			'user' => ['debug_mode' => $this->getDebugMode()],
			'buttons' => $buttons,
			'errors' => null
		]);

		$this->setResponse(new CControllerResponseData($data));
	}
}
