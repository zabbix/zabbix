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


/**
 * @var CView $this
 */
?>

/**
 * Make step result UI element.
 *
 * @param {array} step  Step object returned from server.
 *
 * @return {jQuery}
 */
function makeStepResult(step) {
	if (step.error !== undefined) {
		return jQuery(new Template(jQuery('#preprocessing-step-error-icon').html()).evaluate(
			{error: escapeHtml(step.error) || <?= json_encode(htmlspecialchars(_('<empty string>'))) ?>}
		));
	}

	if (step.result === undefined || step.result === null) {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= json_encode(_('No value')) ?>);
	}
	else if (step.result === '') {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= json_encode(_('<empty string>')) ?>);
	}
	else if (step.warning !== undefined) {
		return jQuery(new Template(jQuery('#preprocessing-step-result-warning').html()).evaluate(
			{result: step.result, result_hint: escapeHtml(step.result), warning: step.warning}
		));
	}
	else if (step.result.indexOf("\n") != -1 || step.result.length > 25) {
		return jQuery(new Template(jQuery('#preprocessing-step-result').html()).evaluate(
			{result: step.result, result_hint: escapeHtml(step.result)}
		));
	}
	else {
		return jQuery('<span>').text(step.result);
	}
}

/**
 * Disable item test form.
 */
function disableItemTestForm() {
	jQuery('#value, #time, [name^=macros]').prop('disabled', true);

	<?php if ($data['is_item_testable']): ?>
		jQuery('#get_value, #get_value_btn').prop('disabled', true);

		<?php if ($data['interface_address_enabled']): ?>
			jQuery('#interface_address').prop('disabled', true);
		<?php endif ?>

		<?php if ($data['interface_port_enabled']): ?>
			jQuery('#interface_port').prop('disabled', true);
		<?php endif ?>

		<?php if ($data['proxies_enabled']): ?>
			for (const element of document.querySelectorAll('#test_with input')) {
				element.disabled = true;
			}

			jQuery('#proxyid').multiSelect('disable');
		<?php endif ?>

	<?php else: ?>
		jQuery('#get_value, #get_value_btn').prop('disabled', true);
	<?php endif ?>

	<?php if ($data['show_prev']): ?>
		jQuery('#prev_time').prop('readonly', true);
	<?php endif ?>

	jQuery('#eol input').prop('disabled', true);
}

/**
 * Enable item test form.
 */
function enableItemTestForm() {
	jQuery('#value, #time, [name^=macros]').prop('disabled', false);

	<?php if ($data['is_item_testable']): ?>
		jQuery('#get_value, #get_value_btn').prop('disabled', false);

		<?php if ($data['interface_address_enabled']): ?>
			jQuery('#interface_address').prop('disabled', false);
		<?php endif ?>

		<?php if ($data['interface_port_enabled']): ?>
			jQuery('#interface_port').prop('disabled', false);
		<?php endif ?>

		<?php if ($data['proxies_enabled']): ?>
			for (const element of document.querySelectorAll('#test_with input')) {
				element.disabled = false;
			}

			jQuery('#proxyid').multiSelect('enable');
		<?php endif ?>

	<?php else: ?>
		jQuery('#get_value, #get_value_btn').prop('disabled', false);
	<?php endif ?>

	<?php if ($data['show_prev']): ?>
		if (!jQuery('#get_value').is(':checked')) {
			jQuery('#prev_value').multilineInput('unsetReadOnly');
			jQuery('#prev_time').prop('readonly', false);
		}
	<?php endif ?>

	jQuery('#eol input').prop('disabled', false);
}

/**
 * Clear previous test results.
 */
function cleanPreviousTestResults() {
	var $form = jQuery('#preprocessing-test-form');

	jQuery('[id^="preproc-test-step-"][id$="-result"]', $form).empty();
	jQuery('[id^="preproc-test-step-"][id$="-name"] > div', $form).remove();
	jQuery('.js-final-result', $form)
		.hide()
		.next()
		.empty()
		.hide();
}

/**
 * Send item get value request and display retrieved results.
 *
 * @param {object} overlay  Overlay dialog object.
 */
