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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class to perform low level application related actions.
 */
class ApplicationsManager {

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
		if ($batch) {
			$applicationids = DB::insertBatch('applications', $applications);
		}
		else {
			$applicationids = DB::insert('applications', $applications);
		}

		foreach ($applications as $anum => $application) {
			$applications[$anum]['applicationid'] = $applicationids[$anum];
		}

		// TODO: REMOVE info
		$dbCursor = DBselect('SELECT a.name, h.name as hostname'.
				' FROM applications a'.
				' INNER JOIN hosts h ON h.hostid=a.hostid'.
				' WHERE '.DBcondition('a.applicationid', $applicationids));
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
		foreach ($applications as $application) {
			$update[] = array(
				'values' => $application,
				'where' => array('applicationid' => $application['applicationid'])
			);
		}
		DB::update('applications', $update);

		// TODO: REMOVE info
		$dbCursor = DBselect('SELECT a.name, h.name as hostname'.
				' FROM applications a'.
				' INNER JOIN hosts h ON h.hostid=a.hostid'.
				' WHERE '.DBcondition('a.applicationid', zbx_objectValues($applications, 'applicationid')));
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

		// to reduce sql count select applications both from template and hosts in one sql (function call)
		// after that get template apps, remove them from hostApps list and convert to hash by applicationid
		$hostIds[] = $templateId;
		$hostApps = $this->getApplicationMapsByHosts($hostIds);
		$applications = $hostApps[$templateId]['byName'];
		unset($hostApps[$templateId]);
		$applications = zbx_toHash($applications, 'applicationid');
		$this->linkApplications($applications, $hostApps);

		return true;
	}

	/**
	 * Inherit template applications.
	 * In case applications are on template, we need to inherit them on child hosts,
	 *
	 * @param array $applications
	 */
	public function inherit(array $applications) {
		$hostIds = $this->getChildHostsFroApplications($applications);
		if (!$hostIds) {
			return;
		}

		$hostApps = $this->getApplicationMapsByHosts($hostIds);

		$this->linkApplications($applications, $hostApps);
	}

	/**
	 * Link passed applications to hosts.
	 * $hostApps is hosts hash with apps byname and byTemplateId, this structure received from getApplicationMapsByHosts method.
	 *
	 * @param array $applications
	 * @param array $hostApps
	 *
	 * @return bool
	 */
	protected function linkApplications(array $applications, array $hostApps) {
		$preparedApps = $this->prepareInheritedApps($applications, $hostApps);

		if (!empty($preparedApps['create'])) {
			$this->create($preparedApps['create'], true);
		}
		if (!empty($preparedApps['update'])) {
			$this->update($preparedApps['update']);
		}

		$inheritedApplications = array_merge($preparedApps['create'], $preparedApps['update']);

		$this->inherit($inheritedApplications);

		return true;
	}

	/**
	 * Get hosts that are linked with templates which passed applications belongs to.
	 *
	 * @param array $applications
	 *
	 * @return array|bool
	 */
	protected function getChildHostsFroApplications(array $applications) {
		$hostIds = array();
		$dbCursor = DBselect('SELECT ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.DBcondition('ht.templateid', array_unique(zbx_objectValues($applications, 'hostid'))));
		while ($dbHost = DBfetch($dbCursor)) {
			$hostIds[] = $dbHost['hostid'];
		}

		return empty($hostIds) ? false : $hostIds;
	}

	/**
	 * Get hosts appllications for each passed hosts.
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
	protected function getApplicationMapsByHosts(array $hostIds) {
		$hostApps = array();
		foreach ($hostIds as $hostid) {
			$hostApps[$hostid] = array('byName' => array(), 'byTemplateId' => array());
		}

		$dbCursor = DBselect('SELECT a.applicationid, a.name, a.hostid, a.templateid'.
				' FROM applications a'.
				' WHERE '.DBcondition('a.hostid', $hostIds));
		while ($dbApp = DBfetch($dbCursor)) {
			$hostApps[$dbApp['hostid']]['byName'][$dbApp['name']] = $dbApp;
			$hostApps[$dbApp['hostid']]['byTemplateId'][$dbApp['templateid']] = $dbApp;
		}

		return $hostApps;
	}

	/**
	 * Generate apps data for inheritance.
	 * Using passed parameters decide if new application must be created on host or existing one must be updated.
	 *
	 * @param array $applications which we need to inherit
	 * @param array $hostApps     hash with apps already existing on hosts
	 *
	 * @throws Exception
	 * @return array with keys 'create' and 'update'
	 */
	protected function prepareInheritedApps(array $applications, array $hostApps) {
		$result = array(
			'create' => array(),
			'update' => array()
		);

		foreach ($applications as $application) {
			$appId = $application['applicationid'];
			foreach ($hostApps as $hostId => $hostApp) {
				$exApplication = null;
				// update by templateid
				if (isset($hostApp['byTemplateId'][$appId])) {
					$exApplication = $hostApp['byTemplateId'][$appId];
				}

				// update by name
				if (isset($hostApp['byName'][$application['name']])) {
					$exApplication = $hostApp['byName'][$application['name']];
					if ($exApplication['templateid'] > 0 && !idcmp($exApplication['templateid'], $application['applicationid'])) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(_s('Application "%1$s" already exists for host "%2$s".', $exApplication['name'], $host['name']));
					}
				}

				$newApplication = $application;
				$newApplication['hostid'] = $hostId;
				$newApplication['templateid'] = $application['applicationid'];

				if ($exApplication) {
					$newApplication['applicationid'] = $exApplication['applicationid'];
					$result['update'][] = $newApplication;
				}
				else {
					$result['create'][] = $newApplication;
				}
			}
		}

		return $result;
	}
}
