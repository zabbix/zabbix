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
		$insertApplications = array();
		foreach ($applications as $app) {
			unset($app['applicationTemplates']);
			$insertApplications[] = $app;
		}
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
	 *   if any of new applications belongs to template, inherit it to all hosts linked to tah template
	 *
	 * @param array $applications
	 * @param array $hostIds
	 *
	 * @return bool
	 */
	public function inherit(array $applications, array $hostIds = array()) {
		$hostsTemplatesMap = $this->getChildHostsFromApplications($applications, $hostIds);
		if (empty($hostsTemplatesMap)) {
			return true;
		}

		$preparedApps = $this->prepareInheritedApps($applications, $hostsTemplatesMap);
		$inheritedApps = $this->save($preparedApps);

		$this->inherit($inheritedApps);

		return true;
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
	 * @param array $applications which we need to inherit
	 * @param array $hostsTemplatesMap
	 *
	 * @throws Exception
	 * @return array with applications, existing apps have 'applicationid' key.
	 */
	protected function prepareInheritedApps(array $applications, array $hostsTemplatesMap) {
		$hostApps = $this->getApplicationMapsByHostIds(array_keys($hostsTemplatesMap));

		$result = array();
		foreach ($applications as $application) {
			$appId = $application['applicationid'];
			foreach ($hostApps as $hostId => $hostApp) {
				// if application template is not linked to host we skip it
				if ($hostsTemplatesMap[$hostId] != $application['hostid']) {
					continue;
				}

				$exApplication = null;
				// update by templateid
				if (isset($hostApp['byTemplateId'][$appId])) {
					$exApplication = $hostApp['byTemplateId'][$appId];

					// check if there's an application on the target host with the same name but from a different template
					// or no template
					$templateIds = zbx_objectValues($hostApp['byName'][$application['name']]['applicationTemplates'], 'templateid');
					if (isset($hostApp['byName'][$application['name']]) && !in_array($appId, $templateIds)) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(_s('Application "%1$s" already exists on "%2$s".', $application['name'], $host['name']));
					}
				}

				// update by name
				if (isset($hostApp['byName'][$application['name']])) {
					$exApplication = $hostApp['byName'][$application['name']];
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
				else {
					$newApplication['applicationTemplates'][] = array(
						'templateid' => $appId
					);
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

		foreach ($applications as $app) {
			if (isset($app['applicationid'])) {
				$appsUpdate[] = $app;
			}
			else {
				$appsCreate[] = $app;
			}
		}

		if (!empty($appsCreate)) {
			$newApps = $this->create($appsCreate, true);
			foreach ($newApps as $num => $newApp) {
				$applications[$num]['applicationid'] = $newApp['applicationid'];
			}
		}
		if (!empty($appsUpdate)) {
			$this->update($appsUpdate);
		}

		return $applications;
	}
}
