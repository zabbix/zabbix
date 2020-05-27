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
		function latestPage() {
			this.refresh_url = '<?= $data['refresh_url'] ?>';
			this.refresh_interval = <?= $data['refresh_interval'] ?>;
			this.running = false;
			this.timeout = null;
		}

		latestPage.prototype = {
			getCurrentForm: function() {
				return $('form[name=items]');
			},
			addMessages: function(messages) {
				$('.wrapper main').before(messages);
			},
			removeMessages: function() {
				$('.wrapper .msg-bad').remove();
			},
			refresh: function() {
				this.setLoading();

				var deferred = $.getJSON(this.refresh_url);

				return this.bindDataEvents(deferred);
			},
			setLoading: function() {
				this.getCurrentForm().addClass('in-progress delayed-15s');
			},
			clearLoading: function() {
				this.getCurrentForm().removeClass('in-progress delayed-15s');
			},
			doRefresh: function(body) {
				this.getCurrentForm().replaceWith(body);
				this.hydrate();
				chkbxRange.init();
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
					this.scheduleRefresh();
				}
			},
			scheduleRefresh: function() {
				this.unscheduleRefresh();
				this.timeout = setTimeout((function() {
					this.timeout = null;
					this.refresh();
				}).bind(this), this.refresh_interval);
			},
			unscheduleRefresh: function() {
				if (this.timeout !== null) {
					clearTimeout(this.timeout);
					this.timeout = null;
				}
			},
			start: function() {
				if (this.refresh_interval != 0) {
					this.running = true;
					this.scheduleRefresh();
				}
			},
			stop: function() {
				this.running = false;
				this.unscheduleRefresh();
			},
			hydrate: function() {
				var open_state_all = 0;

				$('.app-list-toggle').each(function() {
					var open_state = ($(this).data('open-state') === undefined);

					$('span', this).addClass(open_state
						? '<?= ZBX_STYLE_ARROW_DOWN ?>'
						: '<?= ZBX_STYLE_ARROW_RIGHT ?>'
					);

					if (!open_state) {
						var	hostid = $(this).attr('data-host-id');

						if (hostid) {
							$('tr[parent_host_id=' + hostid + ']').hide();
						}
						else {
							$('tr[parent_app_id=' + $(this).attr('data-app-id') + ']').hide();
						}
					}
					else {
						open_state_all = 1;
					}
				});

				$('.app-list-toggle-all').data('open-state', open_state_all);
				$('.app-list-toggle-all span').addClass((open_state_all === 0)
					? '<?= ZBX_STYLE_ARROW_RIGHT ?>'
					: '<?= ZBX_STYLE_ARROW_DOWN ?>'
				);

				// Click event for main toggle (+-) button.
				$('.app-list-toggle-all').click(function() {
					/*
					 * This is for Opera browser with large tables, which renders table layout while showing/hiding
					 * rows.
					 */
					$(this).closest('table').fadeTo(0, 0);

					var open_state = 1 - $(this).data('open-state'),
						del_class = (open_state)
							? '<?= ZBX_STYLE_ARROW_RIGHT ?>'
							: '<?= ZBX_STYLE_ARROW_DOWN ?>',
						add_class = (open_state)
							? '<?= ZBX_STYLE_ARROW_DOWN ?>'
							: '<?= ZBX_STYLE_ARROW_RIGHT ?>',
						applicationids = [],
						hostids = [];

					// Change and store new state.
					$(this).data('open-state', open_state);

					$('span', this)
						.removeClass(del_class)
						.addClass(add_class);

					$('.app-list-toggle').each(function() {
						if ($(this).data('open-state') !== open_state) {
							$(this).data('open-state', open_state);
							$('span', this)
								.removeClass(del_class)
								.addClass(add_class);

							var hostid = $(this).attr('data-host-id');

							if (hostid) {
								$('tr[parent_host_id=' + hostid + ']').toggle(open_state);
								hostids.push(hostid);
							}
							else {
								var applicationid = $(this).attr('data-app-id');

								$('tr[parent_app_id=' + applicationid + ']').toggle(open_state);
								applicationids.push(applicationid);
							}
						}
					});

					/*
					 * This is for Opera browser with large tables, which renders table layout while showing/hiding
					 * rows.
					 */
					$(this).closest('table').fadeTo(0, 1);

					if (!empty(hostids)) {
						updateUserProfile('web.latest.toggle_other', open_state, hostids);
					}
					if (!empty(applicationids)) {
						updateUserProfile('web.latest.toggle', open_state, applicationids);
					}
				});

				// Click event for every toggle (+-) button.
				$('.app-list-toggle').click(function() {
					var open_state = ($(this).data('open-state') === 0),
						del_class = (open_state)
							? '<?= ZBX_STYLE_ARROW_RIGHT ?>'
							: '<?= ZBX_STYLE_ARROW_DOWN ?>',
						add_class = (open_state)
							? '<?= ZBX_STYLE_ARROW_DOWN ?>'
							: '<?= ZBX_STYLE_ARROW_RIGHT ?>',
						open_state_all = 0;

					// Change and store new state.
					$(this).data('open-state', Number(open_state));

					$('span', this)
						.removeClass(del_class)
						.addClass(add_class);

					if (!open_state) {
						$('.app-list-toggle').each(function() {
							if ($(this).data('open-state') !== 0) {
								open_state_all = 1;
							}
						});
					}
					else {
						open_state_all = 1;
					}

					if ($('.app-list-toggle-all').data('open-state') !== open_state_all) {
						$('.app-list-toggle-all').data('open-state', open_state_all);
						$('.app-list-toggle-all span')
							.removeClass(del_class)
							.addClass(add_class);
					}

					var hostid = $(this).attr('data-host-id');

					if (hostid) {
						$('tr[parent_host_id=' + hostid + ']').toggle(open_state);
						updateUserProfile('web.latest.toggle_other', Number(open_state), [hostid]);
					}
					else {
						var applicationid = $(this).attr('data-app-id');

						$('tr[parent_app_id=' + applicationid + ']').toggle(open_state);
						updateUserProfile('web.latest.toggle', Number(open_state), [applicationid]);
					}
				});
			}
		};

		window.latest_page = new latestPage();
		window.latest_page.hydrate();
	});
</script>
