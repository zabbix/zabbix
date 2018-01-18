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


/**
 * This controller is used by gtlc.js to update timeline state in user's profile.
 */
class CControllerTimelineUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'idx' =>		'required|string',
			'idx2' =>		'required|id',
			'stime' =>		'string',
			'period' =>		'int32',
			'isNow' =>		'int32|in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('idx')) {
				case 'web.dashbrd':
				case 'web.screens':
				case 'web.graphs':
				case 'web.httptest':
				case 'web.problem.timeline':
				case 'web.item.graph':
				case 'web.auditlogs.timeline':
				case 'web.slides':
					$ret = true;
					break;

				default:
					$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => '']));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		calculateTime([
			'profileIdx' => $this->getInput('idx'),
			'profileIdx2' => $this->getInput('idx2'),
			'updateProfile' => true,
			'period' => $this->getInput('period'),
			'stime' => $this->getInput('stime'),
			'isNow' => $this->getInput('isNow')
		]);

		$this->setResponse(new CControllerResponseData(['main_block' => '']));
	}
}
