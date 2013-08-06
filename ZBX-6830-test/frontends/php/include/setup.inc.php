<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


function zbx_is_callable($var) {
	foreach ($var as $e) {
		if (!is_callable($e)) {
			return false;
		}
	}

	return true;
}

class CsetupWizard extends CForm {

	function __construct(&$ZBX_CONFIG) {
		$this->DISABLE_NEXT_BUTTON = false;
		$this->ZBX_CONFIG = &$ZBX_CONFIG;

		$this->stage = array(
			0 => array(
				'title' => '1. Welcome',
				'fnc' => 'stage1'
			),
			1 => array(
				'title' => '2. Check of pre-requisites',
				'fnc' => 'stage2'
			),
			2 => array(
				'title' => '3. Configure DB connection',
				'fnc' => 'stage3'
			),
			3 => array(
				'title' => '4. Zabbix server details',
				'fnc' => 'stage4'
			),
			4 => array(
				'title' => '5. Pre-Installation summary',
				'fnc' => 'stage5'
			),
			5 => array(
				'title' => '6. Install',
				'fnc' => 'stage6'
			)
		);

		$this->EventHandler();

		parent::__construct('post');
	}

	function getConfig($name, $default = null) {
		return isset($this->ZBX_CONFIG[$name]) ? $this->ZBX_CONFIG[$name] : $default;
	}

	function setConfig($name, $value) {
		return ($this->ZBX_CONFIG[$name] = $value);
	}

	function getStep() {
		return $this->getConfig('step', 0);
	}

	function DoNext() {
		if (isset($this->stage[$this->getStep() + 1])) {
			$this->ZBX_CONFIG['step']++;
			return true;
		}
		return false;
	}

	function DoBack() {
		if (isset($this->stage[$this->getStep() - 1])) {
			$this->ZBX_CONFIG['step']--;
			return true;
		}
		return false;
	}

	function bodyToString($destroy = true) {
		$left = new CDiv(null, 'left');
		$left->addItem(new CDiv(null, 'setup_logo'));
		$left->addItem(new CDiv(ZABBIX_VERSION, 'setup_version'));
		$left->addItem(new CDiv($this->getList(), 'left_menu'));

		$link1 = new CLink('www.zabbix.com', 'http://www.zabbix.com/', null, null, true);
		$link1->setAttribute('target', '_blank');

		$link2 = new CLink('GPL v2', 'http://www.zabbix.com/license.php', null, null, true);
		$link2->setAttribute('target', '_blank');

		$licence = new CDiv(array($link1, BR(), ' Licensed under ', $link2), 'setup_wizard_licence');
		$left->addItem($licence);

		$right = new CDiv(null, 'right');
		if ($this->getStep() == 0) {
			$right->addItem(new CDiv(null, 'blank_title'));
			$right->addItem(new CDiv($this->getState(), 'blank_under_title'));
			$container = new CDiv(array($left, $right), 'setup_wizard setup_wizard_welcome');
		}
		else {
			$right->addItem(new CDiv($this->stage[$this->getStep()]['title'], 'setup_title'));
			$right->addItem(new CDiv($this->getState(), 'under_title'));
			$container = new CDiv(array($left, $right), 'setup_wizard');
		}

		if (isset($this->stage[$this->getStep() + 1])) {
			$next = new CSubmit('next['.$this->getStep().']', _('Next').' >>');
		}
		else {
			$next = new CSubmit('finish', _('Finish'));
		}

		if (isset($this->HIDE_CANCEL_BUTTON) && $this->HIDE_CANCEL_BUTTON) {
			$cancel = null;
		}
		else {
			$cancel = new CDiv(new CSubmit('cancel', _('Cancel')), 'footer_left');
		}

		if ($this->DISABLE_NEXT_BUTTON) {
			$next->setEnabled(false);
		}

		$footer = new CDiv(array(
			$cancel,
			new CDiv(array(
				$this->getStep() != 0 ? new CSubmit('back['.$this->getStep().']', '<< '._('Previous')) : null,
				$next), 'footer_right')
			), 'footer');

		$container->addItem($footer);


		return parent::bodyToString($destroy).$container->ToString();
	}

