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
			let url = new Curl(),
				data = $.extend(<?= json_encode($data['filter_defaults'])?>, url.getArgumentsObject());

			if (data.inventory.length == 1 && data.inventory[0].value === '') {
				data.inventory = [];
			}

			if (data.tags.length == 1 && data.tags[0].tag === '' && data.tags[0].value === '') {
				data.tags = [];
			}

			console.log('data', data);
			// Modify filter data of flickerfreeScreen object with id 'problem'.
			window.flickerfreeScreen.screens['problem'].data.filter = data;
			$.publish('timeselector.rangeupdate', {
				from: 'now-1y',
				to: data.to
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
