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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script>
	const view = {
		refresh_url: null,
		refresh_data: null,
		refresh_interval: null,
		running: false,
		timeout: null,
		_refresh_message_box: null,
		_popup_message_box: null,

		init({refresh_url, refresh_data, refresh_interval}) {
			this.refresh_url = refresh_url;
			this.refresh_data = refresh_data;
			this.refresh_interval = refresh_interval;

			this.liveFilter();
			this.start();
		},

		getCurrentForm() {
			return $('form[name=items]');
		},

		_addRefreshMessage(messages) {
			this._removeRefreshMessage();

			this._refresh_message_box = $($.parseHTML(messages));
			addMessage(this._refresh_message_box);
		},

		_removeRefreshMessage() {
			if (this._refresh_message_box !== null) {
				this._refresh_message_box.remove();
				this._refresh_message_box = null;
			}
		},

		_addPopupMessage(message_box) {
			this._removePopupMessage();

			this._popup_message_box = message_box;
			addMessage(this._popup_message_box);
		},

		_removePopupMessage() {
			if (this._popup_message_box !== null) {
				this._popup_message_box.remove();
				this._popup_message_box = null;
			}
		},

		refresh() {
			this.setLoading();

			var deferred = $.ajax({
				url: this.refresh_url,
				data: this.refresh_data,
				type: 'post',
				dataType: 'json'
			});

			return this.bindDataEvents(deferred);
		},

		setLoading() {
			this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
		},

		clearLoading() {
			this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
		},

		doRefresh(body) {
			this.getCurrentForm().replaceWith(body);
			chkbxRange.init();
		},

		bindDataEvents(deferred) {
			var that = this;

			deferred
				.done(function(response) {
					that.onDataDone.call(that, response);
				})
				.fail(function(jqXHR) {
					that.onDataFail.call(that, jqXHR);
				})
				.always(this.onDataAlways.bind(this));

			return deferred;
		},

		onDataDone(response) {
			this.clearLoading();
			this._removeRefreshMessage();
			this.doRefresh(response.body);

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}
		},

		onDataFail(jqXHR) {
			// Ignore failures caused by page unload.
			if (jqXHR.status == 0) {
				return;
			}

			this.clearLoading();

			var messages = $(jqXHR.responseText).find('.msg-global');

			if (messages.length) {
				this.getCurrentForm().html(messages);
			}
			else {
				this.getCurrentForm().html(jqXHR.responseText);
			}
		},

		onDataAlways() {
			if (this.running) {
				this.scheduleRefresh();
			}
		},

		scheduleRefresh() {
			this.unscheduleRefresh();
			this.timeout = setTimeout((function() {
				this.timeout = null;
				this.refresh();
			}).bind(this), this.refresh_interval);
		},

		unscheduleRefresh() {
			if (this.timeout !== null) {
				clearTimeout(this.timeout);
				this.timeout = null;
			}
		},

		start() {
			if (this.refresh_interval != 0) {
				this.running = true;
				this.scheduleRefresh();
			}
		},

		stop() {
			this.running = false;
			this.unscheduleRefresh();
		},

		liveFilter() {
			var $filter_hostids = $('#filter_hostids_'),
				$filter_show_without_data = $('#filter_show_without_data');

			$filter_hostids.on('change', function() {
				var no_hosts_selected = !$(this).multiSelect('getData').length;

				if (no_hosts_selected) {
					$filter_show_without_data.prop('checked', true);
				}

				$filter_show_without_data.prop('disabled', no_hosts_selected);
			});

			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					var rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			this._removePopupMessage();
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			this.unscheduleRefresh();

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
				this.scheduleRefresh();
			}, {once: true});
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				uncheckTableRows('');
				view.refresh();
			}
		}
	}
</script>
