<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var CView $this
 */
?>

new CViewSwitcher('timeperiod_type', 'change', <?= json_encode([
	TIMEPERIOD_TYPE_ONETIME =>	['row_timepreiod_start_date', 'row_timeperiod_period_length'],
	TIMEPERIOD_TYPE_DAILY =>	['row_timeperiod_every_day', 'row_timeperiod_period_at_hours_minutes',
		'row_timeperiod_period_length'
	],
	TIMEPERIOD_TYPE_WEEKLY =>	['row_timeperiod_every_week', 'row_timeperiod_dayofweek',
		'row_timeperiod_period_at_hours_minutes', 'row_timeperiod_period_length'
	],
	TIMEPERIOD_TYPE_MONTHLY =>	['row_timeperiod_months', 'row_timeperiod_date', 'row_timeperiod_day',
		'row_timeperiod_week', 'row_timeperiod_week_days', 'row_timeperiod_every',
		'row_timeperiod_period_at_hours_minutes', 'row_timeperiod_period_length'
	]
]) ?>);

jQuery('#month_date_type').change(function() {
	var value = jQuery('input:checked', this).val();

	jQuery('#row_timeperiod_day').toggle(value == 0);
	jQuery('#row_timeperiod_week,#row_timeperiod_week_days').toggle(value == 1);
	overlays_stack.end().centerDialog();
});

jQuery('#timeperiod_type').change(function() {
	if (this.value == <?= TIMEPERIOD_TYPE_MONTHLY ?>) {
		jQuery('#month_date_type').trigger('change');
	}

	jQuery(window).trigger('resize');
}).trigger('change');

/**
 * @param {Overlay} overlay
 */
function submitMaintenancePeriod(overlay) {
	var $container = overlay.$dialogue.find('form'),
		elements = {};

	$container.trimValues(['#start_date']);
	$('>input, >ul>li:visible input', $container)
		.serializeArray()
		.forEach(({name, value}) => elements[name] = value);

	overlay.setLoading();
	overlay.xhr = sendAjaxData('zabbix.php', {
		data: elements,
		dataType: 'json',
		type: 'post',
		success: function(response) {
			if ('error' in response) {
				overlay.unsetLoading();

				overlay.$dialogue.find('.msg-bad').remove();

				const message_box = makeMessageBox('bad', response.error.messages, response.error.title);

				message_box.insertBefore($container);
			}
			else if ('params' in response) {
				var index = response.params.index;

				delete response.params.index;
				jQuery.each(response.params, function(name, value) {
					create_var('maintenanceForm', 'timeperiods[' + index + '][' + name + ']', value);
				});

				document.forms.maintenanceForm.submit();
			}
		}
	});
};
