<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$config = select_config();

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
			'show_timeline' => $fields['show_timeline']
		];

		$data = getSystemStatusData($filter);

		$severity_names = [
			'severity_name_0' => $config['severity_name_0'],
			'severity_name_1' => $config['severity_name_1'],
			'severity_name_2' => $config['severity_name_2'],
			'severity_name_3' => $config['severity_name_3'],
			'severity_name_4' => $config['severity_name_4'],
			'severity_name_5' => $config['severity_name_5']
		];

		if ($filter['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS) {
			$data['groups'] = getSystemStatusTotals($data, $severity_names);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'data' => $data,
			'severity_names' => $severity_names,
			'filter' => $filter,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
