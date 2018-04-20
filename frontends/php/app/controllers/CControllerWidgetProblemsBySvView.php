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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';
require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetProblemsBySvView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_PROBLEMS_BY_SV);
		$this->setValidationRules([
			'name' => 'string',
			'fullscreen' => 'in 0,1',
			'kioskmode' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fullscreen = (bool) $this->getInput('fullscreen', false);
		$kioskmode = $fullscreen && (bool) $this->getInput('kioskmode', false);

		$fields = $this->getForm()->getFieldsData();
		$config = select_config();
		$filter = [
			'groupids' => getSubGroups($fields['groupids']),
			'hostids' => $fields['hostids'],
			'exclude_groupids' => getSubGroups($fields['exclude_groupids']),
			'problem' => $fields['problem'],
			'severities' => $fields['severities'],
			'maintenance' => $fields['maintenance'],
			'hide_empty_groups' => $fields['hide_empty_groups'],
			'ext_ack' => $fields['ext_ack']
		];

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'data' => getSystemStatusData($filter, ['event_ack_enable' => $config['event_ack_enable']]),
			'config' => [
				'event_ack_enable' => $config['event_ack_enable'],
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'filter' => $filter,
			'fullscreen' => $fullscreen,
			'kioskmode' => $kioskmode,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
