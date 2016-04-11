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
 * Class containing methods for operations with hosts.
 *
 * @package API
 */
abstract class CHostGeneral extends CHostBase {

	/**
	 * Allows to:
	 * - add hosts to groups;
	 * - link templates to hosts;
	 * - add new macros to hosts.
	 *
	 * Supported $data parameters are:
	 * - hosts          - an array of hosts to be updated
	 * - templates      - an array of templates to be updated
	 * - groups         - an array of host groups to add the host to
	 * - templates_link - an array of templates to link to the hosts
	 * - macros         - an array of macros to create on the host
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$hostIds = zbx_objectValues($data['hosts'], 'hostid');
		$templateIds = zbx_objectValues($data['templates'], 'templateid');

		$allHostIds = array_merge($hostIds, $templateIds);

		// add groups
		if (!empty($data['groups'])) {
			API::HostGroup()->massAdd($options = array(
				'hosts' => $data['hosts'],
				'templates' => $data['templates'],
				'groups' => $data['groups']
			));
		}

		// link templates
		if (!empty($data['templates_link'])) {
			if (!API::Host()->isWritable($allHostIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$this->link(zbx_objectValues(zbx_toArray($data['templates_link']), 'templateid'), $allHostIds);
		}

		// create macros
		if (!empty($data['macros'])) {
			$data['macros'] = zbx_toArray($data['macros']);

			$hostMacrosToAdd = array();
			foreach ($data['macros'] as $hostMacro) {
				foreach ($allHostIds as $hostid) {
					$hostMacro['hostid'] = $hostid;
					$hostMacrosToAdd[] = $hostMacro;
				}
			}

			API::UserMacro()->create($hostMacrosToAdd);
		}

		$ids = array('hostids' => $hostIds, 'templateids' => $templateIds);

		return array($this->pkOption() => $ids[$this->pkOption()]);
	}

	/**
	 * Allows to:
	 * - remove hosts from groups;
	 * - unlink and clear templates from hosts;
	 * - remove macros from hosts.
	 *
	 * Supported $data parameters are:
	 * - hostids            - an array of host IDs to be updated
	 * - templateids        - an array of template IDs to be updated
	 * - groupids           - an array of host group IDs the hosts should be removed from
	 * - templateids_link   - an array of template IDs to unlink from the hosts
	 * - templateids_clear  - an array of template IDs to unlink and clear from the hosts
	 * - macros             - an array of macros to delete from the hosts
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$allHostIds = array_merge($data['hostids'], $data['templateids']);

		if (!API::Host()->isWritable($allHostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}

		if (!empty($data['templateids_link'])) {
			$this->unlink(zbx_toArray($data['templateids_link']), $allHostIds);
		}

		if (isset($data['templateids_clear'])) {
			$this->unlink(zbx_toArray($data['templateids_clear']), $allHostIds, true);
		}

		if (isset($data['macros'])) {
			$hostMacros = API::UserMacro()->get(array(
				'hostids' => $allHostIds,
				'filter' => array(
					'macro' => $data['macros']
				)
			));
			$hostMacroIds = zbx_objectValues($hostMacros, 'hostmacroid');
			API::UserMacro()->delete($hostMacroIds);
		}

		if (isset($data['groupids'])) {
			API::HostGroup()->massRemove(array(
				'hostids' => $data['hostids'],
				'templateids' => $data['templateids'],
				'groupids' => zbx_toArray($data['groupids'])
			));
		}

		return array($this->pkOption() => $data[$this->pkOption()]);
	}

	protected function link(array $templateIds, array $targetIds) {
		$hostsLinkageInserts = parent::link($templateIds, $targetIds);

		foreach ($hostsLinkageInserts as $hostTplIds){
			Manager::Application()->link($hostTplIds['templateid'], $hostTplIds['hostid']);

			API::DiscoveryRule()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::Itemprototype()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::HostPrototype()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::Item()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			Manager::HttpTest()->link($hostTplIds['templateid'], $hostTplIds['hostid']);
		}

		// we do linkage in two separate loops because for triggers you need all items already created on host
		foreach ($hostsLinkageInserts as $hostTplIds){
			API::Trigger()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::TriggerPrototype()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::GraphPrototype()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));

			API::Graph()->syncTemplates(array(
				'hostids' => $hostTplIds['hostid'],
				'templateids' => $hostTplIds['templateid']
			));
		}

		foreach ($hostsLinkageInserts as $hostTplIds){
			API::Trigger()->syncTemplateDependencies(array(
				'templateids' => $hostTplIds['templateid'],
				'hostids' => $hostTplIds['hostid']
			));
		}

		return $hostsLinkageInserts;
	}

	/**
	 * Unlinks the templates from the given hosts. If $tragetids is set to null, the templates will be unlinked from
	 * all hosts.
	 *
	 * @param array      $templateids
	 * @param null|array $targetids		the IDs of the hosts to unlink the templates from
	 * @param bool       $clear			delete all of the inherited objects from the hosts
	 */
	protected function unlink($templateids, $targetids = null, $clear = false) {
		$flags = ($clear)
			? array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE)
			: array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