function itemGetValueTest(overlay) {
	var $body = overlay.$dialogue.$body,
		$form = overlay.$dialogue.find('form'),
		form_data = $form.serializeJSON(),
		post_data = getItemTestProperties('#preprocessing-test-form'),
		interface = (typeof form_data['interface'] !== 'undefined') ? form_data['interface'] : null,
		url = new Curl('zabbix.php');

	url.setArgument('action', 'popup.itemtest.getvalue');
	url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('itemtest')) ?>);

	post_data = jQuery.extend(post_data, {
		interface: {
			address: interface ? interface['address'].trim() : '',
			port: (interface && interface['port']) ? interface['port'].trim() : '',
			interfaceid: interface ? interface['interfaceid'] : null,
			useip: interface ? interface['useip'] : null,
			details: interface ? interface['details'] : null
		},
		macros: form_data['macros'],
		test_with: form_data['test_with'],
		proxyid: form_data['proxyid'],
		test_type: <?= $data['test_type'] ?>,
		hostid: <?= $data['hostid'] ?>,
		value: form_data['value']
	});

	<?php if ($data['show_prev']): ?>
		post_data['time_change'] = (form_data['upd_prev'] !== '')
			? parseInt(form_data['upd_last']) - parseInt(form_data['upd_prev'])
			: Math.ceil(+new Date() / 1000) - parseInt(form_data['upd_last']);
	<?php endif ?>

	delete post_data.interfaceid;
	delete post_data.delay;

	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: post_data,
		beforeSend: function() {
			jQuery('#get_value_btn').blur().addClass('is-loading');
			overlay.setLoading();
			disableItemTestForm();
			cleanPreviousTestResults();
		},
		complete: function() {
			jQuery('#get_value_btn').removeClass('is-loading');
			enableItemTestForm();
			overlay.unsetLoading();
		},
		success: function(ret) {
			overlay.$dialogue.find('.msg-bad, .msg-good, .msg-warning').remove();

			if ('error' in ret) {
				const message_box = makeMessageBox('bad', ret.error.messages, ret.error.title);

				jQuery($body).prepend(message_box);

				return;
			}

			<?php if ($data['show_prev']): ?>
				if (typeof ret.prev_value !== 'undefined') {
					jQuery('#prev_value', $form).multilineInput('value', ret.prev_value);
					jQuery('#prev_time', $form).val(ret.prev_time);
					jQuery('#upd_prev', $form).val(form_data['upd_last']);
					jQuery('#upd_last', $form).val(Math.ceil(+new Date() / 1000));
				}
			<?php endif ?>

			jQuery('#value', $form).multilineInput('value', ret.value);
			jQuery('#value_warning', $form)
				.toggle('value_warning' in ret)
				.toggleClass('js-retrieved', 'value_warning' in ret)
				.attr('data-hintbox-contents', ret.value_warning);

			if (typeof ret.eol !== 'undefined') {
				jQuery("input[value=" + ret.eol + "]", jQuery("#eol")).prop("checked", "checked");
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Send item preprocessing test details and display results in table.
 *
 * @param {object} overlay  Overlay dialog object.
 */
function itemCompleteTest(overlay) {
	var $body = overlay.$dialogue.$body,
		$form = overlay.$dialogue.find('form'),
		form_data = $form.serializeJSON(),
		post_data = getItemTestProperties('#preprocessing-test-form'),
		interface = (typeof form_data['interface'] !== 'undefined') ? form_data['interface'] : null,
		url = new Curl('zabbix.php');

	url.setArgument('action', 'popup.itemtest.send');
	url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('itemtest')) ?>);

	post_data = jQuery.extend(post_data, {
		get_value: form_data['get_value'] || 0,
		steps: form_data['steps'],
		interface: {
			address: interface ? interface['address'].trim() : '',
			port: (interface && interface['port']) ? interface['port'].trim() : '',
			interfaceid: interface ? interface['interfaceid'] : null,
			useip: interface ? interface['useip'] : null,
			details: interface ? interface['details'] : null
		},
		macros: form_data['macros'],
		test_with: form_data['test_with'],
		proxyid: form_data['proxyid'],
		show_final_result: <?= $data['show_final_result'] ? 1 : 0 ?>,
		test_type: <?= $data['test_type'] ?>,
		hostid: <?= $data['hostid'] ?>,
		valuemapid: <?= $data['valuemapid'] ?>,
		value: form_data['value'],
		not_supported: form_data['not_supported'],
		runtime_error: form_data['runtime_error']
	});

	<?php if ($data['show_prev']): ?>
		if (post_data.get_value) {
			post_data['time_change'] = (form_data['upd_prev'] !== '')
				? parseInt(form_data['upd_last']) - parseInt(form_data['upd_prev'])
				: Math.ceil(+new Date() / 1000) - parseInt(form_data['upd_last']);
		}

		post_data = jQuery.extend(post_data, {
			prev_time: form_data['prev_time'],
			prev_value: form_data['prev_value']
		});
	<?php endif ?>

	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: post_data,
		beforeSend: function() {
			overlay.setLoading();
			disableItemTestForm();
			cleanPreviousTestResults();
		},
		complete: function() {
			enableItemTestForm();
			overlay.unsetLoading();
		},
		success: function(ret) {
			overlay.$dialogue.find('.msg-bad, .msg-good, .msg-warning').remove();

			if ('error' in ret) {
				const message_box = makeMessageBox('bad', ret.error.messages, ret.error.title);

				jQuery($body).prepend(message_box);
			}

			processItemPreprocessingTestResults(ret.steps ?? []);

			<?php if ($data['show_prev']): ?>
				if (typeof ret.prev_value !== 'undefined') {
					jQuery('#prev_value', $form).multilineInput('value', ret.prev_value);
					jQuery('#prev_time', $form).val(ret.prev_time);
					jQuery('#upd_prev', $form).val(post_data['upd_last']);
					jQuery('#upd_last', $form).val(Math.ceil(+new Date() / 1000));
				}
			<?php endif ?>

			if ('not_supported' in ret && jQuery('[name="not_supported"]', $form).length) {
				jQuery('[name="not_supported"]', $form)
					.prop('checked', ret.not_supported != 0)
					.trigger('change');
			}

			jQuery('#value', $form).multilineInput('value', ret.value);
			jQuery('#value_warning', $form)
				.toggle('value_warning' in ret)
				.toggleClass('js-retrieved', 'value_warning' in ret)
				.attr('data-hintbox-contents', ret.value_warning);

			if ('runtime_error' in ret && jQuery('#runtime_error', $form).length) {
				jQuery('#runtime_error', $form).multilineInput('value', ret.runtime_error);
			}

			if (typeof ret.eol !== 'undefined') {
				jQuery("input[value=" + ret.eol + "]", jQuery("#eol")).prop("checked", "checked");
			}

			if (typeof ret.final !== 'undefined') {
				var result = makeStepResult(ret.final);

				if (result !== null) {
					$result = result.css('float', 'right');
				}

				$result_row = jQuery('<div>', {'class': '<?= ZBX_STYLE_TABLE_FORMS_SEPARATOR ?>'})
					.css({whiteSpace: 'normal'})
					.append(jQuery('<div>').append(ret.final.action, $result))
					.css({display: 'block', width: '675px'});

				if (typeof ret.mapped_value !== 'undefined') {
					$mapped_value = makeStepResult({result: ret.mapped_value});
					$mapped_value.css('float', 'right');

					$result_row.append(jQuery('<div>')
						.append(
							jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'})
								.text(<?= json_encode(_('Result with value map applied')) ?>),
							$mapped_value
						)
					);
				}

				jQuery('.js-final-result')
					.show()
					.next()
					.append($result_row)
					.show();
			}
		},
		dataType: 'json',
		type: 'post'
	});

	return false;
}

