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


class C20ImportConverter extends CConverter {

	/**
	 * Converter used for converting trigger expressions from 2.2 to 2.4 format.
	 *
	 * @var CConverter
	 */
	protected $triggerExpressionConverter;

	public function __construct(CConverter $triggerExpressionConverter) {
		$this->triggerExpressionConverter = $triggerExpressionConverter;
	}

	public function convert($value) {
		$content = $value['zabbix_export'];

		$content['version'] = '3.0';

		$content = $this->convertItems($content);
		$content = $this->convertTriggers($content);
		$content = $this->convertDiscoveryRules($content);

		$value['zabbix_export'] = $content;

		return $value;
	}

	/**
	 * Convert item elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	public function convertItems(array $content) {
		if (!isset($content['items'])) {
			return $content;
		}

		foreach ($content['items'] as &$item) {
			if (isset($item['status']) && $item['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$item['status'] = ITEM_STATUS_ACTIVE;
			}
		}
		unset($item);

		return $content;
	}

	/**
	 * Convert trigger elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	public function convertTriggers(array $content) {
		if (!isset($content['triggers'])) {
			return $content;
		}

		foreach ($content['triggers'] as &$trigger) {
			$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);
		}
		unset($trigger);

		return $content;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $content
	 *
	 * @return array
	 */
	public function convertDiscoveryRules(array $content) {
		if (!isset($content['discovery_rules'])) {
			return $content;
		}

		foreach ($content['discovery_rules'] as &$rule) {
			if (isset($rule['status']) && $rule['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$rule['status'] = ITEM_STATUS_ACTIVE;
			}

			$rule = $this->convertDiscoveryRuleFilter($rule);
			$rule = $this->convertTriggerPrototypes($rule);
		}
		unset($rule);

		return $content;
	}

	/**
	 * Convert trigger prototype elements.
	 *
	 * @param array $rule
	 *
	 * @return array
	 */
	public function convertTriggerPrototypes(array $rule) {
		if (!isset($rule['trigger_prototypes'])) {
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
		elseif (!is_array($rule['filter'])) {
			list ($filterMacro, $filterValue) = explode(':', $rule['filter']);
			$rule['filter'] = array(
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'formula' => '',
				'conditions' => array(
					array(
						'macro' => $filterMacro,
						'value' => $filterValue,
						'operator' => CONDITION_OPERATOR_REGEXP,
					)
				)
			);
		}

		return $rule;
	}
}