	function getList() {
		$list = new CList();
		foreach ($this->stage as $id => $data) {
			if ($id < $this->getStep()) {
				$style = 'completed';
			}
			elseif ($id == $this->getStep() && $this->getStep() != 0) {
				$style = 'current';
			}
			else {
				$style = null;
			}

			$list->addItem($data['title'], $style);
		}
		return $list;
	}

	function getState() {
		$fnc = $this->stage[$this->getStep()]['fnc'];
		return $this->$fnc();
	}

	function stage1() {
		return null;
	}

	function stage2() {
		$table = new CTable(null, 'requirements');
		$table->setAlign('center');

		$final_result = true;

		$table->addRow(array(
			SPACE,
			new CCol(_('Current value'), 'header'),
			new CCol(_('Required'), 'header')
		));

		$reqs = FrontendSetup::i()->checkRequirements();
		foreach ($reqs as $req) {
			$result = null;
			if ($req['result']) {
				$result = new CSpan(_('OK'), 'green');
			}
			else {
				$result = new CSpan(_('Fail'), 'link_menu fail');
				$result->setHint($req['error']);
			}

			$table->addRow(array(
				new CCol(
					$req['name'], 'header'),
					$req['current'],
					$req['required'] ? $req['required'] : SPACE,
					$result
				),
				$req['result'] ? SPACE : 'fail');

			$final_result &= (bool) $req['result'];
		}

		if (!$final_result) {
			$this->DISABLE_NEXT_BUTTON = true;

			$final_result = array(
				_('Please correct all issues and press "Retry" button'),
				BR(),
				new CSubmit('retry', _('Retry')));
		}
		else {
			$this->DISABLE_NEXT_BUTTON = false;
			$final_result = array(new CSpan(_('OK'), 'ok'));
		}

		return array(new CDiv(array(BR(), $table, BR()), 'table_wraper'), new CDiv($final_result, 'info_bar'));
	}

	function stage3() {
		global $ZBX_CONFIG;

		$table = new CTable(null, 'requirements');
		$table->setAlign('center');

		$DB['TYPE'] = $this->getConfig('DB_TYPE');

		$cmbType = new CComboBox('type', $DB['TYPE'], 'this.form.submit();');
		foreach ($ZBX_CONFIG['allowed_db'] as $id => $name) {
			$cmbType->addItem($id, $name);
		}
		$table->addRow(array(new CCol(_('Database type'), 'header'), $cmbType));
		switch ($DB['TYPE']) {
			case ZBX_DB_SQLITE3:
				$table->addRow(array(new CCol(_('Database file'), 'header'), new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix'))));
			break;
			default:
				$table->addRow(array(
					new CCol(_('Database host'), 'header'),
					new CTextBox('server', $this->getConfig('DB_SERVER', 'localhost'))
				));
				$table->addRow(array(
					new CCol(_('Database port'), 'header'),
					array(
						new CNumericBox('port', $this->getConfig('DB_PORT', '0'), 5),
						' 0 - use default port'
					)
				));
				$table->addRow(array(
					new CCol(_('Database name'), 'header'),
					new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix'))
				));
				if ($DB['TYPE'] == ZBX_DB_DB2) {
					$table->addRow(array(
						new CCol(_('Database schema'), 'header'),
						new CTextBox('schema', $this->getConfig('DB_SCHEMA', ''))
					));
				}
				$table->addRow(array(
					new CCol(_('User'), 'header'),
					new CTextBox('user', $this->getConfig('DB_USER', 'root'))
				));
				$table->addRow(array(
					new CCol(_('Password'), 'header'),
					new CPassBox('password', $this->getConfig('DB_PASSWORD', ''))
				));
			break;
		}

		global $ZBX_MESSAGES;
		if (!empty($ZBX_MESSAGES)) {
			$lst_error = new CList(null, 'messages');
			foreach ($ZBX_MESSAGES as $msg) {
				$lst_error->addItem($msg['message'], $msg['type']);
			}

			$table = array($table, $lst_error);
		}

		return array(
			new CDiv(new CDiv(array(
				'Please create database manually, and set the configuration parameters for connection to this database.', BR(), BR(),
				'Press "Test connection" button when done.', BR(),
				$table
			), 'vertical_center'), 'table_wraper'),

			new CDiv(array(
				isset($_REQUEST['type']) ? !$this->DISABLE_NEXT_BUTTON ?
					new CSpan(array(_('OK'), BR()), 'ok')
					: new CSpan(array(_('Fail'), BR()), 'fail')
					: null,
				new  CSubmit('retry', 'Test connection')
			), 'info_bar')

		);
	}

	function stage4() {
		$table = new CTable(null, 'requirements');
		$table->setAlign('center');

		$table->addRow(array(
			new CCol(_('Host'), 'header'),
			new CTextBox('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost'))
		));
		$table->addRow(array(
			new CCol(_('Port'), 'header'),
			new CNumericBox('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051'), 5)
		));
		$table->addRow(array(
			new CCol(_('Name'), 'header'),
			new CTextBox('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', ''))
		));

