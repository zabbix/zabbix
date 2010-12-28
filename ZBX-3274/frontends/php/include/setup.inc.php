<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function zbx_is_callable($var){
		foreach($var as $e)
			if(!is_callable($e)) return false;

		return true;
	}

	class CsetupWizard extends CForm{
/* protected *//*
		var $ZBX_CONFIG;
		var $DISABLE_NEXT_BUTTON;
		var $stage = array();
		*/

/* public */
		function __construct(&$ZBX_CONFIG){
			$this->DISABLE_NEXT_BUTTON = false;

			$this->ZBX_CONFIG = &$ZBX_CONFIG;

			$this->stage = array(
				0 => array('title' => '1. Introduction'			, 'fnc' => 'stage0' ),
				1 => array('title' => '2. Licence agreement'		, 'fnc' => 'stage1' ),
				2 => array('title' => '3. Check of pre-requisites'	, 'fnc' => 'stage2' ),
				3 => array('title' => '4. Configure DB connection'	, 'fnc' => 'stage3' ),
				4 => array('title' => '5. Zabbix server details'	, 'fnc' => 'stage4' ),
				//4 => array('title' => '5. Distributed monitoring'	, 'fnc' => 'stage4' ),
				5 => array('title' => '6. Pre-Installation summary'	, 'fnc' => 'stage5' ),
				6 => array('title' => '7. Install'			, 'fnc' => 'stage6' ),
				7 => array('title' => '8. Finish'			, 'fnc' => 'stage7' )
				);

			$this->EventHandler();

			parent::__construct(null, 'post');
		}

		function getConfig($name, $default = null){
			return isset($this->ZBX_CONFIG[$name]) ? $this->ZBX_CONFIG[$name] : $default;
		}

		function setConfig($name, $value){
			return ($this->ZBX_CONFIG[$name] = $value);
		}

		function getStep(){
			return $this->getConfig('step', 0);
		}

		function DoNext(){
			if(isset($this->stage[$this->getStep() + 1])){
				$this->ZBX_CONFIG['step']++;
				return true;
			}
		return false;
		}

		function DoBack(){
			if(isset($this->stage[$this->getStep() - 1])){
				$this->ZBX_CONFIG['step']--;
				return true;
			}
		return false;
		}

		function BodyToString($destroy=true){
			$table = new CTable(null, 'setup_wizard');
			$table->setAlign('center');
			$table->setHeader(array(
				new CCol(S_ZABBIX.SPACE.ZABBIX_VERSION, 'left'),
				SPACE
				),'header');
			$table->addRow(array(SPACE, new CCol($this->stage[$this->getStep()]['title'], 'right')),'title');
			$table->addRow(array(
				new CCol($this->getList(), 'left'),
				new CCol($this->getState(), 'right')
				), 'center');

			$next = new CButton('next['.$this->getStep().']', S_NEXT.' >>');
			if($this->DISABLE_NEXT_BUTTON) $next->setEnabled(false);

			$table->setFooter(array(
				new CCol(new CButton('cancel',S_CANCEL),'left'),
				new CCol(array(
					isset($this->stage[$this->getStep()-1]) ? new CButton('back['.$this->getStep().']', '<< '.S_PREVIOUS) : null,
					isset($this->stage[$this->getStep()+1]) ? $next: new CButton('finish', S_FINISH)
					) , 'right')
				),'footer');

			return parent::BodyToString($destroy).$table->ToString();
		}

		function getList(){
			$list = new CList();
			foreach($this->stage as $id => $data){
				if($id < $this->getStep()) $style = 'completed';
				else if($id == $this->getStep()) $style = 'current';
				else $style = null;

				$list->addItem($data['title'], $style);
			}
		return $list;
		}

		function getState(){
			$fnc = $this->stage[$this->getStep()]['fnc'];
			return  $this->$fnc();
		}

		function stage0(){
			return new CTag('div', 'yes', array('Welcome to the Zabbix frontend installation wizard.',BR(),BR(),
				'This installation wizard will guide you through the installation of Zabbix frontend',BR(),BR(),
				'Click the "Next" button to proceed to the next screen. If you want to change something '.
				'on a previous screen, click "Previous" button',BR(),BR(),
				'You may cancel installation at any time by clicking "Cancel" button'), 'text');
		}

		function stage1(){
			$LICENCE_FILE = 'conf/COPYING';

			$this->DISABLE_NEXT_BUTTON = true;

			$license = 'Missing licence file. See GPL licence.';
			if(file_exists($LICENCE_FILE))
				$license = zbx_nl2br(nbsp(file_get_contents($LICENCE_FILE)));

			$action = <<<JS
if(this.checked) $("next[1]").writeAttribute('disabled', false);
else $("next[1]").writeAttribute('disabled', 'disabled');
JS;

			return array(
				new CDiv(new CSpan($license), 'licence'),
				BR(),
				new CDiv(array(new CCheckBox('agree', 'no', $action), new CLabel('I agree', 'agree')), 'center')
			);
		}

		function stage2(){
			$table = new CTable(null, 'requirements');
			$table->setAlign('center');

			$final_result = true;

			$table->addRow(array(
				SPACE,
				new CCol('Current value', 'header'),
				new CCol('Required', 'header'),
				new CCol('Recommended', 'header'),
				SPACE,
				SPACE
			));

			$reqs = check_php_requirements();
			foreach($reqs as $req){

				$result = null;
				if(!is_null($req['recommended']) && ($req['result'] == 1)){
					$result = new CSpan(S_OK, 'orange');
				}
				else if((!is_null($req['recommended']) && ($req['result'] == 2))
					|| (is_null($req['recommended']) && ($req['result'] == 1))){
					$result = new CSpan(S_OK, 'green');
				}
				else if($req['result'] == 0){
					$result = new CSpan(S_FAIL, 'link_menu fail');
					$result->setHint($req['error']);
				}

				$table->addRow(array(
					new CCol(
						$req['name'], 'header'),
						$req['current'],
						$req['required'] ? $req['required'] : SPACE,
						$req['recommended'] ? $req['recommended'] : SPACE,
						$result
					),
					$req['result'] ? SPACE : 'fail');

				$final_result &= (bool) $req['result'];
			}

			if(!$final_result){
				$this->DISABLE_NEXT_BUTTON = true;

				$this->addVar('trouble',true);

				$final_result = array(
					new CSpan(S_FAIL,'fail'),
					BR(), BR(),
					'Please correct all issues and press "Retry" button',
					BR(), BR(),
					new CButton('retry', S_RETRY)
					);
			}
			else{
				$this->DISABLE_NEXT_BUTTON = false;
				$final_result = new CSpan(S_OK,'ok');
			}

			return array($table, BR(), $final_result);
		}

		function stage3(){
			global $ZBX_CONFIG;

			$table = new CTable(null, 'requirements');
			$table->setAlign('center');

			$DB['TYPE'] = $this->getConfig('DB_TYPE');

			$cmbType = new CComboBox('type', $DB['TYPE'], 'this.form.submit();');
			foreach($ZBX_CONFIG['allowed_db'] as $id => $name){
				$cmbType->addItem($id, $name);
			}
			$table->addRow(array(new CCol(S_TYPE,'header'), $cmbType));
			$table->addRow(array(new CCol(S_HOST,'header'), new CTextBox('server', $this->getConfig('DB_SERVER', 'localhost'))));
			$table->addRow(array(new CCol(S_PORT,'header'), array(new CNumericBox('port', $this->getConfig('DB_PORT', '0'),5),' 0 - use default port')));
			$table->addRow(array(new CCol(S_NAME,'header'), new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix'))));
			$table->addRow(array(new CCol(S_USER,'header'), new CTextBox('user', $this->getConfig('DB_USER',	'root'))));
			$table->addRow(array(new CCol(S_PASSWORD,'header'), new CPassBox('password', $this->getConfig('DB_PASSWORD', ''))));

			if($DB['TYPE'] == 'IBM_DB2')
				$table->addRow(array(new CCol(S_SCHEMA,'header'), new CTextBox('schema', $this->getConfig('DB_SCHEMA', ''))));

			return array(
				'Please create database manually,', BR(),
				'and set the configuration parameters for connection to this database.',
				BR(),BR(),
				'Press "Test connection" button when done.',
				BR(),BR(),
				$table,
				BR(),
				!$this->DISABLE_NEXT_BUTTON ? new CSpan(S_OK, 'ok') :  new CSpan(S_FAIL, 'fail'),
				BR(),
				new CButton('retry', 'Test connection')
			);
		}

		function stage4(){
			$table = new CTable(null, 'requirements');
			$table->setAlign('center');

			$table->addRow(array(new CCol(S_HOST,'header'), new CTextBox('zbx_server',		$this->getConfig('ZBX_SERVER',		'localhost'))));
			$table->addRow(array(new CCol(S_PORT,'header'), new CNumericBox('zbx_server_port',	$this->getConfig('ZBX_SERVER_PORT',	'10051'),5)));
			$table->addRow(array(new CCol(S_NAME,'header'), new CTextBox('zbx_server_name',	$this->getConfig('ZBX_SERVER_NAME',	''))));

			return array(
				'Please enter host name or host IP address', BR(),
				'and port number of Zabbix server,', BR(),
				'as well as the name of the installation (optional).', BR(), BR(),
				$table,
				);
		}

		function stage5(){
			$allowed_db = $this->getConfig('allowed_db', array());

			$table = new CTable(null, 'requirements');
			$table->setAlign('center');
			$table->addRow(array(new CCol('Database type:','header'), $allowed_db[$this->getConfig('DB_TYPE')]));
			$table->addRow(array(new CCol('Database server:','header'), $this->getConfig('DB_SERVER')));
			$table->addRow(array(new CCol('Database port:','header'), $this->getConfig('DB_PORT')));
			$table->addRow(array(new CCol('Database name:','header'), $this->getConfig('DB_DATABASE')));
			$table->addRow(array(new CCol('Database user:','header'), $this->getConfig('DB_USER')));
			$table->addRow(array(new CCol('Database password:','header'),	preg_replace('/./','*',$this->getConfig('DB_PASSWORD'))));
			if($this->getConfig('DB_TYPE', '') == 'IBM_DB2')
				$table->addRow(array(new CCol('Database schema:','header'),	$this->getConfig('DB_SCHEMA')));

			$table->addRow(BR());

			$table->addRow(array(new CCol('Zabbix server:','header'), $this->getConfig('ZBX_SERVER')));
			$table->addRow(array(new CCol('Zabbix server port:','header'), $this->getConfig('ZBX_SERVER_PORT')));
			$table->addRow(array(new CCol('Zabbix server name:','header'), $this->getConfig('ZBX_SERVER_NAME')));

			return array(
				'Please check configuration parameters.', BR(),
				'If all is correct, press "Next" button, or "Previous" button to change configuration parameters.', BR(), BR(),
				$table
			);
		}

		function stage6(){
			global $ZBX_CONFIGURATION_FILE;

			$this->setConfig('ZBX_CONFIG_FILE_CORRECT', true);

			$config = new CConfigFile($ZBX_CONFIGURATION_FILE);
			$config->config = array(
				'DB' => array(
					'TYPE' => $this->getConfig('DB_TYPE'),
					'SERVER' => $this->getConfig('DB_SERVER'),
					'PORT' => $this->getConfig('DB_PORT'),
					'DATABASE' => $this->getConfig('DB_DATABASE'),
					'USER' => $this->getConfig('DB_USER'),
					'PASSWORD' => $this->getConfig('DB_PASSWORD'),
					'SCHEMA' => $this->getConfig('DB_SCHEMA'),
				),
				'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
				'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
				'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME'),
			);
			$config->save();

			if($config->load()){
				$error = '';

				if($config->config['DB']['TYPE'] != $this->getConfig('DB_TYPE')){
					$error = 'Config file DB type is not equal to wizard input.';
				}
				else if($config->config['DB']['SERVER'] != $this->getConfig('DB_SERVER')){
					$error = 'Config file DB server is not equal to wizard input.';
				}
				else if($config->config['DB']['PORT'] != $this->getConfig('DB_PORT')){
					$error = 'Config file DB port is not equal to wizard input.';
				}
				else if($config->config['DB']['DATABASE'] != $this->getConfig('DB_DATABASE')){
					$error = 'Config file DB database is not equal to wizard input.';
				}
				else if($config->config['DB']['USER'] != $this->getConfig('DB_USER')){
					$error = 'Config file DB user is not equal to wizard input.';
				}
				else if($config->config['DB']['PASSWORD'] != $this->getConfig('DB_PASSWORD')){
					$error = 'Config file DB password is not equal to wizard input.';
				}
				else if(($this->getConfig('DB_TYPE') == 'IBM_DB2') && ($config->config['DB']['SCHEMA'] != $this->getConfig('DB_SCHEMA'))){
					$error = 'Config file DB schema is not equal to wizard input.';
				}
				else if($config->config['ZBX_SERVER'] != $this->getConfig('ZBX_SERVER')){
					$error = 'Config file Zabbix server is not equal to wizard input.';
				}
				else if($config->config['ZBX_SERVER_PORT'] != $this->getConfig('ZBX_SERVER_PORT')){
					$error = 'Config file Zabbix server port is not equal to wizard input.';
				}
				else if($config->config['ZBX_SERVER_NAME'] != $this->getConfig('ZBX_SERVER_NAME')){
					$error = 'Config file Zabbix server name is not equal to wizard input.';
				}
			}
			else{
				$error = $config->error;
			}

			clear_messages();
			if(!empty($error)){
				error($error);
				show_messages();
				$this->setConfig('ZBX_CONFIG_FILE_CORRECT', false);
			}


			$this->DISABLE_NEXT_BUTTON = !$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false);

			$table = new CTable(null, 'requirements');
			$table->setAlign('center');

			$table->addRow(array('Configuration file: ', $this->getConfig('ZBX_CONFIG_FILE_CORRECT', false) ?
				new CSpan(S_OK,'ok') :
				new CSpan(S_FAIL,'fail')
			));

			return array(
				$table, BR(),
				$this->DISABLE_NEXT_BUTTON ? array(new CButton('retry', S_RETRY), BR(),BR()) : null,
				!$this->getConfig('ZBX_CONFIG_FILE_CORRECT', false) ?
					array('Please install configuration file manually, or fix permissions on conf directory.',BR(),BR(),
						'Press "Save configuration file" button, download configuration file ',
						'and save it as ',BR(),
						'"'.$ZBX_CONFIGURATION_FILE.'"',BR(),BR(),
						new CButton('save_config',"Save configuration file"),
						BR(),BR()
						)
					: null,
				'When done, press the '.($this->DISABLE_NEXT_BUTTON ? '"Retry"' : '"Next"').' button'
			);
		}

		function stage7(){
			return array(
				'Congratulations on successful instalation of Zabbix frontend.',BR(),BR(),
				'Press "Finish" button to complete installation'
				);
		}

		function CheckConnection(){
			global $DB;
//			global $ZBX_MESSAGES;

			$DB['TYPE']	= $this->getConfig('DB_TYPE');
			if(is_null($DB['TYPE'])) return false;

			$DB['SERVER'] = $this->getConfig('DB_SERVER', 'localhost');
			$DB['PORT']	= $this->getConfig('DB_PORT', '0');
			$DB['DATABASE']	= $this->getConfig('DB_DATABASE', 'zabbix');
			$DB['USER']	= $this->getConfig('DB_USER', 'root');
			$DB['PASSWORD']	= $this->getConfig('DB_PASSWORD', '');
			$DB['SCHEMA'] = $this->getConfig('DB_SCHEMA', '');

			$error = '';
			if(!$result = DBconnect($error)){
//				if(!is_null($ZBX_MESSAGES)) array_pop($ZBX_MESSAGES);
				error($error);
			}
			else{
				$result = true;
				if(!zbx_empty($DB['SCHEMA']) && ($DB['TYPE'] == 'IBM_DB2')){
					$db_schema = DBselect("SELECT schemaname FROM syscat.schemata WHERE schemaname='".db2_escape_string($DB['SCHEMA'])."'");
					$result = DBfetch($db_schema);
				}

				if($result){
					$result = DBexecute('CREATE table zabbix_installation_test ( test_row integer )');
					$result &= DBexecute('DROP table zabbix_installation_test');
				}
			}

			DBclose();

			if($DB['TYPE'] == 'SQLITE3' && !zbx_is_callable(array('sem_get','sem_acquire','sem_release','sem_remove'))){
				error('SQLite3 requires IPC functions');
				$result = false;
			}

			$DB = null;
			return $result;
		}

		function EventHandler(){
			if(isset($_REQUEST['back'][$this->getStep()]))	$this->DoBack();

			if($this->getStep() == 1){
				if(isset($_REQUEST['next'][$this->getStep()])){
					$this->DoNext();
				}
			}
			else if($this->getStep() == 2 && isset($_REQUEST['next'][$this->getStep()]) && !isset($_REQUEST['trouble'])){
				$this->DoNext();
				$this->DISABLE_NEXT_BUTTON = true;
			}
			else if($this->getStep() == 3){
				$this->setConfig('DB_TYPE',	get_request('type',	$this->getConfig('DB_TYPE')));
				$this->setConfig('DB_SERVER', get_request('server',	$this->getConfig('DB_SERVER', 'localhost')));
				$this->setConfig('DB_PORT',	get_request('port',	$this->getConfig('DB_PORT', '0')));
				$this->setConfig('DB_DATABASE',	get_request('database',	$this->getConfig('DB_DATABASE', 'zabbix')));
				$this->setConfig('DB_USER',	get_request('user',	$this->getConfig('DB_USER',	'root')));
				$this->setConfig('DB_PASSWORD',	get_request('password',	$this->getConfig('DB_PASSWORD',	'')));
				$this->setConfig('DB_SCHEMA', get_request('schema', $this->getConfig('DB_SCHEMA', '')));

				if(isset($_REQUEST['retry'])){
					if(!$this->CheckConnection()){
						$this->DISABLE_NEXT_BUTTON = true;
						unset($_REQUEST['next']);
					}
				}
				else if(!isset($_REQUEST['next'][$this->getStep()])){
					$this->DISABLE_NEXT_BUTTON = true;
					unset($_REQUEST['next']);
				}

				if(isset($_REQUEST['next'][$this->getStep()])) $this->DoNext();
			}
			else if($this->getStep() == 4){
				$this->setConfig('ZBX_SERVER',		get_request('zbx_server',	$this->getConfig('ZBX_SERVER',		'localhost')));
				$this->setConfig('ZBX_SERVER_PORT',	get_request('zbx_server_port',	$this->getConfig('ZBX_SERVER_PORT',	'10051')));
				$this->setConfig('ZBX_SERVER_NAME',	get_request('zbx_server_name',	$this->getConfig('ZBX_SERVER_NAME',	'')));
				if(isset($_REQUEST['next'][$this->getStep()])) $this->DoNext();
			}
			else if($this->getStep() == 5 && isset($_REQUEST['next'][$this->getStep()])){
				$this->DoNext();
			}
			else if($this->getStep() == 6){
				if(isset($_REQUEST['save_config'])){
					global $ZBX_CONFIGURATION_FILE;

					/* Make zabbix.conf.php downloadable */
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
							'SCHEMA' => $this->getConfig('DB_SCHEMA'),
						),
						'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
						'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
						'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME'),
					);
					die($config->getString());
				}
			}

			if(isset($_REQUEST['next'][$this->getStep()])){
				$this->DoNext();
			}
		}
	}
?>
