<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
<script type="text/javascript">
	jQuery(function($) {
		function hostPage() {
			let filter_options = <?= json_encode($data['filter_options']) ?>;

			this.refresh_url = '<?= $data['refresh_url'] ?>';
			this.refresh_interval = <?= $data['refresh_interval'] ?>;
			this.running = false;
			this.timeout = null;
			this.deferred = null;

			if (filter_options) {
				this.refresh_counters = this.createCountersRefresh(1);
				this.filter = new CTabFilter($('#monitoring_hosts_filter')[0], filter_options);
				this.filter.on(TABFILTER_EVENT_URLSET, (ev) => {
					let url = new Curl('', false);

					url.setArgument('action', 'host.view.refresh');
					this.refresh_url = url.getUrl();
					this.unscheduleRefresh();
					this.refresh();

					var filter_item = this.filter._active_item;

					if (this.filter._active_item.hasCounter()) {
						$.post('zabbix.php', {
							action: 'host.view.refresh',
							filter_counters: 1,
							counter_index: filter_item._index
						}).done((json) => {
							if (json.filter_counters) {
								filter_item.updateCounter(json.filter_counters.pop());
							}
						});
					}
				});
			}
		}

		hostPage.prototype = {
			createCountersRefresh: function(timeout) {
				if (this.refresh_counters) {
					clearTimeout(this.refresh_counters);
					this.refresh_counters = null;
				}

				return setTimeout(() => this.getFiltersCounters(), timeout);
			},
			getFiltersCounters: function() {
				return $.post('zabbix.php', {
						action: 'host.view.refresh',
						filter_counters: 1
					}).done((json) => {
						if (json.filter_counters) {
							this.filter.updateCounters(json.filter_counters);
						}
					}).always(() => {
						if (this.refresh_interval > 0) {
							this.refresh_counters = this.createCountersRefresh(this.refresh_interval);
						}
					});
			},
			getCurrentForm: function() {
				return $('form[name=host_view]');
			},
			addMessages: function(messages) {
				$('.wrapper main').before(messages);
			},
			removeMessages: function() {
				$('.wrapper .msg-bad').remove();
			},
			refresh: function() {
				this.setLoading();

				this.deferred = $.getJSON(this.refresh_url);

				return this.bindDataEvents(this.deferred);
			},
			setLoading: function() {
				this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
			},
			clearLoading: function() {
				this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
			},
			doRefresh: function(body) {
				this.getCurrentForm().replaceWith(body);
			},
			bindDataEvents: function(deferred) {
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
			onDataDone: function(response) {
				this.clearLoading();
				this.removeMessages();
				this.doRefresh(response.body);

				if ('messages' in response) {
					this.addMessages(response.messages);
				}
			},
			onDataFail: function(jqXHR) {
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
			onDataAlways: function() {
				if (this.running) {
					this.deferred = null;
					this.scheduleRefresh();
				}
			},
			scheduleRefresh: function() {
				this.unscheduleRefresh();

				if (this.refresh_interval > 0) {
					this.timeout = setTimeout((function() {
						this.timeout = null;
						this.refresh();
					}).bind(this), this.refresh_interval);
				}
			},
			unscheduleRefresh: function() {
				if (this.timeout !== null) {
					clearTimeout(this.timeout);
					this.timeout = null;
				}

				if (this.deferred) {
					this.deferred.abort();
				}
			},
			start: function() {
				this.running = true;
				this.refresh();
			}
		};

		window.host_page = new hostPage();
		window.host_page.start();
	});
</script>
