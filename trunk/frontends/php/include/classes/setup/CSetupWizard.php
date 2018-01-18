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


class CSetupWizard extends CForm {

	function __construct() {
		$this->DISABLE_CANCEL_BUTTON = false;
		$this->DISABLE_BACK_BUTTON = false;
		$this->SHOW_RETRY_BUTTON = false;
		$this->STEP_FAILED = false;
		$this->frontendSetup = new CFrontendSetup();

		$this->stage = [
			0 => [
				'title' => _('Welcome'),
				'fnc' => 'stage0'
			],
			1 => [
				'title' => _('Check of pre-requisites'),
				'fnc' => 'stage1'
			],
			2 => [
				'title' => _('Configure DB connection'),
				'fnc' => 'stage2'
			],
			3 => [
				'title' => _('Zabbix server details'),
				'fnc' => 'stage3'
			],
			4 => [
				'title' => _('Pre-installation summary'),
				'fnc' => 'stage4'
			],
			5 => [
				'title' => _('Install'),
				'fnc' => 'stage5'
			]
		];

		$this->eventHandler();

		parent::__construct('post');
	}

	function getConfig($name, $default = null) {
		return CSession::keyExists($name) ? CSession::getValue($name) : $default;
	}

	function setConfig($name, $value) {
		CSession::setValue($name, $value);
	}

	function getStep() {
		return $this->getConfig('step', 0);
	}

	function doNext() {
		if (isset($this->stage[$this->getStep() + 1])) {
			$this->setConfig('step', $this->getStep('step') + 1);

			return true;
		}

		return false;
	}

	function doBack() {
		if (isset($this->stage[$this->getStep() - 1])) {
			$this->setConfig('step', $this->getStep('step') - 1);

			return true;
		}

		return false;
	}

