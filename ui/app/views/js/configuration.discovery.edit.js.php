<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
?>


window.drule_edit_popup = new class {

	init({druleid, dchecks, drule}) {
		this.overlay = overlays_stack.getById('discoveryForm');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.druleid = druleid;
		this.drule = drule;
		this.dcheckid = getUniqueId();

		// Append existing discovery checks to check table.
		if (typeof(dchecks) === 'object') {
			dchecks = Object.values(dchecks);
		}
		for (const dcheck of dchecks) {
			this._addCheck(dcheck);
		}

		this._addRadioButtonValues(drule);
		this._initActionButtons();
	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-check-add')) {
				this._editCheck();
			}
			else if (e.target.classList.contains('js-remove')) {
				this._removeDCheckRow(e.target.closest('tr').id);
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-edit')) {
				this._editCheck(e.target.closest('tr'));
			}
		});
	}

	_updateCheck(row, input) {
		if (!input.host_source) {
			const checkedHostSource = document.querySelector('[name="host_source"]:checked:not([data-id])');
			input.host_source = checkedHostSource ? checkedHostSource.value : '<?= ZBX_DISCOVERY_DNS ?>';
		}

		if (!input.name_source) {
			const checkedNameSource = document.querySelector('[name="name_source"]:checked:not([data-id])');
			input.name_source = checkedNameSource ? checkedNameSource.value : '<?= ZBX_DISCOVERY_UNSPEC ?>';
		}

		delete input.dchecks;

		this._addCheck(input, row);
		this._removeDCheckRow(row.id);
		row.remove();
		this._addInputFields(input);
	}

	_editCheck(btn = null) {
		row = null;

		let params = {
			dcheckid: this.dcheckid
		};

		if (btn !== null) {
			var row = btn.closest('tr');

			params = {
				dcheckid: this.dcheckid,
				update: 1
			};

			var hiddenInputs = row.querySelectorAll('input[type="hidden"]');
			for (var i = 0; i < hiddenInputs.length; i++) {
				var input = hiddenInputs[i];
				var name = input.getAttribute('name').match(/\[([^\]]+)]$/);

				if (name) {
					params[name[1]] = input.value;
				}
			}
		}

		const overlay = PopUp('discovery.check.edit', params, {
			dialogueid: 'discovery-check',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('check.submit', (e) => {
			if (row !== null) {
				this._updateCheck(row, e.detail)
			}
			else {
				this.dcheckid = getUniqueId()
				this._addCheck(e.detail);
			}
		});
	}

	_addRadioButtonValues(drule) {
		jQuery('input:radio[name="uniqueness_criteria"][value='+jQuery.escapeSelector(drule.uniqueness_criteria)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="host_source"][value='+jQuery.escapeSelector(drule.host_source)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="name_source"][value='+jQuery.escapeSelector(drule.name_source)+']')
			.attr('checked', 'checked');

		const that = this;

		document.querySelectorAll('#host_source, #name_source').forEach(function(element) {
			element.addEventListener('change', function(e) {
				that._updateRadioButtonValues(e);
			});
		});
	}

	_updateRadioButtonValues(event) {
		let target = event.target,
			name = target.getAttribute('name');

		if (target.dataset.id) {
			document.querySelectorAll('[name^=dchecks][name$="[' + name + ']"]')
				.forEach(function(dcheck) {
					dcheck.value = (name === 'name_source') ? <?= ZBX_DISCOVERY_UNSPEC ?> : <?= ZBX_DISCOVERY_DNS ?>;
				});
			document.querySelector(
				'[name="dchecks[' + target.dataset.id + '][' + name + ']"]'
			).value = <?= ZBX_DISCOVERY_VALUE ?>;
		}
		else {
			document.querySelectorAll('[name^=dchecks][name$="[' + name + ']"]')
				.forEach(function(dcheck) {
					dcheck.value = target.value;
				});
		}
	}

	_addCheck(input, row = null) {
		delete input.dchecks;

		if (!input.host_source) {
			const checkedHostSource = document.querySelector('[name="host_source"]:checked:not([data-id])');
			input.host_source = checkedHostSource ? checkedHostSource.value : '<?= ZBX_DISCOVERY_DNS ?>';
		}

		if (!input.name_source) {
			const checkedNameSource = document.querySelector('[name="name_source"]:checked:not([data-id])');
			input.name_source = checkedNameSource ? checkedNameSource.value : '<?= ZBX_DISCOVERY_UNSPEC ?>';
		}

		let template;
		template = new Template(document.getElementById('dcheck-row-tmpl').innerHTML);

		if (row) {
			row.insertAdjacentHTML('afterend', template.evaluate(input))
		}
		else {
			document
				.querySelector('#dcheckList tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));

			this._addInputFields(input);
		}

		var available_device_types = [<?= SVC_AGENT ?>, <?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>, <?= SVC_SNMPv3 ?>];

		if (available_device_types.includes(parseInt(input.type))) {
			this._addRadioButtonRows(input);
		}
		this._addRadioButtonValues(this.drule);
	}

	_addInputFields(input) {
		for (let field_name in input) {
			if (input.hasOwnProperty(field_name)) {
				const inputElem = document.createElement('input');
				inputElem.name = `dchecks[${input.dcheckid}][${field_name}]`;
				inputElem.type = 'hidden';
				inputElem.value = input[field_name];

				const dcheckCell = document.getElementById(`dcheckCell_${input.dcheckid}`);
				dcheckCell.appendChild(inputElem);
			}
		}
	}

	_addRadioButtonRows(input) {
		const templates = {
			unique_template: ['#unique-row-tmpl', '#device-uniqueness-list'],
			host_template: ['#host-source-row-tmpl', '#host_source'],
			name_template: ['#name-source-row-tmpl', '#name_source']
		};

		for (const [template, element] of Object.values(templates)) {
			const templateHtml = document.querySelector(template).innerHTML;
			document.querySelector(element).insertAdjacentHTML('beforeend', new Template(templateHtml).evaluate(input));
		}
	}

	_removeDCheckRow(dcheckid) {
		dcheckid = dcheckid.substring(dcheckid.indexOf("_") + 1);

		const elements = {
			uniqueness_criteria_: 'ip',
			host_source_: 'chk_dns',
			name_source_: 'chk_host'
		};

		for (const [key, def] of Object.entries(elements)) {
			const obj = document.querySelector(`#${key}${dcheckid}`);

			if (obj !== null) {
				if (obj.checked) {
					document.querySelector(`#${key}${def}`).checked = true;
				}
				document.querySelector(`#${key}row_${dcheckid}`).remove();
			}
		}
	}

	clone({title, buttons}) {
		this.druleid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'discovery.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('discovery'), JSON_THROW_ON_ERROR) ?>
		);

		this._post(curl.getUrl(), {druleids: [this.druleid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		['name', 'iprange', 'delay'].forEach(
			field => fields[field] = fields[field].trim()
		);

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.druleid === null ? 'discovery.create' : 'discovery.update');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
