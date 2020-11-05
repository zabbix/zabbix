<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/regexp.inc.php';

class CControllerRegExTest extends CController {

	protected function checkInput() {
		$fields = [
			'ajaxdata' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$response = new CAjaxResponse();
		$data = $this->getInput('ajaxdata', []);

		$result = [
			'expressions' => $this->getInput('expressions', []),
			'errors' => [],
			'final' => true
		];

		if (array_key_exists('expressions', $data)) {
			foreach ($data['expressions'] as $id => $expression) {
				try {
					validateRegexp([$expression]);
					$result['expressions'][$id] = CGlobalRegexp::matchExpression($expression, $data['testString']);
					$result['final'] = $result['final'] && $result['expressions'][$id];
				}
				catch (Exception $e) {
					$result['errors'][$id] = $e->getMessage();
					$result['final'] = false;
				}
			}
		}

		$response->success($result);
		$response->send();
	}
}