	protected function bodyToString($destroy = true) {
		$setup_left = (new CDiv([
			(new CDiv())->addClass(ZBX_STYLE_SIGNIN_LOGO), $this->getList()
		]))->addClass(ZBX_STYLE_SETUP_LEFT);

		$setup_right = (new CDiv($this->getStage()))->addClass(ZBX_STYLE_SETUP_RIGHT);

		if (CWebUser::$data && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			$cancel_button = (new CSubmit('cancel', _('Cancel')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass(ZBX_STYLE_FLOAT_LEFT);
			if ($this->DISABLE_CANCEL_BUTTON) {
				$cancel_button->setEnabled(false);
			}
		}
		else {
			$cancel_button = null;
		}

		if (array_key_exists($this->getStep() + 1, $this->stage)) {
			$next_button = new CSubmit('next['.$this->getStep().']', _('Next step'));
		}
		else {
			$next_button = new CSubmit($this->SHOW_RETRY_BUTTON ? 'retry' : 'finish', _('Finish'));
		}

		$back_button = (new CSubmit('back['.$this->getStep().']', _('Back')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(ZBX_STYLE_FLOAT_LEFT);

		if ($this->getStep() == 0 || $this->DISABLE_BACK_BUTTON) {
			$back_button->setEnabled(false);
		}

		$setup_footer = (new CDiv([new CDiv([$next_button, $back_button]), $cancel_button]))
			->addClass(ZBX_STYLE_SETUP_FOOTER);

		$setup_container = (new CDiv([$setup_left, $setup_right, $setup_footer]))->addClass(ZBX_STYLE_SETUP_CONTAINER);

		return parent::bodyToString($destroy).$setup_container->toString();
	}

	function getList() {
		$list = new CList();

		foreach ($this->stage as $id => $data) {
			$list->addItem($data['title'], ($id <= $this->getStep()) ? ZBX_STYLE_SETUP_LEFT_CURRENT : null);
		}

		return $list;
	}

	function getStage() {
		$function = $this->stage[$this->getStep()]['fnc'];
		return $this->$function();
	}

	function stage0() {
		preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version);
		$setup_title = (new CDiv([new CSpan(_('Welcome to')), 'Zabbix '.$version[0]]))->addClass(ZBX_STYLE_SETUP_TITLE);

		return (new CDiv($setup_title))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY);
	}

	function stage1() {
		$table = (new CTable())
			->addClass(ZBX_STYLE_LIST_TABLE)
			->setHeader(['', _('Current value'), _('Required'), '']);

		$messages = [];
		$finalResult = CFrontendSetup::CHECK_OK;

		foreach ($this->frontendSetup->checkRequirements() as $req) {
			if ($req['result'] == CFrontendSetup::CHECK_OK) {
				$class = ZBX_STYLE_GREEN;
				$result = 'OK';
			}
			elseif ($req['result'] == CFrontendSetup::CHECK_WARNING) {
				$class = ZBX_STYLE_ORANGE;
				$result = new CSpan(_x('Warning', 'setup'));
			}
			else {
				$class = ZBX_STYLE_RED;
				$result = new CSpan(_('Fail'));
				$messages[] = ['type' => 'error', 'message' => $req['error']];
			}

			$table->addRow(
				[
					$req['name'],
					$req['current'],
					($req['required'] !== null) ? $req['required'] : '',
					(new CCol($result))->addClass($class)
				]
			);

			if ($req['result'] > $finalResult) {
				$finalResult = $req['result'];
			}
		}

		if ($finalResult == CFrontendSetup::CHECK_FATAL) {
			$message_box = makeMessageBox(false, $messages, null, false, true);
		}
		else {
			$message_box = null;
		}

		return [
			new CTag('h1', true, _('Check of pre-requisites')),
			(new CDiv([$message_box, $table]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage2() {
		$DB['TYPE'] = $this->getConfig('DB_TYPE');

		$table = new CFormList();

		$table->addRow(_('Database type'),
			new CComboBox('type', $DB['TYPE'], 'submit()', CFrontendSetup::getSupportedDatabases())
		);

		$table->addRow(_('Database host'),
			(new CTextBox('server', $this->getConfig('DB_SERVER', 'localhost')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow(_('Database port'), [
			(new CNumericBox('port', $this->getConfig('DB_PORT', '0'), 5, false, false, false))
				->removeAttribute('style')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSpan(_('0 - use default port')))->addClass(ZBX_STYLE_GREY)
		]);

		$table->addRow(_('Database name'),
			(new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		if ($DB['TYPE'] == ZBX_DB_DB2 || $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
			$table->addRow(_('Database schema'),
				(new CTextBox('schema', $this->getConfig('DB_SCHEMA', '')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
		}

		$table->addRow(_('User'),
			(new CTextBox('user', $this->getConfig('DB_USER', 'zabbix')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);
		$table->addRow(_('Password'),
			(new CPassBox('password', $this->getConfig('DB_PASSWORD')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		if ($this->STEP_FAILED) {
			global $ZBX_MESSAGES;

			$message_box = makeMessageBox(false, $ZBX_MESSAGES, _('Cannot connect to the database.'), false, true);
		}
		else {
			$message_box = null;
		}

		return [
			new CTag('h1', true, _('Configure DB connection')),
			(new CDiv([
				new CTag('p', true, _s('Please create database manually, and set the configuration parameters for connection to this database. Press "%1$s" button when done.', _('Next step'))),
				$message_box,
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage3() {
		$table = new CFormList();

		$table->addRow(_('Host'),
			(new CTextBox('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow(_('Port'),
			(new CNumericBox('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051'), 5, false, false, false))
				->removeAttribute('style')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow('Name',
			(new CTextBox('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		return [
			new CTag('h1', true, _('Zabbix server details')),
			(new CDiv([
				new CTag('p', true, _('Please enter the host name or host IP address and port number of the Zabbix server, as well as the name of the installation (optional).')),
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage4() {
		$db_type = $this->getConfig('DB_TYPE');
		$databases = CFrontendSetup::getSupportedDatabases();

		$table = new CFormList();
		$table->addRow((new CSpan(_('Database type')))->addClass(ZBX_STYLE_GREY), $databases[$db_type]);

		$db_port = ($this->getConfig('DB_PORT') == 0) ? _('default') : $this->getConfig('DB_PORT');
		$db_password = preg_replace('/./', '*', $this->getConfig('DB_PASSWORD'));

		$table->addRow((new CSpan(_('Database server')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_SERVER'));
		$table->addRow((new CSpan(_('Database port')))->addClass(ZBX_STYLE_GREY), $db_port);
		$table->addRow((new CSpan(_('Database name')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_DATABASE'));
		$table->addRow((new CSpan(_('Database user')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_USER'));
		$table->addRow((new CSpan(_('Database password')))->addClass(ZBX_STYLE_GREY), $db_password);
		if ($db_type == ZBX_DB_DB2 || $db_type == ZBX_DB_POSTGRESQL) {
			$table->addRow((new CSpan(_('Database schema')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_SCHEMA'));
		}

		$table->addRow(null, null);

		$table->addRow((new CSpan(_('Zabbix server')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER'));
		$table->addRow((new CSpan(_('Zabbix server port')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER_PORT'));
		$table->addRow((new CSpan(_('Zabbix server name')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER_NAME'));

		return [
			new CTag('h1', true, _('Pre-installation summary')),
			(new CDiv([
				new CTag('p', true, _s('Please check configuration parameters. If all is correct, press "%1$s" button, or "%2$s" button to change configuration parameters.', _('Next step'), _('Back'))),
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage5() {
		$this->setConfig('ZBX_CONFIG_FILE_CORRECT', true);

		$config_file_name = Z::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH;
		$config = new CConfigFile($config_file_name);
		$config->config = [
			'DB' => [
				'TYPE' => $this->getConfig('DB_TYPE'),
				'SERVER' => $this->getConfig('DB_SERVER'),
				'PORT' => $this->getConfig('DB_PORT'),
				'DATABASE' => $this->getConfig('DB_DATABASE'),
				'USER' => $this->getConfig('DB_USER'),
				'PASSWORD' => $this->getConfig('DB_PASSWORD'),
				'SCHEMA' => $this->getConfig('DB_SCHEMA')
			],
			'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
			'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
			'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
		];

		$error = false;

		if (!$config->save()) {
			$error = true;
			$messages[] = [
				'type' => 'error',
				'message' => $config->error
			];
		}

		if ($error) {
			$this->SHOW_RETRY_BUTTON = true;

			$this->setConfig('ZBX_CONFIG_FILE_CORRECT', false);

			$message_box = makeMessageBox(false, $messages, _('Cannot create the configuration file.'), false, true);
			$message = [
				new CTag('p', true, _('Alternatively, you can install it manually:')),
				new CTag('ol', true, [
					new CTag('li', true, new CLink(_('Download the configuration file'), 'setup.php?save_config=1')),
					new CTag('li', true, _s('Save it as "%1$s"', $config_file_name))
				]),
			];
		}
		else {
			$this->DISABLE_CANCEL_BUTTON = true;
			$this->DISABLE_BACK_BUTTON = true;

			$message_box = null;
			$message = [
				(new CTag('h1', true, _('Congratulations! You have successfully installed Zabbix frontend.')))
					->addClass(ZBX_STYLE_GREEN),
				new CTag('p', true, _s('Configuration file "%1$s" created.', $config_file_name))
			];
		}

		return [
			new CTag('h1', true, _('Install')),
			(new CDiv([$message_box, $message]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function checkConnection() {
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

			if (!zbx_empty($DB['SCHEMA']) && $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
				$db_schema = DBselect('SELECT schema_name FROM information_schema.schemata WHERE schema_name = \''.pg_escape_string($DB['SCHEMA']).'\';');
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

	function eventHandler() {
		if (hasRequest('back') && array_key_exists($this->getStep(), getRequest('back'))) {
			$this->doBack();
		}

		if ($this->getStep() == 1) {
			if (hasRequest('next') && array_key_exists(1, getRequest('next'))) {
				$finalResult = CFrontendSetup::CHECK_OK;
				foreach ($this->frontendSetup->checkRequirements() as $req) {
					if ($req['result'] > $finalResult) {
						$finalResult = $req['result'];
					}
				}

				if ($finalResult == CFrontendSetup::CHECK_FATAL) {
					$this->STEP_FAILED = true;
					unset($_REQUEST['next']);
				}
				else {
					$this->doNext();
				}
			}
		}
		elseif ($this->getStep() == 2) {
			$this->setConfig('DB_TYPE', getRequest('type', $this->getConfig('DB_TYPE')));
			$this->setConfig('DB_SERVER', getRequest('server', $this->getConfig('DB_SERVER', 'localhost')));
			$this->setConfig('DB_PORT', getRequest('port', $this->getConfig('DB_PORT', '0')));
			$this->setConfig('DB_DATABASE', getRequest('database', $this->getConfig('DB_DATABASE', 'zabbix')));
			$this->setConfig('DB_USER', getRequest('user', $this->getConfig('DB_USER', 'root')));
			$this->setConfig('DB_PASSWORD', getRequest('password', $this->getConfig('DB_PASSWORD', '')));
			$this->setConfig('DB_SCHEMA', getRequest('schema', $this->getConfig('DB_SCHEMA', '')));

			if (hasRequest('next') && array_key_exists(2, getRequest('next'))) {
				if ($this->checkConnection()) {
					$this->doNext();
				}
				else {
					$this->STEP_FAILED = true;
					unset($_REQUEST['next']);
				}
			}
		}
		elseif ($this->getStep() == 3) {
			$this->setConfig('ZBX_SERVER', getRequest('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost')));
			$this->setConfig('ZBX_SERVER_PORT', getRequest('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051')));
			$this->setConfig('ZBX_SERVER_NAME', getRequest('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')));

			if (hasRequest('next') && array_key_exists(3, getRequest('next'))) {
				$this->doNext();
			}
		}
		elseif ($this->getStep() == 4) {
			if (hasRequest('next') && array_key_exists(4, getRequest('next'))) {
				$this->doNext();
			}
		}
		elseif ($this->getStep() == 5) {
			if (hasRequest('save_config')) {
				// make zabbix.conf.php downloadable
				header('Content-Type: application/x-httpd-php');
				header('Content-Disposition: attachment; filename="'.basename(CConfigFile::CONFIG_FILE_PATH).'"');
				$config = new CConfigFile(Z::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH);
				$config->config = [
					'DB' => [
						'TYPE' => $this->getConfig('DB_TYPE'),
						'SERVER' => $this->getConfig('DB_SERVER'),
						'PORT' => $this->getConfig('DB_PORT'),
						'DATABASE' => $this->getConfig('DB_DATABASE'),
						'USER' => $this->getConfig('DB_USER'),
						'PASSWORD' => $this->getConfig('DB_PASSWORD'),
						'SCHEMA' => $this->getConfig('DB_SCHEMA')
					],
					'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
					'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
					'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
				];
				die($config->getString());
			}
		}

		if (hasRequest('next') && array_key_exists($this->getStep(), getRequest('next'))) {
			$this->doNext();
		}
	}
}
