<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
* @var array $data
*/
?>

window.scheduledreport_edit = new class {

	old_dashboardid;

	init({rules, rules_for_clone, dashboard_inaccessible, owner_inaccessible, current_user_id, current_user_name,
			allowed_edit}) {
		this.allowed_edit = allowed_edit;
		this.current_user_id = current_user_id;
		this.current_user_name = current_user_name;
		this.owner_inaccessible = owner_inaccessible;
		this.rules_for_clone = rules_for_clone;
		this.overlay = overlays_stack.getById('scheduledreport.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = document.getElementById('scheduledreport-form');
		this.form = new CForm(this.form_element, rules);

		this.old_dashboardid = this.form_element.querySelector('input[name="dashboardid"]')?.value ?? null;
		this.dashboard_inaccessible = dashboard_inaccessible;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'scheduledreport.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initActions();
	}

	#submit(event) {
		const fields = this.form.getAllValues();
		const curl = new Curl('zabbix.php');
		const reportid = this.form.findFieldByName('reportid')?.getValue()

		curl.setArgument('action', reportid ? 'scheduledreport.update' : 'scheduledreport.create');

		this.overlay.setLoading();
		this.form.validateSubmit(fields).then((result) => {
			if (!result) {
				this.overlay.unsetLoading();
				return;
			}

			if (reportid && fields.dashboardid != this.old_dashboardid) {
				this.#confirmWithSanitizedSubscriptionFields(fields, event.target)
					.then((fields_sanitized) => this.#post(curl.getUrl(), fields_sanitized))
					.catch(() => this.overlay.unsetLoading());
			}
			else {
				this.#post(curl.getUrl(), fields);
			}
		});
	}

	#clone() {
		document.getElementById('reportid').remove();
		this.form.reload(this.rules_for_clone);
		this.overlay.unsetLoading();
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Test')) ?>,
				class: <?= json_encode(ZBX_STYLE_BTN_ALT) ?> + ' js-test',
				keepOpen: true,
				isSubmit: false,
				enabled: this.allowed_edit
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: <?= json_encode(ZBX_STYLE_BTN_ALT) ?>,
				cancel: true,
				action: ''
			}
		];
		this.overlay.setProperties({title: <?= json_encode(_('New scheduled report')) ?>, buttons});
		this.#initFormActions();
		this.overlay.recoverFocus();
		this.overlay.containFocus();

		const {rows} = document.getElementById('subscriptions-table');
		[...rows].filter(n => n.parentNode.nodeName === 'TBODY').map(n => n.remove());

		const {subscriptions} = this.form.getAllValues();

		for (let index in subscriptions) {
			const subscription = subscriptions[index];

			if (subscription.recipient_inaccessible === '1') {
				continue;
			}

			if (subscription.creator_type != <?= ZBX_REPORT_CREATOR_TYPE_RECIPIENT ?>) {
				subscription.creator_inaccessible = 0;
				subscription.creator_name = this.current_user_name;
				subscription.creatorid = this.current_user_id;
			}

			new ReportSubscription(subscription);
		}

		if (this.owner_inaccessible) {
			jQuery('#userid').multiSelect('clean');
			jQuery('#userid').multiSelect('addData', [{id: this.current_user_id, name: this.current_user_name}]);
		}

		if (this.dashboard_inaccessible) {
			jQuery('#dashboardid').multiSelect('clean');
		}
	}

	#test(event) {
		this.form.findFieldByName('name').setChanged();
		this.form.findFieldByName('dashboardid').setChanged();
		this.form
			.validateFieldsForAction(['name', 'dashboardid'])
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				const {dashboardid, period, name, subject, message} = this.form.getAllValues();
				const overlay = PopUp('popup.scheduledreport.test', {
					dashboardid, period, name, subject, message,
					now: Math.floor(Date.now() / 1000),
					[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('scheduledreport')) ?>
				}, {
					dialogue_class: 'modal-popup-medium',
					trigger_element: event.target
				});
				overlay.$dialogue[0].addEventListener('dialogue.close', () => this.overlay.unsetLoading());
			});
	}

	#delete() {
		if (!window.confirm(<?= json_encode(_('Delete scheduled report?')) ?>)) {
			this.overlay.unsetLoading();
			return;
		}

		const curl = new Curl('zabbix.php');
		const reportid = this.form.findFieldByName('reportid').getValue()

		curl.setArgument('action', 'scheduledreport.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('scheduledreport')) ?>);

		this.#post(curl.getUrl(), {reportids: [reportid]});
	}

	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();
					return;
				}
				else if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);
				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.form_element.parentNode.children) {
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

				this.form_element.parentNode.insertBefore(message_box, this.form_element);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	#initFormActions() {
		this.overlay.$dialogue.$footer[0].querySelector('.js-test')
			.addEventListener('click', (event) => this.#test(event));

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', (event) => this.#submit(event));

		this.overlay.$dialogue.$footer[0].querySelector('.js-clone')
			?.addEventListener('click', () => this.#clone());

		this.overlay.$dialogue.$footer[0].querySelector('.js-delete')
			?.addEventListener('click', () => this.#delete());
	}

	#initActions() {
		this.#initFormActions();
		document
			.getElementById('cycle')
			.addEventListener('change', (event) => {
				const show_weekdays = (event.target.value == <?= ZBX_REPORT_CYCLE_WEEKLY ?>);

				document
					.querySelectorAll('#weekdays-label, #weekdays')
					.forEach((elem) => elem.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_weekdays));
			});
	}

	#confirmWithSanitizedSubscriptionFields(fields, focus_element) {
		return new Promise((resolve, reject) => {
			let message = <?= json_encode(_('Report generated by other users will be changed to the current user.')) ?>;
			overlayDialogue({
				class: 'modal-popup',
				content: message,
				buttons: [
					{
						title: <?= json_encode(_('OK')) ?>,
						focused: true,
						action: () => resolve(this.#sanitizeSubscriptions(fields))
					},
					{
						title: <?= json_encode(_('Cancel')) ?>,
						cancel: true,
						class: '<?= ZBX_STYLE_BTN_ALT ?>',
						action: () => reject()
					}
				]
			}, {
				position: Overlay.prototype.POSITION_CENTER,
				trigger_element: focus_element
			});
		});
	}

	#sanitizeSubscriptions(fields) {
		const subscriptions = {};

		for (const [key, value] of Object.entries(fields.subscriptions)) {
			if (value.recipient_inaccessible === '1') {
				continue;
			}

			if (value.creator_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>) {
				value.creatorid = <?= CWebUser::$data['userid'] ?>;
				value.creator_inaccessible = 0;
			}

			subscriptions[key] = value;
		}

		return {...fields, subscriptions};
	}
};
