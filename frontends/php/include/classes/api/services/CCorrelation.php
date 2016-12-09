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
 * Class containing methods for operations with correlations.
 */
class CCorrelation extends CApiService {

	protected $tableName = 'correlation';
	protected $tableAlias = 'c';
	protected $sortColumns = ['correlationid', 'name', 'status'];

	/**
	 * Set correlation default options in addition to global options.
	 */
	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, [
			'selectFilter'		=> null,
			'selectOperations'	=> null,
			'correlationids'	=> null,
			'editable'			=> null,
			'sortfield'			=> '',
			'sortorder'			=> ''
		]);
	}

	/**
	 * Get correlation data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$options = zbx_array_merge($this->getOptions, $options);

		if ($options['editable'] !== null && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return ($options['countOutput'] !== null && $options['groupCount'] === null) ? 0 : [];
		}

		$res = DBselect($this->createSelectQuery($this->tableName(), $options), $options['limit']);

		$result = [];
		while ($row = DBfetch($res)) {
			if ($options['countOutput'] === null) {
				$result[$row[$this->pk()]] = $row;
			}
			else {
				if ($options['groupCount'] === null) {
					$result = $row['rowscount'];
				}
				else {
					$result[] = $row;
				}
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			foreach ($result as &$correlation) {
				// Unset the fields that are returned in the filter.
				unset($correlation['formula'], $correlation['evaltype']);

				if ($options['selectFilter'] !== null) {
					$filter = $this->unsetExtraFields(
						[$correlation['filter']],
						['conditions', 'formula', 'evaltype'],
						$options['selectFilter']
					);
					$filter = reset($filter);

					if (array_key_exists('conditions', $filter)) {
						foreach ($filter['conditions'] as &$condition) {
							unset($condition['correlationid'], $condition['corr_conditionid']);
						}
						unset($condition);
					}

					$correlation['filter'] = $filter;
				}
			}
			unset($correlation);
		}

		// removing keys (hash -> array)
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add correlations.
	 *
	 * @param array  $correlations											An array of correlations.
	 * @param string $correlations[]['name']								Correlation name.
	 * @param string $correlations[]['description']							Correlation description (optional).
	 * @param int    $correlations[]['status']								Correlation status (optional).
	 *																		Possible values are:
	 *																			0 - ZBX_CORRELATION_ENABLED;
	 *																			1 - ZBX_CORRELATION_DISABLED.
	 * @param array	 $correlations[]['filter']								Correlation filter that contains evaluation
	 *																		method, formula and conditions.
	 * @param int    $correlations[]['filter']['evaltype']					Correlation condition evaluation method.
	 *																		Possible values are:
	 *																			0 - CONDITION_EVAL_TYPE_AND_OR;
	 *																			1 - CONDITION_EVAL_TYPE_AND;
	 *																			2 - CONDITION_EVAL_TYPE_OR;
	 *																			3 - CONDITION_EVAL_TYPE_EXPRESSION.
	 * @param string $correlations[]['filter']['formula']					User-defined expression to be used for
	 *																		evaluating conditions of filters with a
	 *																		custom expression. Optional, but required
	 *																		when evaluation method is:
	 *																			3 - CONDITION_EVAL_TYPE_EXPRESSION.
	 * @param array  $correlations[]['filter']['conditions']					An array of correlation conditions.
	 * @param int    $correlations[]['filter']['conditions'][]['type']		Condition type.
	 *																		Possible values are:
	 *																			0 - ZBX_CORR_CONDITION_OLD_EVENT_TAG;
	 *																			1 - ZBX_CORR_CONDITION_NEW_EVENT_TAG;
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																			3 - ZBX_CORR_CONDITION_EVENT_TAG_PAIR;
	 *																			4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																			5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 * @param string $correlations[]['filter']['conditions'][]['formulaid']	Condition formula ID. Optional, but required
	 *																		when evaluation method is:
	 *																			3 - CONDITION_EVAL_TYPE_EXPRESSION.
	 * @param string $correlations[]['filter']['conditions'][]['tag']		Correlation condition tag.
	 * @param int	 $correlations[]['filter']['conditions'][]['operator']	Correlation condition operator. Optional,
	 *																		but required when "type" is one of the following:
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																			4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																			5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 *																		Possible values depend on type:
	 *																		for type ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
	 *																			0 - CONDITION_OPERATOR_EQUAL
	 *																			1 - CONDITION_OPERATOR_NOT_EQUAL
	 *																		for types ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE
	 *																		or ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
	 *																			0 - CONDITION_OPERATOR_EQUAL;
	 *																			1 - CONDITION_OPERATOR_NOT_EQUAL;
	 *																			2 - CONDITION_OPERATOR_LIKE;
	 *																			3 - CONDITION_OPERATOR_NOT_LIKE.
	 * @param string $correlations[]['filter']['conditions'][]['groupid']	Correlation host group ID. Optional, but
	 *																		required when "type" is:
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP.
	 * @param string $correlations[]['filter']['conditions'][]['newtag']	Correlation condition new (current) tag.
	 * @param string $correlations[]['filter']['conditions'][]['oldtag']	Correlation condition old (target/matching)
	 *																		tag.
	 * @param string $correlations[]['filter']['conditions'][]['value']		Correlation condition tag value (optional).
	 * @param array	 $correlations[]['operations']							An array of correlation operations.
	 * @param int	 $correlations[]['operations'][]['type']				Correlation operation type.
	 *																		Possible values are:
	 *																			0 - ZBX_CORR_OPERATION_CLOSE_OLD;
	 *																			1 - ZBX_CORR_OPERATION_CLOSE_NEW.
	 *
	 * @return array
	 */
	public function create($correlations) {
		$correlations = zbx_toArray($correlations);

		$this->validateCreate($correlations);

		foreach ($correlations as &$correlation) {
			$correlation['evaltype'] = $correlation['filter']['evaltype'];
			unset($correlation['formula']);
		}
		unset($correlation);

		// Insert correlations into DB, get back array with new correlation IDs.
		$correlations = DB::save('correlation', $correlations);
		$correlations = zbx_toHash($correlations, 'correlationid');

		$conditions_to_create = [];
		$operations_to_create = [];

		// Collect conditions and operations to be created and set appropriate correlation ID.
		foreach ($correlations as $correlationid => &$correlation) {
			foreach ($correlation['filter']['conditions'] as $condition) {
				$condition['correlationid'] = $correlationid;
				$conditions_to_create[] = $condition;
			}

			foreach ($correlation['operations'] as $operation) {
				$operation['correlationid'] = $correlationid;
				$operations_to_create[] = $operation;
			}
		}
		unset($correlation);

		$conditions = $this->addConditions($conditions_to_create);

		// Group back created correlation conditions by correlation ID to be used for updating correlation formula.
		$conditions_for_correlations = [];
		foreach ($conditions as $condition) {
			$conditions_for_correlations[$condition['correlationid']][$condition['corr_conditionid']] = $condition;
		}

		// Update "formula" field if evaluation method is a custom expression.
		foreach ($correlations as $correlationid => $correlation) {
			if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($correlationid, $correlation['filter']['formula'],
					$conditions_for_correlations[$correlationid]
				);
			}
		}

		DB::save('corr_operation', $operations_to_create);

		return ['correlationids' => array_keys($correlations)];
	}

	/**
	 * Update correlations.
	 *
	 * @param array  $correlations											An array of correlations.
	 * @param string $correlations[]['name']								Correlation name (optional).
	 * @param string $correlations[]['description']							Correlation description (optional).
	 * @param int	 $correlations[]['status']								Correlation status (optional).
	 *																		Possible values are:
	 *																			0 - ZBX_CORRELATION_ENABLED;
	 *																			1 - ZBX_CORRELATION_DISABLED.
	 * @param array	 $correlations[]['filter']								Correlation filter that contains evaluation
	 *																		method, formula and conditions.
	 * @param int	 $correlations[]['filter']['evaltype']					Correlation condition evaluation
	 *																		method (optional).
	 *																		Possible values are:
	 *																			0 - CONDITION_EVAL_TYPE_AND_OR;
	 *																			1 - CONDITION_EVAL_TYPE_AND;
	 *																			2 - CONDITION_EVAL_TYPE_OR;
	 *																			3 - CONDITION_EVAL_TYPE_EXPRESSION.
	 * @param string $correlations[]['filter']['formula']					User-defined expression to be used for
	 *																		evaluating conditions of filters with a
	 *																		custom expression. Optional, but required
	 *																		when evaluation method is changed to
	 *																		CONDITION_EVAL_TYPE_EXPRESSION (or remains the same)
	 *																		and new conditions are set.
	 * @param array  $correlations[]['filter']['conditions']				An array of correlation conditions (optional).
	 * @param int	 $correlations[]['filter']['conditions'][]['type']		Condition type. Optional, but required when
	 *																		new conditions are set.
	 *																		Possible values are:
	 *																			0 - ZBX_CORR_CONDITION_OLD_EVENT_TAG;
	 *																			1 - ZBX_CORR_CONDITION_NEW_EVENT_TAG;
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																			3 - ZBX_CORR_CONDITION_EVENT_TAG_PAIR;
	 *																			4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																			5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 * @param string $correlations[]['filter']['conditions'][]['formulaid']	Condition formula ID. Optional, but required
	 *																		when evaluation method is changed to
	 *																		CONDITION_EVAL_TYPE_EXPRESSION (or remains the same)
	 *																		and new conditions are set.
	 * @param string $correlations[]['filter']['conditions'][]['tag']		Correlation condition tag.
	 * @param int	 $correlations[]['filter']['conditions'][]['operator']	Correlation condition operator. Optional,
	 *																		but required when "type" is changed to one
	 *																		of the following:
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																			4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																			5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 *																		Possible values depend on type:
	 *																		for type ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
	 *																			0 - CONDITION_OPERATOR_EQUAL
	 *																			1 - CONDITION_OPERATOR_NOT_EQUAL
	 *																		for types ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE
	 *																		or ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
	 *																			0 - CONDITION_OPERATOR_EQUAL;
	 *																			1 - CONDITION_OPERATOR_NOT_EQUAL;
	 *																			2 - CONDITION_OPERATOR_LIKE;
	 *																			3 - CONDITION_OPERATOR_NOT_LIKE.
	 * @param string $correlations[]['filter']['conditions'][]['groupid']	Correlation host group ID. Optional, but
	 *																		required when "type" is changed to:
	 *																			2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP.
	 * @param string $correlations[]['filter']['conditions'][]['newtag']	Correlation condition new (current) tag.
	 * @param string $correlations[]['filter']['conditions'][]['oldtag']	Correlation condition old (target/matching)
	 *																		tag.
	 * @param string $correlations[]['filter']['conditions'][]['value']		Correlation condition tag value (optional).
	 * @param array  $correlations[]['operations']							An array of correlation operations (optional).
	 * @param int	 $correlations[]['operations'][]['type']				Correlation operation type (optional).
	 *																		Possible values are:
	 *																			0 - ZBX_CORR_OPERATION_CLOSE_OLD;
	 *																			1 - ZBX_CORR_OPERATION_CLOSE_NEW.
	 *
	 * @return array
	 */
	public function update($correlations) {
		$correlations = zbx_toArray($correlations);
		$db_correlations = [];

		$this->validateUpdate($correlations, $db_correlations);

		$correlations_to_update = [];
		$conditions_to_create = [];
		$conditions_to_delete = [];
		$operations_to_create = [];
		$operations_to_delete = [];

		foreach ($correlations as $correlation) {
			$correlationid = $correlation['correlationid'];

			unset($correlation['evaltype'], $correlation['formula'], $correlation['correlationid']);

			$db_correlation = $db_correlations[$correlationid];

			// Remove fields that have not been changed for correlations.
			if (array_key_exists('name', $correlation) && $correlation['name'] === $db_correlation['name']) {
				unset($correlation['name']);
			}

			if (array_key_exists('description', $correlation)
					&& $correlation['description'] === $db_correlation['description']) {
				unset($correlation['description']);
			}

			if (array_key_exists('status', $correlation) && $correlation['status'] == $db_correlation['status']) {
				unset($correlation['status']);
			}

			$evaltype_changed = false;

			// If the filter is set, something might have changed.
			if (array_key_exists('filter', $correlation)) {
				// Delete old correlation conditions and create new conditions.
				if (array_key_exists('conditions', $correlation['filter'])) {
					$conditions_to_delete[$correlationid] = true;

					foreach ($correlation['filter']['conditions'] as $condition) {
						$condition['correlationid'] = $correlationid;
						$conditions_to_create[] = $condition;
					}
				}

				// Check if evaltype has changed.
				if (array_key_exists('evaltype', $correlation['filter'])) {
					if ($correlation['filter']['evaltype'] != $db_correlation['filter']['evaltype']) {
						// Clear formula field evaluation method is changed and no longer a custom experssion.
						$correlation['evaltype'] = $correlation['filter']['evaltype'];

						if ($correlation['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
							$correlation['formula'] = '';
						}
					}
				}
			}

			// Delete old correlation operations and create new operations.
			if (array_key_exists('operations', $correlation)) {
				$operations_to_delete[$correlationid] = true;

				foreach ($correlation['operations'] as $operation) {
					$operation['correlationid'] = $correlationid;
					$operations_to_create[] = $operation;
				}
			}

			// Add values only if something is set for update.
			if (array_key_exists('name', $correlation)
					|| array_key_exists('description', $correlation)
					|| array_key_exists('status', $correlation)
					|| array_key_exists('evaltype', $correlation)) {
				$correlations_to_update[] = [
					'values' => $correlation,
					'where' => ['correlationid' => $correlationid]
				];
			}
		}

		// Update correlations.
		if ($correlations_to_update) {
			DB::update('correlation', $correlations_to_update);
		}

		// Update conditions. Delete the old ones and create new ones.
		if ($conditions_to_delete) {
			DB::delete('corr_condition', ['correlationid' => array_keys($conditions_to_delete)]);
		}

		if ($conditions_to_create) {
			$conditions = $this->addConditions($conditions_to_create);

			// Group back created correlation conditions by correlation ID to be used for updating correlation formula.
			$conditions_for_correlations = [];
			foreach ($conditions as $condition) {
				$conditions_for_correlations[$condition['correlationid']][$condition['corr_conditionid']] = $condition;
			}

			// Update "formula" field if evaluation method is a custom expression.
			foreach ($correlations as $correlation) {
				if (array_key_exists('filter', $correlation)) {
					$db_correlation = $db_correlations[$correlation['correlationid']];

					// Check if evaluation method has changed.
					if (array_key_exists('evaltype', $correlation['filter'])) {
						if ($correlation['filter']['evaltype'] != $db_correlation['filter']['evaltype']) {
							// The new evaluation method will be saved to DB.
							$correlation['evaltype'] = $correlation['filter']['evaltype'];

							if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
								// When evaluation method has changed, update the custom formula.
								$this->updateFormula($correlation['correlationid'], $correlation['filter']['formula'],
									$conditions_for_correlations[$correlation['correlationid']]
								);
							}
						}
						else {
							/*
							 * The evaluation method has not been changed, but it has been set and it's a custom
							 * expression. The formula needs to be updated in this case.
							 */
							if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
								$this->updateFormula($correlation['correlationid'], $correlation['filter']['formula'],
									$conditions_for_correlations[$correlation['correlationid']]
								);
							}
						}
					}
					elseif ($db_correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						/*
						 * The evaluation method has not been changed and was not set. It's read from DB, but it's still
						 * a custom expression and there are new conditions, so the formula needs to be updated.
						 */
						$this->updateFormula($correlation['correlationid'], $correlation['filter']['formula'],
							$conditions_for_correlations[$correlation['correlationid']]
						);
					}
				}
			}
		}

		// Update operations. Delete the old ones and create new ones.
		if ($operations_to_delete) {
			DB::delete('corr_operation', ['correlationid' => array_keys($operations_to_delete)]);
		}

		if ($operations_to_create) {
			DB::save('corr_operation', $operations_to_create);
		}

		return ['correlationids' => zbx_objectValues($correlations, 'correlationid')];
	}

	/**
	 * Delete correlations.
	 *
	 * @param array $correlationids					An array of correlation IDs.
	 *
	 * @return array
	 */
	public function delete(array $correlationids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can delete correlations.'));
		}

		if (!$correlationids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_correlations = $this->get([
			'output' => ['correlationid'],
			'correlationids' => $correlationids,
			'preservekeys' => true
		]);

		foreach ($correlationids as $correlationid) {
			if (!is_int($correlationid) && !is_string($correlationid)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif (!array_key_exists($correlationid, $db_correlations)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		DB::delete('correlation', ['correlationid' => $correlationids]);

		return ['correlationids' => $correlationids];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $correlations
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $correlations) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can create correlations.'));
		}

		if (!$correlations) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$required_fields = ['name', 'filter', 'operations'];

		// Validate required fields and check if "name" is not empty.
		foreach ($correlations as $correlation) {
			if (!is_array($correlation)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Check required parameters.
			$missing_keys = array_diff($required_fields, array_keys($correlation));

			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Correlation is missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}

			// Validate "name" field.
			if (is_array($correlation['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif ($correlation['name'] === '' || $correlation['name'] === null || $correlation['name'] === false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Correlation name cannot be empty.'));
			}
		}

		// Check for duplicate names.
		$duplicate = CArrayHelper::findDuplicate($correlations, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "%1$s" value "%2$s" for correlation.', 'name', $duplicate['name'])
			);
		}

		// Check if correlation already exists.
		$db_correlations = API::getApiService()->select('correlation', [
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($correlations, 'name')],
			'limit' => 1
		]);

		if ($db_correlations) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Correlation "%1$s" already exists.', $correlations[0]['name'])
			);
		}

		// Set all necessary validators and parser before cycling each correlation.
		$status_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]
		]);

		$filter_evaltype_validator = new CLimitedSetValidator([
			'values' => [CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_AND_OR,
				CONDITION_EVAL_TYPE_EXPRESSION
			]
		]);

		$filter_condition_type_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,	ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,	ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]
		]);

		$filter_condition_hg_operator_validator = new CLimitedSetValidator([
			'values' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL]
		]);

		$filter_condition_tagval_operator_validator = new CLimitedSetValidator([
			'values' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			]
		]);

		$filter_operations_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORR_OPERATION_CLOSE_OLD, ZBX_CORR_OPERATION_CLOSE_NEW]
		]);

		$parser = new CConditionFormula();

		$groupids = [];

		foreach ($correlations as $correlation) {
			// Validate "status" field (optional).
			if (array_key_exists('status', $correlation) && !$status_validator->validate($correlation['status'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
					$correlation['status'],
					'status',
					$correlation['name']
				));
			}

			// Validate "filter" field.
			if (!is_array($correlation['filter'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Validate "evaltype" field.
			if (!array_key_exists('evaltype', $correlation['filter'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect type of calculation for correlation "%1$s".', $correlation['name'])
				);
			}
			elseif (!$filter_evaltype_validator->validate($correlation['filter']['evaltype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
					$correlation['filter']['evaltype'],
					'evaltype',
					$correlation['name']
				));
			}

			// Check if conditions exist and that array is not empty.
			if (!array_key_exists('conditions', $correlation['filter'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('No "%1$s" given for correlation "%2$s".', 'conditions', $correlation['name'])
				);
			}

			// Validate condition operators and other parameters depending on type.
			$groupids = $this->validateConditions($correlation, $filter_condition_type_validator,
				$filter_condition_hg_operator_validator, $filter_condition_tagval_operator_validator
			);

			// Validate custom expressions and formula.
			if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				// Check formula.
				if (!array_key_exists('formula', $correlation['filter'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('No "%1$s" given for correlation "%2$s".', 'formula', $correlation['name'])
					);
				}

				$this->validateFormula($correlation, $parser);

				// Check condition formula IDs.
				$this->validateConditionFormulaIDs($correlation, $parser);
			}

			// Validate operations.
			$this->validateOperations($correlation, $filter_operations_validator);
		}

		// Validate collected group IDs if at least one of correlation conditions was "New event host group".
		if ($groupids) {
			$groups_count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => array_keys($groupids)
			]);

			if ($groups_count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $correlations
	 * @param array $db_correlations
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $correlations, array &$db_correlations) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can update correlations.'));
		}

		if (!$correlations) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Validate given IDs.
		$this->checkObjectIds($correlations, 'correlationid',
			_('No "%1$s" given for correlation.'),
			_('Empty correlation ID.'),
			_('Incorrect correlation ID.')
		);

		$db_correlations = $this->get([
			'output' => ['correlationid', 'name', 'description', 'status'],
			'selectFilter' => ['formula', 'eval_formula', 'evaltype', 'conditions'],
			'selectOperations' => ['type'],
			'correlationids' => zbx_objectValues($correlations, 'correlationid'),
			'preservekeys' => true
		]);

		$check_names = [];

		foreach ($correlations as $correlation) {
			// Check if this correlation exists.
			if (!array_key_exists($correlation['correlationid'], $db_correlations)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Validate "name" field (optional).
			if (array_key_exists('name', $correlation)) {
				if (is_array($correlation['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($correlation['name'] === '' || $correlation['name'] === null || $correlation['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Correlation name cannot be empty.'));
				}

				if ($db_correlations[$correlation['correlationid']]['name'] !== $correlation['name']) {
					$check_names[] = $correlation;
				}
			}
		}

		// Check only if names have changed.
		if ($check_names) {
			// Check for duplicate names.
			$duplicate = CArrayHelper::findDuplicate($check_names, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate "%1$s" value "%2$s" for correlation.', 'name', $duplicate['name'])
				);
			}

			// Check if correlation already exists.
			$db_correlation_names = API::getApiService()->select('correlation', [
				'output' => ['correlationid', 'name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')]
			]);
			$db_correlation_names = zbx_toHash($db_correlation_names, 'name');

			foreach ($check_names as $correlation) {
				if (array_key_exists($correlation['name'], $db_correlation_names)
						&& bccomp($db_correlation_names[$correlation['name']]['correlationid'],
							$correlation['correlationid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Correlation "%1$s" already exists.', $correlation['name'])
					);
				}
			}
		}

		// Set all necessary validators and parser before cycling each correlation.
		$status_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]
		]);

		$filter_evaltype_validator = new CLimitedSetValidator([
			'values' => [CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_AND_OR,
				CONDITION_EVAL_TYPE_EXPRESSION
			]
		]);

		$filter_condition_type_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,	ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,	ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]
		]);

		$filter_condition_hg_operator_validator = new CLimitedSetValidator([
			'values' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL]
		]);

		$filter_condition_tagval_operator_validator = new CLimitedSetValidator([
			'values' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			]
		]);

		$filter_operations_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORR_OPERATION_CLOSE_OLD, ZBX_CORR_OPERATION_CLOSE_NEW]
		]);

		$parser = new CConditionFormula();

		$groupids = [];

		// Populate "name" field, if not set.
		$correlations = $this->extendFromObjects(zbx_toHash($correlations, 'correlationid'), $db_correlations,
			['name']
		);

		foreach ($correlations as $correlation) {
			$db_correlation = $db_correlations[$correlation['correlationid']];

			// Validate "status" field (optional).
			if (array_key_exists('status', $correlation) && !$status_validator->validate($correlation['status'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
					$correlation['status'],
					'status',
					$correlation['name']
				));
			}

			// Validate "filter" field. If filter is set, then something else must exist.
			if (array_key_exists('filter', $correlation)) {
				if (!is_array($correlation['filter']) || !$correlation['filter']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$evaltype_changed = false;

				// Validate "evaltype" field.
				if (array_key_exists('evaltype', $correlation['filter'])) {
					// Check if evaltype has changed.
					if ($correlation['filter']['evaltype'] != $db_correlation['filter']['evaltype']) {
						if (!$filter_evaltype_validator->validate($correlation['filter']['evaltype'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
								$correlation['filter']['evaltype'],
								'evaltype',
								$correlation['name']
							));
						}

						$evaltype_changed = true;
					}
				}
				else {
					// Populate "evaltype" field if not set, so we can later check for custom expressions.
					$correlation['filter']['evaltype'] = $db_correlation['filter']['evaltype'];
				}

				if ($evaltype_changed) {
					if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						if (!array_key_exists('formula', $correlation['filter'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('No "%1$s" given for correlation "%2$s".', 'formula', $correlation['name'])
							);
						}

						$this->validateFormula($correlation, $parser);

						if (!array_key_exists('conditions', $correlation['filter'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('No "%1$s" given for correlation "%2$s".', 'conditions', $correlation['name'])
							);
						}

						$groupids = $this->validateConditions($correlation, $filter_condition_type_validator,
							$filter_condition_hg_operator_validator, $filter_condition_tagval_operator_validator
						);

						$this->validateConditionFormulaIDs($correlation, $parser);
					}
					else {
						if (!array_key_exists('conditions', $correlation['filter'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('No "%1$s" given for correlation "%2$s".', 'conditions', $correlation['name'])
							);
						}

						$groupids = $this->validateConditions($correlation, $filter_condition_type_validator,
							$filter_condition_hg_operator_validator, $filter_condition_tagval_operator_validator
						);
					}
				}
				else {
					if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						if (array_key_exists('formula', $correlation['filter'])) {
							$this->validateFormula($correlation, $parser);

							if (!array_key_exists('conditions', $correlation['filter'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('No "%1$s" given for correlation "%2$s".', 'conditions', $correlation['name'])
								);
							}

							$groupids = $this->validateConditions($correlation, $filter_condition_type_validator,
								$filter_condition_hg_operator_validator, $filter_condition_tagval_operator_validator
							);

							$this->validateConditionFormulaIDs($correlation, $parser);
						}
						elseif (array_key_exists('conditions', $correlation['filter'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('No "%1$s" given for correlation "%2$s".', 'formula', $correlation['name'])
							);
						}
					}
					elseif (array_key_exists('conditions', $correlation['filter'])) {
						$groupids = $this->validateConditions($correlation, $filter_condition_type_validator,
							$filter_condition_hg_operator_validator, $filter_condition_tagval_operator_validator
						);
					}
				}
			}

			// Validate operations (optional).
			if (array_key_exists('operations', $correlation)) {
				$this->validateOperations($correlation, $filter_operations_validator);
			}
		}

		// Validate collected group IDs if at least one of correlation conditions was "New event host group".
		if ($groupids) {
			$groups_count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => array_keys($groupids)
			]);

			if ($groups_count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Converts a formula with letters to a formula with IDs and updates it.
	 *
	 * @param string 	$correlationid
	 * @param string 	$formula_with_letters		Formula with letters.
	 * @param array 	$conditions
	 */
	protected function updateFormula($correlationid, $formula_with_letters, array $conditions) {
		$formulaid_to_conditionid = [];

		foreach ($conditions as $condition) {
			$formulaid_to_conditionid[$condition['formulaid']] = $condition['corr_conditionid'];
		}
		$formula = CConditionHelper::replaceLetterIds($formula_with_letters, $formulaid_to_conditionid);

		DB::updateByPk('correlation', $correlationid, ['formula' => $formula]);
	}

	/**
	 * Validate correlation conditions. Check the "conditions" array, check the "type" field and other fields that
	 * depend on it. As a result return host group IDs that need to be validated afterwards. Otherwise don't return
	 * anything, just throw an error.
	 *
	 * @param array					$correlation											One correlation containing the conditions.
	 * @param string				$correlation['name']									Correlation name for error messages.
	 * @param array					$correlation['filter']									Correlation filter array containing	the conditions.
	 * @param array					$correlation['filter']['conditions']					An array of correlation conditions.
	 * @param int					$correlation['filter']['conditions'][]['type']			Condition type.
	 *																						Possible values are:
	 *																							0 - ZBX_CORR_CONDITION_OLD_EVENT_TAG;
	 *																							1 - ZBX_CORR_CONDITION_NEW_EVENT_TAG;
	 *																							2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																							3 - ZBX_CORR_CONDITION_EVENT_TAG_PAIR;
	 *																							4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																							5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 * @param int					$correlation['filter']['conditions'][]['operator']		Correlation condition operator.
	 *																						Possible values when "type"
	 *																						is one of the following:
	 *																							2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP;
	 *																							4 - ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE;
	 *																							5 - ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE.
	 *																						Possible values depend on type:
	 *																							for type ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
	 *																								0 - CONDITION_OPERATOR_EQUAL
	 *																								1 - CONDITION_OPERATOR_NOT_EQUAL
	 *																							for types ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE
	 *																							or ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
	 *																								0 - CONDITION_OPERATOR_EQUAL;
	 *																								1 - CONDITION_OPERATOR_NOT_EQUAL;
	 *																								2 - CONDITION_OPERATOR_LIKE;
	 *																								3 - CONDITION_OPERATOR_NOT_LIKE.
	 * @param string				$correlations['filter']['conditions'][]['groupid']		Correlation host group ID.
	 *																						Required when "type" is:
	 *																							2 - ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP.
	 * @param CLimitedSetValidator	$filter_condition_type_validator						Validator for conditype type.
	 * @param CLimitedSetValidator	$filter_condition_hg_operator_validator					Validator for host group operator.
	 * @param CLimitedSetValidator	$filter_condition_tagval_operator_validator				Validator for tag value operator.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateConditions(array $correlation, CLimitedSetValidator $filter_condition_type_validator,
			CLimitedSetValidator $filter_condition_hg_operator_validator,
			CLimitedSetValidator $filter_condition_tagval_operator_validator) {
		if (!$correlation['filter']['conditions']) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('No "%1$s" given for correlation "%2$s".', 'conditions', $correlation['name'])
			);
		}

		if (!is_array($correlation['filter']['conditions'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}

		$groupids = [];
		$formulaIds = [];
		$conditions = [];

		foreach ($correlation['filter']['conditions'] as $condition) {
			if (!array_key_exists('type', $condition)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('No condition type given for correlation "%1$s".', $correlation['name'])
				);
			}
			elseif (is_array($condition['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif (!$filter_condition_type_validator->validate($condition['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
					$condition['type'],
					'type',
					$correlation['name']
				));
			}

			switch ($condition['type']) {
				case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
					if (!array_key_exists('tag', $condition)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No "%1$s" given for correlation "%2$s".', 'tag', $correlation['name'])
						);
					}
					elseif (is_array($condition['tag'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect arguments passed to function.')
						);
					}
					elseif ($condition['tag'] === '' || $condition['tag'] === null || $condition['tag'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty'))
						);
					}
					break;

				case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
					if (array_key_exists('operator', $condition)) {
						if (is_array($condition['operator'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('Incorrect arguments passed to function.')
							);
						}
						elseif (!$filter_condition_hg_operator_validator->validate($condition['operator'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
								$condition['operator'],
								'operator',
								$correlation['name']
							));
						}
					}

					if (!array_key_exists('groupid', $condition)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No "%1$s" given for correlation "%2$s".', 'groupid', $correlation['name'])
						);
					}
					elseif (is_array($condition['groupid'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect arguments passed to function.')
						);
					}
					elseif ($condition['groupid'] === '' || $condition['groupid'] === null
							|| $condition['groupid'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'groupid', _('cannot be empty'))
						);
					}

					$groupids[$condition['groupid']] = true;
					break;

				case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
					if (!array_key_exists('oldtag', $condition)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No "%1$s" given for correlation "%2$s".', 'oldtag', $correlation['name'])
						);
					}
					elseif (is_array($condition['oldtag'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect arguments passed to function.')
						);
					}
					elseif ($condition['oldtag'] === '' || $condition['oldtag'] === null
							|| $condition['oldtag'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'oldtag', _('cannot be empty'))
						);
					}

					if (!array_key_exists('newtag', $condition)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No "%1$s" given for correlation "%2$s".', 'newtag', $correlation['name'])
						);
					}
					elseif (is_array($condition['newtag'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect arguments passed to function.')
						);
					}
					elseif ($condition['newtag'] === '' || $condition['newtag'] === null
							|| $condition['newtag'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'newtag', _('cannot be empty'))
						);
					}
					break;

				case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
					if (!array_key_exists('tag', $condition)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No "%1$s" given for correlation "%2$s".', 'tag', $correlation['name'])
						);
					}
					elseif (is_array($condition['tag'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect arguments passed to function.')
						);
					}
					elseif ($condition['tag'] === '' || $condition['tag'] === null || $condition['tag'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty'))
						);
					}

					if (array_key_exists('operator', $condition)) {
						if (is_array($condition['operator'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('Incorrect arguments passed to function.')
							);
						}
						elseif (!$filter_condition_tagval_operator_validator->validate($condition['operator'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
								$condition['operator'],
								'operator',
								$correlation['name']
							));
						}
					}
					break;
			}

			if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				if (array_key_exists($condition['formulaid'], $formulaIds)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Duplicate "%1$s" value "%2$s" for correlation "%3$s".', 'formulaid', $condition['formulaid'],
							$correlation['name']
					));
				}
				else {
					$formulaIds[$condition['formulaid']] = true;
				}
			}

			unset($condition['formulaid']);
			$conditions[] = $condition;
		}

		if (count($conditions) != count(array_unique($conditions, SORT_REGULAR))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Conditions duplicates for correlation "%1$s".', $correlation['name'])
			);
		}

		return $groupids;
	}

	/**
	 * Validate correlation filter "formula" field.
	 *
	 * @param array				$correlation						One correlation containing the filter, formula and name.
	 * @param string			$correlation['name']				Correlation name for error messages.
	 * @param array				$correlation['filter']				Correlation filter array containing the formula.
	 * @param string			$correlation['filter']['formula']	User-defined expression to be used for evaluating
	 *																conditions of filters with a custom expression.
	 * @param CConditionFormula $parser								Condition formula parser.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateFormula(array $correlation, CConditionFormula $parser) {
		if (is_array($correlation['filter']['formula'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}

		if (!$parser->parse($correlation['filter']['formula'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect custom expression "%2$s" for correlation "%1$s": %3$s.',
					$correlation['filter']['formula'], $correlation['name'], $parser->error
				)
			);
		}
	}

	/**
	 * Validate correlation condition formula IDs. Check the "formulaid" field and that formula matches the conditions.
	 *
	 * @param array				$correlation										One correlation containing array of
	 *																				conditions and name.
	 * @param string			$correlation['name']								Correlation name for error messages.
	 * @param array				$correlation['filter']								Correlation filter array containing
	 *																				the conditions.
	 * @param array				$correlation['filter']['conditions']				An array of correlation conditions.
	 * @param string			$correlation['filter']['conditions'][]['formulaid']	Condition formula ID.
	 * @param CConditionFormula $parser												Condition formula parser.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateConditionFormulaIDs(array $correlation, CConditionFormula $parser) {
		foreach ($correlation['filter']['conditions'] as $condition) {
			if (!array_key_exists('formulaid', $condition)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('No "%1$s" given for correlation "%2$s".', 'formulaid', $correlation['name'])
				);
			}
			elseif (is_array($condition['formulaid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif (!preg_match('/[A-Z]+/', $condition['formulaid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect filter condition formula ID given for correlation "%1$s".', $correlation['name'])
				);
			}
		}

		$conditions = zbx_toHash($correlation['filter']['conditions'], 'formulaid');
		$constants = array_unique(zbx_objectValues($parser->constants, 'value'));

		foreach ($constants as $constant) {
			if (!array_key_exists($constant, $conditions)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Condition "%2$s" used in formula "%3$s" for correlation "%1$s" is not defined.',
					$correlation['name'], $constant, $correlation['filter']['formula']
				));
			}

			unset($conditions[$constant]);
		}

		// Check that the "conditions" array has no unused conditions.
		if ($conditions) {
			$condition = reset($conditions);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Condition "%2$s" is not used in formula "%3$s" for correlation "%1$s".', $correlation['name'],
				$condition['formulaid'], $correlation['filter']['formula']
			));
		}
	}

	/**
	 * Validate correlation operations. Check if "operations" is valid, if "type" is valid and there are no duplicate
	 * operations in correlation.
	 *
	 * @param array					$correlation						One correlation containing array of operations and name.
	 * @param string				$correlation['name']				Correlation name for error messages.
	 * @param array					$correlation['operations']			An array of correlation operations.
	 * @param int					$correlation['operations']['type']	Correlation operation type.
	 *																	Possible values are:
	 *																		0 - ZBX_CORR_OPERATION_CLOSE_OLD;
	 *																		1 - ZBX_CORR_OPERATION_CLOSE_NEW.
	 * @param CLimitedSetValidator	$filter_operations_validator		Operations validator.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateOperations(array $correlation, CLimitedSetValidator $filter_operations_validator) {
		if (!is_array($correlation['operations'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}
		elseif (!$correlation['operations']) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('No "%1$s" given for correlation "%2$s".', 'operations', $correlation['name'])
			);
		}

		foreach ($correlation['operations'] as $operation) {
			if (!array_key_exists('type', $operation)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('No operation type given for correlation "%1$s".', $correlation['name'])
				);
			}
			elseif (is_array($operation['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (!$filter_operations_validator->validate($operation['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect value "%1$s" in field "%2$s" for correlation "%3$s".',
					$operation['type'],
					'type',
					$correlation['name']
				));
			}
		}

		// Check that same operation types do not repeat.
		$duplicate = CArrayHelper::findDuplicate($correlation['operations'], 'type');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "%1$s" value "%2$s" for correlation "%3$s".', 'type', $duplicate['type'],
					$correlation['name']
				)
			);
		}
	}

	/**
	 * Insert correlation condition values to their corresponding DB tables.
	 *
	 * @param array $conditions		An array of conditions to create.
	 *
	 * @return array
	 */
	protected function addConditions(array $conditions) {
		$conditions = DB::save('corr_condition', $conditions);

		$corr_condition_tags_to_create = [];
		$corr_condition_hostgroups_to_create = [];
		$corr_condition_tag_pairs_to_create = [];
		$corr_condition_tag_values_to_create = [];

		foreach ($conditions as $condition) {
			switch ($condition['type']) {
				case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
					$corr_condition_tags_to_create[] = $condition;
					break;

				case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
					$corr_condition_hostgroups_to_create[] = $condition;
					break;

				case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
					$corr_condition_tag_pairs_to_create[] = $condition;
					break;

				case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
					$corr_condition_tag_values_to_create[] = $condition;
					break;
			}
		}

		if ($corr_condition_tags_to_create) {
			DB::insert('corr_condition_tag', $corr_condition_tags_to_create, false);
		}

		if ($corr_condition_hostgroups_to_create) {
			DB::insert('corr_condition_group', $corr_condition_hostgroups_to_create, false);
		}

		if ($corr_condition_tag_pairs_to_create) {
			DB::insert('corr_condition_tagpair', $corr_condition_tag_pairs_to_create, false);
		}

		if ($corr_condition_tag_values_to_create) {
			DB::insert('corr_condition_tagvalue', $corr_condition_tag_values_to_create, false);
		}

		return $conditions;
	}

	/**
	 * Apply query output options.
	 *
	 * @param type $table_name
	 * @param type $table_alias
	 * @param array $options
	 * @param array $sql_parts
	 *
	 * @return array
	 */
	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['countOutput'] === null) {
			// Add filter fields.
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sql_parts = $this->addQuerySelect('c.formula', $sql_parts);
				$sql_parts = $this->addQuerySelect('c.evaltype', $sql_parts);
			}

			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sql_parts = $this->addQuerySelect('c.evaltype', $sql_parts);
			}
		}

		return $sql_parts;
	}

	/**
	 * Extend result with requested objects.
	 *
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$correlationids = array_keys($result);

		// Adding formulas and conditions.
		if ($options['selectFilter'] !== null) {
			$formula_requested = $this->outputIsRequested('formula', $options['selectFilter']);
			$eval_formula_requested = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$conditions_requested = $this->outputIsRequested('conditions', $options['selectFilter']);

			$filters = [];

			if ($options['selectFilter']) {
				foreach ($result as $correlation) {
					$filters[$correlation['correlationid']] = [
						'evaltype' => $correlation['evaltype'],
						'formula' => array_key_exists('formula', $correlation) ? $correlation['formula'] : '',
						'conditions' => []
					];
				}

				if ($formula_requested || $eval_formula_requested || $conditions_requested) {
					$sql = 'SELECT c.correlationid,c.corr_conditionid,c.type,ct.tag AS ct_tag,'.
								'cg.operator AS cg_operator,cg.groupid,ctp.oldtag,ctp.newtag,ctv.tag AS ctv_tag,'.
								'ctv.operator AS ctv_operator,ctv.value'.
							' FROM corr_condition c'.
							' LEFT JOIN corr_condition_tag ct ON ct.corr_conditionid = c.corr_conditionid'.
							' LEFT JOIN corr_condition_group cg ON cg.corr_conditionid = c.corr_conditionid'.
							' LEFT JOIN corr_condition_tagpair ctp ON ctp.corr_conditionid = c.corr_conditionid'.
							' LEFT JOIN corr_condition_tagvalue ctv ON ctv.corr_conditionid = c.corr_conditionid'.
							' WHERE '.dbConditionInt('c.correlationid', $correlationids);

					$db_corr_conditions = DBselect($sql);

					while ($row = DBfetch($db_corr_conditions)) {
						$fields = [
							'corr_conditionid' => $row['corr_conditionid'],
							'correlationid' => $row['correlationid'],
							'type' => $row['type']
						];

						switch ($row['type']) {
							case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
							case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
								$fields['tag'] = $row['ct_tag'];
								break;

							case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
								$fields['operator'] = $row['cg_operator'];
								$fields['groupid'] = $row['groupid'];
								break;

							case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
								$fields['oldtag'] = $row['oldtag'];
								$fields['newtag'] = $row['newtag'];
								break;

							case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
							case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
								$fields['tag'] = $row['ctv_tag'];
								$fields['operator'] = $row['ctv_operator'];
								$fields['value'] = $row['value'];
								break;
						}

						$filters[$row['correlationid']]['conditions'][] = $fields;
					}

					foreach ($filters as &$filter) {
						// In case of a custom expression, use the given formula.
						if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
							$formula = $filter['formula'];
						}
						// In other cases generate the formula automatically.
						else {
							$conditions = $filter['conditions'];
							CArrayHelper::sort($conditions, ['type']);
							$conditions_for_formula = [];

							foreach ($conditions as $condition) {
								$conditions_for_formula[$condition['corr_conditionid']] = $condition['type'];
							}

							$formula = CConditionHelper::getFormula($conditions_for_formula, $filter['evaltype']);
						}

						// Generate formulaids from the effective formula.
						$formulaids = CConditionHelper::getFormulaIds($formula);

						foreach ($filter['conditions'] as &$condition) {
							$condition['formulaid'] = $formulaids[$condition['corr_conditionid']];
						}
						unset($condition);

						// Generated a letter based formula only for actions with custom expressions.
						if ($formula_requested && $filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
							$filter['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
						}

						if ($eval_formula_requested) {
							$filter['eval_formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
						}
					}
					unset($filter);
				}
			}
			else {
				// In case no fields are actually selected in "filter", return empty array.
				foreach ($result as $correlation) {
					$filters[$correlation['correlationid']] = [];
				}
			}

			// Add filters to the result.
			foreach ($result as &$correlation) {
				$correlation['filter'] = $filters[$correlation['correlationid']];
			}
			unset($correlation);
		}

		// Adding operations.
		if ($options['selectOperations'] !== null && $options['selectOperations'] != API_OUTPUT_COUNT) {
			$operations = API::getApiService()->select('corr_operation', [
				'output' => $this->outputExtend($options['selectOperations'], [
					'correlationid', 'corr_operationid', 'type'
				]),
				'filter' => ['correlationid' => $correlationids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($operations, 'correlationid', 'corr_operationid');

			foreach ($operations as &$operation) {
				unset($operation['correlationid'], $operation['corr_operationid']);
			}
			unset($operation);

			$result = $relation_map->mapMany($result, $operations, 'operations');
		}

		return $result;
	}
}
