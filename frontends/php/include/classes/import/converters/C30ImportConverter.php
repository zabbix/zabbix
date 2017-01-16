<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Converter for converting import data from 3.0 to 3.2
 */
class C30ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '3.2';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertHosts($data['zabbix_export']['hosts']);
		}
		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = $this->convertHosts($data['zabbix_export']['templates']);
		}
		if (array_key_exists('triggers', $data['zabbix_export'])) {
			$data['zabbix_export']['triggers'] = $this->convertTriggers($data['zabbix_export']['triggers']);
		}
		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = $this->convertMaps($data['zabbix_export']['maps']);
		}

		return $data;
	}

	/**
	 * Convert hosts.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertHosts(array $hosts) {
		foreach ($hosts as &$host) {
			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = $this->convertDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	protected function convertDiscoveryRules(array $discovery_rules) {
		foreach ($discovery_rules as &$discovery_rule) {
			$discovery_rule['trigger_prototypes'] =
				$this->convertTriggers($discovery_rule['trigger_prototypes']);
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert triggers and trigger prototypes.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function convertTriggers(array $triggers) {
		foreach ($triggers as &$trigger) {
			$trigger['recovery_mode'] = ZBX_RECOVERY_MODE_EXPRESSION;
			$trigger['recovery_expression'] = '';
			$trigger['correlation_mode'] = ZBX_TRIGGER_CORRELATION_NONE;
			$trigger['correlation_tag'] = '';
			$trigger['tags'] = [];
			$trigger['manual_close'] = ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED;

			if (array_key_exists('dependencies', $trigger)) {
				$trigger['dependencies'] =
					$this->convertTriggerDependencies($trigger['dependencies']);
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Convert trigger and trigger prototype dependencies.
	 *
	 * @param array $dependencies
	 *
	 * @return array
	 */
	protected function convertTriggerDependencies(array $dependencies) {
		foreach ($dependencies as &$dependency) {
			$dependency['recovery_expression'] = '';
		}
		unset($dependency);

		return $dependencies;
	}

	/**
	 * Convert maps.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function convertMaps(array $maps) {
		foreach ($maps as &$map) {
			$map['selements'] = $this->convertMapElements($map['selements']);
			$map['links'] = $this->convertMapLinks($map['links']);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert map elements.
	 *
	 * @param array $selements
	 *
	 * @return array
	 */
	protected function convertMapElements(array $selements) {
		foreach ($selements as &$selement) {
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selement['element']['recovery_expression'] = '';
			}
		}
		unset($selement);

		return $selements;
	}

	/**
	 * Convert map links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	protected function convertMapLinks(array $links) {
		foreach ($links as &$link) {
			$link['linktriggers'] = $this->convertMapLinkTriggers($link['linktriggers']);
		}
		unset($link);

		return $links;
	}

	/**
	 * Convert map links.
	 *
	 * @param array $linktriggers
	 *
	 * @return array
	 */
	protected function convertMapLinkTriggers(array $linktriggers) {
		foreach ($linktriggers as &$linktrigger) {
			$linktrigger['trigger']['recovery_expression'] = '';
		}
		unset($linktrigger);

		return $linktriggers;
	}
}
