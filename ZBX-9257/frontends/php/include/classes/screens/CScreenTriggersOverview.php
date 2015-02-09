<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CScreenTriggersOverview extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		// fetch hosts
		$hosts = API::Host()->get(array(
			'output' => array('hostid', 'status'),
			'selectGraphs' => ($this->screenitem['style'] == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
			'selectScreens' => ($this->screenitem['style'] == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
			'groupids' => $this->screenitem['resourceid'],
			'preservekeys' => true
		));

		$hostIds = array_keys($hosts);

		$options = array(
			'output' => array(
				'description', 'expression', 'priority', 'url', 'value', 'triggerid', 'lastchange', 'flags'
			),
			'selectHosts' => array('hostid', 'name', 'status'),
			'selectItems' => array('itemid', 'hostid', 'name', 'key_', 'value_type'),
			'hostids' => $hostIds,
			'monitored' => true,
			'skipDependent' => true,
			'sortfield' => 'description'
		);

		// application filter
		if ($this->screenitem['application'] !== '') {
			$applications = API::Application()->get(array(
				'output' => array('applicationid'),
				'hostids' => $hostIds,
				'search' => array('name' => $this->screenitem['application'])
			));
			$options['applicationids'] = zbx_objectValues($applications, 'applicationid');
		}

		$triggers = API::Trigger()->get($options);

		return $this->getOutput(getTriggersOverview($hosts, $triggers, $this->pageFile, $this->screenitem['style'],
			$this->screenid
		));
	}
}
