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
	function latestPage() {
		this.refresh_url = '<?= $data['refresh_url'] ?>';
		this.refresh_interval = <?= $data['refresh_interval'] ?>;
		this.running = false;
		this.timeout = null;
	}

	latestPage.prototype.getCurrentForm = function() {
		return $('form[name=items]');
	};

	latestPage.prototype.addMessages = function(messages) {
		$('.wrapper main').before(messages);
	};

	latestPage.prototype.removeMessages = function() {
		$('.wrapper .msg-bad').remove();
	};

	latestPage.prototype.refresh = function() {
		this.setLoading();

		var deferred = $.getJSON(this.refresh_url);

		return this.bindDataEvents(deferred);
	};

	latestPage.prototype.setLoading = function() {
		this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
	};

	latestPage.prototype.clearLoading = function() {
		this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
	};

	latestPage.prototype.doRefresh = function(body) {
		this.getCurrentForm().replaceWith(body);
		this.hydrate();
		chkbxRange.init();
	};

	latestPage.prototype.bindDataEvents = function(deferred) {
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
	};

	latestPage.prototype.onDataDone = function(response) {
		this.clearLoading();
		this.removeMessages();
		this.doRefresh(response.body);

		if ('messages' in response) {
			this.addMessages(response.messages);
		}
	};

	latestPage.prototype.onDataFail = function(jqXHR) {
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
	};

	latestPage.prototype.onDataAlways = function() {
		if (this.running) {
			this.scheduleRefresh();
		}
	};

	latestPage.prototype.scheduleRefresh = function() {
		this.unscheduleRefresh();
		this.timeout = setTimeout((function() {
			this.timeout = null;
			this.refresh();
		}).bind(this), this.refresh_interval);
	};

	latestPage.prototype.unscheduleRefresh = function() {
		if (this.timeout !== null) {
			clearTimeout(this.timeout);
			this.timeout = null;
		}
	};

	latestPage.prototype.start = function() {
		if (this.refresh_interval != 0) {
			this.running = true;
			this.scheduleRefresh();
		}
	};

	latestPage.prototype.stop = function() {
		this.running = false;
		this.unscheduleRefresh();
	};

	latestPage.prototype.toggleAppGroup = function(group, group_id, collapsed) {
		var $toggle = $('.app-list-toggle[data-' + group + '="' + group_id + '"]');

		$toggle.data('collapsed', collapsed ? 1 : 0);

		$('span', $toggle)
			.removeClass(collapsed ? '<?= ZBX_STYLE_ARROW_DOWN ?>' : '<?= ZBX_STYLE_ARROW_RIGHT ?>')
			.addClass(collapsed ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>');

		$('tr[data-' + group + '="' + group_id + '"]').toggle(!collapsed);
	};

	latestPage.prototype.updateToggleAll = function() {
		var $toggle_all = $('.app-list-toggle-all'),
			has_open_groups = false;

		$('.app-list-toggle').each(function() {
			if (!$(this).data('collapsed')) {
				has_open_groups = true;
			}
		});

		$toggle_all.data('collapsed', has_open_groups ? 0 : 1);

		$('span', $toggle_all)
			.removeClass(has_open_groups ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>')
			.addClass(has_open_groups ? '<?= ZBX_STYLE_ARROW_DOWN ?>' : '<?= ZBX_STYLE_ARROW_RIGHT ?>');
	};

	latestPage.prototype.hydrate = function() {
		var self = this;

		$('.app-list-toggle').each(function() {
			var $toggle = $(this),
				collapsed = $toggle.data('collapsed'),

				group = 'applicationid',
				group_id = $toggle.data(group);

			if (group_id === undefined) {
				group = 'hostid',
				group_id = $toggle.data(group);
			}

			self.toggleAppGroup(group, group_id, collapsed);
		});

		this.updateToggleAll();

		$('.app-list-toggle-all').on('click', function() {
			// For Opera browser with large tables, which renders table layout while showing/hiding rows.
			$(this).closest('table').fadeTo(0, 0);

			var $toggle_all = $(this),
				collapsed_all = $toggle_all.data('collapsed') ? 0 : 1,
				updates = {
					applicationid: [],
					hostid: []
				};

			$('.app-list-toggle').each(function() {
				var $toggle = $(this),
					collapsed = $toggle.data('collapsed');

				if (collapsed == collapsed_all) {
					return;
				}

				var group = 'applicationid',
					group_id = $toggle.data(group);

				if (group_id === undefined) {
					group = 'hostid',
					group_id = $toggle.data(group);
				}

				updates[group].push(group_id);

				self.toggleAppGroup(group, group_id, collapsed_all);
			});

			$toggle_all.data('collapsed', collapsed_all);

			self.updateToggleAll();

			// For Opera browser with large tables, which renders table layout while showing/hiding rows.
			$(this).closest('table').fadeTo(0, 1);

			if (updates.applicationid.length) {
				updateUserProfile('web.latest.collapsed', collapsed_all, updates.applicationid);
			}
			if (updates.hostid.length) {
				updateUserProfile('web.latest.collapsed_other', collapsed_all, updates.hostid);
			}
		});

		$('.app-list-toggle').on('click', function() {
			var $toggle = $(this),
				collapsed = $toggle.data('collapsed') ? 0 : 1,

				group = 'applicationid',
				group_id = $toggle.data(group);

			if (group_id === undefined) {
				group = 'hostid',
				group_id = $toggle.data(group);
			}

			self.toggleAppGroup(group, group_id, collapsed);
			self.updateToggleAll();

			if (group === 'applicationid') {
				updateUserProfile('web.latest.collapsed', collapsed ? 1 : 0, [group_id]);
			}
			else {
				updateUserProfile('web.latest.collapsed_other', collapsed ? 1 : 0, [group_id]);
			}
		});
	};

	jQuery(function($) {
		window.latest_page = new latestPage();
		window.latest_page.hydrate();
	});
</script>
