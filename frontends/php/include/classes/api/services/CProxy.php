<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Class containing methods for operations with proxies.
 *
 * @package API
 */
class CProxy extends CApiService {

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';
	protected $sortColumns = ['hostid', 'host', 'status'];

	/**
	 * Get proxy data.
	 *
	 * @param array  $options
	 * @param array  $options['proxyids']
	 * @param bool   $options['editable']	only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']		returns value in rowscount
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['sortfield']
	 * @param string $options['sortorder']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$userType = self::$userData['type'];

		$sqlParts = [
			'select'	=> ['hostid' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'proxyids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'selectHosts'				=> null,
			'selectInterface'			=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			if ($permission == PERM_READ_WRITE) {
				return [];
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);
			$sqlParts['where'][] = dbConditionInt('h.hostid', $options['proxyids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['hostid'] = 'h.hostid';
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['select']['status'] = 'h.status';
			$sqlParts['select']['lastaccess'] = 'h.lastaccess';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = ['COUNT(DISTINCT h.hostid) AS rowscount'];
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($proxy = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $proxy['rowscount'];
			}
			else {
				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				$result[$proxy['proxyid']] = $proxy;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function create(array $proxies) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = zbx_toArray($proxies);

		$proxies = $this->convertDeprecatedValues($proxies);

		$this->validateCreate($proxies);

		foreach ($proxies as &$proxy) {
			// Clean encryption fields.
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
				if (!array_key_exists('tls_connect', $proxy)) {
					$proxy['tls_psk_identity'] = '';
					$proxy['tls_psk'] = '';
					$proxy['tls_issuer'] = '';
					$proxy['tls_subject'] = '';
				}
				else {
					if ($proxy['tls_connect'] != HOST_ENCRYPTION_PSK) {
						$proxy['tls_psk_identity'] = '';
						$proxy['tls_psk'] = '';
					}

					if ($proxy['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE) {
						$proxy['tls_issuer'] = '';
						$proxy['tls_subject'] = '';
					}
				}
			}
			elseif ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				if (!array_key_exists('tls_accept', $proxy)) {
					$proxy['tls_psk_identity'] = '';
					$proxy['tls_psk'] = '';
					$proxy['tls_issuer'] = '';
					$proxy['tls_subject'] = '';
				}
				else {
					if (($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK) {
						$proxy['tls_psk_identity'] = '';
						$proxy['tls_psk'] = '';
					}

					if (($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {
						$proxy['tls_issuer'] = '';
						$proxy['tls_subject'] = '';
					}
				}
			}

			// Mark the interface as main to pass host interface validation.
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && array_key_exists('interface', $proxy)) {
				$proxy['interface']['main'] = INTERFACE_PRIMARY;
			}
		}
		unset($proxy);

		$proxyids = DB::insert('hosts', $proxies);

		$hostUpdate = [];
		foreach ($proxies as $key => $proxy) {
			if (!empty($proxy['hosts'])) {
				$hostUpdate[] = [
					'values' => ['proxy_hostid' => $proxyids[$key]],
					'where' => ['hostid' => zbx_objectValues($proxy['hosts'], 'hostid')]
				];
			}

			// create interface
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
				$proxy['interface']['hostid'] = $proxyids[$key];

				if (!API::HostInterface()->create($proxy['interface'])) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface creation failed.'));
				}
			}
		}

		DB::update('hosts', $hostUpdate);

		return ['proxyids' => $proxyids];
	}

	/**
	 * Update proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function update(array $proxies) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = zbx_toArray($proxies);

		$proxies = $this->convertDeprecatedValues($proxies);

		$proxyids = zbx_objectValues($proxies, 'proxyid');

		foreach ($proxies as &$proxy) {
			if (array_key_exists('proxyid', $proxy)) {
				$proxy['hostid'] = $proxy['proxyid'];
			}
			elseif (array_key_exists('hostid', $proxy)) {
				$proxy['proxyid'] = $proxy['hostid'];
			}
		}
		unset($proxy);

		$db_proxies = $this->get([
			'output' => ['proxyid', 'hostid', 'host', 'status', 'tls_connect', 'tls_accept', 'tls_issuer',
				'tls_subject', 'tls_psk_identity', 'tls_psk'
			],
			'proxyids' => $proxyids,
			'editable' => true,
			'preservekeys' => true
		]);

		$this->validateUpdate($proxies, $db_proxies);

		foreach ($proxies as &$proxy) {
			$status = array_key_exists('status', $proxy) ? $proxy['status'] : $db_proxies[$proxy['proxyid']]['status'];

			// Clean encryption fields.
			$tls_connect = array_key_exists('tls_connect', $proxy)
				? $proxy['tls_connect']
				: $db_proxies[$proxy['proxyid']]['tls_connect'];

			$tls_accept = array_key_exists('tls_accept', $proxy)
				? $proxy['tls_accept']
				: $db_proxies[$proxy['proxyid']]['tls_accept'];

			// Clean PSK fields.
			if ($tls_connect != HOST_ENCRYPTION_PSK && ($tls_accept & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK) {
				$proxy['tls_psk_identity'] = '';
				$proxy['tls_psk'] = '';
			}

			// Clean certificate fields.
			if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE
					&& ($tls_accept & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {
				$proxy['tls_issuer'] = '';
				$proxy['tls_subject'] = '';
			}

			// Mark the interface as main to pass host interface validation.
			if ($status == HOST_STATUS_PROXY_PASSIVE && array_key_exists('interface', $proxy)) {
				$proxy['interface']['main'] = INTERFACE_PRIMARY;
			}
		}
		unset($proxy);

		$proxyUpdate = [];
		$hostUpdate = [];

		foreach ($proxies as $proxy) {
			$proxyUpdate[] = [
				'values' => $proxy,
				'where' => ['hostid' => $proxy['proxyid']]
			];

			if (isset($proxy['hosts'])) {
				// unset proxy for all hosts except for discovered hosts
				$hostUpdate[] = [
					'values' => ['proxy_hostid' => 0],
					'where' => [
						'proxy_hostid' => $proxy['proxyid'],
						'flags' => ZBX_FLAG_DISCOVERY_NORMAL
					]
				];

				$hostUpdate[] = [
					'values' => ['proxy_hostid' => $proxy['proxyid']],
					'where' => ['hostid' => zbx_objectValues($proxy['hosts'], 'hostid')]
				];
			}

			if (array_key_exists('status', $proxy) && $proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				// If this is an active proxy, delete it's interface.

				$interfaces = API::HostInterface()->get([
					'hostids' => $proxy['hostid'],
					'output' => ['interfaceid']
				]);
				$interfaceIds = zbx_objectValues($interfaces, 'interfaceid');

				if ($interfaceIds) {
					API::HostInterface()->delete($interfaceIds);
				}
			}
			elseif (array_key_exists('interface', $proxy) && is_array($proxy['interface'])) {
				// Update the interface of a passive proxy.

				$proxy['interface']['hostid'] = $proxy['hostid'];

				$result = isset($proxy['interface']['interfaceid'])
					? API::HostInterface()->update($proxy['interface'])
					: API::HostInterface()->create($proxy['interface']);

				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface update failed.'));
				}
			}
		}

		DB::update('hosts', $proxyUpdate);
		DB::update('hosts', $hostUpdate);

		return ['proxyids' => $proxyids];
	}

	/**
	 * Delete proxy.
	 *
	 * @param array	$proxyIds
	 *
	 * @return array
	 */
	public function delete(array $proxyIds) {
		$this->validateDelete($proxyIds);

		$dbProxies = DBselect(
			'SELECT h.hostid,h.host'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.hostid', $proxyIds)
		);
		$dbProxies = DBfetchArrayAssoc($dbProxies, 'hostid');

		$actionIds = [];

		// get conditions
		$dbActions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_PROXY.
				' AND '.dbConditionString('c.value', $proxyIds)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionIds[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if ($actionIds) {
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionIds]
			]);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_PROXY,
			'value' => $proxyIds
		]);

		// delete interface
		DB::delete('interface', ['hostid' => $proxyIds]);

		// delete host
		DB::delete('hosts', ['hostid' => $proxyIds]);

		// TODO: remove info from API
		foreach ($dbProxies as $proxy) {
			info(_s('Deleted: Proxy "%1$s".', $proxy['host']));
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_PROXY, '['.$proxy['host'].'] ['.$proxy['hostid'].']');
		}

		return ['proxyids' => $proxyIds];
	}

	/**
	 * Check if proxies can be deleted.
	 *  - only super admin can delete proxy
	 *  - cannot delete proxy if it is used to monitor host
	 *  - cannot delete proxy if it is used in discovery rule
	 *
	 * @param array $proxyIds
	 */
	protected function validateDelete(array $proxyIds) {
		if (empty($proxyIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkPermissions($proxyIds);
		$this->checkUsedInDiscoveryRule($proxyIds);
		$this->checkUsedForMonitoring($proxyIds);
	}

	/**
	 * Check if user has read permissions for proxy.
	 *
	 * @param array $proxyIds
	 *
	 * @return bool
	 */
	public function isReadable(array $proxyIds) {
		if (empty($proxyIds)) {
			return true;
		}

		$proxyIds = array_unique($proxyIds);

		$count = $this->get([
			'proxyids' => $proxyIds,
			'countOutput' => true
		]);

		return (count($proxyIds) == $count);
	}

	/**
	 * Check if user has write permissions for proxy.
	 *
	 * @param array $proxyIds
	 *
	 * @return bool
	 */
	public function isWritable(array $proxyIds) {
		if (empty($proxyIds)) {
			return true;
		}

		$proxyIds = array_unique($proxyIds);

		$count = $this->get([
			'proxyids' => $proxyIds,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($proxyIds) == $count);
	}

	/**
	 * Checks if the given proxies are editable.
	 *
	 * @param array $proxyIds	proxy IDs to check
	 *
	 * @throws APIException		if the user has no permissions to edit proxies or a proxy does not exist
	 */
	protected function checkPermissions(array $proxyIds) {
		if (!$this->isWritable($proxyIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check if proxy is used in discovery rule.
	 *
	 * @param array $proxyIds
	 */
	protected function checkUsedInDiscoveryRule(array $proxyIds) {
		$dRule = DBfetch(DBselect(
			'SELECT dr.druleid,dr.name,dr.proxy_hostid'.
			' FROM drules dr'.
			' WHERE '.dbConditionInt('dr.proxy_hostid', $proxyIds),
			1
		));
		if ($dRule) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.zbx_dbstr($dRule['proxy_hostid'])));

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Proxy "%1$s" is used by discovery rule "%2$s".', $proxy['host'], $dRule['name']));
		}
	}

	/**
	 * Check if proxy is used to monitor hosts.
	 *
	 * @param array $proxyIds
	 */
	protected function checkUsedForMonitoring(array $proxyIds) {
		$host = DBfetch(DBselect(
			'SELECT h.name,h.proxy_hostid'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.proxy_hostid', $proxyIds),
			1
		));
		if ($host) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.zbx_dbstr($host['proxy_hostid'])));

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host "%1$s" is monitored with proxy "%2$s".', $host['name'], $proxy['host']));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null && $options['selectInterface'] !== null) {
			$sqlParts = $this->addQuerySelect('h.hostid', $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$proxyIds = array_keys($result);

		// selectHosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$hosts = API::Host()->get([
				'output' => $this->outputExtend($options['selectHosts'], ['hostid', 'proxy_hostid']),
				'proxyids' => $proxyIds,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($hosts, 'proxy_hostid', 'hostid');
			$hosts = $this->unsetExtraFields($hosts, ['proxy_hostid', 'hostid'], $options['selectHosts']);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding host interface
		if ($options['selectInterface'] !== null && $options['selectInterface'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get([
				'output' => $this->outputExtend($options['selectInterface'], ['interfaceid', 'hostid']),
				'hostids' => $proxyIds,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');
			$interfaces = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'], $options['selectInterface']);
			$result = $relationMap->mapOne($result, $interfaces, 'interface');

			foreach ($result as $key => $proxy) {
				if (!empty($proxy['interface'])) {
					$result[$key]['interface'] = $proxy['interface'];
				}
			}
		}

		return $result;
	}

	/**
	 * Convert deprecated "interfaces" to "interface".
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	protected function convertDeprecatedValues($proxies) {
		foreach ($proxies as $key => $proxy) {
			if (isset($proxy['interfaces'])) {
				$this->deprecated('Array of "interfaces" is deprecated, use single "interface" instead.');

				$proxy['interface'] = reset($proxy['interfaces']);
				unset($proxy['interfaces']);

				$proxies[$key] = $proxy;
			}
		}

		return $proxies;
	}

	/**
	 * Validate connections from/to proxy and PSK fields.
	 *
	 * @param array $proxies	proxies data array
	 *
	 * @throws APIException	if incorrect encryption options.
	 */
	protected function validateEncryption(array $proxies) {
		foreach ($proxies as $proxy) {
			$available_connect_types = [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE];
			$available_accept_types = [
				HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, (HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK),
				HOST_ENCRYPTION_CERTIFICATE, (HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_CERTIFICATE),
				(HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)
			];

			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && array_key_exists('tls_connect', $proxy)
					&& !in_array($proxy['tls_connect'], $available_connect_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_connect',
					_s('unexpected value "%1$s"', $proxy['tls_connect'])
				));
			}

			if ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE && array_key_exists('tls_accept', $proxy)
					&& !in_array($proxy['tls_accept'], $available_accept_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_accept',
					_s('unexpected value "%1$s"', $proxy['tls_accept'])
				));
			}

			// PSK validation.
			if ((array_key_exists('tls_connect', $proxy) && $proxy['tls_connect'] == HOST_ENCRYPTION_PSK
					&& $proxy['status'] == HOST_STATUS_PROXY_PASSIVE)
						|| (array_key_exists('tls_accept', $proxy)
							&& ($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK
							&& $proxy['status'] == HOST_STATUS_PROXY_ACTIVE)) {
				if (!array_key_exists('tls_psk_identity', $proxy) || zbx_empty($proxy['tls_psk_identity'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('cannot be empty'))
					);
				}

				if (!array_key_exists('tls_psk', $proxy) || zbx_empty($proxy['tls_psk'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('cannot be empty'))
					);
				}

				if (!preg_match('/^([0-9a-f]{2})+$/i', $proxy['tls_psk'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _(
						'Incorrect value used for PSK field. It should consist of an even number of hexadecimal characters.'
					));
				}

				if (strlen($proxy['tls_psk']) < PSK_MIN_LEN) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('PSK is too short. Minimum is %1$s hex-digits.', PSK_MIN_LEN)
					);
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $proxies	proxies data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $proxies) {
		$proxy_db_fields = ['host' => null];
		$names = [];

		foreach ($proxies as $proxy) {
			if (!check_db_fields($proxy_db_fields, $proxy)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for proxy "%1$s".', $proxy['host']));
			}

			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $proxy['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect characters used for proxy name "%1$s".', $proxy['host'])
				);
			}

			$names[$proxy['host']] = true;
		}

		$proxy_exists = $this->get([
			'output' => ['proxyid', 'host'],
			'filter' => ['host' => array_keys($names)],
			'limit' => 1
		]);

		if ($proxy_exists) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%s" already exists.', $proxy_exists[0]['host']));
		}

		$hostids = [];

		foreach ($proxies as $proxy) {
			if (!array_key_exists('status', $proxy)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No status for proxy.'));
			}
			elseif ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE && $proxy['status'] != HOST_STATUS_PROXY_PASSIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value used for proxy status "%1$s".', $proxy['status'])
				);
			}

			// interface
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE
					&& (!array_key_exists('interface', $proxy)
						|| !is_array($proxy['interface']) || !$proxy['interface'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interface provided for proxy "%s".', $proxy['host']));
			}

			if (array_key_exists('hosts', $proxy) && $proxy['hosts']) {
				$hostids = array_merge($hostids, zbx_objectValues($proxy['hosts'], 'hostid'));
			}
		}

		if ($hostids) {
			// Check if host exists.
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $hostids,
				'editable' => true
			]);

			if (!$hosts) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Check if any of the affected hosts are discovered.
			$this->checkValidator($hostids, new CHostNormalValidator([
				'message' => _('Cannot update proxy for discovered host "%1$s".')
			]));
		}

		$this->validateEncryption($proxies);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $proxies		proxies data array
	 * @param array $db_proxies		db proxies data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $proxies, array $db_proxies) {
		$proxy_db_fields = ['proxyid' => null];
		$names = [];

		foreach ($proxies as $proxy) {
			if (!check_db_fields($proxy_db_fields, $proxy)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for proxy "%1$s".', $proxy['host']));
			}

			if (!array_key_exists($proxy['proxyid'], $db_proxies)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// host
			if (array_key_exists('host', $proxy)) {
				if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $proxy['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect characters used for proxy name "%1$s".', $proxy['host'])
					);
				}

				if ($proxy['host'] !== $db_proxies[$proxy['proxyid']]['host']) {
					$names[$proxy['host']] = true;
				}
			}
		}

		// Check names that have been changed.
		if ($names) {
			$proxies_exists = $this->get([
				'output' => ['proxyid'],
				'filter' => ['host' => array_keys($names)]
			]);

			foreach ($proxies as $proxy) {
				if (array_key_exists('host', $proxy) && $proxy['host'] !== $db_proxies[$proxy['proxyid']]['host']) {
					foreach ($proxies_exists as $proxy_exists) {
						if (bccomp($proxy_exists['proxyid'], $proxy['proxyid']) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%s" already exists.', $proxy['host']));
						}
					}
				}
			}
		}

		$hostids = [];

		foreach ($proxies as $proxy) {
			if (array_key_exists('status', $proxy) && ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE
					&& $proxy['status'] != HOST_STATUS_PROXY_PASSIVE)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value used for proxy status "%1$s".', $proxy['status'])
				);
			}

			if (array_key_exists('hosts', $proxy) && $proxy['hosts']) {
				$hostids = array_merge($hostids, zbx_objectValues($proxy['hosts'], 'hostid'));
			}
		}

		if ($hostids) {
			// Check if host exists.
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $hostids,
				'editable' => true
			]);

			if (!$hosts) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Check if any of the affected hosts are discovered.
			$this->checkValidator($hostids, new CHostNormalValidator([
				'message' => _('Cannot update proxy for discovered host "%1$s".')
			]));
		}

		$status = array_key_exists('status', $proxy) ? $proxy['status'] : $db_proxies[$proxy['proxyid']]['status'];

		// interface
		if ($status == HOST_STATUS_PROXY_PASSIVE && array_key_exists('interface', $proxy)
				&& (!is_array($proxy['interface']) || !$proxy['interface'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('No interface provided for proxy "%s".', $proxy['host'])
			);
		}

		$proxies = $this->extendFromObjects(zbx_toHash($proxies, 'proxyid'), $db_proxies, [
			'status', 'tls_connect', 'tls_accept', 'tls_psk_identity', 'tls_psk'
		]);

		$this->validateEncryption($proxies);
	}
}
