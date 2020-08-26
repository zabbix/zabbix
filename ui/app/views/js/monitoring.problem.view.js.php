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
		var filter = new CTabFilter($('#monitoring_problem_filter')[0], <?= json_encode($data['filter_options']) ?>);

		filter.on(TABFILTER_EVENT_URLSET, (ev) => {
			// Modify filter data of flickerfreeScreen object with id 'problem'.
			var form = filter._active_item._content_container.querySelector('form'),
				filter_data = window.flickerfreeScreen.screens.problem.data.filter;

			// Show
			filter_data.show = form.elements.show.value;

			// Host groups
			filter_data.groupids = [];

			if (form.elements['groupids[]'] instanceof HTMLElement) {
				filter_data.groupids = [form.elements['groupids[]'].value];
			}
			else if (form.elements['groupids[]']) {
				filter_data.groupids = [].map.call(form.elements['groupids[]'], host => host.value);
			}

			// Hosts
			filter_data.hostids = [];

			if (form.elements['hostids[]'] instanceof HTMLElement) {
				filter_data.hostids = [form.elements['hostids[]'].value];
			}
			else if (form.elements['hostids[]']) {
				filter_data.hostids = [].map.call(form.elements['hostids[]'], host => host.value);
			}

			// Application
			// Triggers
			// Problem
			// Severity
			// Host inventory
			filter_data.inventory = [];

			for (const elm in form.querySelectorAll('[name^="inventory["')) {
				// Uff fill data from multidimensional inputs manually.
			}

			// Tags
			// Show tags
			// Tag display priority
			// Show operation data
			// Show suppressed problems
			// Show unacknowledged only
			// Compact view
			// Show timeline
			// Show details
			// Highlight whole row

			console.log('filter set data', window.flickerfreeScreen.screens.problem.data.filter);

			$.publish('timeselector.rangeupdate', {
				from: 'now-1y',
				to: 'now'
			});
		});

		$(document).on({
			mouseenter: function() {
				if ($(this)[0].scrollWidth > $(this)[0].offsetWidth) {
					$(this).attr({title: $(this).text()});
				}
			},
			mouseleave: function() {
				if ($(this).is('[title]')) {
					$(this).removeAttr('title');
				}
			}
		}, 'table.<?= ZBX_STYLE_COMPACT_VIEW ?> a.<?= ZBX_STYLE_LINK_ACTION ?>');

		$.subscribe('acknowledge.create', function(event, response) {
			// Clear all selected checkboxes in Monitoring->Problems.
			if (chkbxRange.prefix === 'problem') {
				chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
				chkbxRange.clearSelectedOnFilterChange();
			}

			window.flickerfreeScreen.refresh('problem');

			clearMessages();
			addMessage(makeMessageBox('good', [], response.message, true, false));
		});

		$(document).on('submit', '#problem_form', function(e) {
			e.preventDefault();

			var eventids = $('[id^="eventids_"]:checked', $(this)).map(function() {
					return $(this).val();
				}).get();

			acknowledgePopUp({eventids: eventids}, this);
		});
	});
</script>
