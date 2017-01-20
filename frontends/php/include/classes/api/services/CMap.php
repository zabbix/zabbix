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
 * Class containing methods for operations with maps.
 *
 * @package API
 */
class CMap extends CMapElement {

	protected $tableName = 'sysmaps';
	protected $tableAlias = 's';
	protected $sortColumns = ['name', 'width', 'height'];

	/**
	 * Get map data.
	 *
	 * @param array  $options
	 * @param array  $options['userids']					User IDs
	 * @param array  $options['groupids']					HostGroup IDs
	 * @param array  $options['hostids']					Host IDs
	 * @param bool   $options['monitored_hosts']			only monitored Hosts
	 * @param bool   $options['templated_hosts']			include templates in result
	 * @param bool   $options['with_items']					only with items
	 * @param bool   $options['with_monitored_items']		only with monitored items
	 * @param bool   $options['with_triggers'] only with	triggers
	 * @param bool   $options['with_monitored_triggers']	only with monitored triggers
	 * @param bool   $options['with_httptests'] only with	http tests
	 * @param bool   $options['with_monitored_httptests']	only with monitored http tests
	 * @param bool   $options['with_graphs']				only with graphs
	 * @param bool   $options['editable']					only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']						count Hosts, returned column name is rowscount
	 * @param string $options['pattern']					search hosts by pattern in host names
	 * @param int    $options['limit']						limit selection
	 * @param string $options['sortorder']
	 * @param string $options['sortfield']
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get(array $options = []) {
		$result = [];
		$user_data = self::$userData;

		$sql_parts = [
			'select'	=> ['sysmaps' => 's.sysmapid'],
			'from'		=> ['sysmaps' => 'sysmaps s'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'sysmapids'					=> null,
			'userids'					=> null,
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
			'selectSelements'			=> null,
			'selectLinks'				=> null,
			'selectIconMap'				=> null,
			'selectUrls'				=> null,
			'selectUsers'				=> null,
			'selectUserGroups'			=> null,
			'countOutput'				=> null,
			'expandUrls' 				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if ($options['countOutput'] !== null) {
			$count_output = true;
			$options['output'] = array('sysmapid');
			$options['countOutput'] = null;
			$options['limit'] = null;
		}
		else {
			$count_output = false;
		}

		// Editable + permission check.
		if ($user_data['type'] != USER_TYPE_SUPER_ADMIN && $user_data['type'] != USER_TYPE_ZABBIX_ADMIN
				&& !$options['nopermissions']) {
			$public_maps = '';

			if ($options['editable']) {
				$permission = PERM_READ_WRITE;
			}
			else {
				$permission = PERM_READ;
				$public_maps = ' OR s.private='.PUBLIC_SHARING;
			}

			$user_groups = getUserGroupsByUserId($user_data['userid']);

			$sql_parts['where'][] = '(EXISTS ('.
					'SELECT NULL'.
					' FROM sysmap_user su'.
					' WHERE s.sysmapid=su.sysmapid'.
						' AND su.userid='.$user_data['userid'].
						' AND su.permission>='.$permission.
				')'.
				' OR EXISTS ('.
					'SELECT NULL'.
					' FROM sysmap_usrgrp sg'.
					' WHERE s.sysmapid=sg.sysmapid'.
						' AND '.dbConditionInt('sg.usrgrpid', $user_groups).
						' AND sg.permission>='.$permission.
				')'.
				' OR s.userid='.$user_data['userid'].
				$public_maps.
			')';
		}

		// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);
			$sql_parts['where']['sysmapid'] = dbConditionInt('s.sysmapid', $options['sysmapids']);
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sql_parts['where'][] = dbConditionInt('s.userid', $options['userids']);
		}

		// search
		if (!is_null($options['search'])) {
			zbx_db_search('sysmaps s', $options, $sql_parts);
		}

		// filter
		if (!is_null($options['filter'])) {
			$this->dbFilter('sysmaps s', $options, $sql_parts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$sysmapids = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$res = DBselect($this->createSelectQueryFromParts($sql_parts), $sql_parts['limit']);
		while ($sysmap = DBfetch($res)) {
			$sysmapids[$sysmap['sysmapid']] = $sysmap['sysmapid'];

			// originally we intended not to pass those parameters if advanced labels are off, but they might be useful
			// leaving this block commented
			// if (isset($sysmap['label_format']) && ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_OFF)) {
			// 	unset($sysmap['label_string_hostgroup'], $sysmap['label_string_host'], $sysmap['label_string_trigger'], $sysmap['label_string_map'], $sysmap['label_string_image']);
			// }

			$result[$sysmap['sysmapid']] = $sysmap;
		}

		if ($user_data['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if ($result) {
				$linkTriggers = [];

				$dbLinkTriggers = DBselect(
					'SELECT slt.triggerid,sl.sysmapid'.
					' FROM sysmaps_link_triggers slt,sysmaps_links sl'.
					' WHERE '.dbConditionInt('sl.sysmapid', $sysmapids).
						' AND sl.linkid=slt.linkid'
				);
				while ($linkTrigger = DBfetch($dbLinkTriggers)) {
					$linkTriggers[$linkTrigger['sysmapid']] = $linkTrigger['triggerid'];
				}

				if ($linkTriggers) {
					$all_triggers = API::Trigger()->get([
						'output' => ['triggerid'],
						'triggerids' => $linkTriggers,
						'preservekeys' => true
					]);

					foreach ($linkTriggers as $id => $triggerid) {
						if (!array_key_exists($triggerid, $all_triggers)) {
							unset($result[$id], $sysmapids[$id]);
						}
					}
				}

				$hostsToCheck = [];
				$mapsToCheck = [];
				$triggersToCheck = [];
				$hostGroupsToCheck = [];

				$selements = [];
				$dbSelements = DBselect(
					'SELECT se.*'.
					' FROM sysmaps_elements se'.
					' WHERE '.dbConditionInt('se.sysmapid', $sysmapids)
				);

				while ($selement = DBfetch($dbSelements)) {
					$selements[$selement['selementid']] = $selement;

					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST:
							$hostsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$mapsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$triggersToCheck[$selement['elementid']] = $selement['elementid'];
							break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$hostGroupsToCheck[$selement['elementid']] = $selement['elementid'];
							break;
					}
				}

				if ($hostsToCheck) {
					$allowedHosts = API::Host()->get([
						'output' => ['hostid'],
						'hostids' => $hostsToCheck,
						'preservekeys' => true
					]);

					foreach ($hostsToCheck as $elementid) {
						if (!array_key_exists($elementid, $allowedHosts)) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($mapsToCheck) {
					$allowedMaps = $this->get([
						'output' => ['sysmapid'],
						'sysmapids' => $mapsToCheck,
						'preservekeys' => true
					]);

					foreach ($mapsToCheck as $elementid) {
						if (!array_key_exists($elementid, $allowedMaps)) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($triggersToCheck) {
					$allowedTriggers = API::Trigger()->get([
						'triggerids' => $triggersToCheck,
						'preservekeys' => true,
						'output' => ['triggerid']
					]);

					foreach ($triggersToCheck as $elementid) {
						if (!isset($allowedTriggers[$elementid])) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if ($hostGroupsToCheck) {
					$allowedHostGroups = API::HostGroup()->get([
						'output' => ['groupid'],
						'groupids' => $hostGroupsToCheck,
						'preservekeys' => true
					]);

					foreach ($hostGroupsToCheck as $elementid) {
						if (!array_key_exists($elementid, $allowedHostGroups)) {
							foreach ($selements as $selementid => $selement) {
								if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
										&& bccomp($selement['elementid'], $elementid) == 0) {
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}
			}
		}

		if ($count_output) {
			return count($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $sysmapids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $sysmapids) {
		if (!$sysmapids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_maps = $this->get([
			'output' => ['sysmapid'],
			'sysmapids' => $sysmapids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($sysmapids as $sysmapid) {
			if (!array_key_exists($sysmapid, $db_maps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * Validate the input parameters for the create() method.
	 *
	 * @param array $maps		maps data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $maps) {
		if (!$maps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$user_data = self::$userData;

		$map_db_fields = [
			'name' => null,
			'width' => null,
			'height' => null,
			'urls' => [],
			'selements' => [],
			'links' => []
		];

		// Validate mandatory fields and map name.
		foreach ($maps as $map) {
			if (!check_db_fields($map_db_fields, $map)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect fields for sysmap.'));
			}
		}

		// Check for duplicate names.
		$duplicate = CArrayHelper::findDuplicate($maps, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for map.', $duplicate['name'])
			);
		}

		// Check if map already exists.
		$db_maps = $this->get([
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($maps, 'name')],
			'nopermissions' => true,
			'limit' => 1
		]);

		if ($db_maps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Map "%1$s" already exists.', $db_maps[0]['name']));
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		// Continue to check 2 more mandatory fields and other optional fields.
		foreach ($maps as $map) {
			// Check mandatory fields "width" and "height".
			if ($map['width'] > 65535 || $map['width'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "width" value for map "%1$s".', $map['name'])
				);
			}

			if ($map['height'] > 65535 || $map['height'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "height" value for map "%1$s".', $map['name'])
				);
			}

			// Check if owner can be set.
			if (array_key_exists('userid', $map)) {
				if ($map['userid'] === '' || $map['userid'] === null || $map['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map owner cannot be empty.'));
				}
				elseif ($map['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set map owner.'));
				}
			}

			// Check for invalid "private" values.
			if (array_key_exists('private', $map)) {
				if (!$private_validator->validate($map['private'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect "private" value "%1$s" for map "%2$s".', $map['private'], $map['name'])
					);
				}
			}

			$userids = [];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				if (!is_array($map['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($map['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if (array_key_exists('private', $map) && $map['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for map "%2$s".', $share['userid'], $map['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $map) && $map['userid']) {
				$userids[$map['userid']] = $map['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for map "%1$s".', $map['name'])
					);
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				if (!is_array($map['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($map['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Field "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if (array_key_exists('private', $map) && $map['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate usrgrpid "%1$s" in user groups for map "%2$s".',
							$share['usrgrpid'],
							$map['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for map "%1$s".', $map['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}

			// Map labels.
			$map_labels = ['label_type' => ['typeName' => _('icon')]];

			if (array_key_exists('label_format', $map) && $map['label_format'] == SYSMAP_LABEL_ADVANCED_ON) {
				$map_labels['label_type_hostgroup'] = [
					'string' => 'label_string_hostgroup',
					'typeName' => _('host group')
				];
				$map_labels['label_type_host'] = [
					'string' => 'label_string_host',
					'typeName' => _('host')
				];
				$map_labels['label_type_trigger'] = [
					'string' => 'label_string_trigger',
					'typeName' => _('trigger')
				];
				$map_labels['label_type_map'] = [
					'string' => 'label_string_map',
					'typeName' => _('map')
				];
				$map_labels['label_type_image'] = [
					'string' => 'label_string_image',
					'typeName' => _('image')
				];
			}

			foreach ($map_labels as $label_name => $label_data) {
				if (!array_key_exists($label_name, $map)) {
					continue;
				}

				if (sysmapElementLabel($map[$label_name]) === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_CUSTOM) {
					if (!array_key_exists('string', $label_data)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
						);
					}

					if (!array_key_exists($label_data['string'], $map) || zbx_empty($map[$label_data['string']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Custom label for map "%2$s" elements of type "%1$s" may not be empty.',
								$label_data['typeName'],
								$map['name']
							)
						);
					}
				}

				if ($label_name == 'label_type_image' && $map[$label_name] == MAP_LABEL_TYPE_STATUS) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}

				if ($label_name === 'label_type' || $label_name === 'label_type_host') {
					continue;
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_IP) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect %1$s label type value for map "%2$s".', $label_data['typeName'], $map['name'])
					);
				}
			}

			// Validating grid options.
			$possibleGridSizes = [20, 40, 50, 75, 100];

			// Grid size.
			if (array_key_exists('grid_size', $map) && !in_array($map['grid_size'], $possibleGridSizes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s".',
					$map['grid_size'],
					implode('", "', $possibleGridSizes)
				));
			}

			// Grid auto align.
			if (array_key_exists('grid_align', $map) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON
					&& $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"',
					$map['grid_align'],
					SYSMAP_GRID_ALIGN_ON,
					SYSMAP_GRID_ALIGN_OFF
				));
			}

			// Grid show.
			if (array_key_exists('grid_show', $map) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON
					&& $map['grid_show'] != SYSMAP_GRID_SHOW_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s".',
					$map['grid_show'],
					SYSMAP_GRID_SHOW_ON,
					SYSMAP_GRID_SHOW_OFF
				));
			}

			// Urls.
			if (array_key_exists('urls', $map) && $map['urls']) {
				$url_names = zbx_toHash($map['urls'], 'name');

				foreach ($map['urls'] as $url) {
					if ($url['name'] === '' || $url['url'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL should have both "name" and "url" fields for map "%1$s".', $map['name'])
						);
					}

					if (!array_key_exists($url['name'], $url_names)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL name should be unique for map "%1$s".', $map['name'])
						);
					}

					unset($url_names[$url['name']]);
				}
			}

			if (array_key_exists('selements', $map) && !is_array($map['selements'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Map selement links.
			if (array_key_exists('links', $map) && $map['links']) {
				$selementids = zbx_objectValues($map['selements'], 'selementid');

				foreach ($map['links'] as $link) {
					if (!in_array($link['selementid1'], $selementids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Link selementid1 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
							$link['selementid1'],
							$map['name']
						));
					}

					if (!in_array($link['selementid2'], $selementids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Link selementid2 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
							$link['selementid2'],
							$map['name']
						));
					}
				}
			}
		}
	}

	/**
	 * Validate the input parameters for the update() method.
	 *
	 * @param array $maps			maps data array
	 * @param array $db_maps		db maps data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $maps, array $db_maps) {
		if (!$maps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$user_data = self::$userData;

		// Validate given IDs.
		$this->checkObjectIds($maps, 'sysmapid',
			_('No "%1$s" given for map.'),
			_('Empty map ID.'),
			_('Incorrect map ID.')
		);

		$check_names = [];

		foreach ($maps as $map) {
			// Check if this map exists and user has write permissions.
			if (!array_key_exists($map['sysmapid'], $db_maps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Validate "name" field.
			if (array_key_exists('name', $map)) {
				if (is_array($map['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($map['name'] === '' || $map['name'] === null || $map['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map name cannot be empty.'));
				}

				if ($db_maps[$map['sysmapid']]['name'] !== $map['name']) {
					$check_names[] = $map;
				}
			}
		}

		if ($check_names) {
			// Check for duplicate names.
			$duplicate = CArrayHelper::findDuplicate($check_names, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate "name" value "%1$s" for map.', $duplicate['name'])
				);
			}

			$db_map_names = $this->get([
				'output' => ['sysmapid', 'name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')],
				'nopermissions' => true
			]);
			$db_map_names = zbx_toHash($db_map_names, 'name');

			// Check for existing names.
			foreach ($check_names as $map) {
				if (array_key_exists($map['name'], $db_map_names)
						&& bccomp($db_map_names[$map['name']]['sysmapid'], $map['sysmapid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Map "%1$s" already exists.', $map['name'])
					);
				}
			}
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		foreach ($maps as $map) {
			// Check if owner can be set.
			if (array_key_exists('userid', $map)) {
				if ($map['userid'] === '' || $map['userid'] === null || $map['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Map owner cannot be empty.'));
				}
				elseif ($map['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set map owner.'));
				}
			}

			// Unset extra field.
			unset($db_maps[$map['sysmapid']]['userid']);

			$map = array_merge($db_maps[$map['sysmapid']], $map);

			// Check "width" and "height" fields.
			if ($map['width'] > 65535 || $map['width'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "width" value for map "%1$s".', $map['name'])
				);
			}

			if ($map['height'] > 65535 || $map['height'] < 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "height" value for map "%1$s".', $map['name'])
				);
			}

			if (!$private_validator->validate($map['private'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "private" value "%1$s" for map "%2$s".', $map['private'], $map['name'])
				);
			}

			$userids = [];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				if (!is_array($map['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($map['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if ($map['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for map "%2$s".', $share['userid'], $map['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $map) && $map['userid']) {
				$userids[$map['userid']] = $map['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for map "%1$s".', $map['name'])
					);
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				if (!is_array($map['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($map['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for map "%2$s".',
							implode(', ', $missing_keys),
							$map['name'])
						);
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for map "%2$s".',
									$field,
									$map['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for map "%2$s".',
							$share['permission'],
							$map['name']
						));
					}

					if ($map['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map "%1$s" is public and read-only sharing is disallowed.', $map['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate usrgrpid "%1$s" in user groups for map "%2$s".',
							$share['usrgrpid'],
							$map['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for map "%1$s".', $map['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}

			// Map labels.
			$map_labels = ['label_type' => ['typeName' => _('icon')]];

			if (array_key_exists('label_format', $map)
					&& $map['label_format'] == SYSMAP_LABEL_ADVANCED_ON) {
				$map_labels['label_type_hostgroup'] = [
					'string' => 'label_string_hostgroup',
					'typeName' => _('host group')
				];
				$map_labels['label_type_host'] = [
					'string' => 'label_string_host',
					'typeName' => _('host')
				];
				$map_labels['label_type_trigger'] = [
					'string' => 'label_string_trigger',
					'typeName' => _('trigger')
				];
				$map_labels['label_type_map'] = [
					'string' => 'label_string_map',
					'typeName' => _('map')
				];
				$map_labels['label_type_image'] = [
					'string' => 'label_string_image',
					'typeName' => _('image')
				];
			}

			foreach ($map_labels as $label_name => $labelData) {
				if (!array_key_exists($label_name, $map)) {
					continue;
				}

				if (sysmapElementLabel($map[$label_name]) === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_CUSTOM) {
					if (!array_key_exists('string', $labelData)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect %1$s label type value for map "%2$s".',
							$labelData['typeName'],
							$map['name']
						));
					}

					if (!array_key_exists($labelData['string'], $map) || zbx_empty($map[$labelData['string']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Custom label for map "%2$s" elements of type "%1$s" may not be empty.',
							$labelData['typeName'],
							$map['name']
						));
					}
				}

				if ($label_name === 'label_type_image' && $map[$label_name] == MAP_LABEL_TYPE_STATUS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}

				if ($label_name === 'label_type' || $label_name === 'label_type_host') {
					continue;
				}

				if ($map[$label_name] == MAP_LABEL_TYPE_IP) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect %1$s label type value for map "%2$s".',
						$labelData['typeName'],
						$map['name']
					));
				}
			}

			// Validating grid options.
			$possibleGridSizes = [20, 40, 50, 75, 100];

			// Grid size.
			if (array_key_exists('grid_size', $map) && !in_array($map['grid_size'], $possibleGridSizes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s".',
					$map['grid_size'],
					implode('", "', $possibleGridSizes)
				));
			}

			// Grid auto align.
			if (array_key_exists('grid_align', $map) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON
					&& $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"',
					$map['grid_align'],
					SYSMAP_GRID_ALIGN_ON,
					SYSMAP_GRID_ALIGN_OFF
				));
			}

			// Grid show.
			if (array_key_exists('grid_show', $map) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON
					&& $map['grid_show'] != SYSMAP_GRID_SHOW_OFF) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s".',
					$map['grid_show'],
					SYSMAP_GRID_SHOW_ON,
					SYSMAP_GRID_SHOW_OFF
				));
			}

			// Urls.
			if (array_key_exists('urls', $map) && !empty($map['urls'])) {
				$urlNames = zbx_toHash($map['urls'], 'name');

				foreach ($map['urls'] as $url) {
					if ($url['name'] === '' || $url['url'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL should have both "name" and "url" fields for map "%1$s".', $map['name'])
						);
					}

					if (!array_key_exists($url['name'], $urlNames)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('URL name should be unique for map "%1$s".', $map['name'])
						);
					}

					unset($urlNames[$url['name']]);
				}
			}

			if (array_key_exists('selements', $map) && !is_array($map['selements'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Map selement links.
			if (array_key_exists('links', $map) && $map['links']) {
				$selementids = zbx_objectValues($map['selements'], 'selementid');

				foreach ($map['links'] as $link) {
					if (!in_array($link['selementid1'], $selementids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Link selementid1 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
							$link['selementid1'],
							$map['name']
						));
					}

					if (!in_array($link['selementid2'], $selementids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Link selementid2 field is pointing to a nonexistent map selement ID "%1$s" for map "%2$s".',
							$link['selementid2'],
							$map['name']
						));
					}
				}
			}
		}
	}

	/**
	 * Add map.
	 *
	 * @param array  $maps
	 * @param string $maps['name']
	 * @param array  $maps['width']
	 * @param int    $maps['height']
	 * @param string $maps['backgroundid']
	 * @param string $maps['highlight']
	 * @param array  $maps['label_type']
	 * @param int    $maps['label_location']
	 * @param int    $maps['grid_size']			size of one grid cell. 100 refers to 100x100 and so on.
	 * @param int    $maps['grid_show']			does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
	 * @param int    $maps['grid_align']		do elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
	 *
	 * @return array
	 */
	public function create($maps) {
		$maps = zbx_toArray($maps);

		$this->validateCreate($maps);

		foreach ($maps as &$map) {
			$map['userid'] = array_key_exists('userid', $map) ? $map['userid'] : self::$userData['userid'];
		}
		unset($map);

		$sysmapids = DB::insert('sysmaps', $maps);

		$shared_users = [];
		$shared_user_groups = [];
		$urls = [];
		$selements = [];
		$links = [];

		foreach ($sysmapids as $key => $sysmapid) {
			// Map user shares.
			if (array_key_exists('users', $maps[$key])) {
				foreach ($maps[$key]['users'] as $user) {
					$shared_users[] = [
						'sysmapid' => $sysmapid,
						'userid' => $user['userid'],
						'permission' => $user['permission']
					];
				}
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $maps[$key])) {
				foreach ($maps[$key]['userGroups'] as $user_group) {
					$shared_user_groups[] = [
						'sysmapid' => $sysmapid,
						'usrgrpid' => $user_group['usrgrpid'],
						'permission' => $user_group['permission']
					];
				}
			}

			if (array_key_exists('urls', $maps[$key])) {
				foreach ($maps[$key]['urls'] as $url) {
					$url['sysmapid'] = $sysmapid;
					$urls[] = $url;
				}
			}

			if (array_key_exists('selements', $maps[$key])) {
				foreach ($maps[$key]['selements'] as $snum => $selement) {
					$maps[$key]['selements'][$snum]['sysmapid'] = $sysmapid;
				}

				$selements += $maps[$key]['selements'];
			}

			if (array_key_exists('links', $maps[$key])) {
				foreach ($maps[$key]['links'] as $lnum => $link) {
					$maps[$key]['links'][$lnum]['sysmapid'] = $sysmapid;
				}

				$links += $maps[$key]['links'];
			}
		}