		// check that all triggers on templates that we unlink, don't have items from another templates
		$sql = 'SELECT DISTINCT t.description'.
			' FROM triggers t,functions f,items i'.
			' WHERE t.triggerid=f.triggerid'.
			' AND f.itemid=i.itemid'.
			' AND '.dbConditionInt('i.hostid', $templateids).
			' AND EXISTS ('.
			'SELECT ff.triggerid'.
			' FROM functions ff,items ii'.
			' WHERE ff.itemid=ii.itemid'.
			' AND ff.triggerid=t.triggerid'.
			' AND '.dbConditionInt('ii.hostid', $templateids, true).
			')'.
			' AND t.flags='.ZBX_FLAG_DISCOVERY_NORMAL;
		if ($dbTrigger = DBfetch(DBSelect($sql, 1))) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot unlink trigger "%s", it has items from template that is left linked to host.', $dbTrigger['description'])
			);
		}

		$sqlFrom = ' triggers t,hosts h';
		$sqlWhere = ' EXISTS ('.
			'SELECT ff.triggerid'.
			' FROM functions ff,items ii'.
			' WHERE ff.triggerid=t.templateid'.
			' AND ii.itemid=ff.itemid'.
			' AND '.dbConditionInt('ii.hostid', $templateids).')'.
			' AND '.dbConditionInt('t.flags', $flags);


		if (!is_null($targetids)) {
			$sqlFrom = ' triggers t,functions f,items i,hosts h';
			$sqlWhere .= ' AND '.dbConditionInt('i.hostid', $targetids).
				' AND f.itemid=i.itemid'.
				' AND t.triggerid=f.triggerid'.
				' AND h.hostid=i.hostid';
		}
		$sql = 'SELECT DISTINCT t.triggerid,t.description,t.flags,t.expression,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbTriggers = DBSelect($sql);
		$triggers = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY_PROTOTYPE => array()
		);
		$triggerids = array();
		while ($trigger = DBfetch($dbTriggers)) {
			$triggers[$trigger['flags']][$trigger['triggerid']] = array(
				'description' => $trigger['description'],
				'expression' => explode_exp($trigger['expression']),
				'triggerid' => $trigger['triggerid'],
				'host' => $trigger['host']
			);
			if (!in_array($trigger['triggerid'], $triggerids)) {
				array_push($triggerids, $trigger['triggerid']);
			}
		}

		if (!empty($triggers[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Trigger()->delete(array_keys($triggers[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear triggers'));
			}
			else{
				DB::update('triggers', array(
					'values' => array('templateid' => 0),
					'where' => array('triggerid' => array_keys($triggers[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($triggers[ZBX_FLAG_DISCOVERY_NORMAL] as $trigger) {
					info(_s('Unlinked: Trigger "%1$s" on "%2$s".', $trigger['description'], $trigger['host']));
				}
			}
		}

		if (!empty($triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE])) {
			if ($clear) {
				$result = API::TriggerPrototype()->delete(array_keys($triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear triggers'));
			}
			else{
				DB::update('triggers', array(
					'values' => array('templateid' => 0),
					'where' => array('triggerid' => array_keys($triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]))
				));

				foreach ($triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE] as $trigger) {
					info(_s('Unlinked: Trigger prototype "%1$s" on "%2$s".', $trigger['description'], $trigger['host']));
				}
			}
		}

		/* ITEMS, DISCOVERY RULES {{{ */
		$sqlFrom = ' items i1,items i2,hosts h';
		$sqlWhere = ' i2.itemid=i1.templateid'.
			' AND '.dbConditionInt('i2.hostid', $templateids).
			' AND '.dbConditionInt('i1.flags', $flags).
			' AND h.hostid=i1.hostid';

		if (!is_null($targetids)) {
			$sqlWhere .= ' AND '.dbConditionInt('i1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT i1.itemid,i1.flags,i1.name,i1.hostid,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbItems = DBSelect($sql);
		$items = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY_RULE => array(),
			ZBX_FLAG_DISCOVERY_PROTOTYPE => array(),
		);
		while ($item = DBfetch($dbItems)) {
			$items[$item['flags']][$item['itemid']] = array(
				'name' => $item['name'],
				'host' => $item['host']
			);
		}

		if (!empty($items[ZBX_FLAG_DISCOVERY_RULE])) {
			if ($clear) {
				$result = API::DiscoveryRule()->delete(array_keys($items[ZBX_FLAG_DISCOVERY_RULE]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear discovery rules'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY_RULE]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY_RULE] as $discoveryRule) {
					info(_s('Unlinked: Discovery rule "%1$s" on "%2$s".', $discoveryRule['name'], $discoveryRule['host']));
				}
			}
		}

		if (!empty($items[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Item()->delete(array_keys($items[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear items'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY_NORMAL] as $item) {
					info(_s('Unlinked: Item "%1$s" on "%2$s".', $item['name'], $item['host']));
				}
			}
		}

		if (!empty($items[ZBX_FLAG_DISCOVERY_PROTOTYPE])) {
			if ($clear) {
				$result = API::Itemprototype()->delete(array_keys($items[ZBX_FLAG_DISCOVERY_PROTOTYPE]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear item prototypes'));
			}
			else{
				DB::update('items', array(
					'values' => array('templateid' => 0),
					'where' => array('itemid' => array_keys($items[ZBX_FLAG_DISCOVERY_PROTOTYPE]))
				));

				foreach ($items[ZBX_FLAG_DISCOVERY_PROTOTYPE] as $item) {
					info(_s('Unlinked: Item prototype "%1$s" on "%2$s".', $item['name'], $item['host']));
				}
			}
		}
		/* }}} ITEMS, DISCOVERY RULES */

		// host prototypes
		// we need only to unlink host prototypes. in case of unlink and clear they will be deleted together with LLD rules.
		if (!$clear && isset($items[ZBX_FLAG_DISCOVERY_RULE])) {
			$discoveryRuleIds = array_keys($items[ZBX_FLAG_DISCOVERY_RULE]);

			$hostPrototypes = DBfetchArrayAssoc(DBSelect(
				'SELECT DISTINCT h.hostid,h.host,h3.host AS parent_host'.
				' FROM hosts h'.
					' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
					' INNER JOIN hosts h2 ON h.templateid=h2.hostid'.
					' INNER JOIN host_discovery hd2 ON h.hostid=hd.hostid'.
					' INNER JOIN items i ON hd.parent_itemid=i.itemid'.
					' INNER JOIN hosts h3 ON i.hostid=h3.hostid'.
				' WHERE '.dbConditionInt('hd.parent_itemid', $discoveryRuleIds)
			), 'hostid');
			if ($hostPrototypes) {
				DB::update('hosts', array(
					'values' => array('templateid' => 0),
					'where' => array('hostid' => array_keys($hostPrototypes))
				));
				DB::update('group_prototype', array(
					'values' => array('templateid' => 0),
					'where' => array('hostid' => array_keys($hostPrototypes))
				));
				foreach ($hostPrototypes as $hostPrototype) {
					info(_s('Unlinked: Host prototype "%1$s" on "%2$s".', $hostPrototype['host'], $hostPrototype['parent_host']));
				}
			}
		}


		/* GRAPHS {{{ */
		$sqlFrom = ' graphs g,hosts h';
		$sqlWhere = ' EXISTS ('.
			'SELECT ggi.graphid'.
			' FROM graphs_items ggi,items ii'.
			' WHERE ggi.graphid=g.templateid'.
			' AND ii.itemid=ggi.itemid'.
			' AND '.dbConditionInt('ii.hostid', $templateids).')'.
			' AND '.dbConditionInt('g.flags', $flags);


		if (!is_null($targetids)) {
			$sqlFrom = ' graphs g,graphs_items gi,items i,hosts h';
			$sqlWhere .= ' AND '.dbConditionInt('i.hostid', $targetids).
				' AND gi.itemid=i.itemid'.
				' AND g.graphid=gi.graphid'.
				' AND h.hostid=i.hostid';
		}
		$sql = 'SELECT DISTINCT g.graphid,g.name,g.flags,h.name as host'.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere;
		$dbGraphs = DBSelect($sql);
		$graphs = array(
			ZBX_FLAG_DISCOVERY_NORMAL => array(),
			ZBX_FLAG_DISCOVERY_PROTOTYPE => array(),
		);
		while ($graph = DBfetch($dbGraphs)) {
			$graphs[$graph['flags']][$graph['graphid']] = array(
				'name' => $graph['name'],
				'graphid' => $graph['graphid'],
				'host' => $graph['host']
			);
		}

		if (!empty($graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE])) {
			if ($clear) {
				$result = API::GraphPrototype()->delete(array_keys($graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear graph prototypes'));
			}
			else{
				DB::update('graphs', array(
					'values' => array('templateid' => 0),
					'where' => array('graphid' => array_keys($graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]))
				));

				foreach ($graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE] as $graph) {
					info(_s('Unlinked: Graph prototype "%1$s" on "%2$s".', $graph['name'], $graph['host']));
				}
			}
		}

		if (!empty($graphs[ZBX_FLAG_DISCOVERY_NORMAL])) {
			if ($clear) {
				$result = API::Graph()->delete(array_keys($graphs[ZBX_FLAG_DISCOVERY_NORMAL]), true);
				if (!$result) self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear graphs.'));
			}
			else{
				DB::update('graphs', array(
					'values' => array('templateid' => 0),
					'where' => array('graphid' => array_keys($graphs[ZBX_FLAG_DISCOVERY_NORMAL]))
				));

				foreach ($graphs[ZBX_FLAG_DISCOVERY_NORMAL] as $graph) {
					info(_s('Unlinked: Graph "%1$s" on "%2$s".', $graph['name'], $graph['host']));
				}
			}
		}
		/* }}} GRAPHS */

		// http tests
		$sqlWhere = '';
		if (!is_null($targetids)) {
			$sqlWhere = ' AND '.dbConditionInt('ht1.hostid', $targetids);
		}
		$sql = 'SELECT DISTINCT ht1.httptestid,ht1.name,h.name as host'.
				' FROM httptest ht1'.
				' INNER JOIN httptest ht2 ON ht2.httptestid=ht1.templateid'.
				' INNER JOIN hosts h ON h.hostid=ht1.hostid'.
				' WHERE '.dbConditionInt('ht2.hostid', $templateids).
				$sqlWhere;
		$dbHttpTests = DBSelect($sql);
		$httpTests = array();
		while ($httpTest = DBfetch($dbHttpTests)) {
			$httpTests[$httpTest['httptestid']] = array(
				'name' => $httpTest['name'],
				'host' => $httpTest['host']
			);
		}

		if (!empty($httpTests)) {
			if ($clear) {
				$result = API::HttpTest()->delete(array_keys($httpTests), true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear Web scenarios.'));
				}
			}
			else {
				DB::update('httptest', array(
					'values' => array('templateid' => 0),
					'where' => array('httptestid' => array_keys($httpTests))
				));
				foreach ($httpTests as $httpTest) {
					info(_s('Unlinked: Web scenario "%1$s" on "%2$s".', $httpTest['name'], $httpTest['host']));
				}
			}
		}

		/* APPLICATIONS {{{ */
		$sql = 'SELECT at.application_templateid,at.applicationid,h.name,h.host,h.hostid'.
			' FROM applications a1,application_template at,applications a2,hosts h'.
			' WHERE a1.applicationid=at.applicationid'.
				' AND at.templateid=a2.applicationid'.
				' AND '.dbConditionInt('a2.hostid', $templateids).
				' AND a1.hostid=h.hostid';
		if ($targetids) {
			$sql .= ' AND '.dbConditionInt('a1.hostid', $targetids);
		}
		$query = DBselect($sql);
		$applicationTemplates = array();
		while ($applicationTemplate = DBfetch($query)) {
			$applicationTemplates[] = array(
				'applicationid' => $applicationTemplate['applicationid'],
				'application_templateid' => $applicationTemplate['application_templateid'],
				'name' => $applicationTemplate['name'],
				'hostid' => $applicationTemplate['hostid'],
				'host' => $applicationTemplate['host']
			);
		}

		if ($applicationTemplates) {
			// unlink applications from templates
			DB::delete('application_template', array(
				'application_templateid' => zbx_objectValues($applicationTemplates, 'application_templateid')
			));

			if ($clear) {
				// delete inherited applications that are no longer linked to any templates
				$applications = DBfetchArray(DBselect(
					'SELECT a.applicationid'.
					' FROM applications a'.
						' LEFT JOIN application_template at ON a.applicationid=at.applicationid '.
					' WHERE '.dbConditionInt('a.applicationid', zbx_objectValues($applicationTemplates, 'applicationid')).
						' AND at.applicationid IS NULL'
				));
				$result = API::Application()->delete(zbx_objectValues($applications, 'applicationid'), true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot unlink and clear applications.'));
				}
			}
			else{
				foreach ($applicationTemplates as $application) {
					info(_s('Unlinked: Application "%1$s" on "%2$s".', $application['name'], $application['host']));
				}
			}
		}
		/* }}} APPLICATIONS */

		parent::unlink($templateids, $targetids);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// adding groups
		if ($options['selectGroups'] !== null) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
			$groups = API::HostGroup()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// adding templates
		if ($options['selectParentTemplates'] !== null) {
			if ($options['selectParentTemplates'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$templates = API::Template()->get(array(
					'output' => $options['selectParentTemplates'],
					'nodeids' => $options['nodeids'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($templates, 'host');
				}
				$result = $relationMap->mapMany($result, $templates, 'parentTemplates', $options['limitSelects']);
			}
			else {
				$templates = API::Template()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				));
				$templates = zbx_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['parentTemplates'] = isset($templates[$hostid]) ? $templates[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding items
		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = API::Item()->get(array(
					'output' => $this->outputExtend('items', array('hostid', 'itemid'), $options['selectItems']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, array('hostid', 'itemid'), $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::Item()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$items = zbx_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['items'] = isset($items[$hostid]) ? $items[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding discoveries
		if ($options['selectDiscoveries'] !== null) {
			if ($options['selectDiscoveries'] != API_OUTPUT_COUNT) {
				$items = API::DiscoveryRule()->get(array(
					'output' => $this->outputExtend('items', array('hostid', 'itemid'), $options['selectDiscoveries']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($items, 'name');
				}

				$relationMap = $this->createRelationMap($items, 'hostid', 'itemid');

				$items = $this->unsetExtraFields($items, array('hostid', 'itemid'), $options['selectDiscoveries']);
				$result = $relationMap->mapMany($result, $items, 'discoveries', $options['limitSelects']);
			}
			else {
				$items = API::DiscoveryRule()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$items = zbx_toHash($items, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['discoveries'] = isset($items[$hostid]) ? $items[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding triggers
		if ($options['selectTriggers'] !== null) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,f.triggerid'.
						' FROM items i,functions f'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=f.itemid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['triggerid']);
				}

				$triggers = API::Trigger()->get(array(
					'output' => $options['selectTriggers'],
					'nodeids' => $options['nodeids'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
				}
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::Trigger()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				));
				$triggers = zbx_toHash($triggers, 'hostid');

				foreach ($result as $hostid => $host) {
					$result[$hostid]['triggers'] = isset($triggers[$hostid]) ? $triggers[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding graphs
		if ($options['selectGraphs'] !== null) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				// discovered items
				$res = DBselect(
					'SELECT i.hostid,gi.graphid'.
						' FROM items i,graphs_items gi'.
						' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND i.itemid=gi.itemid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostid'], $relation['graphid']);
				}

				$graphs = API::Graph()->get(array(
					'output' => $options['selectGraphs'],
					'nodeids' => $options['nodeids'],
					'graphids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::Graph()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				));
				$graphs = zbx_toHash($graphs, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['graphs'] = isset($graphs[$hostid]) ? $graphs[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding http tests
		if ($options['selectHttpTests'] !== null) {
			if ($options['selectHttpTests'] != API_OUTPUT_COUNT) {
				$httpTests = API::HttpTest()->get(array(
					'output' => $this->outputExtend('httptest', array('hostid', 'httptestid'), $options['selectHttpTests']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($httpTests, 'name');
				}

				$relationMap = $this->createRelationMap($httpTests, 'hostid', 'httptestid');

				$httpTests = $this->unsetExtraFields($httpTests, array('hostid', 'httptestid'), $options['selectHttpTests']);
				$result = $relationMap->mapMany($result, $httpTests, 'httpTests', $options['limitSelects']);
			}
			else {
				$httpTests = API::HttpTest()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$httpTests = zbx_toHash($httpTests, 'hostid');
				foreach ($result as $hostId => $host) {
					$result[$hostId]['httpTests'] = isset($httpTests[$hostId]) ? $httpTests[$hostId]['rowscount'] : 0;
				}
			}
		}

		// adding applications
		if ($options['selectApplications'] !== null) {
			if ($options['selectApplications'] != API_OUTPUT_COUNT) {
				$applications = API::Application()->get(array(
					'output' => $this->outputExtend('applications', array('hostid', 'applicationid'), $options['selectApplications']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($applications, 'name');
				}

				$relationMap = $this->createRelationMap($applications, 'hostid', 'applicationid');

				$applications = $this->unsetExtraFields($applications, array('hostid', 'applicationid'),
					$options['selectApplications']
				);
				$result = $relationMap->mapMany($result, $applications, 'applications', $options['limitSelects']);
			}
			else {
				$applications = API::Application()->get(array(
					'output' => $options['selectApplications'],
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));

				$applications = zbx_toHash($applications, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['applications'] = isset($applications[$hostid]) ? $applications[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding macros
		if ($options['selectMacros'] !== null && $options['selectMacros'] != API_OUTPUT_COUNT) {
			$macros = API::UserMacro()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $this->outputExtend('hostmacro', array('hostid', 'hostmacroid'), $options['selectMacros']),
				'hostids' => $hostids,
				'preservekeys' => true
			));

			$relationMap = $this->createRelationMap($macros, 'hostid', 'hostmacroid');

			$macros = $this->unsetExtraFields($macros, array('hostid', 'hostmacroid'), $options['selectMacros']);
			$result = $relationMap->mapMany($result, $macros, 'macros', $options['limitSelects']);
		}

		return $result;
	}
}
