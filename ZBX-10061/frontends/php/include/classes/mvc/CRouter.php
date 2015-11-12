<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		'dashboard.favourite'	=> ['CControllerDashboardFavourite',	'layout.javascript',	null],
		'dashboard.sort'		=> ['CControllerDashboardSort',			'layout.javascript',	null],
		'dashboard.view'		=> ['CControllerDashboardView',			'layout.htmlpage',		'monitoring.dashboard.view'],
		'dashboard.widget'		=> ['CControllerDashboardWidget',		'layout.javascript',	null],
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
		'profile.update'		=> ['CControllerProfileUpdate',			'layout.json',	null],
		'proxy.create'			=> ['CControllerProxyCreate',			null,					null],
		'proxy.delete'			=> ['CControllerProxyDelete',			null,					null],
		'proxy.edit'			=> ['CControllerProxyEdit',				'layout.htmlpage',		'administration.proxy.edit'],
		'proxy.hostdisable'		=> ['CControllerProxyHostDisable',		null,					null],
		'proxy.hostenable'		=> ['CControllerProxyHostEnable',		null,					null],
		'proxy.list'			=> ['CControllerProxyList',				'layout.htmlpage',		'administration.proxy.list'],
		'proxy.update'			=> ['CControllerProxyUpdate',			null,					null],
		'report.status'			=> ['CControllerReportStatus',			'layout.htmlpage',		'report.status'],
		'script.create'			=> ['CControllerScriptCreate',			null,					null],
		'script.delete'			=> ['CControllerScriptDelete',			null,					null],
		'script.edit'			=> ['CControllerScriptEdit',			'layout.htmlpage',		'administration.script.edit'],
		'script.list'			=> ['CControllerScriptList',			'layout.htmlpage',		'administration.script.list'],
		'script.update'			=> ['CControllerScriptUpdate',			null,					null],
		'system.warning'		=> ['CControllerSystemWarning',			'layout.warning',		'system.warning'],
		'widget.discovery.view'	=> ['CControllerWidgetDiscoveryView',	'layout.widget',		'monitoring.widget.discovery.view'],
		'widget.hosts.view'		=> ['CControllerWidgetHostsView',		'layout.widget',		'monitoring.widget.hosts.view'],
		'widget.issues.view'	=> ['CControllerWidgetIssuesView',		'layout.widget',		'monitoring.widget.issues.view'],
		'widget.status.view'	=> ['CControllerWidgetStatusView',		'layout.widget',		'monitoring.widget.status.view'],
		'widget.system.view'	=> ['CControllerWidgetSystemView',		'layout.widget',		'monitoring.widget.system.view'],
		'widget.web.view'		=> ['CControllerWidgetWebView',			'layout.widget',		'monitoring.widget.web.view']
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
		if (array_key_exists($this->action, $this->routes))
		{
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