		return array(
			'Please enter host name or host IP address', BR(),
			'and port number of Zabbix server,', BR(),
			'as well as the name of the installation (optional).', BR(), BR(),
			$table,
		);
	}

	function stage5() {
		$allowed_db = $this->getConfig('allowed_db', array());

		$DB['TYPE'] = $this->getConfig('DB_TYPE');

		$table = new CTable(null, 'requirements');
		$table->setAlign('center');
		$table->addRow(array(
			new CCol(_('Database type'), 'header'),
			$allowed_db[$this->getConfig('DB_TYPE')]
		));

		switch ($DB['TYPE']) {
			case ZBX_DB_SQLITE3:
				$table->addRow(array(
					new CCol(_('Database file'), 'header'),
					$this->getConfig('DB_DATABASE')
				));
				break;
			default:
				$table->addRow(array(new CCol(_('Database server'), 'header'), $this->getConfig('DB_SERVER')));
				if ($this->getConfig('DB_PORT') == 0) {
					$table->addRow(array(new CCol(_('Database port'), 'header'), _('default')));
				}
				else {
					$table->addRow(array(new CCol(_('Database port'), 'header'), $this->getConfig('DB_PORT')));
				}
				$table->addRow(array(new CCol(_('Database name'), 'header'), $this->getConfig('DB_DATABASE')));
				$table->addRow(array(new CCol(_('Database user'), 'header'), $this->getConfig('DB_USER')));
				$table->addRow(array(new CCol(_('Database password'), 'header'), preg_replace('/./', '*', $this->getConfig('DB_PASSWORD'))));
				if ($this->getConfig('DB_TYPE', '') == ZBX_DB_DB2) {
					$table->addRow(array(new CCol(_('Database schema'), 'header'), $this->getConfig('DB_SCHEMA')));
				}
				break;
		}

		$table->addRow(BR());
		$table->addRow(array(new CCol(_('Zabbix server'), 'header'), $this->getConfig('ZBX_SERVER')));
		$table->addRow(array(new CCol(_('Zabbix server port'), 'header'), $this->getConfig('ZBX_SERVER_PORT')));
		$table->addRow(array(new CCol(_('Zabbix server name'), 'header'), $this->getConfig('ZBX_SERVER_NAME')));

		return array(
			'Please check configuration parameters.', BR(),
			'If all is correct, press "Next" button, or "Previous" button to change configuration parameters.', BR(), BR(),
			$table
		);
	}

	function stage6() {
		$this->setConfig('ZBX_CONFIG_FILE_CORRECT', true);

		$config = new CConfigFile(Z::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH);
		$config->config = array(
			'DB' => array(
				'TYPE' => $this->getConfig('DB_TYPE'),
				'SERVER' => $this->getConfig('DB_SERVER'),
				'PORT' => $this->getConfig('DB_PORT'),
				'DATABASE' => $this->getConfig('DB_DATABASE'),
				'USER' => $this->getConfig('DB_USER'),
				'PASSWORD' => $this->getConfig('DB_PASSWORD'),
				'SCHEMA' => $this->getConfig('DB_SCHEMA')
			),
			'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
			'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
			'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
		);
		$config->save();

		try {
			$error = false;
			$config->load();

			if ($config->config['DB']['TYPE'] != $this->getConfig('DB_TYPE')) {
				$error = true;
			}
			elseif ($config->config['DB']['SERVER'] != $this->getConfig('DB_SERVER')) {
				$error = true;
			}
			elseif ($config->config['DB']['PORT'] != $this->getConfig('DB_PORT')) {
				$error = true;
			}
			elseif ($config->config['DB']['DATABASE'] != $this->getConfig('DB_DATABASE')) {
				$error = true;
			}
			elseif ($config->config['DB']['USER'] != $this->getConfig('DB_USER')) {
				$error = true;
			}
			elseif ($config->config['DB']['PASSWORD'] != $this->getConfig('DB_PASSWORD')) {
				$error = true;
			}
			elseif ($this->getConfig('DB_TYPE') == ZBX_DB_DB2 && $config->config['DB']['SCHEMA'] != $this->getConfig('DB_SCHEMA')) {
				$error = true;
			}
			elseif ($config->config['ZBX_SERVER'] != $this->getConfig('ZBX_SERVER')) {
				$error = true;
			}
			elseif ($config->config['ZBX_SERVER_PORT'] != $this->getConfig('ZBX_SERVER_PORT')) {
				$error = true;
			}
			elseif ($config->config['ZBX_SERVER_NAME'] != $this->getConfig('ZBX_SERVER_NAME')) {
				$error = true;
			}
			$error_text = 'Unable to overwrite the existing configuration file. ';
		}
		catch (ConfigFileException $e) {
			$error = true;
			$error_text = 'Unable to create the configuration file. ';
		}

		clear_messages();
		if ($error) {
			$this->setConfig('ZBX_CONFIG_FILE_CORRECT', false);
		}

		$this->DISABLE_NEXT_BUTTON = !$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false);
		$this->HIDE_CANCEL_BUTTON = !$this->DISABLE_NEXT_BUTTON;


		$table = array('Configuration file', BR(), '"'.Z::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH.'"',
			BR(), 'created: ', $this->getConfig('ZBX_CONFIG_FILE_CORRECT', false)
			? new CSpan(_('OK'), 'ok')
			: new CSpan(_('Fail'), 'fail')
		);

		return array(
			$table, BR(), BR(),
			$this->DISABLE_NEXT_BUTTON ? array(new CSubmit('retry', _('Retry')), BR(), BR()) : null,
			!$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false)
				? array($error_text, BR(), 'Please install it manually, or fix permissions on the conf directory.', BR(), BR(),
					'Press the "Download configuration file" button, download the configuration file ',
					'and save it as ', BR(),
					'"'.Z::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH.'"', BR(), BR(),
					new CSubmit('save_config', 'Download configuration file'),
					BR(), BR()
				)
				: array(
					'Congratulations on successful installation of Zabbix frontend.', BR(), BR()
				),
			'When done, press the '.($this->DISABLE_NEXT_BUTTON ? '"Retry"' : '"Finish"').' button'
		);
	}

	function CheckConnection() {
		global $DB;

		if (!$this->getConfig('check_fields_result')) {
			return false;
		}

		$DB['TYPE'] = $this->getConfig('DB_TYPE');
		if (is_null($DB['TYPE'])) {
			return false;
		}

		$DB['SERVER'] = $this->getConfig('DB_SERVER', 'localhost');
		$DB['PORT'] = $this->getConfig('DB_PORT', '0');
		$DB['DATABASE'] = $this->getConfig('DB_DATABASE', 'zabbix');
		$DB['USER'] = $this->getConfig('DB_USER', 'root');
		$DB['PASSWORD'] = $this->getConfig('DB_PASSWORD', '');
		$DB['SCHEMA'] = $this->getConfig('DB_SCHEMA', '');

		$error = '';

		// during setup set debug to false to avoid displaying unwanted PHP errors in messages
		if (!$result = DBconnect($error)) {
			error($error);
		}
		else {
			$result = true;
			if (!zbx_empty($DB['SCHEMA']) && $DB['TYPE'] == ZBX_DB_DB2) {
				$db_schema = DBselect('SELECT schemaname FROM syscat.schemata WHERE schemaname=\''.db2_escape_string($DB['SCHEMA']).'\'');
				$result = DBfetch($db_schema);
			}

			if ($result) {
				$result = DBexecute('CREATE TABLE zabbix_installation_test (test_row INTEGER)');
				$result &= DBexecute('DROP TABLE zabbix_installation_test');
			}
		}

		DBclose();

		$DB = null;
		return $result;
	}

	function EventHandler() {
		if (isset($_REQUEST['back'][$this->getStep()])) {
			$this->DoBack();
		}

		if ($this->getStep() == 1) {
			if (isset($_REQUEST['next'][$this->getStep()])) {
				$this->DoNext();
			}
			$this->DISABLE_NEXT_BUTTON = true;
		}
		elseif ($this->getStep() == 2) {
			$this->setConfig('DB_TYPE', get_request('type', $this->getConfig('DB_TYPE')));
			$this->setConfig('DB_SERVER', get_request('server', $this->getConfig('DB_SERVER', 'localhost')));
			$this->setConfig('DB_PORT', get_request('port', $this->getConfig('DB_PORT', '0')));
			$this->setConfig('DB_DATABASE', get_request('database', $this->getConfig('DB_DATABASE', 'zabbix')));
			$this->setConfig('DB_USER', get_request('user', $this->getConfig('DB_USER', 'root')));
			$this->setConfig('DB_PASSWORD', get_request('password', $this->getConfig('DB_PASSWORD', '')));
			$this->setConfig('DB_SCHEMA', get_request('schema', $this->getConfig('DB_SCHEMA', '')));

			if (isset($_REQUEST['retry'])) {
				if (!$this->CheckConnection()) {
					$this->DISABLE_NEXT_BUTTON = true;
					unset($_REQUEST['next']);
				}
			}
			elseif (!isset($_REQUEST['next'][$this->getStep()])) {
				$this->DISABLE_NEXT_BUTTON = true;
				unset($_REQUEST['next']);
			}

			if (isset($_REQUEST['next'][$this->getStep()])) {
				$this->DoNext();
			}
		}
		elseif ($this->getStep() == 3) {
			$this->setConfig('ZBX_SERVER', get_request('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost')));
			$this->setConfig('ZBX_SERVER_PORT', get_request('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051')));
			$this->setConfig('ZBX_SERVER_NAME', get_request('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')));
			if (isset($_REQUEST['next'][$this->getStep()])) {
				$this->DoNext();
			}
		}
		elseif ($this->getStep() == 4 && isset($_REQUEST['next'][$this->getStep()])) {
			$this->DoNext();
		}
		elseif ($this->getStep() == 5) {
			if (isset($_REQUEST['save_config'])) {
				global $ZBX_CONFIGURATION_FILE;

				// make zabbix.conf.php downloadable
				header('Content-Type: application/x-httpd-php');
				header('Content-Disposition: attachment; filename="'.basename($ZBX_CONFIGURATION_FILE).'"');
				$config = new CConfigFile($ZBX_CONFIGURATION_FILE);
				$config->config = array(
					'DB' => array(
						'TYPE' => $this->getConfig('DB_TYPE'),
						'SERVER' => $this->getConfig('DB_SERVER'),
						'PORT' => $this->getConfig('DB_PORT'),
						'DATABASE' => $this->getConfig('DB_DATABASE'),
						'USER' => $this->getConfig('DB_USER'),
						'PASSWORD' => $this->getConfig('DB_PASSWORD'),
						'SCHEMA' => $this->getConfig('DB_SCHEMA')
					),
					'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
					'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
					'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
				);
				die($config->getString());
			}
		}

		if (isset($_REQUEST['next'][$this->getStep()])) {
			$this->DoNext();
		}
	}
}
