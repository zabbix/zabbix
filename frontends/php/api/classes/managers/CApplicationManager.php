<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
 * Class to perform low level application related actions.
 */
class CApplicationManager {

	/**
	 * Create new application.
	 * If $batch is true it performs batch insert, in this case all applications must have same fields in same order.
	 *
	 * @param array $applications
	 * @param bool  $batch
	 *
	 * @return array
	 */
	public function create(array $applications, $batch = false) {
		$insertApplications = $applications;
		foreach ($insertApplications as &$app) {
			unset($app['applicationTemplates']);
		}
		unset($app);

		if ($batch) {
			$applicationids = DB::insertBatch('applications', $insertApplications);
		}
		else {
			$applicationids = DB::insert('applications', $insertApplications);
		}

		$applicationTemplates = array();
		foreach ($applications as $anum => &$application) {
			$application['applicationid'] = $applicationids[$anum];

			if (isset($application['applicationTemplates'])) {
				foreach ($application['applicationTemplates'] as $applicationTemplate) {
					$applicationTemplates[] = array(
						'applicationid' => $application['applicationid'],
						'templateid' => $applicationTemplate['templateid']
					);
				}
			}
		}

		// link inherited apps
		DB::insertBatch('application_template', $applicationTemplates);

		// TODO: REMOVE info
		$dbCursor = DBselect('SELECT a.name, h.name as hostname'.
				' FROM applications a'.
				' INNER JOIN hosts h ON h.hostid=a.hostid'.
				' WHERE '.dbConditionInt('a.applicationid', $applicationids));
		while ($app = DBfetch($dbCursor)) {
			info(_s('Created: Application "%1$s" on "%2$s".', $app['name'], $app['hostname']));
		}

		return $applications;
	}

	/**
	 * Update applications.
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	public function update(array $applications) {
		$update = array();
		$applicationTemplates = array();
		foreach ($applications as $application) {
			if (isset($application['applicationTemplates'])) {
				foreach ($application['applicationTemplates'] as $applicationTemplate) {
					$applicationTemplates[] = $applicationTemplate;
				}
				unset($application['applicationTemplates']);
			}

			$update[] = array(
				'values' => $application,
				'where' => array('applicationid' => $application['applicationid'])
			);
		}
		DB::update('applications', $update);

		// replace existing application templates
		if ($applicationTemplates) {
			$dbAplicationTemplates = DBfetchArray(DBselect(
				'SELECT * '.
				' FROM application_template at'.
				' WHERE '.dbConditionInt('at.applicationid', zbx_objectValues($applications, 'applicationid'))
			));
			DB::replace('application_template', $dbAplicationTemplates, $applicationTemplates);
		}

		// TODO: REMOVE info
		$dbCursor = DBselect(
			'SELECT a.name,h.name AS hostname'.
			' FROM applications a'.
				' INNER JOIN hosts h ON h.hostid=a.hostid'.
			' WHERE '.dbConditionInt('a.applicationid', zbx_objectValues($applications, 'applicationid'))
		);
		while ($app = DBfetch($dbCursor)) {
			info(_s('Updated: Application "%1$s" on "%2$s".', $app['name'], $app['hostname']));
		}

		return $applications;
	}

	/**
	 * Link applications in template to hosts.
	 *
	 * @param $templateId
	 * @param $hostIds
	 *
	 * @return bool
	 */
	public function link($templateId, $hostIds) {
		$hostIds = zbx_toArray($hostIds);

		// fetch template applications
		$applications = DBfetchArray(DBselect(
			'SELECT a.applicationid,a.name,a.hostid'.
			' FROM applications a'.
			' WHERE a.hostid='.zbx_dbstr($templateId)
		));

		$this->inherit($applications, $hostIds);

		return true;
	}

