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
 * Add popup inputs to main form and submit.
 *
 * @param {object} response
 */
function submitOperationPopup(response) {
	var form_param = response.form.param,
		input_name = response.form.input_name,
		inputs = response.inputs;

	var input_keys = {
		opmessage_grp: 'usrgrpid',
		opmessage_usr: 'userid',
		opcommand_grp: 'groupid',
		opcommand_hst: 'hostid',
		opgroup: 'groupid',
		optemplate: 'templateid'
	};

	for (var i in inputs) {
		if (inputs.hasOwnProperty(i) && inputs[i] !== null) {
			if (i === 'opmessage' || i === 'opcommand' || i === 'opinventory') {
				for (var j in inputs[i]) {
					if (inputs[i].hasOwnProperty(j)) {
						create_var('action.edit', input_name + '[' + i + ']' + '[' + j + ']', inputs[i][j], false);
					}
				}
			}
			else if (i === 'opconditions') {
				for (var j in inputs[i]) {
					if (inputs[i].hasOwnProperty(j)) {
						create_var(
							'action.edit',
							input_name + '[' + i + ']' + '[' + j + '][conditiontype]',
							inputs[i][j]['conditiontype'],
							false
						);
						create_var(
							'action.edit',
							input_name + '[' + i + ']' + '[' + j + '][operator]',
							inputs[i][j]['operator'],
							false
						);
						create_var(
							'action.edit',
							input_name + '[' + i + ']' + '[' + j + '][value]',
							inputs[i][j]['value'],
							false
						);
					}
				}
			}
			else if (['opmessage_grp', 'opmessage_usr', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'optemplate']
					.indexOf(i) !== -1) {
				for (var j in inputs[i]) {
					if (inputs[i].hasOwnProperty(j)) {
						create_var(
							'action.edit',
							input_name + '[' + i + ']' + '[' + j + ']' + '[' + input_keys[i] + ']',
							inputs[i][j][input_keys[i]],
							false
						);
					}
				}
			}
			else {
				create_var('action.edit', input_name + '[' + i + ']', inputs[i], false);
			}
		}
	}

	submitFormWithParam('action.edit', form_param, '1');
}
