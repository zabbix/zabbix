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
	private $routes = array(
		// action					controller								layout					view
		'dashboard.favourite'	=> array('CControllerDashboardFavourite',	'layout.javascript',	null),
		'dashboard.sort'		=> array('CControllerDashboardSort',		'layout.javascript',	null),
		'dashboard.view'		=> array('CControllerDashboardView',		'layout.htmlpage',		'monitoring.dashboard.view'),
		'dashboard.widget'		=> array('CControllerDashboardWidget',		'layout.javascript',	null),
		'discovery.view'		=> array('CControllerDiscoveryView',		'layout.htmlpage',		'monitoring.discovery.view'),
		'favourite.create'		=> array('CControllerFavouriteCreate',		'layout.javascript',	null),
		'favourite.delete'		=> array('CControllerFavouriteDelete',		'layout.javascript',	null),
		'map.view'				=> array('CControllerMapView',				'layout.htmlpage',		'monitoring.map.view'),
		'mediatype.create'		=> array('CControllerMediatypeCreate',		null,					null),
		'mediatype.delete'		=> array('CControllerMediatypeDelete',		null,					null),
		'mediatype.disable'		=> array('CControllerMediatypeDisable',		null,					null),
		'mediatype.edit'		=> array('CControllerMediatypeEdit',		'layout.htmlpage',		'administration.mediatype.edit'),
		'mediatype.enable'		=> array('CControllerMediatypeEnable',		null,					null),
		'mediatype.list'		=> array('CControllerMediatypeList',		'layout.htmlpage',		'administration.mediatype.list'),
		'mediatype.update'		=> array('CControllerMediatypeUpdate',		null,					null),
		'proxy.create'			=> array('CControllerProxyCreate',			null,					null),
		'proxy.delete'			=> array('CControllerProxyDelete',			null,					null),
		'proxy.edit'			=> array('CControllerProxyEdit',			'layout.htmlpage',		'administration.proxy.edit'),
		'proxy.hostdisable'		=> array('CControllerProxyHostDisable',		null,					null),
		'proxy.hostenable'		=> array('CControllerProxyHostEnable',		null,					null),
		'proxy.list'			=> array('CControllerProxyList',			'layout.htmlpage',		'administration.proxy.list'),
		'proxy.update'			=> array('CControllerProxyUpdate',			null,					null),
		'report.status'			=> array('CControllerReportStatus',			'layout.htmlpage',		'report.status'),
		'script.create'			=> array('CControllerScriptCreate',			null,					null),
		'script.delete'			=> array('CControllerScriptDelete',			null,					null),
		'script.edit'			=> array('CControllerScriptEdit',			'layout.htmlpage',		'administration.script.edit'),
		'script.list'			=> array('CControllerScriptList',			'layout.htmlpage',		'administration.script.list'),
		'script.update'			=> array('CControllerScriptUpdate',			null,					null),
		'system.warning'		=> array('CControllerSystemWarning',		'layout.warning',		'system.warning'),
		'widget.discovery.view'	=> array('CControllerWidgetDiscoveryView',	'layout.widget',		'monitoring.widget.discovery.view'),
		'widget.hosts.view'		=> array('CControllerWidgetHostsView',		'layout.widget',		'monitoring.widget.hosts.view'),
		'widget.issues.view'	=> array('CControllerWidgetIssuesView',		'layout.widget',		'monitoring.widget.issues.view'),
		'widget.status.view'	=> array('CControllerWidgetStatusView',		'layout.widget',		'monitoring.widget.status.view'),
		'widget.system.view'	=> array('CControllerWidgetSystemView',		'layout.widget',		'monitoring.widget.system.view'),
		'widget.web.view'		=> array('CControllerWidgetWebView',		'layout.widget',		'monitoring.widget.web.view')
	);

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