	/**
	 * Inherit passed applications to hosts.
	 * If $hostIds is empty that means that we need to inherit all $applications to hosts which are linked to templates
	 * where $applications belong.
	 *
	 * Usual use case is:
	 *   inherit is called with some $hostIds passed
	 *   new applications are created/updated
	 *   inherit is called again with created/updated applications but empty $hostIds
	 *   if any of new applications belongs to template, inherit it to all hosts linked to that template
	 *
	 * @param array $applications
	 * @param array $hostIds
	 *
	 * @return bool
	 */
	public function inherit(array $applications, array $hostIds = array()) {
		$hostTemplateMap = $this->getChildHostsFromApplications($applications, $hostIds);
		if (empty($hostTemplateMap)) {
			return true;
		}

		$hostApps = $this->getApplicationMapsByHostIds(array_keys($hostTemplateMap));
		$preparedApps = $this->prepareInheritedApps($applications, $hostTemplateMap, $hostApps);
		$inheritedApps = $this->save($preparedApps);

		$applications = zbx_toHash($applications, 'applicationid');

		// update application linkage
		$oldApplicationTemplateIds = array();
		$movedAppTemplateIds = array();
		$childAppIdsPairs = array();
		$oldChildApps = array();
		foreach ($inheritedApps as $newChildApp) {
			$oldChildAppsByTemplateId = $hostApps[$newChildApp['hostid']]['byTemplateId'];

			foreach ($newChildApp['applicationTemplates'] as $applicationTemplate) {
				// check if the parent of this application had a different child on the same host
				if (isset($oldChildAppsByTemplateId[$applicationTemplate['templateid']])
						&& $oldChildAppsByTemplateId[$applicationTemplate['templateid']]['applicationid'] != $newChildApp['applicationid']) {

					// if a different child existed, find the template-application link and remove it later
					$oldChildApp = $oldChildAppsByTemplateId[$applicationTemplate['templateid']];
					$oldApplicationTemplates = zbx_toHash($oldChildApp['applicationTemplates'], 'templateid');
					$oldApplicationTemplateIds[] = $oldApplicationTemplates[$applicationTemplate['templateid']]['application_templateid'];

					// save the IDs of the affected templates and old
					if (isset($applications[$applicationTemplate['templateid']])) {
						$movedAppTemplateIds[] = $applications[$applicationTemplate['templateid']]['hostid'];
						$childAppIdsPairs[$oldChildApp['applicationid']] =  $newChildApp['applicationid'];
					}

					$oldChildApps[] = $oldChildApp;
				}
			}
		}

		// move all items and web scenarios from the old app to the new
		if ($childAppIdsPairs) {
			$this->moveInheritedItems($movedAppTemplateIds, $childAppIdsPairs);
			$this->moveInheritedHttpTests($movedAppTemplateIds, $childAppIdsPairs);
		}

		// delete old application links
		if ($oldApplicationTemplateIds) {
			DB::delete('application_template', array(
				'application_templateid' => $oldApplicationTemplateIds
			));
		}

		// delete old children that have only one parent
		$delAppIds = array();
		foreach ($oldChildApps as $app) {
			if (count($app['applicationTemplates']) == 1) {
				$delAppIds[] = $app['applicationid'];
			}
		}
		if ($delAppIds && $emptyIds = $this->fetchEmptyIds($delAppIds)) {
			$this->delete($emptyIds);
		}

		$this->inherit($inheritedApps);

		return true;
	}

	/**
	 * Replaces applications for all items inherited from templates $templateIds according to the map given in
	 * $appIdPairs.
	 *
	 * @param array $templateIds
	 * @param array $appIdPairs		an array of source application ID - target application ID pairs
	 *
	 * @return void
	 */
	protected function moveInheritedItems(array $templateIds, array $appIdPairs) {
		// fetch existing item application links for all items inherited from template $templateIds
		$itemApps = DBfetchArray(DBselect(
			'SELECT ia2.itemappid,ia2.applicationid,ia2.itemid'.
			' FROM items i,items i2,items_applications ia2'.
			' WHERE i.itemid=i2.templateid'.
				' AND i2.itemid=ia2.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateIds).
				' AND '.dbConditionInt('ia2.applicationid', array_keys($appIdPairs))
		));

		// find item application links to target applications that may already exist
		$query = DBselect(
			'SELECT ia.itemid,ia.applicationid'.
			' FROM items_applications ia'.
			' WHERE '.dbConditionInt('ia.applicationid', $appIdPairs).
				' AND '.dbConditionInt('ia.itemid', zbx_objectValues($itemApps, 'itemid'))
		);
		$exItemAppIds = array();
		while ($row = DBfetch($query)) {
			$exItemAppIds[$row['itemid']][$row['applicationid']] = $row['applicationid'];
		}

		$newAppItems = array();
		$delAppItemIds = array();
		foreach ($itemApps as $itemApp) {
			// if no link to the target app exists, add a new one
			if (!isset($exItemAppIds[$itemApp['itemid']][$appIdPairs[$itemApp['applicationid']]])) {
				$newAppItems[$appIdPairs[$itemApp['applicationid']]][] = $itemApp['itemappid'];
			}
			// if the link to the target app already exists, delete the link to the old app
			else {
				$delAppItemIds[] = $itemApp['itemappid'];
			}
		}

		// link the items to the new apps
		foreach ($newAppItems as $targetAppId => $itemAppIds) {
			DB::updateByPk('items_applications', $itemAppIds, array(
				'applicationid' => $targetAppId
			));
		}

		// delete old item application links
		if ($delAppItemIds) {
			DB::delete('items_applications', array('itemappid' => $delAppItemIds));
		}
	}

