<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


ob_start(); ?>

/**
 * Make step result UI element.
 *
 * @param array step  Step object returned from server.
 *
 * @return jQuery
 */
function makeStepResult(step) {
	if (typeof step.error !== 'undefined') {
		return jQuery(new Template(jQuery('#preprocessing-step-error-icon').html()).evaluate(
			{error: step.error || <?= CJs::encodeJson(_('<empty string>')) ?>}
		));
	}
	else if (typeof step.result === 'undefined' || step.result === null) {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= CJs::encodeJson(_('No value')) ?>);
	}
	else if (step.result === '') {
		return jQuery('<span>', {'class': '<?= ZBX_STYLE_GREY ?>'}).text(<?= CJs::encodeJson(_('<empty string>')) ?>);
	}
	else if (step.result.indexOf("\n") != -1 || step.result.length > 25) {
		return jQuery(new Template(jQuery('#preprocessing-step-result').html()).evaluate(
			jQuery.extend({result: step.result})
		));
	}
	else {
		return jQuery('<span>').text(step.result);
	}
}

/**
 * Send item preprocessing test details and display results in table.
 *
 * @param string formid  Selector for form to send.
 */
function itemPreprocessingTest(form) {
	var form = jQuery(form),
		url = new Curl(jQuery(form).attr('action')),
		is_prev_enabled = <?= $data['show_prev'] ? 'true' : 'false' ?>;

	jQuery.ajax({
		url: url.getUrl(),
		data: jQuery(form).serialize(),
		beforeSend: function() {
			jQuery('#value, #time, [name^=macros]').prop('disabled', true);
			if (is_prev_enabled) {
				jQuery('#prev_value, #prev_time').prop('disabled', true);
			}

			jQuery('<span>')
				.addClass('preloader')
				.insertAfter(jQuery('.submit-test-btn'))
				.css({
					'display': 'inline-block',
					'margin': '0 10px -8px'
				});

			jQuery('.submit-test-btn')
				.prop('disabled', true)
				.hide();

			// Clean previous results.
			jQuery('[id^="preproc-test-step-"][id$="-result"]').empty();
			jQuery('[id^="preproc-test-step-"][id$="-name"] > div').remove();
			jQuery('#final-result')
				.hide()
				.find('.table-forms-td-right')
				.empty();
		},
		success: function(ret) {
			jQuery(form).parent().find('.msg-bad, .msg-good').remove();

			processItemPreprocessingTestResults(ret.steps);

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore(jQuery(form));
			}

			if (typeof ret.final !== 'undefined') {
				var result = makeStepResult(ret.final);
				if (result !== null) {
					$result = jQuery(result).css('float', 'right');
				}

				jQuery('#final-result')
					.show()
					.find('.table-forms-td-right')
						.append(ret.final.action)
						.append($result);
			}

			jQuery('#value, #time, [name^=macros]').prop('disabled', false);
			if (is_prev_enabled) {
				jQuery('#prev_value, #prev_time').prop('disabled', false);
			}

			jQuery('.preloader').remove();
			jQuery('.submit-test-btn')
				.prop('disabled', false)
				.show();
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Process test results and make visual changes in test dialog results block.
 *
 * @param array steps  Array of objects containing details about each preprocessing step test results.
 */
function processItemPreprocessingTestResults(steps) {
	var tmpl_gray_label = new Template(jQuery('#preprocessing-gray-label').html()),
		tmpl_act_done = new Template(jQuery('#preprocessing-step-action-done').html());

	steps.each(function(step, i) {
		if (typeof step.action !== 'undefined') {
			switch (step.action) {
				case <?= ZBX_PREPROC_FAIL_DEFAULT ?>:
					step.action = null;
					break;

				case <?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>:
					step.action = jQuery(tmpl_gray_label.evaluate(<?= CJs::encodeJson([
						'label' => _('Discard value')
					]) ?>));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_VALUE ?>:
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= CJs::encodeJson([
						'action_name' => _('Set value to')
					]) ?>, {failed: step.result})));
					break;

				case <?= ZBX_PREPROC_FAIL_SET_ERROR ?>:
					step.action = jQuery(tmpl_act_done.evaluate(jQuery.extend(<?= CJs::encodeJson([
						'action_name' => _('Set error to')
					]) ?>, {failed: step.failed})));
					break;
			}
		}

		step.result = makeStepResult(step);

		if (typeof step.action !== 'undefined' && step.action !== null) {
			jQuery('#preproc-test-step-'+i+'-name').append(jQuery(tmpl_gray_label.evaluate(<?= CJs::encodeJson([
				'label' => _('Custom on fail')
			]) ?>)));
		}

		jQuery('#preproc-test-step-'+i+'-result').append(step.result, step.action);
	});
}

/**
 * Collect values from opened preprocessing test dialog and save input values for repeated use.
 */
function savePreprocessingTestInputs() {
	var is_prev_enabled = <?= $data['show_prev'] ? 'true' : 'false' ?>,
		input_values = {
			value: jQuery('#value').multilineInput('value'),
			eol: jQuery('#eol').find(':checked').val()
		},
		macros = {};

	if (is_prev_enabled) {
		input_values.prev_value = jQuery('#prev_value').multilineInput('value');
		input_values.prev_time = jQuery('#prev_time').val();
	}

	jQuery('[name^=macros]').each(function(i, macro) {
		var name = macro.name.toString();
		macros[name.substr(7, name.length - 8)] = macro.value;
	});
	input_values.macros = macros;

	jQuery('<?= ($data['step_obj'] == -1)
		? '.preprocessing-list-foot'
		: '.preprocessing-list-item[data-step='.$data['step_obj'].']'
	?>', jQuery('#preprocessing')).data('test-data', input_values);
}

jQuery(document).ready(function($) {
	$('#final-result').hide();

	$('#value').multilineInput({
		placeholder: <?= CJs::encodeJson(_('value')) ?>,
		value: <?= CJs::encodeJson($data['value']) ?>,
		monospace_font: false,
		maxlength: 65535,
		autofocus: true,
		readonly: false,
		grow: 'auto',
		rows: 0
	});

	$('#prev_value').multilineInput({
		placeholder: <?= $data['show_prev'] ? CJs::encodeJson(_('value')) : '""' ?>,
		value: <?= CJs::encodeJson($data['prev_value']) ?>,
		monospace_font: false,
		maxlength: 65535,
		disabled: <?= $data['show_prev'] ? 'false' : 'true' ?>,
		grow: 'auto',
		rows: 0
	});

	$('#preprocessing-test-form .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
});

<?php return ob_get_clean(); ?>
