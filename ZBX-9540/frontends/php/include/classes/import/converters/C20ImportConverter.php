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


/**
 * Converter for converting import data from 1.8 to 2.0.
 */
class C20ImportConverter extends CConverter {

	/**
	 * Converter used for converting trigger expressions from 2.2 to 2.4 format.
	 *
	 * @var CConverter
	 */
	protected $triggerExpressionConverter;

	public function __construct() {
		$this->triggerExpressionConverter = new C20TriggerConverter();
	}

	public function convert($value) {
		$content = $value['zabbix_export'];

		$content['version'] = '3.0';

		$content = $this->convertHosts($content);
		$content = $this->convertTemplates($content);
		$content = $this->convertTriggers($content);
		$content = $this->convertScreens($content);
		$content = $this->convertMaps($content);

		$value['zabbix_export'] = $content;

		return $value;
	}

	/**
	 * Convert host elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertHosts(array $content) {
		if (!isset($content['hosts']) || !$content['hosts']) {
			return $content;
		}

		foreach ($content['hosts'] as &$host) {
			$host = $this->convertHostInterfaces($host);
			$host = $this->convertItems($host);
			$host = $this->convertDiscoveryRules($host);
		}
		unset($host);

		return $content;
	}

	/**
	 * Convert template elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertTemplates(array $content) {
		if (!isset($content['templates']) || !$content['templates']) {
			return $content;
		}

		foreach ($content['templates'] as &$template) {
			$template = $this->convertDiscoveryRules($template);
		}
		unset($template);

		return $content;
	}

	/**
	 * Convert item elements.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertItems(array $host) {
		if (!isset($host['items']) || !$host['items']) {
			return $host;
		}

		foreach ($host['items'] as &$item) {
			if (isset($item['status']) && $item['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$item['status'] = ITEM_STATUS_ACTIVE;
			}
		}
		unset($item);

		return $host;
	}

	/**
	 * Convert interface elements.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertHostInterfaces(array $host) {
		if (!isset($host['interfaces'])) {
			return $host;
		}

		foreach ($host['interfaces'] as &$interface) {
			if (!isset($interface['bulk']) && $interface['type'] == INTERFACE_TYPE_SNMP) {
				$interface['bulk'] = SNMP_BULK_ENABLED;
			}
		}
		unset($interface);

		return $host;
	}

	/**
	 * Convert trigger elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertTriggers(array $content) {
		if (!array_key_exists('triggers', $content)) {
			return $content;
		}

		foreach ($content['triggers'] as &$trigger) {
			if (array_key_exists('dependencies', $trigger)) {
				foreach ($trigger['dependencies'] as &$dependency) {
					$dependency['expression'] = $this->triggerExpressionConverter->convert($dependency['expression']);
				}
				unset($dependency);
			}

			$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);
		}
		unset($trigger);

		return $content;
	}

	/**
	 * Convert screen elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertScreens(array $content) {
		if (!isset($content['screens']) || !$content['screens']) {
			return $content;
		}

		foreach ($content['screens'] as &$screen) {
			if (isset($screen['screen_items']) && $screen['screen_items']) {
				foreach ($screen['screen_items'] as &$screenItem) {
					if ($screenItem['rowspan'] == 0) {
						$screenItem['rowspan'] = 1;
					}
					if ($screenItem['colspan'] == 0) {
						$screenItem['colspan'] = 1;
					}
				}
				unset($screenItem);
			}
		}
		unset($screen);

		return $content;
	}

	/**
	 * Convert map elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	protected function convertMaps(array $content) {
		if (!isset($content['maps']) || !$content['maps']) {
			return $content;
		}

		foreach ($content['maps'] as &$map) {
			if (array_key_exists('selements', $map)) {
				foreach ($map['selements'] as &$selement) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						$selement['element']['expression'] = $this->triggerExpressionConverter->convert(
							$selement['element']['expression']
						);
					}
				}
				unset($selement);
			}

				foreach ($map['links'] as &$link) {
					if (array_key_exists('linktriggers', $link)) {
						foreach ($link['linktriggers'] as &$linktrigger) {
							$linktrigger['trigger']['expression'] = $this->triggerExpressionConverter->convert(
								$linktrigger['trigger']['expression']
							);
						}
						unset($linktrigger);
					}
				}
				unset($link);
		}
		unset($map);

		return $content;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function convertDiscoveryRules(array $host) {
		if (!isset($host['discovery_rules']) || !$host['discovery_rules']) {
			return $host;
		}

		foreach ($host['discovery_rules'] as &$rule) {
			if (isset($rule['status']) && $rule['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$rule['status'] = ITEM_STATUS_ACTIVE;
			}

			if (!array_key_exists('host_prototypes', $rule)) {
				$rule['host_prototypes'] = [];
			}

			$rule = $this->convertDiscoveryRuleFilter($rule);
			$rule = $this->convertTriggerPrototypes($rule);
		}
		unset($rule);

		return $host;
	}

	/**
	 * Convert trigger prototype elements.
	 *
	 * @param array $rule
	 *
	 * @return array
	 */
	protected function convertTriggerPrototypes(array $rule) {
		if (!isset($rule['trigger_prototypes']) || !$rule['trigger_prototypes']) {
			return $rule;
		}

		foreach ($rule['trigger_prototypes'] as &$trigger) {
			$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);
		}
		unset($trigger);

		return $rule;
	}

	/**
	 * Convert filters from the 2.0 and 2.2 string representations to a 2.4 array.
	 *
	 * @param array $rule
	 * @return array
	 */
	protected function convertDiscoveryRuleFilter(array $rule) {
		if (!isset($rule['filter'])) {
			return $rule;
		}
		// empty filters were exported as ":"
		elseif ($rule['filter'] === ':') {
			unset($rule['filter']);

			return $rule;
		}
		// string filters were exported as "{#MACRO}:regex"
		elseif ($rule['filter'] && !is_array($rule['filter'])) {
			list ($filterMacro, $filterValue) = explode(':', $rule['filter']);
			$rule['filter'] = array(
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'formula' => '',
				'conditions' => array(
					array(
						'macro' => $filterMacro,
						'value' => $filterValue,
						'operator' => CONDITION_OPERATOR_REGEXP
					)
				)
			);
		}

		return $rule;
	}
}
