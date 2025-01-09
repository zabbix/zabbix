<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/classes/core/APP.php';

try {
	APP::getInstance()->run(APP::EXEC_MODE_DEFAULT);
}
catch (DBException $e) {
	echo (new CView('general.warning', [
		'header' => 'Database error',
		'messages' => [CWebUser::getType() == USER_TYPE_SUPER_ADMIN && $e->getCode() != DB::INIT_ERROR
			? _('System error occurred. Please contact Zabbix administrator.')
			: $e->getMessage()
		],
		'theme' => ZBX_DEFAULT_THEME
	]))->getOutput();

	exit;
}
catch (ConfigFileException $e) {
	switch ($e->getCode()) {
		case CConfigFile::CONFIG_NOT_FOUND:
			redirect('setup.php');
			exit;

		case CConfigFile::CONFIG_ERROR:
			echo (new CView('general.warning', [
				'header' => 'Configuration file error',
				'messages' => [$e->getMessage()],
				'theme' => ZBX_DEFAULT_THEME
			]))->getOutput();

			exit;

		case CConfigFile::CONFIG_VAULT_ERROR:
			echo (new CView('general.warning', [
				'header' => _('Vault connection failed.'),
				'messages' => [$e->getMessage()],
				'theme' => ZBX_DEFAULT_THEME
			]))->getOutput();

			exit;
	}
}
catch (Exception $e) {
	echo (new CView('general.warning', [
		'header' => $e->getMessage(),
		'messages' => [],
		'theme' => ZBX_DEFAULT_THEME
	]))->getOutput();

	exit;
}

CProfiler::getInstance()->start();

global $page;

$page = [
	'title' => null,
	'file' => null,
	'scripts' => null,
	'type' => null,
	'menu' => null
];