	/**
	 * Return IDs of applications that are not used by items or HTTP tests.
	 *
	 * @param array $applicationIds
	 *
	 * @return array
	 */
	public function fetchEmptyIds(array $applicationIds) {
		return DBfetchColumn(DBselect(
			'SELECT a.applicationid '.
			' FROM applications a'.
			' WHERE '.dbConditionInt('a.applicationid', $applicationIds).
				' AND NOT EXISTS (SELECT NULL FROM items_applications ia WHERE a.applicationid=ia.applicationid)'.
				' AND NOT EXISTS (SELECT NULL FROM httptest ht WHERE a.applicationid=ht.applicationid)'
		), 'applicationid');
	}

	/**
	 * Return IDs of applications that are children only (!) of the given parents.
	 *
	 * @param array $parentApplicationIds
	 *
	 * @return array
	 */
	public function fetchExclusiveChildIds(array $parentApplicationIds) {
		return DBfetchColumn(DBselect(
			'SELECT at.applicationid '.
			' FROM application_template at'.
			' WHERE '.dbConditionInt('at.templateid', $parentApplicationIds).
				' AND NOT EXISTS (SELECT NULL FROM application_template at2 WHERE '.
					' at.applicationid=at2.applicationid'.
					' AND '.dbConditionInt('at2.templateid', $parentApplicationIds, true).
				')'
		), 'applicationid');
	}

	/**
	 * Delete applications.
	 *
	 * @param array $applicationIds
	 */
	public function delete(array $applicationIds) {
		// unset applications from http tests
		DB::update('httptest', array(
			'values' => array('applicationid' => null),
			'where' => array('applicationid' => $applicationIds)
		));

		// remove Monitoring > Latest data toggle profile values related to given aplications
		CProfile::delete('web.latest.toggle', $applicationIds);

		DB::delete('applications', array('applicationid' => $applicationIds));
	}

	/**
	 * Replaces the applications for all http tests inherited from templates $templateIds according to the map given in
	 * $appIdPairs.
	 *
	 * @param array $templateIds
	 * @param array $appIdPairs		an array of source application ID - target application ID pairs
	 *
	 * @return void
	 */
	protected function moveInheritedHttpTests(array $templateIds, array $appIdPairs) {
		// find all http tests inherited from the given templates and linked to the given applications
		$query = DBselect(
			'SELECT ht2.applicationid,ht2.httptestid'.
			' FROM httptest ht,httptest ht2'.
			' WHERE ht.httptestid=ht2.templateid'.
				' AND '.dbConditionInt('ht.hostid', $templateIds).
				' AND '.dbConditionInt('ht2.applicationid', array_keys($appIdPairs))
		);
		$targetAppHttpTestIds = array();
		while ($row = DBfetch($query)) {
			$targetAppHttpTestIds[$appIdPairs[$row['applicationid']]][] = $row['httptestid'];
		}

		// link the http test to the new apps
		foreach ($targetAppHttpTestIds as $targetAppId => $httpTestIds) {
			DB::updateByPk('httptest', $httpTestIds, array(
				'applicationid' => $targetAppId
			));
		}
	}

