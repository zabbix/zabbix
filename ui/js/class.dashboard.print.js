class CDashboardPrint extends CDashboard {
	activate() {
		if (this._dashboard_pages.size === 0) {
			throw new Error('Cannot activate dashboard without dashboard pages.');
		}

		this._state = DASHBOARD_STATE_ACTIVE;

		this._selected_dashboard_page = this._getInitialDashboardPage();

		for (const dashboard_page of this.getDashboardPages()) {
			this.#activateDashboardPage(dashboard_page);
		}
	}

	addDashboardPage({dashboard_pageid, name, display_period, widgets}, container) {
		const dashboard_page = new CDashboardPage(container, {
			data: {
				dashboard_pageid,
				name,
				display_period
			},
			dashboard: {
				templateid: this._data.templateid,
				dashboardid: this._data.dashboardid
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			max_columns: this._max_columns,
			max_rows: this._max_rows,
			widget_min_rows: this._widget_min_rows,
			widget_max_rows: this._widget_max_rows,
			widget_defaults: this._widget_defaults,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			csrf_token: this._csrf_token,
			unique_id: this._createUniqueId()
		});

		this._dashboard_pages.set(dashboard_page, {});

		for (const widget_data of widgets) {
			dashboard_page.addWidgetFromData({
				...widget_data,
				is_new: false,
				unique_id: this._createUniqueId()
			});
		}

		this._target.classList.toggle(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE, this._dashboard_pages.size > 1);

		return dashboard_page;
	}

	#activateDashboardPage(dashboard_page) {
		dashboard_page.on(CDashboardPage.EVENT_REQUIRE_DATA_SOURCE, this._events.dashboardPageRequireDataSource);

		dashboard_page.start();
		dashboard_page.activate();
	}
}