		DB::insert('sysmap_user', $shared_users);
		DB::insert('sysmap_usrgrp', $shared_user_groups);
		DB::insert('sysmap_url', $urls);

		if ($selements) {
			$selementids = $this->createSelements($selements);

			if ($links) {
				$map_virt_selements = [];
				foreach ($selementids['selementids'] as $key => $selementid) {
					$map_virt_selements[$selements[$key]['selementid']] = $selementid;
				}

				foreach ($links as $key => $link) {
					$links[$key]['selementid1'] = $map_virt_selements[$link['selementid1']];
					$links[$key]['selementid2'] = $map_virt_selements[$link['selementid2']];
				}
				unset($map_virt_selements);

				$linkids = $this->createLinks($links);

				$link_triggers = [];
				foreach ($linkids['linkids'] as $key => $linkId) {
					if (!array_key_exists('linktriggers', $links[$key])) {
						continue;
					}

					foreach ($links[$key]['linktriggers'] as $link_trigger) {
						$link_trigger['linkid'] = $linkId;
						$link_triggers[] = $link_trigger;
					}
				}

				if ($link_triggers) {
					$this->createLinkTriggers($link_triggers);
				}
			}
		}

		return ['sysmapids' => $sysmapids];
	}

	/**
	 * Update map.
	 *
	 * @param array  $maps						multidimensional array with Hosts data
	 * @param string $maps['sysmapid']
	 * @param string $maps['name']
	 * @param array  $maps['width']
	 * @param int    $maps['height']
	 * @param string $maps['backgroundid']
	 * @param array  $maps['label_type']
	 * @param int    $maps['label_location']
	 * @param int    $maps['grid_size']			size of one grid cell. 100 refers to 100x100 and so on.
	 * @param int    $maps['grid_show']			does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
	 * @param int    $maps['grid_align']		do elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
	 *
	 * @return array
	 */
	public function update(array $maps) {
		$maps = zbx_toArray($maps);
		$sysmapids = zbx_objectValues($maps, 'sysmapid');

		$db_maps = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'sysmapids' => zbx_objectValues($maps, 'sysmapid'),
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectUrls' => ['sysmapid', 'sysmapurlid', 'name', 'url'],
			'selectUsers' => ['sysmapuserid', 'sysmapid', 'userid', 'permission'],
			'selectUserGroups' => ['sysmapusrgrpid', 'sysmapid', 'usrgrpid', 'permission'],
			'editable' => true,
			'preservekeys' => true
		]);

		$this->validateUpdate($maps, $db_maps);

		$update_maps = [];
		$url_ids_to_delete = [];
		$urls_to_update = [];
		$urls_to_add = [];
		$selements_to_delete = [];
		$selements_to_update = [];
		$selements_to_add = [];
		$links_to_delete = [];
		$links_to_update = [];
		$links_to_add = [];
		$shared_userids_to_delete = [];
		$shared_users_to_update = [];
		$shared_users_to_add = [];
		$shared_user_groupids_to_delete = [];
		$shared_user_groups_to_update = [];
		$shared_user_groups_to_add = [];

		foreach ($maps as $map) {
			$update_maps[] = [
				'values' => $map,
				'where' => ['sysmapid' => $map['sysmapid']],
			];

			$db_map = $db_maps[$map['sysmapid']];

			// Map user shares.
			if (array_key_exists('users', $map)) {
				$user_shares_diff = zbx_array_diff($map['users'], $db_map['users'], 'userid');

				foreach ($user_shares_diff['both'] as $update_user_share) {
					$shared_users_to_update[] = [
						'values' => $update_user_share,
						'where' => ['userid' => $update_user_share['userid'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($user_shares_diff['first'] as $new_shared_user) {
					$new_shared_user['sysmapid'] = $map['sysmapid'];
					$shared_users_to_add[] = $new_shared_user;
				}

				$shared_userids_to_delete = array_merge($shared_userids_to_delete,
					zbx_objectValues($user_shares_diff['second'], 'sysmapuserid')
				);
			}

			// Map user group shares.
			if (array_key_exists('userGroups', $map)) {
				$user_group_shares_diff = zbx_array_diff($map['userGroups'], $db_map['userGroups'],
					'usrgrpid'
				);

				foreach ($user_group_shares_diff['both'] as $update_user_share) {
					$shared_user_groups_to_update[] = [
						'values' => $update_user_share,
						'where' => ['usrgrpid' => $update_user_share['usrgrpid'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($user_group_shares_diff['first'] as $new_shared_user_group) {
					$new_shared_user_group['sysmapid'] = $map['sysmapid'];
					$shared_user_groups_to_add[] = $new_shared_user_group;
				}

				$shared_user_groupids_to_delete = array_merge($shared_user_groupids_to_delete,
					zbx_objectValues($user_group_shares_diff['second'], 'sysmapusrgrpid')
				);
			}

			// Urls.
			if (array_key_exists('urls', $map)) {
				$url_diff = zbx_array_diff($map['urls'], $db_map['urls'], 'name');

				foreach ($url_diff['both'] as $updateUrl) {
					$urls_to_update[] = [
						'values' => $updateUrl,
						'where' => ['name' => $updateUrl['name'], 'sysmapid' => $map['sysmapid']]
					];
				}

				foreach ($url_diff['first'] as $new_url) {
					$new_url['sysmapid'] = $map['sysmapid'];
					$urls_to_add[] = $new_url;
				}

				$url_ids_to_delete = array_merge($url_ids_to_delete,
					zbx_objectValues($url_diff['second'], 'sysmapurlid')
				);
			}

			// Map elements.
			if (array_key_exists('selements', $map)) {
				$selement_diff = zbx_array_diff($map['selements'], $db_map['selements'], 'selementid');

				// We need sysmapid for add operations.
				foreach ($selement_diff['first'] as $new_selement) {
					$new_selement['sysmapid'] = $map['sysmapid'];
					$selements_to_add[] = $new_selement;
				}

				$selements_to_update = array_merge($selements_to_update, $selement_diff['both']);
				$selements_to_delete = array_merge($selements_to_delete, $selement_diff['second']);
			}

			// Links.
			if (array_key_exists('links', $map)) {
				$link_diff = zbx_array_diff($map['links'], $db_map['links'], 'linkid');

				// We need sysmapId for add operations.
				foreach ($link_diff['first'] as $newLink) {
					$newLink['sysmapid'] = $map['sysmapid'];

					$links_to_add[] = $newLink;
				}

				$links_to_update = array_merge($links_to_update, $link_diff['both']);
				$links_to_delete = array_merge($links_to_delete, $link_diff['second']);
			}
		}

		DB::update('sysmaps', $update_maps);

		// User shares.
		DB::insert('sysmap_user', $shared_users_to_add);
		DB::update('sysmap_user', $shared_users_to_update);

		if ($shared_userids_to_delete) {
			DB::delete('sysmap_user', ['sysmapuserid' => $shared_userids_to_delete]);
		}

		// User group shares.
		DB::insert('sysmap_usrgrp', $shared_user_groups_to_add);
		DB::update('sysmap_usrgrp', $shared_user_groups_to_update);

		if ($shared_user_groupids_to_delete) {
			DB::delete('sysmap_usrgrp', ['sysmapusrgrpid' => $shared_user_groupids_to_delete]);
		}

		// Urls.
		DB::insert('sysmap_url', $urls_to_add);
		DB::update('sysmap_url', $urls_to_update);

		if ($url_ids_to_delete) {
			DB::delete('sysmap_url', ['sysmapurlid' => $url_ids_to_delete]);
		}

		// Selements.
		$new_selementids = ['selementids' => []];
		if ($selements_to_add) {
			$new_selementids = $this->createSelements($selements_to_add);
		}

		if ($selements_to_update) {
			$this->updateSelements($selements_to_update);
		}

		if ($selements_to_delete) {
			$this->deleteSelements($selements_to_delete);
		}

		// Links.
		if ($links_to_add || $links_to_update) {
			$selements_names = [];
			foreach ($new_selementids['selementids'] as $key => $selementId) {
				$selements_names[$selements_to_add[$key]['selementid']] = $selementId;
			}

			foreach ($selements_to_update as $selement) {
				$selements_names[$selement['selementid']] = $selement['selementid'];
			}

			foreach ($links_to_add as $key => $link) {
				if (array_key_exists($link['selementid1'], $selements_names)) {
					$links_to_add[$key]['selementid1'] = $selements_names[$link['selementid1']];
				}
				if (array_key_exists($link['selementid2'], $selements_names)) {
					$links_to_add[$key]['selementid2'] = $selements_names[$link['selementid2']];
				}
			}

			foreach ($links_to_update as $key => $link) {
				if (array_key_exists($link['selementid1'], $selements_names)) {
					$links_to_update[$key]['selementid1'] = $selements_names[$link['selementid1']];
				}
				if (array_key_exists($link['selementid2'], $selements_names)) {
					$links_to_update[$key]['selementid2'] = $selements_names[$link['selementid2']];
				}
			}

			unset($selements_names);
		}

		$new_linkids = ['linkids' => []];
		$update_linkids = ['linkids' => []];

		if ($links_to_add) {
			$new_linkids = $this->createLinks($links_to_add);
		}

		if ($links_to_update) {
			$update_linkids = $this->updateLinks($links_to_update);
		}

		if ($links_to_delete) {
			$this->deleteLinks($links_to_delete);
		}

		// Link triggers.
		$link_triggers_to_delete = [];
		$link_triggers_to_update = [];
		$link_triggers_to_add = [];

		foreach ($new_linkids['linkids'] as $key => $linkid) {
			if (!array_key_exists('linktriggers', $links_to_add[$key])) {
				continue;
			}

			foreach ($links_to_add[$key]['linktriggers'] as $link_trigger) {
				$link_trigger['linkid'] = $linkid;
				$link_triggers_to_add[] = $link_trigger;
			}
		}

		$db_links = [];

		$link_trigger_resource = DBselect(
			'SELECT slt.* FROM sysmaps_link_triggers slt WHERE '.dbConditionInt('slt.linkid', $update_linkids['linkids'])
		);
		while ($db_link_trigger = DBfetch($link_trigger_resource)) {
			zbx_subarray_push($db_links, $db_link_trigger['linkid'], $db_link_trigger);
		}

		foreach ($update_linkids['linkids'] as $key => $linkid) {
			if (!array_key_exists('linktriggers', $links_to_update[$key])) {
				continue;
			}

			$db_link_triggers = array_key_exists($linkid, $db_links) ? $db_links[$linkid] : [];
			$db_link_triggers_diff = zbx_array_diff($links_to_update[$key]['linktriggers'],
				$db_link_triggers, 'linktriggerid'
			);

			foreach ($db_link_triggers_diff['first'] as $new_link_trigger) {
				$new_link_trigger['linkid'] = $linkid;
				$link_triggers_to_add[] = $new_link_trigger;
			}

			$link_triggers_to_update = array_merge($link_triggers_to_update, $db_link_triggers_diff['both']);
			$link_triggers_to_delete = array_merge($link_triggers_to_delete, $db_link_triggers_diff['second']);
		}

		if ($link_triggers_to_delete) {
			$this->deleteLinkTriggers($link_triggers_to_delete);
		}

		if ($link_triggers_to_add) {
			$this->createLinkTriggers($link_triggers_to_add);
		}

		if ($link_triggers_to_update) {
			$this->updateLinkTriggers($link_triggers_to_update);
		}

		return ['sysmapids' => $sysmapids];
	}

	/**
	 * Delete Map.
	 *
	 * @param array $sysmapids
	 *
	 * @return array
	 */
	public function delete(array $sysmapids) {
		$this->validateDelete($sysmapids);

		DB::delete('sysmaps_elements', [
			'elementid' => $sysmapids,
			'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
		]);
		DB::delete('screens_items', [
			'resourceid' => $sysmapids,
			'resourcetype' => SCREEN_RESOURCE_MAP
		]);
		DB::delete('profiles', [
			'idx' => 'web.maps.sysmapid',
			'value_id' => $sysmapids
		]);
		DB::delete('profiles', [
			'idx' => 'web.favorite.sysmapids',
			'source' => 'sysmapid',
			'value_id' => $sysmapids
		]);
		DB::delete('sysmaps', ['sysmapid' => $sysmapids]);

		return ['sysmapids' => $sysmapids];
	}

	private function expandUrlMacro($url, $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$macro = '{HOSTGROUP.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$macro = '{TRIGGER.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$macro = '{MAP.ID}';
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$macro = '{HOST.ID}';
				break;

			default:
				$macro = false;
		}

		if ($macro) {
			$url['url'] = str_replace($macro, $selement['elementid'], $url['url']);
		}

		return $url;
	}

	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'sysmapids' => $ids,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'sysmapids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$sysmapIds = array_keys($result);

		// adding elements
		if ($options['selectSelements'] !== null && $options['selectSelements'] != API_OUTPUT_COUNT) {
			$selements = API::getApiService()->select('sysmaps_elements', [
				'output' => $this->outputExtend($options['selectSelements'], ['selementid', 'sysmapid']),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($selements, 'sysmapid', 'selementid');

			// add selement URLs
			if ($this->outputIsRequested('urls', $options['selectSelements'])) {
				foreach ($selements as &$selement) {
					$selement['urls'] = [];
				}
				unset($selement);

				if (!is_null($options['expandUrls'])) {
					$dbMapUrls = DBselect(
						'SELECT su.sysmapurlid,su.sysmapid,su.name,su.url,su.elementtype'.
						' FROM sysmap_url su'.
						' WHERE '.dbConditionInt('su.sysmapid', $sysmapIds)
					);
					while ($mapUrl = DBfetch($dbMapUrls)) {
						foreach ($selements as $snum => $selement) {
							if (bccomp($selement['sysmapid'], $mapUrl['sysmapid']) == 0
									&& (($selement['elementtype'] == $mapUrl['elementtype']
											&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP
											)
											|| ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
													&& $mapUrl['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST)
										)) {
								$selements[$snum]['urls'][] = $this->expandUrlMacro($mapUrl, $selement);
							}
						}
					}
				}

				$dbSelementUrls = DBselect(
					'SELECT seu.sysmapelementurlid,seu.selementid,seu.name,seu.url'.
					' FROM sysmap_element_url seu'.
					' WHERE '.dbConditionInt('seu.selementid', array_keys($selements))
				);
				while ($selementUrl = DBfetch($dbSelementUrls)) {
					$selements[$selementUrl['selementid']]['urls'][] = is_null($options['expandUrls'])
						? $selementUrl
						: $this->expandUrlMacro($selementUrl, $selements[$selementUrl['selementid']]);
				}
			}

			$selements = $this->unsetExtraFields($selements, ['sysmapid', 'selementid'], $options['selectSelements']);
			$result = $relation_map->mapMany($result, $selements, 'selements');
		}

		// adding icon maps
		if ($options['selectIconMap'] !== null && $options['selectIconMap'] != API_OUTPUT_COUNT) {
			$iconMaps = API::getApiService()->select($this->tableName(), [
				'output' => ['sysmapid', 'iconmapid'],
				'filter' => ['sysmapid' => $sysmapIds]
			]);

			$relation_map = $this->createRelationMap($iconMaps, 'sysmapid', 'iconmapid');

			$iconMaps = API::IconMap()->get([
				'output' => $this->outputExtend($options['selectIconMap'], ['iconmapid']),
				'iconmapids' => zbx_objectValues($iconMaps, 'iconmapid'),
				'preservekeys' => true
			]);

			$iconMaps = $this->unsetExtraFields($iconMaps, ['iconmapid'], $options['selectIconMap']);

			$result = $relation_map->mapOne($result, $iconMaps, 'iconmap');
		}

		// adding links
		if ($options['selectLinks'] !== null && $options['selectLinks'] != API_OUTPUT_COUNT) {
			$links = API::getApiService()->select('sysmaps_links', [
				'output' => $this->outputExtend($options['selectLinks'], ['sysmapid', 'linkid']),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($links, 'sysmapid', 'linkid');

			// add link triggers
			if ($this->outputIsRequested('linktriggers', $options['selectLinks'])) {
				$linkTriggers = DBFetchArrayAssoc(DBselect(
					'SELECT DISTINCT slt.*'.
					' FROM sysmaps_link_triggers slt'.
					' WHERE '.dbConditionInt('slt.linkid', $relation_map->getRelatedIds())
				), 'linktriggerid');
				$linkTriggerRelationMap = $this->createRelationMap($linkTriggers, 'linkid', 'linktriggerid');
				$links = $linkTriggerRelationMap->mapMany($links, $linkTriggers, 'linktriggers');
			}

			$links = $this->unsetExtraFields($links, ['sysmapid', 'linkid'], $options['selectLinks']);
			$result = $relation_map->mapMany($result, $links, 'links');
		}

		// adding urls
		if ($options['selectUrls'] !== null && $options['selectUrls'] != API_OUTPUT_COUNT) {
			$links = API::getApiService()->select('sysmap_url', [
				'output' => $this->outputExtend($options['selectUrls'], ['sysmapid', 'sysmapurlid']),
				'filter' => ['sysmapid' => $sysmapIds],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($links, 'sysmapid', 'sysmapurlid');

			$links = $this->unsetExtraFields($links, ['sysmapid', 'sysmapurlid'], $options['selectUrls']);
			$result = $relation_map->mapMany($result, $links, 'urls');
		}

		// Adding user shares.
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'sysmapid', 'userid', 'sysmap_user');
			// Get all allowed users.
			$related_users = API::User()->get([
				'output' => ['userid'],
				'userids' => $relation_map->getRelatedIds(),
				'preservekeys' => true
			]);

			$related_userids = zbx_objectValues($related_users, 'userid');

			if ($related_userids) {
				$users = API::getApiService()->select('sysmap_user', [
					'output' => $this->outputExtend($options['selectUsers'], ['sysmapid', 'userid']),
					'filter' => ['sysmapid' => $sysmapIds, 'userid' => $related_userids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($users, 'sysmapid', 'sysmapuserid');

				$users = $this->unsetExtraFields($users, ['sysmapuserid', 'userid', 'permission'],
					$options['selectUsers']
				);

				foreach ($users as &$user) {
					unset($user['sysmapid']);
				}
				unset($user);

				$result = $relation_map->mapMany($result, $users, 'users');
			}
			else {
				foreach ($result as &$row) {
					$row['users'] = [];
				}
				unset($row);
			}
		}

		// Adding user group shares.
		if ($options['selectUserGroups'] !== null && $options['selectUserGroups'] != API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'sysmapid', 'usrgrpid', 'sysmap_usrgrp');
			// Get all allowed groups.
			$related_groups = API::UserGroup()->get([
				'output' => ['usrgrpid'],
				'usrgrpids' => $relation_map->getRelatedIds(),
				'preservekeys' => true
			]);

			$related_groupids = zbx_objectValues($related_groups, 'usrgrpid');

			if ($related_groupids) {
				$user_groups = API::getApiService()->select('sysmap_usrgrp', [
					'output' => $this->outputExtend($options['selectUserGroups'], ['sysmapid', 'usrgrpid']),
					'filter' => ['sysmapid' => $sysmapIds, 'usrgrpid' => $related_groupids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($user_groups, 'sysmapid', 'sysmapusrgrpid');

				$user_groups = $this->unsetExtraFields($user_groups, ['sysmapusrgrpid', 'usrgrpid', 'permission'],
					$options['selectUserGroups']
				);

				foreach ($user_groups as &$user_group) {
					unset($user_group['sysmapid']);
				}
				unset($user_group);

				$result = $relation_map->mapMany($result, $user_groups, 'userGroups');
			}
			else {
				foreach ($result as &$row) {
					$row['userGroups'] = [];
				}
				unset($row);
			}
		}

		return $result;
	}
}
