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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetProblemsBySvView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_PROBLEMS_BY_SV);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$filter = [
			'groupids' => getSubGroups($fields['groupids']),
			'exclude_groupids' => getSubGroups($fields['exclude_groupids']),
			'hostids' => $fields['hostids'],
			'problem' => $fields['problem'],
			'severities' => $fields['severities'],
			'show_type' => $fields['show_type'],
			'layout' => $fields['layout'],
			'show_suppressed' => $fields['show_suppressed'],
			'hide_empty_groups' => $fields['hide_empty_groups'],
			'show_opdata' => $fields['show_opdata'],
			'ext_ack' => $fields['ext_ack'],
			'show_timeline' => $fields['show_timeline'],
			'evaltype' => $fields['evaltype'],
			'tags' => $fields['tags']
		];

		$data = getSystemStatusData($filter);

		if ($filter['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS) {
			$data['groups'] = getSystemStatusTotals($data);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'initial_load' => (bool) $this->getInput('initial_load', 0),
			'data' => $data,
			'filter' => $filter,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'allowed' => $data['allowed']
		]));
	}
}
