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

	private $default_action = 'dashboard.get';
	private $layout = 'default_layout';
	private $controller = 'default_controller';
	private $view = 'default_view';

	private $request = array();

	private $routes = array(
// action layout controller view
		'proxy.create'		=> array('general.page.layout', 'CControllerProxyCreate',		'administration.proxy.create'),
		'proxy.delete'		=> array('general.page.layout', 'CControllerProxyDelete',		'administration.proxy.delete'),
		'proxy.edit'		=> array('general.page.layout', 'CControllerProxyEdit',			'administration.proxy.edit'),
		'proxy.list'		=> array('general.page.layout', 'CControllerProxyList',			'administration.proxy.list'),
// TODO No need to specify layout and view here. Perhaps we should set layout=general.page.redirect or similar?
		'proxy.massdelete'	=> array('general.page.layout', 'CControllerProxyMassDelete',	'administration.proxy.list'),
		'proxy.massdisable'	=> array('general.page.layout', 'CControllerProxyMassDisable',	'administration.proxy.list'),
		'proxy.massenable'	=> array('general.page.layout', 'CControllerProxyMassEnable',	'administration.proxy.list'),
		'proxy.update'		=> array('general.page.layout', 'CControllerProxyUpdate',		'administration.proxy.list')
	);

	public function __construct($request) {
		$this->request = $request;
		$this->calculateRoute();
	}

	public function calculateRoute() {
		$action = isset($this->request['action']) ? $this->request['action'] : $this->default_action;
		if (isset($this->routes[$action]))
		{
			$this->layout = $this->routes[$action][0];
			$this->controller = $this->routes[$action][1];
			$this->view = $this->routes[$action][2];
		}
	}

	public function getLayout() {
		return $this->layout;
	}

	public function getController() {
		return $this->controller;
	}

	public function getView() {
		return $this->view;
	}
}
