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

require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of proxies');
$page['file'] = 'proxies.php';
$page['hist_arg'] = array('');

// Set default action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'proxy.list';
$_REQUEST['action'] = $action;

$router = new CRouter($_REQUEST);

$controller = $router->getController();

$controller = new $controller();
$response = $controller->run();


// Controller returned data
if ($response instanceof CControllerResponseData)
{
	$view = new CView($router->getView(), $response->getData());

	$data['main_block'] = $view->getOutput();
	$layout = new CView($router->getLayout(), $data);
	echo $layout->getOutput();
}
else if ($response instanceof CControllerResponseRedirect)
{
	header('Content-Type: text/html; charset=UTF-8');
	session_start();
	if ($response->getMessageOk() !== null) {
		$_SESSION['msg'] = $response->getMessageOk();
	}
	if ($response->getMessageError() !== null) {
		$_SESSION['msg_err'] = $response->getMessageError();
	}
	if ($response->getFormData() !== null) {
		$_SESSION['formData'] = $response->getFormData();
	}

	redirect($response->getLocation());
}
else
{
	// Exception
}
