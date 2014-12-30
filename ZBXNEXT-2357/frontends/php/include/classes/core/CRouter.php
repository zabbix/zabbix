<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		// action					controller						layout					view
		'discovery.view'	=> array('CControllerDiscoveryView',	'layout.htmlpage',		'monitoring.discovery.view'),
		'favourite.create'	=> array('CControllerFavouriteCreate',	'layout.javascript',	null),
		'favourite.delete'	=> array('CControllerFavouriteDelete',	'layout.javascript',	null),
		'map.view'			=> array('CControllerMapView',			'layout.htmlpage',		'monitoring.map.view'),
		'proxy.create'		=> array('CControllerProxyCreate',		null,					null),
		'proxy.delete'		=> array('CControllerProxyDelete',		null,					null),
		'proxy.formcreate'	=> array('CControllerProxyFormCreate',	'layout.htmlpage',		'administration.proxy.edit'),
		'proxy.formedit'	=> array('CControllerProxyFormEdit',	'layout.htmlpage',		'administration.proxy.edit'),
		'proxy.list'		=> array('CControllerProxyList',		'layout.htmlpage',		'administration.proxy.list'),
		'proxy.massdelete'	=> array('CControllerProxyMassDelete',	null,					null),
		'proxy.massdisable'	=> array('CControllerProxyMassDisable',	null,					null),
		'proxy.massenable'	=> array('CControllerProxyMassEnable',	null,					null),
		'proxy.update'		=> array('CControllerProxyUpdate',		null,					null),
		'script.create'		=> array('CControllerScriptCreate',		null,					null),
		'script.delete'		=> array('CControllerScriptDelete',		null,					null),
		'script.formcreate'	=> array('CControllerScriptFormCreate',	'layout.htmlpage',		'administration.script.edit'),
		'script.formedit'	=> array('CControllerScriptFormEdit',	'layout.htmlpage',		'administration.script.edit'),
		'script.list'		=> array('CControllerScriptList',		'layout.htmlpage',		'administration.script.list'),
		'script.massdelete'	=> array('CControllerScriptMassDelete',	null,					null),
		'script.update'		=> array('CControllerScriptUpdate',		null,					null)
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
		if (isset($this->routes[$this->action]))
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