/**
 * Process test results and make visual changes in test dialog results block.
 *
 * @param {array} steps  Array of objects containing details about each preprocessing step test results.
 */
function processItemPreprocessingTestResults(steps) {
	var tmpl_gray_label = new Template(jQuery('#preprocessing-gray-label').html()),
		tmpl_act_done = new Template(jQuery('#preprocessing-step-action-done').html());

	steps.forEach(function(step, i) {
		if (typeof step.action !== 'undefined') {
			switch (step.action) {
				case <?= ZBX_PREPROC_FAIL_DEFAULT ?>:
					step.action = null;
					break;

				case <?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>:
					step.action = jQuery(tmpl_gray_label.evaluate(<?= json_encode([
						'label' => _('Discard value')
					]) ?>));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_VALUE ?>:
					step.result = step.result === '' ? <?= json_encode(_('<empty string>')) ?> : step.result;
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= json_encode([
						'action_name' => _('Set value to')
					]) ?>, {failed: step.result, failed_hint: escapeHtml(step.result)})));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_ERROR ?>:
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= json_encode([
						'action_name' => _('Set error to')
					]) ?>, {failed: step.failed, failed_hint: escapeHtml(step.failed)})));
					break;
			}
		}

		step.result = makeStepResult(step);

		if (typeof step.action !== 'undefined' && step.action !== null) {
			jQuery('#preproc-test-step-' + i + '-name').append(jQuery(tmpl_gray_label.evaluate(<?= json_encode([
				'label' => _('Custom on fail')
			]) ?>)));
		}

		jQuery('#preproc-test-step-' + i + '-result').append(step.result, step.action);
	});
}

/**
 * Collect values from opened item test dialog and save input values for repeated use.
 */
