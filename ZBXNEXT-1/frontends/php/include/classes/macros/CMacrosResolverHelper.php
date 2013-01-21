<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
 * Helper class that simplifies working with CMacrosResolver class.
 */
class CMacrosResolverHelper {

	/**
	 * @var CMacrosResolver
	 */
	private static $macrosResolver;

	/**
	 * Resolve macros.
	 *
	 * @static
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public static function resolve(array $options) {
		self::init();
		return self::$macrosResolver->resolve($options);
	}

	/**
	 * Resolve macros in http test name.
	 *
	 * @static
	 *
	 * @param int $hostId
	 * @param string $name
	 *
	 * @return string
	 */
	public static function resolveHttpTestName($hostId, $name) {
		self::init();
		$macros = self::$macrosResolver->resolve(array(
			'config' => 'httpTestName',
			'data' => array($hostId => array($name))
		));
		return $macros[$hostId][0];
	}

	/**
	 * Resolve macros in trigger name.
	 *
	 * @static
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function resolveTriggerName(array $trigger) {
		$macros = self::resolveTriggerNames(array($trigger));
		$macros = reset($macros);
		return $macros['description'];
	}

	/**
	 * Resolve macros in trigger names.
	 *
	 * @static
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public static function resolveTriggerNames(array $triggers) {
		self::init();
		return self::$macrosResolver->resolve(array(
			'config' => 'triggerName',
			'data' => zbx_toHash($triggers, 'triggerid')
		));
	}

	/**
	 * Resolve macros in trigger description.
	 *
	 * @static
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function resolveTriggerDescription(array $trigger) {
		$macros = self::resolveTriggerDescriptions(array($trigger));
		$macros = reset($macros);
		return $macros['comments'];
	}

	/**
	 * Resolve macros in trigger descriptions.
	 *
	 * @static
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public static function resolveTriggerDescriptions(array $triggers) {
		self::init();
		return self::$macrosResolver->resolve(array(
			'config' => 'triggerDescription',
			'data' => zbx_toHash($triggers, 'triggerid')
		));
	}

	/**
	 * Get trigger by id and resolve macros in trigger name.
	 *
	 * @static
	 *
	 * @param int $triggerId
	 *
	 * @return string
	 */
	public static function resolveTriggerNameById($triggerId) {
		$macros = self::resolveTriggerNameByIds(array($triggerId));
		$macros = reset($macros);
		return $macros['description'];
	}

	/**
	 * Get triggers by ids and resolve macros in trigger names.
	 *
	 * @static
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	public static function resolveTriggerNameByIds(array $triggerIds) {
		self::init();
		$triggers = DBfetchArray(DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.triggerid', $triggerIds)
		));
		return self::$macrosResolver->resolve(array(
			'config' => 'triggerName',
			'data' => zbx_toHash($triggers, 'triggerid')
		));
	}

	/**
	 * Resolve macros in trigger reference.
	 *
	 * @static
	 *
	 * @param string $expression
	 * @param string $text
	 *
	 * @return string
	 */
	public static function resolveTriggerReference($expression, $text) {
		self::init();
		return self::$macrosResolver->resolveTriggerReference($expression, $text);
	}

	/**
	 * Resolve macros in event description.
	 *
	 * @static
	 *
	 * @param array $event
	 *
	 * @return string
	 */
	public static function resolveEventDescription(array $event) {
		self::init();
		$macros = self::$macrosResolver->resolve(array(
			'config' => 'eventDescription',
			'data' => array($event['triggerid'] => $event)
		));
		$macros = reset($macros);
		return $macros['description'];
	}

	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param type $label string in which macros should be resolved
	 * @param array $items list of graph items
	 * @param array $items[n]['hostid'] graph n-th item corresponding host Id
	 *
	 * @return string label with macros replaced with corresponding values
	 */
	public static function resolveGraphLabel($label, $items) {
		self::init();
		return self::$macrosResolver->resolve(array(
			'config' => 'graphName',
			'data' => array('str' => $label, 'items' => $items)
		));
	}

	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param array $data list of graphs
	 * @param int $data[]['graphid'] id of graph
	 * @param string $data[]['name'] name of graph
	 *
	 * @return array inputed data with resolved names
	 */
	public static function resolveGraphNameByIds($data) {
		self::init();
		$graphIds = zbx_objectValues($data, 'graphid');

		$items = DBfetchArray(DBselect(
			'SELECT i.hostid,gi.graphid'.
			' FROM graphs_items gi,items i'.
			' WHERE gi.itemid=i.itemid'.
				' AND '.dbConditionInt('gi.graphid', $graphIds).
			' ORDER BY gi.sortorder'
		));

		$itemsByGraphId = array();
		foreach ($items as $item) {
			$itemsByGraphId[$item['graphid']][]['hostid'] = $item['hostid'];
		}

		foreach ($itemsByGraphId as $graphId => $items) {
			$data[$graphId]['name'] = self::$macrosResolver->resolve(array(
				'config' => 'graphName',
				'data' => array('str' => $data[$graphId]['name'], 'items' => $items)
			));
		}

		return $data;
	}

	/**
	 * Create CMacrosResolver object and store in static variable.
	 *
	 * @static
	 */
	private static function init() {
		if (self::$macrosResolver === null) {
			self::$macrosResolver = new CMacrosResolver();
		}
	}
}
