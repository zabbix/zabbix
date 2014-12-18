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
	private $layout = null;
	private $controller = null;
	private $view = null;

	private $action = null;

	private $routes = array(
// action layout controller view
		'proxy.create'		=> array(null,					'CControllerProxyCreate',		null),
		'proxy.delete'		=> array(null,					'CControllerProxyDelete',		null),
		'proxy.formcreate'	=> array('general.page.layout', 'CControllerProxyFormCreate',	'administration.proxy.edit'),
		'proxy.formedit'	=> array('general.page.layout', 'CControllerProxyFormEdit',		'administration.proxy.edit'),
		'proxy.list'		=> array('general.page.layout', 'CControllerProxyList',			'administration.proxy.list'),
		'proxy.massdelete'	=> array(null,					'CControllerProxyMassDelete',	null),
		'proxy.massdisable'	=> array(null,					'CControllerProxyMassDisable',	null),
		'proxy.massenable'	=> array(null,					'CControllerProxyMassEnable',	null),
		'proxy.update'		=> array(null,					'CControllerProxyUpdate',		null),
		'script.create'		=> array(null,					'CControllerScriptCreate',		null),
		'script.delete'		=> array(null,					'CControllerScriptDelete',		null),
		'script.formcreate'	=> array('general.page.layout', 'CControllerScriptFormCreate',	'administration.script.edit'),
		'script.formedit'	=> array('general.page.layout', 'CControllerScriptFormEdit',	'administration.script.edit'),
		'script.list'		=> array('general.page.layout', 'CControllerScriptList',		'administration.script.list'),
		'script.massdelete'	=> array(null,					'CControllerScriptMassDelete',	null),
		'script.update'		=> array(null,					'CControllerScriptUpdate',		null)
	);

	public function __construct($action) {
		$this->action = $action;
		$this->calculateRoute();
	}

	public function calculateRoute() {
		if (isset($this->routes[$this->action]))
		{
			$this->layout = $this->routes[$this->action][0];
			$this->controller = $this->routes[$this->action][1];
			$this->view = $this->routes[$this->action][2];
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

	public function getAction() {
		return $this->action;
	}
}