function saveItemTestInputs() {
	var $form = jQuery('#preprocessing-test-form'),
		$test_obj,
		input_values = {
			value: jQuery('#value').multilineInput('value'),
			not_supported: jQuery('[name="not_supported"]').is(':checked') ? 1 : 0,
			eol: jQuery('#eol').find(':checked').val()
		},
		form_data = $form.serializeJSON(),
		interface = (typeof form_data['interface'] !== 'undefined') ? form_data['interface'] : null,
		macros = {};

	<?php if ($data['is_item_testable']): ?>
		if (jQuery('#runtime_error').length) {
			input_values.runtime_error = jQuery('#runtime_error').multilineInput('value');
		}

		const test_with = $form[0].querySelector('[name="test_with"]:checked').value;
		const proxyid = jQuery('#proxyid', $form).multiSelect('getData').map((proxy) => proxy.id)[0] || 0;

		input_values = jQuery.extend(input_values, {
			get_value: jQuery('#get_value', $form).is(':checked') ? 1 : 0,
			test_with,
			proxyid: test_with == <?= CControllerPopupItemTest::TEST_WITH_PROXY ?> ? proxyid : 0,
			interfaceid: <?= $data['interfaceid'] ?> || 0,
			address: jQuery('#interface_address', $form).val(),
			port: jQuery('#interface_port', $form).val(),
			interface_details: (interface && 'details' in interface) ? interface['details'] : null
		});
	<?php endif ?>

	<?php if ($data['show_prev']): ?>
		input_values = jQuery.extend(input_values, {
			prev_value: jQuery('#prev_value').multilineInput('value'),
			prev_time: jQuery('#prev_time').val()
		});
	<?php endif ?>

	jQuery('[name^=macros]').each(function(i, macro) {
		var name = macro.name.toString();
		macros[name.substr(7, name.length - 8)] = macro.value;
	});
	input_values.macros = macros;

	<?php if ($data['step_obj'] == -2): ?>
		$test_obj = jQuery('.overlay-dialogue-footer');
	<?php elseif ($data['step_obj'] == -1): ?>
		$test_obj = jQuery('.preprocessing-list-foot', jQuery('#preprocessing'));
	<?php else: ?>
		$test_obj = jQuery('.preprocessing-list-item[data-step=<?= $data['step_obj'] ?>]', jQuery('#preprocessing'));
	<?php endif ?>

	$test_obj.data('test-data', input_values);
}

