<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CRouter {
	/**
	 * Layout used for view rendering.
	 *
	 * @var string
	 */
	private $layout = null;

	/**
	 * Controller class for action handling.
	 *
	 * @var string
	 */
	private $controller = null;

	/**
	 * View used to generate HTML, CSV, JSON and other content.
	 *
	 * @var string
	 */
	private $view = null;

	/**
	 * Unique action (request) identifier.
	 *
	 * @var string
	 */
	private $action = null;

	/**
	 * Mapping between action and corresponding controller, layout and view.
	 *
	 * @var array
	 */
	private $routes = [
		// action					controller							layout					view
		'acknowledge.create'	=> ['CControllerAcknowledgeCreate',		null,					null],
		'acknowledge.edit'		=> ['CControllerAcknowledgeEdit',		'layout.htmlpage',		'monitoring.acknowledge.edit'],
		'dashboard.view'		=> ['CControllerDashboardView',			'layout.htmlpage',		'monitoring.dashboard.view'],
		'dashboard.list'		=> ['CControllerDashboardList',			'layout.htmlpage',		'monitoring.dashboard.list'],
		'dashboard.delete'		=> ['CControllerDashboardDelete',		null,					null],
		'dashbrd.widget.config'	=> ['CControllerDashbrdWidgetConfig',	'layout.json',			'monitoring.dashboard.config'],
		'dashbrd.widget.check'	=> ['CControllerDashbrdWidgetCheck',	'layout.json',			null],
		'dashboard.update'		=> ['CControllerDashboardUpdate',		'layout.json',			null],
		'dashboard.get'	        => ['CControllerDashboardGet',	        'layout.json',			null],
		'dashboard.share.update'	=> ['CControllerDashboardShareUpdate',		'layout.json',	null],
		'dashboard.properties.edit' => ['CControllerDashboardPropertiesEdit',	'layout.json',	'dashbrd.properties.edit'],
		'dashboard.share.edit'	=> ['CControllerDashboardShareEdit',	'layout.json',			'dashbrd.sharing.edit'],
		'dashbrd.widget.rfrate'	=> ['CControllerDashbrdWidgetRfRate',	'layout.json',			null],
		'discovery.view'		=> ['CControllerDiscoveryView',			'layout.htmlpage',		'monitoring.discovery.view'],
		'favourite.create'		=> ['CControllerFavouriteCreate',		'layout.javascript',	null],
		'favourite.delete'		=> ['CControllerFavouriteDelete',		'layout.javascript',	null],
		'map.view'				=> ['CControllerMapView',				'layout.htmlpage',		'monitoring.map.view'],
		'mediatype.create'		=> ['CControllerMediatypeCreate',		null,					null],
		'mediatype.delete'		=> ['CControllerMediatypeDelete',		null,					null],
		'mediatype.disable'		=> ['CControllerMediatypeDisable',		null,					null],
		'mediatype.edit'		=> ['CControllerMediatypeEdit',			'layout.htmlpage',		'administration.mediatype.edit'],
		'mediatype.enable'		=> ['CControllerMediatypeEnable',		null,					null],
		'mediatype.list'		=> ['CControllerMediatypeList',			'layout.htmlpage',		'administration.mediatype.list'],
		'mediatype.update'		=> ['CControllerMediatypeUpdate',		null,					null],
		'problem.view'			=> ['CControllerProblemView',			'layout.htmlpage',		'monitoring.problem.view'],
		'problem.view.csv'		=> ['CControllerProblemView',			'layout.csv',			'monitoring.problem.view'],
		'profile.update'		=> ['CControllerProfileUpdate',			'layout.json',			null],
		'proxy.create'			=> ['CControllerProxyCreate',			null,					null],
		'proxy.delete'			=> ['CControllerProxyDelete',			null,					null],
		'proxy.edit'			=> ['CControllerProxyEdit',				'layout.htmlpage',		'administration.proxy.edit'],
		'proxy.hostdisable'		=> ['CControllerProxyHostDisable',		null,					null],
		'proxy.hostenable'		=> ['CControllerProxyHostEnable',		null,					null],
		'proxy.list'			=> ['CControllerProxyList',				'layout.htmlpage',		'administration.proxy.list'],
		'proxy.update'			=> ['CControllerProxyUpdate',			null,					null],
		'report.services'		=> ['CControllerReportServices',		'layout.htmlpage',		'report.services'],
		'report.status'			=> ['CControllerReportStatus',			'layout.htmlpage',		'report.status'],
		'script.create'			=> ['CControllerScriptCreate',			null,					null],
		'script.delete'			=> ['CControllerScriptDelete',			null,					null],
		'script.edit'			=> ['CControllerScriptEdit',			'layout.htmlpage',		'administration.script.edit'],
		'script.list'			=> ['CControllerScriptList',			'layout.htmlpage',		'administration.script.list'],
		'script.update'			=> ['CControllerScriptUpdate',			null,					null],
		'system.warning'		=> ['CControllerSystemWarning',			'layout.warning',		'system.warning'],
		'timeline.update'		=> ['CControllerTimelineUpdate',		'layout.json',			null],
		'web.view'				=> ['CControllerWebView',				'layout.htmlpage',		'monitoring.web.view'],
		'widget.favgrph.view'	=> ['CControllerWidgetFavGraphsView',	'layout.widget',		'monitoring.widget.favgraphs.view'],
		'widget.favmap.view'	=> ['CControllerWidgetFavMapsView',		'layout.widget',		'monitoring.widget.favmaps.view'],
		'widget.favscr.view'	=> ['CControllerWidgetFavScreensView',	'layout.widget',		'monitoring.widget.favscreens.view'],
		'widget.dscvry.view'	=> ['CControllerWidgetDiscoveryView',	'layout.widget',		'monitoring.widget.discovery.view'],
		'widget.graph.view'		=> ['CControllerWidgetGraphView',		'layout.widget',		'monitoring.widget.graph.view'],
		'widget.hoststat.view'	=> ['CControllerWidgetHostsView',		'layout.widget',		'monitoring.widget.hosts.view'],
		'widget.problems.view'	=> ['CControllerWidgetProblemsView',	'layout.widget',		'monitoring.widget.problems.view'],
		'widget.stszbx.view'	=> ['CControllerWidgetStatusView',		'layout.widget',		'monitoring.widget.status.view'],
		'widget.syssum.view'	=> ['CControllerWidgetSystemView',		'layout.widget',		'monitoring.widget.system.view'],
		'widget.webovr.view'	=> ['CControllerWidgetWebView',			'layout.widget',		'monitoring.widget.web.view'],
		'widget.clock.view'		=> ['CControllerWidgetClockView',		'layout.widget',		'monitoring.widget.clock.view'],
		'widget.sysmap.view'	=> ['CControllerWidgetSysmapView',		'layout.widget',		'monitoring.widget.sysmap.view'],
		'widget.navigationtree.view'			=> ['CControllerWidgetNavigationtreeView',				'layout.widget',	'monitoring.widget.navigationtree.view'],
		'widget.navigationtree.edititemdialog'	=> ['CControllerWidgetNavigationtreeItemEditDialog',	'layout.json',		null],
		'widget.navigationtree.edititem'		=> ['CControllerWidgetNavigationtreeItemEdit',			'layout.json',		null],
		'widget.actlog.view'	=> ['CControllerWidgetActionLogView',	'layout.widget',		'monitoring.widget.actionlog.view'],
		'widget.dataover.view'	=> ['CControllerWidgetDataOverView',	'layout.widget',		'monitoring.widget.dataover.view'],
		'widget.trigover.view'	=> ['CControllerWidgetTrigOverView',	'layout.widget',		'monitoring.widget.trigover.view'],
		'widget.url.view'		=> ['CControllerWidgetUrlView',			'layout.widget',		'monitoring.widget.url.view'],
		'widget.plaintext.view'	=> ['CControllerWidgetPlainTextView',	'layout.widget',		'monitoring.widget.plaintext.view'],
		'popup.generic'			=> ['CControllerPopupGeneric',			'layout.json',			'popup.generic'],
		'popup.httpstep'		=> ['CControllerPopupHttpStep',			'layout.json',			'popup.httpstep'],
		'popup.media'			=> ['CControllerPopupMedia',			'layout.json',			'popup.media'],
		'popup.scriptexec'		=> ['CControllerPopupScriptExec',		'layout.json',			'popup.scriptexec'],
		'popup.triggerexpr'		=> ['CControllerPopupTriggerExpr',		'layout.json',			'popup.triggerexpr'],
		'popup.services'		=> ['CControllerPopupServices',			'layout.json',			'popup.services'],
		'popup.testtriggerexpr'	=> ['CControllerPopupTestTriggerExpr',	'layout.json',			'popup.testtriggerexpr'],
		'popup.triggerwizard'	=> ['CControllerPopupTriggerWizard',	'layout.json',			'popup.triggerwizard'],
		'trigdesc.update'		=> ['CControllerTrigDescUpdate',		'layout.json',			null],
		'popup.trigdesc.view'	=> ['CControllerPopupTrigDescView',		'layout.json',			'popup.trigdesc.view']
	];

	public function __construct($action) {
		$this->action = $action;
		$this->calculateRoute();
	}

	/**
	 * Locate and set controller, layout and view by action name.
	 *
	 * @return string
	 */
	public function calculateRoute() {
		if (array_key_exists($this->action, $this->routes)) {
			$this->controller = $this->routes[$this->action][0];
			$this->layout = $this->routes[$this->action][1];
			$this->view = $this->routes[$this->action][2];
		}
	}

	/**
	 * Returns layout name.
	 *
	 * @return string
	 */
	public function getLayout() {
		return $this->layout;
	}

	/**
	 * Returns controller name.
	 *
	 * @return string
	 */
	public function getController() {
		return $this->controller;
	}

	/**
	 * Returns view name.
	 *
	 * @return string
	 */
	public function getView() {
		return $this->view;
	}

	/**
	 * Returns action name.
	 *
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}
}
