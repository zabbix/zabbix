<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	latestPage.prototype.liveFilter = function() {
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
	};

	$(function() {
		window.latest_page = new latestPage();
		window.latest_page.liveFilter();
	});
</script>
