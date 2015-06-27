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
		$hosts = API::Host()->get([
			'output' => ['hostid', 'status'],
			'selectGraphs' => ($this->screenitem['style'] == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
			'selectScreens' => ($this->screenitem['style'] == STYLE_LEFT) ? API_OUTPUT_COUNT : null,
			'groupids' => $this->screenitem['resourceid'],
			'preservekeys' => true
		]);

		$hostIds = array_keys($hosts);

		$options = [
			'output' => [
				'triggerid', 'expression', 'description', 'url', 'value', 'priority', 'lastchange', 'flags'
			],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
			'hostids' => $hostIds,
			'monitored' => true,
			'skipDependent' => true,
			'sortfield' => 'description',
			'preservekeys' => true
		];

		// application filter
		if ($this->screenitem['application'] !== '') {
			$applications = API::Application()->get([
				'output' => ['applicationid'],
				'hostids' => $hostIds,
				'search' => ['name' => $this->screenitem['application']]
			]);
			$options['applicationids'] = zbx_objectValues($applications, 'applicationid');
		}

		$triggers = API::Trigger()->get($options);

		$triggers = CMacrosResolverHelper::resolveTriggerUrl($triggers);

		return $this->getOutput(getTriggersOverview($hosts, $triggers, $this->pageFile, $this->screenitem['style'],
			$this->screenid
		));
	}
}