	/**
	 * Get array with hosts that are linked with templates which passed applications belongs to as key and templateid that host
	 * is linked to as value.
	 * If second parameter $hostIds is not empty, result should contain only passed host ids.
	 *
	 * For example we have template T1 with application A1 linked to host H1 and H2.
	 * When we pass A1 to this function it should return array like:
	 *     array(H1_id => T1_id, H2_id => T1_id);
	 *
	 * @param array $applications
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getChildHostsFromApplications(array $applications, array $hostIds = array()) {
		$hostsTemapltesMap = array();

		$sqlWhere = empty($hostIds) ? '' : ' AND '.dbConditionInt('ht.hostid', $hostIds);
		$dbCursor = DBselect(
			'SELECT ht.templateid,ht.hostid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionInt('ht.templateid', array_unique(zbx_objectValues($applications, 'hostid'))).
				$sqlWhere
		);
		while ($dbHost = DBfetch($dbCursor)) {
			$hostsTemapltesMap[$dbHost['hostid']] = $dbHost['templateid'];
		}

		return $hostsTemapltesMap;
	}

	/**
	 * Generate apps data for inheritance.
	 * Using passed parameters decide if new application must be created on host or existing one must be updated.
	 *
	 * @param array $applications 		applications which we need to inherit
	 * @param array $hostsTemplatesMap
	 * @param array $hostApps			array of existing applications on the child host returned by
	 * 									self::getApplicationMapsByHostIds()
	 *
	 * @return array with applications, existing apps have 'applicationid' key.
	 */
	protected function prepareInheritedApps(array $applications, array $hostsTemplatesMap, array $hostApps) {
		$result = array();
		foreach ($applications as $application) {
			$appId = $application['applicationid'];
			foreach ($hostApps as $hostId => $hostApp) {
				// if application template is not linked to host we skip it
				if ($hostsTemplatesMap[$hostId] != $application['hostid']) {
					continue;
				}

				$exApplication = null;

				// look for an application with the same name, it one exists - link the parent app to it
				if (isset($hostApp['byName'][$application['name']])) {
					$exApplication = $hostApp['byName'][$application['name']];
				}
				// if no application with the same name exists, look for a child app via templateid
				// use it only if it has only one parent, otherwise a new app must be created
				elseif (isset($hostApp['byTemplateId'][$appId]) && count($hostApp['byTemplateId'][$appId]['applicationTemplates']) == 1) {
					$exApplication = $hostApp['byTemplateId'][$appId];
				}

				$newApplication = $application;
				$newApplication['hostid'] = $hostId;
				if ($exApplication) {
					$newApplication['applicationid'] = $exApplication['applicationid'];

					// add the new template link to an existing child applications if it's not present yet
					$exApplicationTemplates = isset($exApplication['applicationTemplates']) ? $exApplication['applicationTemplates'] : array();
					$newApplication['applicationTemplates'] = $exApplicationTemplates;
					if (!in_array($appId, zbx_objectValues($newApplication['applicationTemplates'], 'templateid'))) {
						$newApplication['applicationTemplates'][] = array(
							'applicationid' => $newApplication['applicationid'],
							'templateid' => $appId
						);
					}
				}
				// if no matching child app exists - create a new one
				else {
					$newApplication['applicationTemplates'] = array(array(
						'templateid' => $appId
					));
					unset($newApplication['applicationid']);
				}
				$result[] = $newApplication;
			}
		}

		return $result;
	}

	/**
	 * Get hosts applications for each passed hosts.
	 * Each host has two hashes with applications, one with name keys other with templateid keys.
	 *
	 * Resulting structure is:
	 * array(
	 *     'hostid1' => array(
	 *         'byName' => array(app1data, app2data, ...),
	 *         'nyTemplateId' => array(app1data, app2data, ...)
	 *     ), ...
	 * );
	 *
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getApplicationMapsByHostIds(array $hostIds) {
		$hostApps = array();
		foreach ($hostIds as $hostid) {
			$hostApps[$hostid] = array('byName' => array(), 'byTemplateId' => array());
		}

		// fetch applications
		$applications = DbFetchArrayAssoc(DBselect(
			'SELECT a.applicationid,a.name,a.hostid'.
				' FROM applications a'.
				' WHERE '.dbConditionInt('a.hostid', $hostIds)
		), 'applicationid');
		$query = DBselect(
			'SELECT *'.
				' FROM application_template at'.
				' WHERE '.dbConditionInt('at.applicationid', array_keys($applications))
		);
		while ($applicationTemplate = DbFetch($query)) {
			$applications[$applicationTemplate['applicationid']]['applicationTemplates'][] = $applicationTemplate;
		}

		foreach ($applications as $app) {
			$hostApps[$app['hostid']]['byName'][$app['name']] = $app;

			if (isset($app['applicationTemplates'])) {
				foreach ($app['applicationTemplates'] as $applicationTemplate) {
					$hostApps[$app['hostid']]['byTemplateId'][$applicationTemplate['templateid']] = $app;
				}
			}
		}

		return $hostApps;
	}

	/**
	 * Save applications. If application has applicationid it gets updated otherwise a new one is created.
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	protected function save(array $applications) {
		$appsCreate = array();
		$appsUpdate = array();

		foreach ($applications as $key => $app) {
			if (isset($app['applicationid'])) {
				$appsUpdate[] = $app;
			}
			else {
				$appsCreate[$key] = $app;
			}
		}

		if (!empty($appsCreate)) {
			$newApps = $this->create($appsCreate, true);
			foreach ($newApps as $key => $newApp) {
				$applications[$key]['applicationid'] = $newApp['applicationid'];
			}
		}
		if (!empty($appsUpdate)) {
			$this->update($appsUpdate);
		}

		return $applications;
	}
}
