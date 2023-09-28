<?php
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


/**
 * @var CView $this
 */
?>

<script>
	const view = {
		init({dashboard, widget_defaults, time_period}) {
			timeControl.refreshPage = false;

			const dashboard_page_containers = document.querySelectorAll('.<?= ZBX_STYLE_DASHBOARD_GRID ?>');
			let page_number = 0;

			for (const page of dashboard.pages) {
				const dashboard_page = new CDashboardPage(dashboard_page_containers[page_number], {
					data: {
						dashboard_pageid: page.dashboard_pageid,
						name: page.name,
						display_period: page.display_period
					},
					dashboard: {
						templateid: null,
						dashboardid: dashboard.dashboardid
					},
					cell_width: 100 / <?= DASHBOARD_MAX_COLUMNS ?>,
					cell_height: 70,
					max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
					max_rows: <?= DASHBOARD_MAX_ROWS ?>,
					widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					widget_defaults: widget_defaults,
					is_editable: false,
					is_edit_mode: false,
					can_edit_dashboards: false,
					time_period: time_period,
					dynamic_hostid: null,
					unique_id: page.dashboard_pageid
				});

				for (const widget_data of page.widgets) {
					dashboard_page.addWidget({
						...widget_data,
						is_new: false,
						unique_id: widget_data.widgetid
					});
				}

				dashboard_page.start();
				dashboard_page.activate();

				page_number = page_number + 1;
			}

			const page_heights = {};
			const dashboard_header_height = 47;
			const page_header_height = 26;
			const screen_width = 1920;

			const pages = document.querySelectorAll('.dashboard-page');

			pages.forEach((page) => {
				page_heights[page.classList[1]] = Math.floor(page.getBoundingClientRect().height);
			});

			const page_styles = document.createElement('style');
			document.head.appendChild(page_styles);

			for (const page in page_heights) {
				let page_height = page_header_height + page_heights[page];

				if (page == 'page_1') {
					page_height += dashboard_header_height;
				}

				page_styles.sheet.insertRule(
					'@page '+ page + ' {' +
						' size: ' + screen_width + 'px '+ page_height + 'px;' +
					'}'
				);

				page_styles.sheet.insertRule(
					'.' + page +' { page: '+ page +'; }'
				);
			}
		}
	}
</script>
