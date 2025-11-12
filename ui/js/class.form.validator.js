/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CFormValidator {

	static SUCCESS = 0;
	static ERROR = 1;

	static ERROR_LEVEL_PRIMARY = 0;
	static ERROR_LEVEL_DELAYED = 1;
	static ERROR_LEVEL_UNIQ = 2;
	static ERROR_LEVEL_API = 3;
	static ERROR_LEVEL_UNKNOWN = 4;

	/**
	 * AbortSignal object instance used to abort currently running validation.
	 *
	 * @type {AbortController}
	 */
	#abort_controller;

	/**
	 * Validation rules.
	 *
	 * @type {Object}
	 */
	#rules = null;

	/**
	 * Map of field values, types and absolute paths in format {"/absolute/path": {"value": "abc", "type": "string"}}.
	 *
	 * Field type is taken from the first valid ruleset (one of multiple alternative rulesets provided in #rules for
	 * particular field). #when_fields are collected before actual validation and  used during validation to get values
	 * and types when field is referred in other field rulesets, e.g., in "when" condition.
	 *
	 * @type {Object}
	 */
	#when_fields;

	/**
	 * API uniqueness check rules collected before actual validation that must be called at the end of validation.
	 *
	 * @type {Array}
	 */
	#api_uniq_rules;

	/**
	 * Delayed checks that are collected before actual validation and performed at the end of validation. "use" checks
	 * calls ?action=validate server-side controller to check value using specified parser or validator class.
	 *
	 * @type {Array}
	 */
	#use_checks;

	/**
	 * Object where all errors and field absolute paths are collected during validation.
	 *
	 * @type {Object}
	 */
	#errors;

	constructor(rules) {
		this.#rules = rules;
		this.#abort_controller = new AbortController();
	}

	/**
	 * Function called to validate field values according their rules each time when field value has been changed.
	 *
	 * @param {Object} values  Object of form fields as keys and fields values as object values. Typically collected
	 *                         using CForm.getAllValues() method. Only values included in fields array are validated.
	 * @param {Array}  fields  Typically this will be array of one field (but may be multiple fields) that has been
	 *                         changed and have to be validated.
	 *
	 * @returns {Promise}
	 */
	validateChanges(values, fields) {
		return new Promise(async (resolve_whole, reject_whole) => {
			this.#abort_controller.signal.addEventListener('abort', reject_whole);

			const fields_to_validate = this.#getFieldsToValidate(fields);
			const values_to_validate = this.#getValuesToValidate(values, fields_to_validate);
			const validation_rules = this.#getRelatedRules(fields_to_validate);

			const {when_fields_data, api_uniq_rules, use_checks} = this.#resolveFieldReferences(values_to_validate,
				values
			);

			this.#when_fields = when_fields_data;
			this.#api_uniq_rules = api_uniq_rules;
			this.#use_checks = use_checks;

			try {
				const r1 = this.#validate(values_to_validate, validation_rules);
				const r2 = await this.#validateDelayed();
				const r3 = await this.#validateApiUniqueness();
				const r4 = this.#validateDistinctness(values, validation_rules);
				resolve_whole(r1 && r2 && r3 && r4);
			}
			catch (error) {
				if (error.cause !== 'RulesError' && error.type !== 'abort') {
					console.error(error);
				}
			}
		});
	}

	/**
	 * Function called to validate all form field according the #rules before pushing values to server controller.
	 *
	 * @param {Object} values  Object of form fields as keys and fields values as object values. Typically collected
	 *                         using CForm.getAllValues() method. All known (included in #rules) fields are validated.
	 *
	 * @returns {Promise}
	 */
	validateSubmit(values) {
		return new Promise(async (resolve_whole) => {
			const {when_fields_data, api_uniq_rules, use_checks} = this.#resolveFieldReferences(values, values);

			this.#when_fields = when_fields_data;
			this.#api_uniq_rules = api_uniq_rules;
			this.#use_checks = use_checks;

			try {
				const r1 = this.#validate(values, this.#rules);
				const r2 = this.#validateDistinctness(values, this.#rules);
				const r3 = await this.#validateDelayed();
				const r4 = await this.#validateApiUniqueness();
				resolve_whole(r1 && r2 && r3 && r4);
			}
			catch (error) {
				if (error.cause !== 'RulesError' && error.type !== 'abort') {
					console.error(error);
				}
			}
		});
	}

	/**
	 * Function to abort running validateChanges validation.
	 */
	abortValidationInProgress() {
		this.#abort_controller.abort();
	}

	/**
	 * Main validation function to start validate values according to the given rules.
	 *
	 * @param {Object}            value  Values to validate.
	 * @param {Object | boolean}  rules  Rules to use for validation.
	 *
	 * @returns {boolean}  Returns true if validation passes. False otherwise.
	 */
	#validate(value, rules) {
		this.#errors = {};

		if (rules === false) {
			throw new Error('Invalid validation rules.', {cause: 'RulesError'});
		}

		const {result, error, path = ''} = this.#validateObject(rules, value, '');
		if (error) {
			this.#addError(path, error, CFormValidator.ERROR_LEVEL_PRIMARY);
		}

		return result == CFormValidator.SUCCESS;
	}

	/**
	 * Pre-processing function to collect and resolve referenced fields before actual validation.
	 *
	 * Function has the following purposes:
	 * - Resolve paths and collect path/value pairs for all fields referenced by 'when' rules.
	 * - If rules contains 'api_uniq' check, function will prepare API request parameters and makes array containing
	 *   paths to the fields that are linked to particular api_uniq check.
	 *
	 * @param {Object} data_to_validate  Values to use to find field related data and values for later use during validation.
	 * @param {Object} data_all          (optional) rules to use to decided what fields must be validated and what is their
	 *                                   actual types. If rules are not given, the general #rules are used.
	 *
	 * @returns {Object}  Function returns 3 objects (described in exact order):
	 *                  - {Object} of values and their types mapped with field absolute paths;
	 *                  - {Array} containing API uniqueness checks that must be performed later;
	 *                  - {Array} containing server-side parser/validator checks that must be performed later.
	 */
	#resolveFieldReferences(data_to_validate, data_all) {
		const when_fields_data = {};
		const api_uniq_rules = [];
		const use_checks = [];
		const rules_all = JSON.parse(JSON.stringify(this.#rules));

		/**
		 * Function will check if given ruleset has 'when' condition and resolves 'when' field references.
		 */
		const updateWhenReferences = (rule_set, field_path) => {
			if (!('when' in rule_set)) {
				return [];
			}

			const when_paths = [];

			rule_set.when.forEach(when => {
				const when_path = this.#getFieldAbsolutePath(when[0], field_path);

				if (!(when_path in when_fields_data)) {
					const field_type = getRuleSetsByPath(when_path)[0].type;
					const field_data = getFieldDataByPath(when_path);

					/*
					 * For fields of type='array' and type='objects' values are not stored because only supported
					 * 'when' methods are 'empty' and 'not_empty' so we need to know only if values was filled.
					 */
					if (field_type === 'array') {
						when_fields_data[when_path] = {
							type: field_type,
							value: field_data instanceof Array && (field_data.length > 0)
						};
					}
					else if (field_type === 'objects') {
						when_fields_data[when_path] = {
							type: field_type,
							value: Object.keys(field_data).length > 0
						};
					}
					else if (['id', 'integer', 'float', 'string'].includes(field_type)) {
						when_fields_data[when_path] = {type: field_type, value: field_data};
					}
				}

				// checkField and findMatchingRule assumes that order is guaranteed in when_paths.
				when_paths.push(when_path);
			});

			return when_paths;
		};

		/**
		 * Returns field type and data for given absolute path.
		 */
		const getFieldDataByPath = (field_path) => {
			const path_for_data_lookup = field_path.split('/').slice(1);
			let field_data = data_all;

			while (path_for_data_lookup.length) {
				const part = path_for_data_lookup.shift();

				if (!(part in field_data)) {
					return null;
				}

				field_data = field_data[part];
			}

			return field_data;
		};

		/**
		 * Return array with actual rule sets for field at given path.
		 */
		const getRuleSetsByPath = (field_path) => {
			const path_for_ruleset_lookup = field_path.split('/').slice(1);
			let matching_rulesets = [rules_all];
			let path_so_far = '';

			while (path_for_ruleset_lookup.length) {
				if (!matching_rulesets.length) {
					break;
				}

				const part = path_for_ruleset_lookup.shift();

				path_so_far += '/' + part;

				// Skip numeric parts of field_path, as they are not present in rules.
				if (!isNaN(parseInt(part))) {
					continue;
				}

				const next_level_matching_rulesets = [];

				matching_rulesets.forEach(({type, fields, field}) => {
					if (fields === undefined) {
						fields = {};
					}

					if (type === 'object' || type === 'objects') {
						next_level_matching_rulesets.push(...filterMatchingRuleSets(fields[part], path_so_far));
					}
					else if (type === 'array') {
						next_level_matching_rulesets.push(...filterMatchingRuleSets(field[part], path_so_far));
					}
				});

				matching_rulesets = next_level_matching_rulesets;
			}

			// Single field should not be matched to multiple types.
			// JS validation will ignore rules with when clauses to such fields.
			if (!matching_rulesets.length || matching_rulesets.some(({type}) => type != matching_rulesets[0]['type'])) {
				matching_rulesets = [{type: null}];
			}

			return matching_rulesets;
		};

		/**
		 * Rule matching is necessary to find which rule defines field type.
		 */
		const filterMatchingRuleSets = (rule_sets, field_path) => {
			if (rule_sets === undefined || !rule_sets.length) {
				return [];
			}

			return rule_sets.filter(rule_set => {
				if (!('when' in rule_set)) {
					return true;
				}

				const when_paths = updateWhenReferences(rule_set, field_path);

				return when_paths.every((when_path, index) => {
					const {0: _, ...when_rules} = rule_set.when[index];

					return when_path in when_fields_data && this.#checkValue(when_rules, when_fields_data[when_path]);
				});
			});
		};

		/**
		 * Function to check if field with given path exists in data object.
		 */
		const pathInObject = (object, path) => {
			const path_array = path.split('/');

			if (path_array[0] === '') {
				path_array.shift();
			}

			let current = object;

			for (let part of path_array) {
				if (current[part] === undefined) {
					return false
				}

				current = current[part];
			}

			return true;
		};

		/**
		 * Function to check if rules has 'api_uniq' directive, resolve referenced paths, prepare API parameters.
		 */
		const checkApiUniq = (rule_set, field_path) => {
			if (!('api_uniq' in rule_set)) {
				return;
			}

			rule_set.api_uniq.forEach(api_uniq => {
				const [method, api_params, id_field, error_msg] = api_uniq;
				const referenced_fields = [];
				const parameters = {filter: {}};
				let exclude_id = null;

				if (id_field !== null) {
					const id_field_path = this.#getFieldAbsolutePath(id_field, field_path);
					exclude_id = getFieldDataByPath(id_field_path);
				}

				Object.entries(api_params.filter).forEach(([api_field, value]) => {
					value = String(value);

					if (value.startsWith('{') && value.endsWith('}')) {
						const param_field_name = value.slice(1, -1);
						const param_field_path = this.#getFieldAbsolutePath(param_field_name, field_path);
						const param_data = getFieldDataByPath(param_field_path);

						referenced_fields.push(param_field_path);
						parameters.filter[api_field] = param_data;
					}
					else {
						parameters.filter[api_field] = value;
					}
				});

				Object.entries(api_params).forEach(([api_field, value]) => {
					if (api_field === 'filter') {
						return;
					}

					value = String(value);

					if (value.startsWith('{') && value.endsWith('}')) {
						const param_field_name = value.slice(1, -1);
						const param_field_path = this.#getFieldAbsolutePath(param_field_name, field_path);
						const param_data = getFieldDataByPath(param_field_path);

						referenced_fields.push(param_field_path);
						parameters[api_field] = param_data;
					}
					else {
						parameters[api_field] = value;
					}
				});

				const validated_fields = referenced_fields.filter(path => pathInObject(data_to_validate, path));

				if (validated_fields.length) {
					api_uniq_rules.push({method, parameters, fields: referenced_fields, exclude_id, error_msg});
				}
			});
		};

		/**
		 * Store 'use' rule for delayed check.
		 */
		const checkUse = (rules, path) => {
			if (!('use' in rules)) {
				return;
			}

			const value = getFieldDataByPath(path);

			if (value !== '') {
				use_checks.push({rules, path, value});
			}
		};

		const checkField = (rule_set, field, data, field_path) => {
			const when_paths = updateWhenReferences(rule_set, field_path);

			if (rule_set.type === 'objects' || rule_set.type === 'array') {
				if (data[field] !== null) {
					Object.entries(data[field]).forEach(([key, value]) => scanObject(value, field_path + '/' + key));
				}
			}
			else if (rule_set.type === 'object') {
				if (data[field] !== null) {
					scanObject(data[field], field_path);
				}
			}
			else if (['id', 'integer', 'float', 'string'].includes(rule_set.type)) {
				if (!when_paths.length) {
					checkUse(rule_set, field_path);
				}
				else {
					const when_match = when_paths.every((when_path, index) => {
						const when_rules = {...rule_set.when[index]};
						delete when_rules[0];

						return when_path in when_fields_data
							&& this.#checkValue(when_rules, when_fields_data[when_path]);
					});

					when_match && checkUse(rule_set, field_path);
				}
			}
		};

		const scanObject = (data, field_path) => {
			if (!this.#isTypeObject(data)) {
				return;
			}

			const rule_sets = getRuleSetsByPath(field_path);

			rule_sets.forEach(rule_set => {
				checkApiUniq(rule_set, field_path);

				if ('fields' in rule_set) {
					Object.entries(rule_set.fields).forEach(([field, rule_sets]) => {
						if (field in data) {
							rule_sets.forEach(rule_set => checkField(rule_set, field, data, field_path + '/' + field));
						}
					});
				}
			});
		};

		scanObject(data_to_validate, '');

		return {when_fields_data, api_uniq_rules, use_checks};
	}

	/**
	 * Call API request to validate all api based validations.
	 *
	 * @param {Array} validatons
	 *
	 * @returns {Promise}
	 */
	#validateApiExists(validations) {
		const url = new URL('zabbix.php', location.href);

		url.searchParams.set('action', 'validate.api.exists');

		return fetch(url.href, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({validations}),
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.catch(exception => {
				console.error(exception);

				return {result: false};
			});
	}

	/**
	 * Check data uniqueness using JS API call.
	 *
	 * @returns {Promise}
	 */
	#validateApiUniqueness() {
		const api_uniq_checks = this.#api_uniq_rules.filter(check => {
			// If at least one of involved (referenced in parameters) field has error, api_uniq check is not performed.
			if (check.fields.some(field_path => (field_path in this.#errors
					&& this.#errors[field_path].some(error => error.message !== '')))) {

				return false;
			}

			// If all requested parameters are empty, skip this check.
			return Object.values(check.parameters).some(value => value !== '');
		});

		const api_validations = api_uniq_checks.map(check => {
			const {method, parameters, exclude_id} = check;
			const [api, api_method] = method.split('.');

			return {
				api,
				method: api_method,
				options: parameters,
				exclude_id,
				field: check.fields[0],
				error_msg: check.error_msg
			};
		});

		if (api_validations.length) {
			return this.#validateApiExists(api_validations)
				.then(result => {
					if (result.result === false && result.errors) {
						result.errors.forEach(
							error => this.#addError(error.field, error.message, CFormValidator.ERROR_LEVEL_API)
						);
					}

					return result.result;
				});
		}

		return Promise.resolve(true);
	}

	/**
	 * Function to perform delayed "use" checks that involves server-side parsers and validators.
	 *
	 * @returns {Promise}
	 */
	#validateDelayed() {
		const delayed_checks = this.#use_checks.filter((check) => {
			let are_all_fields_valid = true;

			// If at least one of involved field has error, ?action=validate check is skipped.
			if (check.path in this.#errors && this.#errors[check.path].some((error) => error.message !== '')) {
				are_all_fields_valid = false;
			}

			return are_all_fields_valid;
		});

		return new Promise((resolve) => {
			if (delayed_checks.length) {
				let requests = [];
				let id = 0;

				for (const check of delayed_checks) {
					requests.push(new Promise((resolve) => {
						const curl = new Curl('zabbix.php');
						curl.setArgument('action', 'validate');

						return fetch(curl.getUrl(), {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json'
							},
							credentials: 'same-origin',
							body: JSON.stringify({
								use: check.rules.use,
								value: check.value,
								jsonrpc: '2.0',
								id: ++id
							}),
						})
							.then((response) => response.json())
							.then((response) => {
								if ('result' in response && response.result !== '') {
									check.error_msg = this.#getMessage(check.rules, 'use', response.result);
								}

								resolve();
							});
					}));
				}

				Promise.all(requests).then(() => {
					let result_all = true;

					delayed_checks.forEach((check) => {
						if ('error_msg' in check) {
							this.#addError(check.path, check.error_msg, CFormValidator.ERROR_LEVEL_DELAYED);
							result_all = false;
						}
					});

					resolve(result_all);
				});
			}
			else {
				resolve(true);
			}
		});
	}

	/**
	 * Function to check if ruleset has custom error message for particular check.
	 *
	 * @param {Object} rules            Ruleset object to check if it contains custom message.
	 * @param {string} check_name       Check name.
	 * @param {string} default_message  Default message used when no custom message is defined.
	 *
	 * @returns {string}
	 */
	#getMessage(rules, check_name, default_message) {
		return rules?.messages?.[check_name] || default_message;
	}

	/**
	 * Add path to #errors object. This is used also to know what fields have been validated.
	 *
	 * @param {string} path   Absolute path of field.
	 * @param {int}    level  Level in which validation was performed (optional).
	 */
	#addPath(path, level = CFormValidator.ERROR_LEVEL_UNKNOWN) {
		this.#addError(path, '', level);
	}

	/**
	 * Add error to #errors object.
	 *
	 * @param {string} path     Absolute path of field.
	 * @param {string} message  Error message.
	 * @param {int}    level    Error level (optional).
	 */
	#addError(path, message, level = CFormValidator.ERROR_LEVEL_UNKNOWN) {
		if (!(path in this.#errors)) {
			this.#errors[path] = [];
		}

		this.#errors[path] = this.#errors[path].filter((error) => !(error.message === '' && error.level == level));

		if (this.#errors[path].some((error) => error.message === message && error.level == level) === false) {
			this.#errors[path].push({message, level});
		}
	}

	/**
	 * Function that can be used to register error without path. Such errors are meant to be showed on the top of the
	 * form without linkage to some specific field.
	 *
	 * @param {string} error
	 */
	addGeneralError(error) {
		this.#addPath('');
		this.#addError('', error);
	}

	/**
	 * Function to return all collected errors grouped by fields.
	 *
	 * @returns {Object}
	 */
	getErrors() {
		return this.#errors;
	}

	/**
	 * Function to find subset of #rules that have to be used for validation of some specific fields.
	 *
	 * @param {Array} fields  Fields to find validation rules for.
	 *
	 * @returns {Object}
	 */
	#getRelatedRules(fields) {
		fields = fields.map((field) => field.split('/').filter(part => /^\d+$/.test(part) === false).join('/'));

		const getRules = (rules, rule_path, parent_matching = false) => {
			const rule = {};
			let path_matching = false;

			Object.entries(rules).forEach(([key, value]) => {
				if (key === 'fields') {
					Object.entries(value).forEach(([field_name, rule_sets]) => {
						const child_matching = parent_matching || fields.includes(rule_path + '/' + field_name);

						rule_sets = [...rule_sets].map(rule_set => {
							return getRules(rule_set, rule_path + '/' + field_name, child_matching);
						});

						rule_sets = rule_sets.filter(rule_set => rule_set);
						if (rule_sets.length) {
							if (!('fields' in rule)) {
								rule.fields = {};
							}

							rule.fields[field_name] = rule_sets;
							path_matching = true;
						}
					});
				}
				else {
					// 'when' rule should be kept in the rules, only if this 'when' rule value matches any of fields
					// that will need to be validated. Necessary for multilevel 'when' rules on 'object' type rules.
					rule[key] = value;
				}
			});

			return parent_matching || path_matching ? rule : false;
		};

		return getRules(this.#rules, '');
	}

	/**
	 * This function will extend the list of fields actually changed with related fields that have to be re-validated
	 * on-change. Function works with absolute paths.
	 *
	 * @param {Array} fields  Actually changed.
	 */
	#getFieldsToValidate(fields) {
		/*
		 * When reading the variable names in this function take a note of following:
		 * - field_path - is path to a field in specific object row. It contains row indexes.
		 * - rule_path - is path to a field in rules. It doesn't have row indexes.
		 */
		const getFieldRules = (field_path) => {
			let rules = [this.#rules];

			for (const part of field_path.split('/').slice(1)) {
				if (!isNaN(parseInt(part))) {
					continue;
				}

				const next_level_rules = [];

				for (const rule of rules) {
					if (rule.fields === undefined) {
						return false;
					}

					if (!this.#isTypeObject(rule) || !(part in rule.fields)) {
						return false;
					}

					next_level_rules.push(...rule.fields[part]);
				}

				rules = next_level_rules;
			}

			return rules;
		};

		const findRelatedFieldPaths = (lookup_field_path) => {
			const scan = (lookup_rule_path, rules, current_rule_path) => {
				const current_field_name = current_rule_path.split('/').at(-1);
				let related_fields = [];

				Object.entries(rules).forEach(([rule_key, rule_value]) => {
					if (rule_key === 'when') {
						rule_value.forEach((when) => {
							// Add fields that relates on current lookup field.
							if (lookup_rule_path === this.#getFieldAbsolutePath(when[0], current_rule_path)) {
								related_fields.push(this.#getFieldAbsolutePath(current_field_name, lookup_field_path));
							}
						});
					}
					else if (rule_key === 'api_uniq') {
						// If lookup field is used in API uniqueness check then all fields used in that API
						// check should be validated.
						rule_value.forEach((api_uniq) => {
							let parameter_fields = Object.values(api_uniq[1])
								.filter(value => String(value).startsWith('{') && String(value).endsWith('}'))
								.map(field => field.slice(1, -1));

							const has_match = parameter_fields.some((field) => {
								return this.#getFieldAbsolutePath(field, current_rule_path + '/') === lookup_rule_path;
							});

							if (has_match) {
								parameter_fields = parameter_fields.map(field => {
									return this.#getFieldAbsolutePath(field, lookup_field_path)
								});
								related_fields = [...related_fields, ...parameter_fields];

								// Make referred ID field as related if api check have field reference in parameters.
								if ('2' in api_uniq && api_uniq[2] !== null) {
									related_fields.push(this.#getFieldAbsolutePath(api_uniq[2], lookup_field_path));
								}
							}
						});
					}
					else if (rule_key === 'fields') {
						Object.entries(rule_value).forEach(([field_name, rule_sets]) => {
							const object_field_rule_path = current_rule_path + '/' + field_name;

							rule_sets.forEach((rule_set) => {
								const rel_fields = scan(lookup_rule_path, rule_set, object_field_rule_path);

								related_fields = [...related_fields, ...rel_fields];
							});
						});
					}
				});

				return related_fields;
			};

			const lookup_rule_path = lookup_field_path.split('/')
				.filter((part) => /^\d+$/.test(part) === false)
				.join('/');

			return scan(lookup_rule_path, this.#rules, '');
		};

		// Collect absolute paths for fields that have to be validated (changed field + related fields).
		const fields_to_validate = [];
		const fields_to_lookup = fields.map((field) => '/' + field.replace(/]$/, '').split(/\]\[|\[/).join('/'));

		while (fields_to_lookup.length > 0) {
			const field = fields_to_lookup.shift();

			if (!getFieldRules(field)) {
				// Field is not found in rules. Error in rules or page.
				continue;
			}

			fields_to_validate.push(field);

			const related_field_paths = findRelatedFieldPaths(field);

			related_field_paths.forEach((rel_field) => {
				if (!fields_to_validate.includes(rel_field) && !fields_to_lookup.includes(rel_field)) {
					fields_to_lookup.push(rel_field);
				}
			});
		}

		return fields_to_validate;
	}

	/**
	 * Function returns value subset of given paths only.
	 *
	 * @param {Object} values
	 * @param {Array}  fields_to_validate
	 */
	#getValuesToValidate(values, fields_to_validate) {
		const getValueByPath = (field_path, all_values) => {
			let data = all_values;

			for (const part of field_path.split('/').slice(1)) {
				if (!(part in data)) {
					return null;
				}

				data = data[part];
			}

			return data;
		};

		let subset = {};

		fields_to_validate.forEach((field_path) => {
			const parts = field_path.split('/').slice(1);
			const value = getValueByPath(field_path, values);

			subset = objectSetDeepValue(subset, parts, value);
		});

		return subset;
	}

	/**
	 * Validate single field using the ruleset.
	 *
	 * @param {Object} rules  Single ruleset.
	 * @param {Object} data   Data object where expected field value is defined.
	 * @param {string|number} field  Field name used in rules and data object.
	 * @param {string} path   Absolute path of particular field.
	 *
	 * @returns {Object}
	 */
	#validateField(rules, data, field, path) {
		if ('when' in rules && this.#testWhenCondition(rules.when, path) === false) {
			return {result: CFormValidator.SUCCESS};
		}

		if (!(field in data) || data[field] === null) {
			if ('required' in rules) {
				this.#addError(path, this.#getMessage(rules, 'required', t('This field cannot be empty.')),
					CFormValidator.ERROR_LEVEL_PRIMARY
				);

				return {result: CFormValidator.ERROR};
			}

			return {result: CFormValidator.SUCCESS};
		}

		const validator = {
			'id': this.#validateId,
			'integer': this.#validateInt32,
			'float': this.#validateFloat,
			'string': this.#validateStringUtf8,
			'array': this.#validateArray,
			'object': this.#validateObject,
			'objects': this.#validateObjects,
			'file': this.#validateFile
		}[rules.type] || null;

		if (validator !== null) {
			const {result, error, value = data[field]} = validator.call(this, rules, data[field], path);

			if (result == CFormValidator.ERROR) {
				error && this.#addError(path, error, CFormValidator.ERROR_LEVEL_PRIMARY);

				return {result};
			}
			else {
				data[field] = value;
			}
		}

		return {result: CFormValidator.SUCCESS, value: data};
	}

	/**
	 * Identifier validator.
	 *
	 * @param {Object} rules  Ruleset object to use for validation.
	 * @param {any}    value  Expected to be ID.
	 *
	 * @returns {Object}
	 */
	#validateId(rules, value) {
		if (!this.#isTypeId(value)) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('This value is not a valid identifier.'))
			};
		}

		return {result: CFormValidator.SUCCESS, value: String(value).replace(/^0+/, '') || '0'};
	}

	/**
	 * Integers validator.
	 *
	 * @param {Object} rules  Ruleset object to use for validation.
	 * @param {any}    value  Expected to be integer.
	 *
	 * @returns {Object}
	 */
	#validateInt32(rules, value) {
		if (!this.#isTypeInt32(value)) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('This value is not a valid integer.'))
			};
		}

		value = parseInt(value);

		if (!this.#checkNumericIn(rules, value)) {
			const values = rules['in'].filter(val => !Array.isArray(val));
			const ranges = rules['in']
				.filter(val => Array.isArray(val))
				.map(val => `${val[0] ?? ''}:${val[1] ?? ''}`);
			const errors = [];

			if (values.length) {
				errors.push(values.length > 1 ? sprintf(t('one of %1$s'), values.join(', ')) : values[0]);
			}

			if (ranges.length) {
				errors.push(ranges.length > 1
					? sprintf(t('within ranges %1$s'), ranges.join(', '))
					: sprintf(t('within range %1$s'), ranges[0])
				);
			}

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'in', sprintf(t('This value must be %1$s.'), errors.join(t(' or '))))
			};
		}

		if (!this.#checkNumericNotIn(rules, value)) {
			const values = rules['not_in'].filter(val => !Array.isArray(val));
			const ranges = rules['not_in']
				.filter(val => Array.isArray(val))
				.map(val => `${val[0] ?? ''}:${val[1] ?? ''}`);
			const errors = [];

			if (values.length) {
				errors.push(values.length > 1 ? sprintf(t('one of %1$s'), values.join(', ')) : values[0]);
			}

			if (ranges.length) {
				errors.push(ranges.length > 1
					? sprintf(t('within ranges %1$s'), ranges.join(', '))
					: sprintf(t('within range %1$s'), ranges[0])
				);
			}

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'not_in',
					sprintf(t('This value cannot be %1$s.'), errors.join(t(' or ')))
				)
			};
		}

		if ('min' in rules && value < rules['min']) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'min',
					sprintf(t('This value must be no less than "%1$s".'), rules['min'])
				)
			};
		}

		if ('max' in rules && value > rules['max']) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'max',
					sprintf(t('This value must be no greater than "%1$s".'), rules['max'])
				)
			};
		}

		return {result: CFormValidator.SUCCESS, value};
	}

	/**
	 * Floating point number validator.
	 *
	 * @param {Object} rules  Ruleset object to use for validation.
	 * @param {any}    value  Expected to be a number.
	 *
	 * @returns {Object}
	 */
	#validateFloat(rules, value) {
		if (!this.#isTypeFloat(value)) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('This value is not a valid floating-point value.'))
			};
		}

		value = parseFloat(value);

		if (!this.#checkNumericIn(rules, value)) {
			const values = rules['in'].filter(val => !Array.isArray(val));
			const ranges = rules['in']
				.filter(val => Array.isArray(val))
				.map(val => `${val[0] ?? ''}:${val[1] ?? ''}`);
			const errors = [];

			if (values.length) {
				errors.push(values.length > 1 ? sprintf(t('one of %1$s'), values.join(', ')) : values[0]);
			}

			if (ranges.length) {
				errors.push(ranges.length > 1
					? sprintf(t('within ranges %1$s'), ranges.join(', '))
					: sprintf(t('within range %1$s'), ranges[0])
				);
			}

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'in', sprintf(t('This value must be %1$s.'), errors.join(t(' or '))))
			};
		}

		if (!this.#checkNumericNotIn(rules, value)) {
			const values = rules['not_in'].filter(val => !Array.isArray(val));
			const ranges = rules['not_in']
				.filter(val => Array.isArray(val))
				.map(val => `${val[0] ?? ''}:${val[1] ?? ''}`);
			const errors = [];

			if (values.length) {
				errors.push(values.length > 1 ? sprintf(t('one of %1$s'), values.join(', ')) : values[0]);
			}

			if (ranges.length) {
				errors.push(ranges.length > 1
					? sprintf(t('within ranges %1$s'), ranges.join(', '))
					: sprintf(t('within range %1$s'), ranges[0])
				);
			}

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'not_in',
					sprintf(t('This value cannot be %1$s.'), errors.join(t(' or ')))
				)
			};
		}

		if ('min' in rules && value < rules['min']) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'min',
					sprintf(t('This value must be no less than "%1$s".'),  rules['min'])
				)
			};
		}

		if ('max' in rules && value > rules['max']) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'max',
					sprintf(t('This value must be no greater than "%1$s".'),  rules['max'])
				)
			};
		}

		return {result: CFormValidator.SUCCESS, value};
	}

	/**
	 * String validator.
	 *
	 * @param {Object} rules  Ruleset object to use for validation.
	 * @param {any}    value  Expected to be a string.
	 *
	 * @returns {Object}
	 */
	#validateStringUtf8(rules, value) {
		if (typeof value !== 'string') {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('This value is not a valid string.'))
			};
		}

		if (('not_empty' in rules) && value === '') {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'not_empty', t('This field cannot be empty.'))
			};
		}

		if (('allow_macro' in rules) && value !== '' && this.#isUserMacro(value)) {
			return {result: CFormValidator.SUCCESS};
		}

		if ('length' in rules && value.length > rules.length) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'length', t('This value is too long.'))
			};
		}

		if ('regex' in rules) {
			const {pattern, flags} = this.#extractRegex(rules.regex);

			const re = new RegExp(pattern, flags);
			if (!re.test(value)) {
				return {
					result: CFormValidator.ERROR,
					error: this.#getMessage(rules, 'regex', t('This value does not match pattern.'))
				};
			}
		}

		if (!this.#checkStringIn(rules, value)) {
			const values = rules['in'].map(val => `"${val}"`).join(', ');

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'in', rules['in'].length > 1
					? sprintf(t('This value must be one of %1$s.'), values)
					: sprintf(t('This value must be %1$s.'), values)
				)
			};
		}

		if (!this.#checkStringNotIn(rules, value)) {
			const values = rules['not_in'].map(val => `"${val}"`).join(', ');

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'not_in', rules['not_in'].length > 1
					? sprintf(t('This value cannot be one of %1$s.'), values)
					: sprintf(t('This value cannot be %1$s.'), values)
				)
			};
		}

		return {result: CFormValidator.SUCCESS};
	}

	/**
	 * Split regular expression into pattern and flags.
	 *
	 * @param {string} regex  Regular expression string.
	 *
	 * @returns {Object}
	 */
	#extractRegex(regex) {
		const flags = regex.split('/').pop();
		const pattern = regex.substring(1, regex.length - flags.length - 1);

		return {pattern, flags};
	}

	/**
	 * Function to validate data that according the rules is expected to be an object.
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    data   Data to validate.
	 * @param {string} path   Path of field.
	 *
	 * @returns {Object}
	 */
	#validateObject(rules, data, path) {
		if (Array.isArray(data) && !data.length) {
			/*
			 * Object without properties may arrive here as empty array.
			 * That's not actually the error so simply normalize it.
			 */
			data = {};
		}

		if (!this.#isTypeObject(data)) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('An array is expected.'))
			};
		}

		if (!Object.keys(data).length) {
			return {result: CFormValidator.SUCCESS};
		}

		// Validate fields according the rules.fields array.
		if ('fields' in rules) {
			let has_error = false;

			for (const [field, rule_sets] of Object.entries(rules.fields)) {
				this.#addPath(path + '/' + field, CFormValidator.ERROR_LEVEL_PRIMARY);

				for (let i = 0; Object.keys(rule_sets).length > i; i++) {
					const rule_set = rule_sets[i];
					const {result, value = data} = this.#validateField(rule_set, data, field, path + '/' + field);

					if (result === CFormValidator.ERROR) {
						has_error = true;
					}
					else {
						data = value;
					}
				}
			}

			if (has_error) {
				return {result: CFormValidator.ERROR};
			}
		}

		return {result: CFormValidator.SUCCESS, data};
	}

	/**
	 * Array of objects validator.
	 *
	 * @param {Object} rules           Ruleset to use for validation.
	 * @param {any}    objects_values  Data to validate.
	 * @param {string} path            Path of field.
	 *
	 * @returns {Object}
	 */
	#validateObjects(rules, objects_values, path) {
		if (!this.#isTypeObject(objects_values) && !Array.isArray(objects_values)) {
			this.#addError(path, this.#getMessage(rules, 'type', t('An array is expected.')),
				CFormValidator.ERROR_LEVEL_PRIMARY
			);

			return {result: CFormValidator.ERROR};
		}

		if (!this.#isTypeObject(objects_values)) {
			/*
			 * Object without properties may arrive here as empty array.
			 * That's not actually the error so simply normalize it.
			 *
			 * Another case why this is needed is that some arrays are passed as objects having IDs used as keys.
			 */
			objects_values = {...objects_values};
		}

		if ('not_empty' in rules && !Object.keys(objects_values).length) {
			this.#addError(path, this.#getMessage(rules, 'not_empty', t('This field cannot be empty.')),
				CFormValidator.ERROR_LEVEL_PRIMARY
			);

			return {result: CFormValidator.ERROR};
		}

		const normalized_values = {};
		let has_error = false;

		if ('fields' in rules) {
			for (const [key, obj] of Object.entries(objects_values)) {
				const {result, error, value = obj} = this.#validateObject({fields: rules.fields}, obj, path + '/' + key);

				if (result == CFormValidator.ERROR) {
					error && this.#addError(path + '/' + key, error, CFormValidator.ERROR_LEVEL_PRIMARY);
					has_error = true;
				}
				else {
					normalized_values[key] = value;
				}
			}
		}

		if (has_error) {
			return {result: CFormValidator.ERROR};
		}
		objects_values = normalized_values;

		return {result: CFormValidator.SUCCESS, value: objects_values};
	}

	/**
	 * Function to validate data that according to the rules is expected to be a file.
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    value  Data to validate (an uploaded file).
	 *
	 * @returns {Object}
	 */
	#validateFile(rules, value) {
		if (!(value instanceof File)) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'type', t('This value is not a valid file.'))
			};
		}

		if ('not_empty' in rules && value.size == 0) {
			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'not_empty', t('This field cannot be empty.'))
			};
		}

		if (rules['max-size'] && value.size > rules['max-size']) {
			const error_msg = rules['file-type'] === 'image'
				? t('Image size must be less than %1$s.')
				: t('File size must be less than %1$s.');

			return {
				result: CFormValidator.ERROR,
				error: this.#getMessage(rules, 'max-size',
					sprintf(error_msg, rules['max-size-human-readable'])
				)
			};
		}

		if (rules['file-type'] !== 'file' && value.size > 0) {
			if (!value.type.startsWith(`${rules['file-type']}/`)) {
				return {
					result: CFormValidator.ERROR,
					error: this.#getMessage(rules, 'file-type', t('File format is unsupported.'))
				};
			}
		}

		return {result: CFormValidator.SUCCESS, value};
	}

	/**
	 * Array of values validator.
	 *
	 * @param {Object} rules         Ruleset to use for validation.
	 * @param {any}    array_values  Data to validate.
	 * @param {string} path          Path of field.
	 *
	 * @returns {Object}
	 */
	#validateArray(rules, array_values, path) {
		/*
		 * Some arrays received from form are interpreted as objects so, if it's object but all keys are numeric, it's
		 * actually array.
		 */
		if (this.#isTypeObject(array_values)
			&& Object.keys(array_values).every((k) => this.#isTypeInt32(k) && parseInt(k) >= 0)) {
			array_values = Object.values(array_values);
		}

		if (!Array.isArray(array_values)) {
			this.#addError(path, this.#getMessage(rules, 'type', t('An array is expected.')),
				CFormValidator.ERROR_LEVEL_PRIMARY
			);

			return {result: CFormValidator.ERROR};
		}

		if ('not_empty' in rules && !array_values.filter(v => v !== null).length) {
			this.#addError(path, this.#getMessage(rules, 'not_empty', t('This field cannot be empty.')),
				CFormValidator.ERROR_LEVEL_PRIMARY
			);

			return {result: CFormValidator.ERROR};
		}

		if ('field' in rules) {
			const normalized_values = [];

			for (let i = 0; array_values.length > i; i++) {
				const {result, error, value = array_values} = this.#validateField(rules.field, array_values, i,
					path + '/' + i
				);

				if (result === CFormValidator.ERROR) {
					error && this.#addError(path, error, CFormValidator.ERROR_LEVEL_PRIMARY);

					return {result: CFormValidator.ERROR};
				}
				else {
					normalized_values.push(value[i]);
				}
			}
			array_values = normalized_values;
		}

		return {result: CFormValidator.SUCCESS, value: array_values};
	}

	/**
	 * Check identifier value.
	 *
	 * @param {any} value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#isTypeId(value) {
		return /^\d+$/.test(value);
	}

	/**
	 * Check integer value.
	 *
	 * @param {any} value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#isTypeInt32(value) {
		if (String(value).match(/^[-]?\d+$/) === null) {
			return false;
		}

		value = parseInt(value);

		return !isNaN(value) && value >= -2147483648 && value <= 2147483647;
	}

	/**
	 * Floating point number validator.
	 *
	 * @param {any} value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#isTypeFloat(value) {
		return isFinite(value);
	}

	/**
	 * Check if value is JS object.
	 *
	 * @param {any} value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#isTypeObject(value) {
		return typeof value === 'object' && !Array.isArray(value) && value !== null;
	}

	/**
	 * Check if given value matches one of values inside $rules['in'].
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#checkNumericIn(rules, value) {
		if (!('in' in rules)) {
			return true;
		}

		return rules['in'].some((allowed) => {
			return Array.isArray(allowed) ? this.#isInRange(value, allowed) : allowed == value;
		});
	}

	/**
	 * Check if given value is not one of values inside $rules['not_in'].
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#checkNumericNotIn(rules, value) {
		if (!('not_in' in rules)) {
			return true;
		}

		return rules['not_in'].every((disallowed) => {
			return Array.isArray(disallowed) ? !this.#isInRange(value, disallowed) : disallowed != value;
		});
	}

	/**
	 * Check if given value is inside given range(s).
	 *
	 * @param {number} value     Value to validate.
	 * @param {Object} in_rules  Normalized "in" condition.
	 *
	 * @returns {boolean}
	 */
	#isInRange(value, in_rules) {
		const [from, to] = in_rules;

		if (from === null) {
			return value <= to;
		}
		else if (to === null) {
			return from <= value;
		}
		else {
			return from <= value && value <= to;
		}
	}

	/**
	 * Check if given value matches one of values inside $rules['in'].
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#checkStringIn(rules, value) {
		if (!('in' in rules)) {
			return true;
		}

		return rules['in'].includes(value);
	}

	/**
	 * Check if given value is not one of values inside $rules['not_in'].
	 *
	 * @param {Object} rules  Ruleset to use for validation.
	 * @param {any}    value  Value to validate.
	 *
	 * @returns {boolean}
	 */
	#checkStringNotIn(rules, value) {
		if (!('not_in' in rules)) {
			return true;
		}

		return !rules['not_in'].includes(value);
	}

	/**
	 * Check if value looks as user macro.
	 *
	 * @param {string} value  Value to check.
	 *
	 * @returns {boolean}
	 */
	#isUserMacro(value) {
		return value.match(/^\{\$[A-Z0-9._]+(:.*)?\}$/) !== null;
	}

	/**
	 * Calculate result of 'when' conditions.
	 *
	 * @param {Array}  when_rules  Array of 'when' condition objects.
	 * @param {string} field_path  Absolute path of field.
	 *
	 * @returns {boolean}
	 */
	#testWhenCondition(when_rules, field_path) {
		return when_rules.every(when => {
			const {0: when_field_name, ...when_conditions} = when;
			const when_field = this.#getWhenFieldValue(when_field_name, field_path);

			return this.#checkValue({type: when_field.type, ...when_conditions}, when_field);
		});
	}

	/**
	 * Find the value and type for field that is referred in 'when' condition.
	 *
	 * @param {string} when_field  Field name (or relative path) that is defined in 'when' condition.
	 * @param {string} field_path  Field path which has 'when' condition defined.
	 *
	 * @returns {object}  Returns object containing type and value for requested field.
	 */
	#getWhenFieldValue(when_field, field_path) {
		const target_path = this.#getFieldAbsolutePath(when_field, field_path);

		return (target_path in this.#when_fields) ? this.#when_fields[target_path] : {'type': null};
	}

	/**
	 * Calculate field absolute path using its name and path where it is defined in roles.
	 *
	 * @param {string} field_name  Target field name, e.g., 'host'.
	 * @param {string} field_path  Path where field is defined in rules.
	 *
	 * @returns {string}
	 */
	#getFieldAbsolutePath(field_name, field_path) {
		const target_path = [...field_path.split('/').slice(0, -1), field_name];

		return `/${target_path.join('/')}`.replace(/\/\/+/g, '/');
	}

	/**
	 * Check if given value matches given rules. This function might look similar to #validateField.
	 *
	 * @param {Object} rule_set  Ruleset of validated field.
	 * @param {Object} data      Value to validate.
	 *
	 * @returns {boolean}
	 */
	#checkValue(rule_set, data) {
		if ('exist' in rule_set) {
			return data.value !== null;
		}

		if ('not_exist' in rule_set) {
			return data.value === null;
		}

		switch (data.type) {
			case 'id':
				return this.#validateId(rule_set, data.value)['result'] == CFormValidator.SUCCESS;

			case 'integer':
				return this.#validateInt32(rule_set, data.value)['result'] == CFormValidator.SUCCESS;

			case 'float':
				return this.#validateFloat(rule_set, data.value)['result'] == CFormValidator.SUCCESS;

			case 'string':
				return this.#validateStringUtf8(rule_set, data.value)['result'] == CFormValidator.SUCCESS;

			case 'objects':
			case 'object':
			case 'array':
				if ('not_empty' in rule_set) {
					return data.value === true;
				}
				else if ('empty' in rule_set) {
					return !('value' in data) || data.value === false;
				}

				return true;

			default:
				return false;
		}
	}

	/**
	 * Check if given set of objects are unique in given set of values according the 'uniq' rules.
	 *
	 * @param {Object} data_all  Whole data array.
	 * @param {Object} rules     Rules to use for validation.
	 *
	 * @returns {boolean}
	 */
	#validateDistinctness(data_all, rules) {
		const getValueByPath = (field_path) => {
			let data = data_all;

			for (const part of field_path.split('/').slice(1)) {
				if (!this.#isTypeObject(data) || !(part in data)) {
					return null;
				}

				data = data[part];
			}

			return data;
		}

		const areObjectsEqual = (a, b) => {
			if (Object.keys(a).length != Object.keys(b).length) {
				return false;
			}

			return Object.keys(a).every((key) => a[key] === b[key]);
		};

		const checkDistinctness = (field_names, objects_values, field_path, ruleset) => {
			const uniq_values = [];
			const values = {};

			let is_distinct = true;

			for (const [index, data] of Object.entries(objects_values)) {
				const data_new = {};

				for (const key in data) {
					if (field_names.includes(key)) {
						data_new[key] = data[key];
					}
				}

				let new_field_path = field_path + '/' + index + '/' + Object.keys(data_new)[0];

				this.#addPath(new_field_path, CFormValidator.ERROR_LEVEL_UNIQ);

				if (Object.values(data_new).every(value => value === null) === false) {
					values[new_field_path] = data_new;
				}
			}

			for (const [field_path, data] of Object.entries(values)) {
				const has_equal = uniq_values.some(uniq_entry => {
					if (!areObjectsEqual(data, uniq_entry)) {
						return false;
					}

					const vals = [];

					for (const [key, value] of Object.entries(data)) {
						vals.push(key + '=' + value);
					}

					const error_msg = this.#getMessage(ruleset, 'uniq',
						sprintf(t('Entry "%1$s" is not unique.'), vals.join(', '))
					);

					this.#addError(field_path, error_msg, CFormValidator.ERROR_LEVEL_UNIQ);

					return true;
				});

				if (!has_equal) {
					uniq_values.push(data);
				}
				else {
					is_distinct = false;
				}
			}

			return is_distinct;
		};

		const scan = (ruleset, path) => {
			const object_values = getValueByPath(path);

			if (!this.#isTypeObject(object_values)) {
				return true;
			}

			if ('uniq' in ruleset) {
				for (const field_names of [...ruleset.uniq]) {
					if (!checkDistinctness(field_names, object_values, path, ruleset)) {
						return false;
					}
				}
			}

			let is_distinct = true;

			if ('fields' in ruleset) {
				for (const [field_name, child_rulesets] of Object.entries(ruleset.fields)) {
					for (const child_ruleset of child_rulesets) {
						if (!scan(child_ruleset, path + '/' + field_name)) {
							is_distinct = false;
						}
					}
				}
			}

			return is_distinct;
		};

		return scan(rules, '');
	}
}
