<?php
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


?>
<script type="text/javascript">
	/**
	 * Collect current preprocessing step properties.
	 *
	 * @param {array}  step_nums  List of step numbers to collect.
	 *
	 * @return array
	 */
	function getPreprocessingSteps(step_nums) {
		var $preprocessing = jQuery('#preprocessing'),
			steps = [];

		step_nums.forEach(function(num) {
			var type = jQuery('[name="preprocessing[' + num + '][type]"]', $preprocessing).val(),
				error_handler = jQuery('[name="preprocessing[' + num + '][on_fail]"]').is(':checked')
					? jQuery('[name="preprocessing[' + num + '][error_handler]"]').val()
					: <?= ZBX_PREPROC_FAIL_DEFAULT ?>,
				params = [];

			var on_fail = {
				error_handler: error_handler,
				error_handler_params: (error_handler == <?= ZBX_PREPROC_FAIL_SET_VALUE ?>
						|| error_handler == <?= ZBX_PREPROC_FAIL_SET_ERROR ?>)
					? jQuery('[name="preprocessing[' + num + '][error_handler_params]"]').val()
					: ''
			};

			if (type == <?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>) {
				const inputs = document.querySelectorAll(`.group-json-mapping[data-index="${num}"] input`);

				[...inputs].map((input) => params.push(input.value));
			} else {
				if (jQuery('[name="preprocessing[' + num + '][params][0]"]', $preprocessing).length) {
					params.push(jQuery('[name="preprocessing[' + num + '][params][0]"]', $preprocessing).val());
				}
				if (jQuery('[name="preprocessing[' + num + '][params][1]"]', $preprocessing).length) {
					params.push(jQuery('[name="preprocessing[' + num + '][params][1]"]', $preprocessing).val());
				}
				if (jQuery('[name="preprocessing[' + num + '][params][2]"]:not(:disabled)', $preprocessing).length) {
					if (type == <?= ZBX_PREPROC_CSV_TO_JSON ?>) {
						if (jQuery('[name="preprocessing[' + num + '][params][2]"]', $preprocessing).is(':checked')) {
							params.push(jQuery('[name="preprocessing[' + num + '][params][2]"]', $preprocessing).val());
						}
					}
					else {
						params.push(jQuery('[name="preprocessing[' + num + '][params][2]"]', $preprocessing).val());
					}
				}
			}

			steps.push(jQuery.extend({
				type: type,
				params: params
			}, on_fail));
		});

		return steps;
	}

	/**
	 * Collect item properties based on it's type.
	 *
	 * @param {string}  form_selector    Form selector.
	 *
	 * @return object
	 */
	function getItemTestProperties(form_selector) {
		var $form = jQuery(form_selector),
			form_data,
			properties = {};

		// Form must be enabled at moment when values are collected.
		if (jQuery('#key').prop('readonly')) {
			$form = $form.clone();
			jQuery(':disabled', $form).removeAttr('disabled');
		}

		form_data = $form.serializeJSON();
		delete $form;

		const timeout = 'custom_timeout' in form_data
				&& form_data['custom_timeout'] != <?= ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED ?>
			? form_data['inherited_timeout']
			: form_data['timeout'];

		// Item type specific properties.
		switch (+form_data['type']) {
			case <?= ITEM_TYPE_ZABBIX ?>:
				properties = {
					key: form_data['key'].trim(),
					timeout
				};
				break;

			case <?= ITEM_TYPE_SIMPLE ?>:
				properties = {
					key: form_data['key'].trim(),
					username: form_data['username'],
					password: form_data['password'],
					timeout
				};
				break;

			case <?= ITEM_TYPE_SNMP ?>:
				properties = {
					snmp_oid: form_data['snmp_oid'],
					timeout,
					flags: form_data['flags']
				};
				break;

			case <?= ITEM_TYPE_INTERNAL ?>:
				properties = {
					key: form_data['key'].trim()
				};
				break;

			case <?= ITEM_TYPE_EXTERNAL ?>:
				properties = {
					key: form_data['key'].trim(),
					timeout
				};
				break;

			case <?= ITEM_TYPE_DB_MONITOR ?>:
				properties = {
					key: form_data['key'].trim(),
					params_ap: form_data['params_ap'],
					username: form_data['username'],
					password: form_data['password'],
					timeout
				};
				break;

			case <?= ITEM_TYPE_HTTPAGENT ?>:
				properties = {
					key: form_data['key'].trim(),
					http_authtype: form_data['http_authtype'],
					follow_redirects: form_data['follow_redirects'] || 0,
					headers: form_data['headers'],
					http_proxy: form_data['http_proxy'],
					output_format: form_data['output_format'] || 0,
					posts: form_data['posts'],
					post_type: form_data['post_type'],
					query_fields: form_data['query_fields'],
					request_method: form_data['request_method'],
					retrieve_mode: form_data['retrieve_mode'],
					ssl_cert_file: form_data['ssl_cert_file'],
					ssl_key_file: form_data['ssl_key_file'],
					ssl_key_password: form_data['ssl_key_password'],
					status_codes: form_data['status_codes'],
					timeout,
					url: form_data['url'],
					verify_host: form_data['verify_host'] || 0,
					verify_peer: form_data['verify_peer'] || 0
				};

				if (properties.authtype != <?= ZBX_HTTP_AUTH_NONE ?>) {
					properties = jQuery.extend(properties, {
						http_username: form_data['http_username'],
						http_password: form_data['http_password']
					});
				}
				break;

			case <?= ITEM_TYPE_IPMI ?>:
				properties = {
					key: form_data['key'].trim(),
					ipmi_sensor: form_data['ipmi_sensor']
				};
				break;

			case <?= ITEM_TYPE_SSH ?>:
				properties = {
					key: form_data['key'].trim(),
					authtype: form_data['authtype'],
					params_es: form_data['params_es'],
					username: form_data['username'],
					password: form_data['password'],
					timeout
				};

				if (properties.authtype == <?= ITEM_AUTHTYPE_PUBLICKEY ?>) {
					properties = jQuery.extend(properties, {
						publickey: form_data['publickey'],
						privatekey: form_data['privatekey']
					});
				}
				break;

			case <?= ITEM_TYPE_TELNET ?>:
				properties = {
					key: form_data['key'].trim(),
					params_es: form_data['params_es'],
					username: form_data['username'],
					password: form_data['password'],
					timeout
				};
				break;

			case <?= ITEM_TYPE_JMX ?>:
				properties = {
					key: form_data['key'].trim(),
					jmx_endpoint: form_data['jmx_endpoint'],
					username: form_data['username'],
					password: form_data['password']
				};
				break;

			case <?= ITEM_TYPE_CALCULATED ?>:
				properties = {
					key: form_data['key'].trim(),
					params_f: form_data['params_f'],
				};
				break;

			case <?= ITEM_TYPE_SCRIPT ?>:
				properties = {
					key: form_data['key'].trim(),
					parameters: form_data['parameters'],
					script: form_data['script'],
					timeout
				};
				break;

			case <?= ITEM_TYPE_BROWSER ?>:
				properties = {
					key: form_data['key'].trim(),
					parameters: form_data['parameters'],
					browser_script: form_data['browser_script'],
					timeout
				};
				break;
		}

		// Common properties.
		return jQuery.extend(properties, {
			delay: form_data['delay'] || '',
			value_type: form_data['value_type'] || <?= CControllerPopupItemTest::ZBX_DEFAULT_VALUE_TYPE ?>,
			item_type: form_data['type'],
			itemid: <?= array_key_exists('itemid', $data) ? (int) $data['itemid'] : 0 ?>,
			valuemapid: form_data['valuemapid'],
			interfaceid: form_data['interfaceid'] || 0
		});
	}

	/**
	 * Creates item test modal dialog.
	 *
	 * @param {array} step_nums          List of step numbers to collect.
	 * @param {bool}  show_final_result  Either the final result should be displayed.
	 * @param {bool}  get_value          Either to show 'get value from host' section.
	 * @param {Node}  trigger_element    UI element that triggered function.
	 * @param {int}   step_obj_nr        Value defines which 'test' button was pressed to open test item dialog:
	 *                                     - 'test' button in edit form footer (-2);
	 *                                     - 'test all' button in preprocessinf tab (-1);
	 *                                     - 'test' button to test single preprocessing step (step index).
	 */
	function openItemTestDialog(step_nums, show_final_result, get_value, trigger_element, step_obj_nr) {
		var $row = jQuery(trigger_element)
					.closest('.preprocessing-list-item, .preprocessing-list-foot, .overlay-dialogue-footer'),
			item_properties = getItemTestProperties('form[name="itemForm"]'),
			cached_values = $row.data('test-data') || [];

		if (cached_values.interfaceid != item_properties.interfaceid) {
			delete cached_values.interfaceid;
			delete cached_values.address;
			delete cached_values.port;
			delete cached_values.interface_details;
		}

		PopUp('popup.itemtest.edit', jQuery.extend(item_properties, {
			steps: getPreprocessingSteps(step_nums),
			hostid: <?= $data['hostid'] ?>,
			test_type: <?= $data['preprocessing_test_type'] ?>,
			step_obj: step_obj_nr,
			show_final_result: show_final_result ? 1 : 0,
			get_value: get_value ? 1 : 0,
			data: cached_values
		}), {dialogueid: 'item-test', dialogue_class: 'modal-popup-generic', trigger_element});
	}
</script>
