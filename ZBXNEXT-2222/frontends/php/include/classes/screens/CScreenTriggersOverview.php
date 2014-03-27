<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
			'output' => array('name', 'hostid', 'status'),
			'selectScreens' => ($this->screenitem['style'] == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
			'groupids' => $this->screenitem['resourceid'],
			'preservekeys' => true
		));

		// application filter
		$applications = array();
		if ($this->screenitem['application'] !== '') {
			$applications = API::Application()->get(array(
				'output' => array('applicationid'),
				'hostids' => zbx_objectValues($hosts, 'hostid'),
				'search' => array('name' => $this->screenitem['application'])
			));
		}

		$triggers = API::Trigger()->get(array(
			'output' => array(
				'description', 'expression', 'priority', 'url', 'value', 'triggerid', 'lastchange', 'flags'
			),
			'selectHosts' => array('hostid', 'name'),
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'applicationids' => $applications ? zbx_objectValues($applications, 'applicationid') : null,
			'monitored' => true,
			'skipDependent' => true,
			'sortfield' => 'description'
		));

		return $this->getOutput(getTriggersOverview($hosts, $triggers, $this->pageFile, $this->screenitem['style'],
			$this->screenid
		));
	}
}