jQuery(document).ready(function($) {
	$('.js-final-result').hide().next().hide();

	<?php if ($data['show_prev']): ?>
		jQuery('#upd_last').val(Math.ceil(+new Date() / 1000));
	<?php endif ?>

	$('#value').multilineInput({
		placeholder: <?= json_encode(_('value')) ?>,
		value: <?= json_encode($data['value']) ?>,
		monospace_font: false,
		autofocus: true,
		readonly: <?= $data['not_supported'] != 0 ? 'true' : 'false' ?>,
		grow: 'auto',
		rows: 0
	});

	$('#runtime_error').length && $('#runtime_error').multilineInput({
		placeholder: <?= json_encode(_('error text')) ?>,
		value: <?= json_encode($data['runtime_error']) ?>,
		monospace_font: false,
		autofocus: true,
		readonly: <?= $data['not_supported'] != 0 ? 'false' : 'true' ?>,
		grow: 'auto',
		rows: 0
	});

	$('#prev_value').multilineInput({
		placeholder: <?= $data['show_prev'] ? json_encode(_('value')) : '""' ?>,
		value: <?= json_encode($data['prev_value']) ?>,
		monospace_font: false,
		disabled: <?= $data['show_prev'] ? 'false' : 'true' ?>,
		grow: 'auto',
		rows: 0
	});

	$('#not_supported').on('change', function() {
		const $form = $('#preprocessing-test-form');

		$('#value', $form).multilineInput(this.checked ? 'setReadOnly' : 'unsetReadOnly');
		$('#runtime_error', $form).length && $('#runtime_error', $form).multilineInput(
			this.checked && !$('[name="get_value"]', $form).is(':checked') ? 'unsetReadOnly' : 'setReadOnly'
		);
	});

	<?php if ($data['is_item_testable']): ?>
		$('#proxyid').multiSelect();

		document.getElementById('test_with').addEventListener('change', (e) => {
			document.querySelector('.js-test-with-proxy').style.display =
				e.target.value == <?= CControllerPopupItemTest::TEST_WITH_SERVER ?> ? 'none' : '';
		});

		$('#get_value').on('change', function() {
			var $rows = $('.js-host-address-row, .js-test-with-row, .js-get-value-row, [class*=js-popup-row-snmp]'),
				$form = $('#preprocessing-test-form'),
				$submit_btn = overlays_stack.getById('item-test').$btn_submit,
				$not_supported = $('[name="not_supported"]', $form);

			if ($(this).is(':checked')) {
				$('#value', $form).multilineInput('setReadOnly');
				$('#value_warning.js-retrieved').show();

				$not_supported.prop('disabled', true);
				$('#runtime_error').length && $('#runtime_error', $form).multilineInput('setReadOnly');

				<?php if ($data['show_prev']): ?>
					$('#prev_value', $form).multilineInput('setReadOnly');
					$('#prev_time', $form).prop('readonly', true);
				<?php endif ?>

				<?php if ($data['proxies_enabled']): ?>
					for (const element of document.querySelectorAll('#test_with input')) {
						element.disabled = false;
					}

					$('#proxyid').multiSelect('enable');
				<?php endif ?>

				<?php if ($data['interface_address_enabled']): ?>
					$('#interface_address').prop('disabled', false);
				<?php endif ?>

				<?php if ($data['interface_port_enabled']): ?>
					$('#interface_port').prop('disabled', false);
				<?php endif ?>

				$submit_btn.html(<?= json_encode(_('Get value and test')) ?>);
				$rows.show();

				<?php if ($data['show_snmp_form']): ?>
					$('#interface_details_version').on('change', function (e) {
						$(`.js-popup-row-snmp-community, .js-popup-row-snmp-max-repetition,
							.js-popup-row-snmpv3-contextname, .js-popup-row-snmpv3-securityname,
							.js-popup-row-snmpv3-securitylevel, .js-popup-row-snmpv3-authprotocol,
							.js-popup-row-snmpv3-authpassphrase, .js-popup-row-snmpv3-privprotocol,
							.js-popup-row-snmpv3-privpassphrase`).hide();

						switch (e.target.value) {
							case '<?= SNMP_V1 ?>':
								$('#interface_details_securitylevel').off('change');
								$('.js-popup-row-snmp-community').show();
								break;
							case '<?= SNMP_V2C ?>':
								$('#interface_details_securitylevel').off('change');
								$('.js-popup-row-snmp-community').show();
								$('.js-popup-row-snmp-max-repetition').show();
								break;
							case '<?= SNMP_V3 ?>':
								$(`.js-popup-row-snmpv3-contextname, .js-popup-row-snmpv3-securityname,
									.js-popup-row-snmpv3-securitylevel, .js-popup-row-snmp-max-repetition`).show();

								$('#interface_details_securitylevel').on('change', function (e) {
									$(`.js-popup-row-snmpv3-authprotocol, .js-popup-row-snmpv3-authpassphrase,
										.js-popup-row-snmpv3-privprotocol, .js-popup-row-snmpv3-privpassphrase`).hide();
									switch (e.target.value) {
										case '<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV ?>':
											$(`.js-popup-row-snmpv3-authprotocol, .js-popup-row-snmpv3-authpassphrase`)
												.show();
											break;
										case '<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV ?>':
											$(`.js-popup-row-snmpv3-authprotocol, .js-popup-row-snmpv3-authpassphrase,
												.js-popup-row-snmpv3-privprotocol, .js-popup-row-snmpv3-privpassphrase`)
												.show();
											break;
									}

									overlays_stack.end().centerDialog();
								}).trigger('change');
								break;
						}

						overlays_stack.end().centerDialog();
					}).trigger('change');
				<?php endif ?>
			}
			else {
				if ($not_supported.length) {
					$not_supported
						.prop('disabled', false)
						.trigger('change');
				}
				else {
					$('#value', $form).multilineInput('unsetReadOnly');
				}
				$('#value_warning').hide();

				<?php if ($data['show_prev']): ?>
					$('#prev_value', $form).multilineInput('unsetReadOnly');
					$('#prev_time', $form).prop('readonly', false);
				<?php endif ?>

				<?php if ($data['proxies_enabled']): ?>
					for (const element of document.querySelectorAll('#test_with input')) {
						element.disabled = true;
					}

					$('#proxyid').multiSelect('disable');
				<?php endif ?>

				<?php if ($data['interface_address_enabled']): ?>
					$('#interface_address').prop('disabled', false);
				<?php endif ?>

				<?php if ($data['interface_port_enabled']): ?>
					$('#interface_port').prop('disabled', false);
				<?php endif ?>

				$submit_btn.html(<?= json_encode(_('Test')) ?>);
				$rows.hide();
			}
		}).trigger('change');

		$('#get_value_btn').on('click', function() {
			itemGetValueTest(overlays_stack.getById('item-test'));
		});
	<?php endif ?>

	$('#preprocessing-test-form .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
});
