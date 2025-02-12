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


/**
 * Setup wizard form.
 */
class CSetupWizard extends CForm {

	public const STAGE_WELCOME			= 1;
	public const STAGE_REQUIREMENTS		= 2;
	public const STAGE_DB_CONNECTION	= 3;
	public const STAGE_SETTINGS			= 4;
	public const STAGE_SUMMARY			= 5;
	public const STAGE_INSTALL			= 6;

	private $frontend_setup;
	private $stages;

	private $disable_cancel_button = false;
	private $disable_back_button = false;
	private $show_retry_button = false;

	private $step_failed = false;

	public function __construct() {
		parent::__construct();

		$this->setId('setup-form');

		$this->frontend_setup = new CFrontendSetup();

		$this->stages = [
			self::STAGE_WELCOME => [
				'title' => _('Welcome'),
				'fn' => 'stageWelcome'
			],
			self::STAGE_REQUIREMENTS => [
				'title' => _('Check of pre-requisites'),
				'fn' => 'stageRequirements'
			],
			self::STAGE_DB_CONNECTION => [
				'title' => _('Configure DB connection'),
				'fn' => 'stageDbConnection'
			],
			self::STAGE_SETTINGS => [
				'title' => _('Settings'),
				'fn' => 'stageSettings'
			],
			self::STAGE_SUMMARY => [
				'title' => _('Pre-installation summary'),
				'fn' => 'stageSummary'
			],
			self::STAGE_INSTALL => [
				'title' => _('Install'),
				'fn' => 'stageInstall'
			]
		];

		$this->doAction();
	}

	public function getStep(): int {
		$step = $this->getConfig('step');

		return array_key_exists($step, $this->stages) ? $step : self::STAGE_WELCOME;
	}

	private function doAction(): void {
		/*
		 * Having non-super-admin authenticated at this step means:
		 *   - Either the config file has been manually created by the user.
		 *   - Or dealing with a spoofed session cookie.
		 *
		 * Since it is not possible to distinguish between the two, skip data validation and prevent stage switching.
		 * Any of either cases is only possible with self::STAGE_INSTALL stage.
		 */
		if (CWebUser::$data && CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
			return;
		}

		if (hasRequest('back') && array_key_exists($this->getStep(), getRequest('back'))) {
			$this->doBack();
		}

		if ($this->getStep() == self::STAGE_REQUIREMENTS) {
			if (hasRequest('next') && array_key_exists(self::STAGE_REQUIREMENTS, getRequest('next'))) {
				$default_lang = getRequest('default_lang', $this->getConfig('default_lang'));
				$this->frontend_setup->setDefaultLang($default_lang);
				$finalResult = CFrontendSetup::CHECK_OK;

				foreach ($this->frontend_setup->checkRequirements() as $req) {
					if ($req['result'] > $finalResult) {
						$finalResult = $req['result'];
					}
				}

				if ($finalResult == CFrontendSetup::CHECK_FATAL) {
					$this->step_failed = true;
					unset($_REQUEST['next']);
				}
				else {
					$this->doNext();
				}
			}
		}
		elseif ($this->getStep() == self::STAGE_DB_CONNECTION) {
			$config = new CConfigFile(APP::getRootDir().CConfigFile::CONFIG_FILE_PATH);

			$input = [
				'DB_TYPE' => getRequest('type', $this->getConfig('DB_TYPE')),
				'DB_SERVER' => getRequest('server', $this->getConfig('DB_SERVER', $config->config['DB']['SERVER'])),
				'DB_PORT' => getRequest('port', $this->getConfig('DB_PORT', $config->config['DB']['PORT'])),
				'DB_DATABASE' => getRequest('database', $this->getConfig('DB_DATABASE', 'zabbix')),
				'DB_CREDS_STORAGE' => getRequest('creds_storage',
					$this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG)
				),
				'DB_SCHEMA' => getRequest('schema', $this->getConfig('DB_SCHEMA', $config->config['DB']['SCHEMA'])),
				'DB_ENCRYPTION' => (bool) getRequest('tls_encryption',
					$this->getConfig('DB_ENCRYPTION', $config->config['DB']['ENCRYPTION'])
				),
				'DB_ENCRYPTION_ADVANCED' => (bool) getRequest('verify_certificate',
					$this->getConfig('DB_ENCRYPTION_ADVANCED', false)
				),
				'DB_VERIFY_HOST' => (bool) getRequest('verify_host', $this->getConfig('DB_VERIFY_HOST', false)),
				'DB_KEY_FILE' => getRequest('key_file',
					$this->getConfig('DB_KEY_FILE', $config->config['DB']['KEY_FILE'])
				),
				'DB_CERT_FILE' => getRequest('cert_file',
					$this->getConfig('DB_CERT_FILE', $config->config['DB']['CERT_FILE'])
				),
				'DB_CA_FILE' => getRequest('ca_file',
					$this->getConfig('DB_CA_FILE', $config->config['DB']['CA_FILE'])
				),
				'DB_CIPHER_LIST' => getRequest('cipher_list',
					$this->getConfig('DB_CIPHER_LIST', $config->config['DB']['CIPHER_LIST'])
				)
			];

			if (!$input['DB_ENCRYPTION_ADVANCED']) {
				$input['DB_KEY_FILE'] = '';
				$input['DB_CERT_FILE'] = '';
				$input['DB_CA_FILE'] = '';
				$input['DB_CIPHER_LIST'] = '';
			}
			else if ($input['DB_TYPE'] === ZBX_DB_MYSQL) {
				$input['DB_VERIFY_HOST'] = true;
			}

			if ($input['DB_TYPE'] !== ZBX_DB_POSTGRESQL) {
				$input['DB_SCHEMA'] = '';
			}

			foreach ($input as $name => $value) {
				$this->setConfig($name, $value);
			}

			switch ($this->getConfig('DB_CREDS_STORAGE')) {
				case DB_STORE_CREDS_VAULT_HASHICORP:
					$this->setConfig('DB_VAULT_URL', getRequest('vault_url',
						$this->getConfig('DB_VAULT_URL', CVaultHashiCorp::API_ENDPOINT_DEFAULT)
					));

					$this->setConfig('DB_VAULT_PREFIX', getRequest('vault_prefix_hashicorp',
						$this->getConfig('DB_VAULT_PREFIX', '')
					));

					$this->setConfig('DB_VAULT_DB_PATH', getRequest('vault_db_path',
						$this->getConfig('DB_VAULT_DB_PATH', '')
					));

					$this->setConfig('DB_VAULT_TOKEN', getRequest('vault_token', $this->getConfig('DB_VAULT_TOKEN')));

					$this->unsetConfig(['DB_USER', 'DB_PASSWORD', 'DB_VAULT_CERTIFICATES', 'DB_VAULT_CERT_FILE',
						'DB_VAULT_KEY_FILE'
					]);
					break;

				case DB_STORE_CREDS_VAULT_CYBERARK:
					$this->setConfig('DB_VAULT_URL', getRequest('vault_url',
						$this->getConfig('DB_VAULT_URL', CVaultCyberArk::API_ENDPOINT_DEFAULT)
					));

					$this->setConfig('DB_VAULT_PREFIX', getRequest('vault_prefix_cyberark',
						$this->getConfig('DB_VAULT_PREFIX', '')
					));

					$this->setConfig('DB_VAULT_DB_PATH', getRequest('vault_query_string',
						$this->getConfig('DB_VAULT_DB_PATH', '')
					));

					$vault_certificates = (bool) getRequest('vault_certificates',
						$this->getConfig('DB_VAULT_CERTIFICATES', false)
					);
					$this->setConfig('DB_VAULT_CERTIFICATES', $vault_certificates);

					$vault_cert_file = $vault_certificates
						? getRequest('vault_cert_file', $this->getConfig('DB_VAULT_CERT_FILE', ''))
						: '';
					$this->setConfig('DB_VAULT_CERT_FILE', $vault_cert_file);

					$vault_key_file = $vault_certificates
						? getRequest('vault_key_file', $this->getConfig('DB_VAULT_KEY_FILE', ''))
						: '';
					$this->setConfig('DB_VAULT_KEY_FILE', $vault_key_file);

					$this->unsetConfig(['DB_USER', 'DB_PASSWORD', 'DB_VAULT_TOKEN']);
					break;

				default:
					$this->setConfig('DB_USER', getRequest('user', $this->getConfig('DB_USER', 'root')));
					$this->setConfig('DB_PASSWORD', getRequest('password', $this->getConfig('DB_PASSWORD', '')));

					$this->unsetConfig(['DB_VAULT_URL', 'DB_VAULT_DB_PATH', 'DB_VAULT_TOKEN', 'DB_VAULT_CERTIFICATES',
						'DB_VAULT_CERT_FILE', 'DB_VAULT_KEY_FILE'
					]);
					break;
			}

			if ($this->getConfig('DB_SERVER') === '') {
				$this->setConfig('DB_SERVER', $config->config['DB']['SERVER']);
			}

			if (hasRequest('next') && array_key_exists(self::STAGE_DB_CONNECTION, getRequest('next'))) {
				switch ($this->getConfig('DB_CREDS_STORAGE')) {
					case DB_STORE_CREDS_VAULT_HASHICORP:
						$vault_provider = new CVaultHashiCorp($this->getConfig('DB_VAULT_URL'),
							$this->getConfig('DB_VAULT_PREFIX'), $this->getConfig('DB_VAULT_DB_PATH'),
							$this->getConfig('DB_VAULT_TOKEN')
						);
						break;

					case DB_STORE_CREDS_VAULT_CYBERARK:
						$vault_provider = new CVaultCyberArk($this->getConfig('DB_VAULT_URL'),
							$this->getConfig('DB_VAULT_PREFIX'), $this->getConfig('DB_VAULT_DB_PATH'),
							$this->getConfig('DB_VAULT_CERT_FILE'), $this->getConfig('DB_VAULT_KEY_FILE')
						);
						break;

					default:
						$vault_provider = null;
						break;
				}

				$db_connected = false;

				if ($vault_provider !== null) {
					if (ini_get('allow_url_fopen') != 1) {
						error(_('Please enable "allow_url_fopen" directive.'));
					}
					else {
						$db_credentials = $vault_provider->validateParameters()
							? $vault_provider->getCredentials()
							: null;

						if ($db_credentials === null) {
							foreach ($vault_provider->getErrors() as $error) {
								error($error);
							}
						}
						else {
							$db_connected = $this->dbConnect($db_credentials['user'], $db_credentials['password']);
						}
					}
				}
				else {
					$db_connected = $this->dbConnect();
				}

				if ($db_connected) {
					if ($this->checkConnection()) {
						$this->doNext();
					}

					$this->dbClose();
				}
				else {
					$this->step_failed = true;
					unset($_REQUEST['next']);
				}
			}
		}
		elseif ($this->getStep() == self::STAGE_SETTINGS) {
			$this->setConfig('ZBX_SERVER_NAME', getRequest('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')));

			if (hasRequest('next') && array_key_exists(self::STAGE_SETTINGS, getRequest('next'))) {
				$this->doNext();
			}
		}
		elseif ($this->getStep() == self::STAGE_INSTALL) {
			if (hasRequest('save_config')) {
				$vault_config = [
					'VAULT' => '',
					'VAULT_URL' => '',
					'VAULT_PREFIX' => '',
					'VAULT_DB_PATH' => '',
					'VAULT_TOKEN' => '',
					'VAULT_CERT_FILE' => '',
					'VAULT_KEY_FILE' => ''
				];

				$db_creds_config = [
					'USER' => '',
					'PASSWORD' => ''
				];

				switch ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG)) {
					case DB_STORE_CREDS_VAULT_HASHICORP:
						$vault_config['VAULT'] = CVaultHashiCorp::NAME;
						$vault_config['VAULT_URL'] = $this->getConfig('DB_VAULT_URL');
						$vault_config['VAULT_PREFIX'] = $this->getConfig('DB_VAULT_PREFIX');
						$vault_config['VAULT_DB_PATH'] = $this->getConfig('DB_VAULT_DB_PATH');
						$vault_config['VAULT_TOKEN'] = $this->getConfig('DB_VAULT_TOKEN');
						break;

					case DB_STORE_CREDS_VAULT_CYBERARK:
						$vault_config['VAULT'] = CVaultCyberArk::NAME;
						$vault_config['VAULT_URL'] = $this->getConfig('DB_VAULT_URL');
						$vault_config['VAULT_PREFIX'] = $this->getConfig('DB_VAULT_PREFIX');
						$vault_config['VAULT_DB_PATH'] = $this->getConfig('DB_VAULT_DB_PATH');
						$vault_config['VAULT_CERT_FILE'] = $this->getConfig('DB_VAULT_CERT_FILE');
						$vault_config['VAULT_KEY_FILE'] = $this->getConfig('DB_VAULT_KEY_FILE');
						break;

					default:
						$db_creds_config['USER'] = $this->getConfig('DB_USER');
						$db_creds_config['PASSWORD'] = $this->getConfig('DB_PASSWORD');
						break;
				}

				// make zabbix.conf.php downloadable
				header('Content-Type: application/x-httpd-php');
				header('Content-Disposition: attachment; filename="'.basename(CConfigFile::CONFIG_FILE_PATH).'"');
				$config = new CConfigFile(APP::getRootDir().CConfigFile::CONFIG_FILE_PATH);
				$config->config = [
					'DB' => [
						'TYPE' => $this->getConfig('DB_TYPE'),
						'SERVER' => $this->getConfig('DB_SERVER'),
						'PORT' => $this->getConfig('DB_PORT'),
						'DATABASE' => $this->getConfig('DB_DATABASE'),
						'SCHEMA' => $this->getConfig('DB_SCHEMA'),
						'ENCRYPTION' => (bool) $this->getConfig('DB_ENCRYPTION'),
						'VERIFY_HOST' => (bool) $this->getConfig('DB_VERIFY_HOST'),
						'KEY_FILE' => $this->getConfig('DB_KEY_FILE'),
						'CERT_FILE' => $this->getConfig('DB_CERT_FILE'),
						'CA_FILE' => $this->getConfig('DB_CA_FILE'),
						'CIPHER_LIST' => $this->getConfig('DB_CIPHER_LIST')
					] + $db_creds_config + $vault_config,
					'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
				];
				die($config->getString());
			}
		}

		if (hasRequest('next') && array_key_exists($this->getStep(), getRequest('next'))) {
			$this->doNext();
		}
	}

	private function doNext(): void {
		if (array_key_exists($this->getStep() + 1, $this->stages)) {
			$this->setConfig('step', $this->getStep() + 1);
		}
	}

	private function doBack(): void {
		if (array_key_exists($this->getStep() - 1, $this->stages)) {
			$this->setConfig('step', $this->getStep() - 1);
		}
	}

	protected function bodyToString($destroy = true): string {
		$setup_left = (new CDiv())
			->addClass(ZBX_STYLE_SETUP_LEFT)
			->addItem(makeLogo(LOGO_TYPE_NORMAL))
			->addItem($this->getList());

		$setup_right = (new CDiv($this->getStage()))->addClass(ZBX_STYLE_SETUP_RIGHT);

		if (CWebUser::$data && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			$cancel_button = (new CSubmit('cancel', _('Cancel')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass(ZBX_STYLE_FLOAT_LEFT);

			if ($this->disable_cancel_button) {
				$cancel_button->setEnabled(false);
			}
		}
		else {
			$cancel_button = null;
		}

		$back_button = (new CSubmit('back['.($this->getStep()).']', _('Back')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(ZBX_STYLE_FLOAT_LEFT);

		if ($this->getStep() == self::STAGE_WELCOME || $this->disable_back_button) {
			$back_button->setEnabled(false);
		}

		if (array_key_exists($this->getStep() + 1, $this->stages)) {
			$next_button = new CSubmit('next['.($this->getStep()).']', _('Next step'));
		}
		else {
			$next_button = new CSubmit($this->show_retry_button ? 'retry' : 'finish', _('Finish'));
		}

		$setup_footer = (new CDiv([new CDiv([$next_button, $back_button]), $cancel_button]))
			->addClass(ZBX_STYLE_SETUP_FOOTER);

		$setup_container = (new CDiv([$setup_left, $setup_right, $setup_footer]))->addClass(ZBX_STYLE_SETUP_CONTAINER);

		return parent::bodyToString().$setup_container->toString();
	}

	private function getStage(): array {
		$function = $this->stages[$this->getStep()]['fn'];

		return $this->$function();
	}

	private function stageWelcome(): array {
		preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version);
		$setup_title = (new CDiv([new CSpan(_('Welcome to')), 'Zabbix '.$version[0]]))->addClass(ZBX_STYLE_SETUP_TITLE);

		$default_lang = $this->getConfig('default_lang');
		$lang_select = (new CSelect('default_lang'))
			->setId('default-lang')
			->setValue($default_lang)
			->setFocusableElementId('label-default-lang')
			->setAttribute('autofocus', 'autofocus');

		$all_locales_available = true;

		foreach (getLocales() as $localeid => $locale) {
			if (!$locale['display']) {
				continue;
			}

			/*
			 * Checking if this locale exists in the system. The only way of doing it is to try and set one
			 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
			 */
			$locale_available = ($localeid === ZBX_DEFAULT_LANG
				|| setlocale(LC_MONETARY, zbx_locale_variants($localeid))
			);

			$lang_select->addOption((new CSelectOption($localeid, $locale['name']))->setDisabled(!$locale_available));

			if (!$locale_available) {
				$all_locales_available = false;
			}
		}

		// Restoring original locale.
		setlocale(LC_MONETARY, zbx_locale_variants($default_lang));

		$language_error = null;

		if (!function_exists('bindtextdomain')) {
			$language_error = makeErrorIcon('Translations are unavailable because the PHP gettext module is missing.');

			$lang_select->setReadonly();
		}
		elseif (!$all_locales_available) {
			$language_error = makeWarningIcon(
				_('You are not able to choose some of the languages, because locales for them are not installed on the web server.')
			);
		}

		$language_select = (new CFormList())
			->addRow(new CLabel(_('Default language'), $lang_select->getFocusableElementId()), [
				$lang_select, $language_error
			]);

		return [(new CDiv([$setup_title, $language_select]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)];
	}

	private function stageRequirements(): array {
		$table = (new CTable())
			->addClass(ZBX_STYLE_LIST_TABLE)
			->setHeader(['', _('Current value'), _('Required'), '']);

		$messages = [];
		$default_lang = getRequest('default_lang', $this->getConfig('default_lang'));
		$this->frontend_setup->setDefaultLang($default_lang);
		$finalResult = CFrontendSetup::CHECK_OK;

		foreach ($this->frontend_setup->checkRequirements() as $req) {
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
					$req['required'] !== null ? $req['required'] : '',
					(new CCol($result))->addClass($class)
				]
			);

			if ($req['result'] > $finalResult) {
				$finalResult = $req['result'];
			}
		}

		if ($finalResult == CFrontendSetup::CHECK_FATAL) {
			$message_box = makeMessageBox(ZBX_STYLE_MSG_BAD, $messages, null, false, true);
		}
		else {
			$message_box = null;
		}

		return [
			new CTag('h1', true, _('Check of pre-requisites')),
			(new CDiv([$message_box, $table]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	private function stageDbConnection(): array {
		$DB['TYPE'] = $this->getConfig('DB_TYPE', key(CFrontendSetup::getSupportedDatabases()));

		$config = new CConfigFile(APP::getRootDir().CConfigFile::CONFIG_FILE_PATH);

		$table = (new CFormList())
			->addItem([
				(new CVar('tls_encryption', 0))->removeId(),
				(new CVar('verify_certificate', 0))->removeId(),
				(new CVar('verify_host', 0))->removeId()
			])
			->addRow(new CLabel(_('Database type'), 'label-type'),
				(new CSelect('type'))
					->setId('type')
					->setFocusableElementId('label-type')
					->setValue($DB['TYPE'])
					->addOptions(CSelect::createOptionsFromArray(CFrontendSetup::getSupportedDatabases()))
			)
			->addRow(_('Database host'),
				(new CTextBox('server', $this->getConfig('DB_SERVER', $config->config['DB']['SERVER'])))
					->setAttribute('placeholder', $config->config['DB']['SERVER'])
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(_('Database port'), [
				(new CNumericBox('port', $this->getConfig('DB_PORT', $config->config['DB']['PORT']), 5, false, false,
					false
				))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CSpan(_('0 - use default port')))->addClass(ZBX_STYLE_GREY)
			])
			->addRow(_('Database name'),
				(new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix')))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(_('Database schema'),
				(new CTextBox('schema', $this->getConfig('DB_SCHEMA', $config->config['DB']['SCHEMA'])))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				'db_schema_row',
				ZBX_STYLE_DISPLAY_NONE
			);

		$db_creds_storage = (int) $this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG);

		$table
			->addRow(_('Store credentials in'),
				(new CRadioButtonList('creds_storage', $db_creds_storage))
					->addValue(_('Plain text'), DB_STORE_CREDS_CONFIG)
					->addValue(_('HashiCorp Vault'), DB_STORE_CREDS_VAULT_HASHICORP)
					->addValue(_('CyberArk Vault'), DB_STORE_CREDS_VAULT_CYBERARK)
					->setModern(true)
			)
			// Plaintext.
			->addRow(_('User'),
				(new CTextBox('user', $this->getConfig('DB_USER', 'zabbix')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				'db_user',
				$db_creds_storage != DB_STORE_CREDS_CONFIG ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(_('Password'),
				(new CPassBox('password', $this->getConfig('DB_PASSWORD')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				'db_password',
				$db_creds_storage != DB_STORE_CREDS_CONFIG ? ZBX_STYLE_DISPLAY_NONE : null
			)
			// Vault common.
			->addRow(
				(new CLabel(_('Vault API endpoint')))->setAsteriskMark(),
				(new CTextBox('vault_url',
					$this->getConfig('DB_VAULT_URL', $db_creds_storage == DB_STORE_CREDS_VAULT_HASHICORP
						? CVaultHashiCorp::API_ENDPOINT_DEFAULT
						: CVaultCyberArk::API_ENDPOINT_DEFAULT
					)
				))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'vault_url_row',
				!in_array($db_creds_storage, [DB_STORE_CREDS_VAULT_HASHICORP, DB_STORE_CREDS_VAULT_CYBERARK])
					? ZBX_STYLE_DISPLAY_NONE
					: null
			)
			// HashiCorp Vault - related fields.
			->addRow(
				_('Vault prefix'),
				(new CTextBox('vault_prefix_hashicorp', $db_creds_storage == DB_STORE_CREDS_VAULT_HASHICORP
					? $this->getConfig('DB_VAULT_PREFIX')
					: ''
				))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('maxlength', 2048)
					->setAttribute('placeholder', CVaultHashiCorp::DB_PREFIX_PLACEHOLDER),
				'vault_prefix_hashicorp_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(
				(new CLabel(_('Vault secret path')))->setAsteriskMark(),
				(new CTextBox('vault_db_path', $db_creds_storage == DB_STORE_CREDS_VAULT_HASHICORP
					? $this->getConfig('DB_VAULT_DB_PATH')
					: ''
				))
					->setAttribute('placeholder', CVaultHashiCorp::DB_PATH_PLACEHOLDER)
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				'vault_db_path_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_HASHICORP ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(_('Vault authentication token'),
				(new CTextBox('vault_token', $this->getConfig('DB_VAULT_TOKEN')))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAttribute('maxlength', 2048),
				'vault_token_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_HASHICORP ? ZBX_STYLE_DISPLAY_NONE : null
			)
			// CyberArk Vault - related fields.
			->addRow(
				_('Vault prefix'),
				(new CTextBox('vault_prefix_cyberark', $db_creds_storage == DB_STORE_CREDS_VAULT_CYBERARK
					? $this->getConfig('DB_VAULT_PREFIX')
					: ''
				))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('maxlength', 2048)
					->setAttribute('placeholder', CVaultCyberArk::DB_PREFIX_DEFAULT),
				'vault_prefix_cyberark_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(
				(new CLabel(_('Vault secret query string')))->setAsteriskMark(),
				(new CTextBox('vault_query_string', $db_creds_storage == DB_STORE_CREDS_VAULT_CYBERARK
					? $this->getConfig('DB_VAULT_DB_PATH')
					: ''
				))
					->setAttribute('placeholder', CVaultCyberArk::DB_PATH_PLACEHOLDER)
					->setAttribute('maxlength', 2048)
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'vault_query_string_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(
				(new CLabel(_('Vault certificates'), 'vault_certificates_toggle')),
				(new CCheckBox('vault_certificates'))
					->setId('vault_certificates_toggle')
					->setChecked($this->getConfig('DB_VAULT_CERTIFICATES', false))
					->setUncheckedValue(0),
				'vault_certificates_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK ? ZBX_STYLE_DISPLAY_NONE : null
			)
			->addRow(_('SSL certificate file'),
				(new CTextBox('vault_cert_file',
					$this->getConfig('DB_VAULT_CERT_FILE', 'conf/certs/cyberark-cert.pem')
				))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('maxlength', 2048),
				'vault_cert_file_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK || !$this->getConfig('DB_VAULT_CERTIFICATES', false)
					? ZBX_STYLE_DISPLAY_NONE
					: null
			)
			->addRow(_('SSL key file'),
				(new CTextBox('vault_key_file', $this->getConfig('DB_VAULT_KEY_FILE', 'conf/certs/cyberark-key.pem')))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('maxlength', 2048),
				'vault_key_file_row',
				$db_creds_storage != DB_STORE_CREDS_VAULT_CYBERARK || !$this->getConfig('DB_VAULT_CERTIFICATES', false)
					? ZBX_STYLE_DISPLAY_NONE
					: null
			);

		$table
			->addRow(_('Database TLS encryption'),
				[
					(new CCheckBox('tls_encryption'))->setChecked($this->getConfig('DB_ENCRYPTION', true)),
					(new CDiv(
						_('Connection will not be encrypted because it uses a socket file (on Unix) or shared memory (Windows).')
					))
						->setId('tls_encryption_hint')
						->addClass(ZBX_STYLE_DISPLAY_NONE)
				],
				'db_encryption_row',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow(_('Verify database certificate'),
				(new CCheckBox('verify_certificate'))->setChecked($this->getConfig('DB_ENCRYPTION_ADVANCED')),
				'db_verify_host',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow((new CLabel(_('Database TLS CA file')))->setAsteriskMark(),
				(new CTextBox('ca_file', $this->getConfig('DB_CA_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'db_cafile_row',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow(_('Database TLS key file'),
				(new CTextBox('key_file', $this->getConfig('DB_KEY_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'db_keyfile_row',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow(_('Database TLS certificate file'),
				(new CTextBox('cert_file', $this->getConfig('DB_CERT_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'db_certfile_row',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow(_('Database host verification'),
				(new CCheckBox('verify_host'))->setChecked($this->getConfig('DB_VERIFY_HOST')),
				'db_verify_host_row',
				ZBX_STYLE_DISPLAY_NONE
			)
			->addRow(_('Database TLS cipher list'),
				(new CTextBox('cipher_list', $this->getConfig('DB_CIPHER_LIST')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
				'db_cipher_row',
				ZBX_STYLE_DISPLAY_NONE
			);

		if ($this->step_failed) {
			$message_box = makeMessageBox(ZBX_STYLE_MSG_BAD, CMessageHelper::getMessages(),
				_('Cannot connect to the database.'), false, true
			);
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

	private function stageSettings(): array {
		$timezones[ZBX_DEFAULT_TIMEZONE] = CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(), _('System'));
		$timezones += CTimezoneHelper::getList();

		$table = (new CFormList())
			->addRow(
				_('Zabbix server name'),
				(new CTextBox('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(
				new CLabel(_('Default time zone'), 'label-default-timezone'),
				(new CSelect('default_timezone'))
					->setValue($this->getConfig('default_timezone', ZBX_DEFAULT_TIMEZONE))
					->addOptions(CSelect::createOptionsFromArray($timezones))
					->setFocusableElementId('label-default-timezone')
					->setAttribute('autofocus', 'autofocus')
			)
			->addRow(new CLabel(_('Default theme'), 'label-default-theme'),
				(new CSelect('default_theme'))
					->setId('default-theme')
					->setFocusableElementId('label-default-theme')
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setValue($this->getConfig('default_theme'))
					->addOptions(CSelect::createOptionsFromArray(APP::getThemes()))
			);

		return [
			new CTag('h1', true, _('Settings')),
			(new CDiv($table))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	private function stageSummary(): array {
		$db_type = $this->getConfig('DB_TYPE');
		$databases = CFrontendSetup::getSupportedDatabases();

		$table = (new CFormList())
			->addRow(
				(new CSpan(_('Database type')))->addClass(ZBX_STYLE_GREY),
				$databases[$db_type]
			);

		$db_port = $this->getConfig('DB_PORT') == 0 ? _('default') : $this->getConfig('DB_PORT');

		if ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG) == DB_STORE_CREDS_CONFIG) {
			$db_password = preg_replace('/./', '*', $this->getConfig('DB_PASSWORD'));
			$db_username = $this->getConfig('DB_USER');
		}
		else {
			$db_password = _('Stored in Vault secret');
			$db_username = _('Stored in Vault secret');
		}

		$table
			->addRow(
				(new CSpan(_('Database server')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_SERVER')
			)
			->addRow(
				(new CSpan(_('Database port')))->addClass(ZBX_STYLE_GREY),
				$db_port
			)
			->addRow(
				(new CSpan(_('Database name')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_DATABASE')
			)
			->addRow(
				(new CSpan(_('Database user')))->addClass(ZBX_STYLE_GREY),
				$db_username
			)
			->addRow(
				(new CSpan(_('Database password')))->addClass(ZBX_STYLE_GREY),
				$db_password
			);

		if ($db_type === ZBX_DB_POSTGRESQL) {
			$table->addRow(
				(new CSpan(_('Database schema')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_SCHEMA')
			);
		}

		if ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG) == DB_STORE_CREDS_VAULT_HASHICORP) {
			$table
				->addRow(
					(new CSpan(_('Vault API endpoint')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_URL')
				)
				->addRow(
					(new CSpan(_('Vault prefix')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_PREFIX')
				)
				->addRow(
					(new CSpan(_('Vault secret path')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_DB_PATH')
				)
				->addRow(
					(new CSpan(_('Vault authentication token')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_TOKEN')
				);
		}

		if ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG) == DB_STORE_CREDS_VAULT_CYBERARK) {
			$table
				->addRow(
					(new CSpan(_('Vault API endpoint')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_URL')
				)
				->addRow(
					(new CSpan(_('Vault prefix')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_PREFIX')
				)
				->addRow(
					(new CSpan(_('Vault secret query string')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_DB_PATH')
				)
				->addRow(
					(new CSpan(_('Vault certificates')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VAULT_CERTIFICATES') ? 'true' : 'false'
				);

			if ($this->getConfig('DB_VAULT_CERTIFICATES')) {
				$table
					->addRow(
						(new CSpan(_('SSL certificate file')))->addClass(ZBX_STYLE_GREY),
						$this->getConfig('DB_VAULT_CERT_FILE', '')
					)
					->addRow(
						(new CSpan(_('SSL key file')))->addClass(ZBX_STYLE_GREY),
						$this->getConfig('DB_VAULT_KEY_FILE', '')
					);
			}
		}

		$table->addRow(
			(new CSpan(_('Database TLS encryption')))->addClass(ZBX_STYLE_GREY),
			$this->getConfig('DB_ENCRYPTION') ? 'true' : 'false'
		);

		if ($this->getConfig('DB_ENCRYPTION_ADVANCED')) {
			$table
				->addRow(
					(new CSpan(_('Database TLS CA file')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_CA_FILE')
				)
				->addRow(
					(new CSpan(_('Database TLS key file')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_KEY_FILE')
				)
				->addRow(
					(new CSpan(_('Database TLS certificate file')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_CERT_FILE')
				)
				->addRow(
					(new CSpan(_('Database host verification')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_VERIFY_HOST') ? 'true' : 'false'
				);

			if ($db_type === ZBX_DB_MYSQL) {
				$table->addRow(
					(new CSpan(_('Database TLS cipher list')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('DB_CIPHER_LIST')
				);
			}
		}

		$server_name = $this->getConfig('ZBX_SERVER_NAME');

		if ($server_name !== '') {
			$table
				->addRow(null)
				->addRow(
					(new CSpan(_('Zabbix server name')))->addClass(ZBX_STYLE_GREY),
					$this->getConfig('ZBX_SERVER_NAME')
				);
		}

		return [
			new CTag('h1', true, _('Pre-installation summary')),
			(new CDiv([
				new CTag('p', true, _s('Please check configuration parameters. If all is correct, press "%1$s" button, or "%2$s" button to change configuration parameters.', _('Next step'), _('Back'))),
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	private function stageInstall(): array {
		/*
		 * Having non-super-admin authenticated at this step means:
		 *   - Either the config file has been manually created by the user.
		 *   - Or dealing with a spoofed session cookie.
		 *
		 * Since it is not possible to distinguish between the two, it's also impossible to validate the config file
		 * and display any discrepancies with the configuration stored within the session.
		 */
		if (CWebUser::$data && CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
			CSessionHelper::clear();

			return $this->stageInstalled();
		}

		$vault_config = [
			'VAULT' => '',
			'VAULT_URL' => '',
			'VAULT_PREFIX' => '',
			'VAULT_DB_PATH' => '',
			'VAULT_TOKEN' => '',
			'VAULT_CERT_FILE' => '',
			'VAULT_KEY_FILE' => ''
		];

		$db_creds_config = [
			'USER' => '',
			'PASSWORD' => ''
		];

		$db_user = null;
		$db_password = null;

		if ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG) == DB_STORE_CREDS_VAULT_HASHICORP) {
			$vault_config['VAULT'] = CVaultHashiCorp::NAME;
			$vault_config['VAULT_URL'] = $this->getConfig('DB_VAULT_URL');
			$vault_config['VAULT_PREFIX'] = $this->getConfig('DB_VAULT_PREFIX');
			$vault_config['VAULT_DB_PATH'] = $this->getConfig('DB_VAULT_DB_PATH');
			$vault_config['VAULT_TOKEN'] = $this->getConfig('DB_VAULT_TOKEN');

			$vault_provider = new CVaultHashiCorp($vault_config['VAULT_URL'], $vault_config['VAULT_PREFIX'],
				$vault_config['VAULT_DB_PATH'], $vault_config['VAULT_TOKEN']
			);

			$db_credentials = $vault_provider->getCredentials();

			if ($db_credentials === null) {
				$this->step_failed = true;
				$this->setConfig('step', self::STAGE_DB_CONNECTION);
				return $this->stageDbConnection();
			}

			$db_user = $db_credentials['user'];
			$db_password = $db_credentials['password'];
		}
		elseif ($this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG) == DB_STORE_CREDS_VAULT_CYBERARK) {
			$vault_config['VAULT'] = CVaultCyberArk::NAME;
			$vault_config['VAULT_URL'] = $this->getConfig('DB_VAULT_URL');
			$vault_config['VAULT_PREFIX'] = $this->getConfig('DB_VAULT_PREFIX');
			$vault_config['VAULT_DB_PATH'] = $this->getConfig('DB_VAULT_DB_PATH');
			$vault_config['VAULT_CERT_FILE'] = $this->getConfig('DB_VAULT_CERT_FILE');
			$vault_config['VAULT_KEY_FILE'] = $this->getConfig('DB_VAULT_KEY_FILE');

			$vault_provider = new CVaultCyberArk($vault_config['VAULT_URL'], $vault_config['VAULT_PREFIX'],
				$vault_config['VAULT_DB_PATH'], $vault_config['VAULT_CERT_FILE'], $vault_config['VAULT_KEY_FILE']
			);

			$db_credentials = $vault_provider->getCredentials();

			if ($db_credentials === null) {
				$this->step_failed = true;
				$this->setConfig('step', self::STAGE_DB_CONNECTION);
				return $this->stageDbConnection();
			}

			$db_user = $db_credentials['user'];
			$db_password = $db_credentials['password'];
		}
		else {
			$db_creds_config['USER'] = $this->getConfig('DB_USER');
			$db_creds_config['PASSWORD'] = $this->getConfig('DB_PASSWORD');
		}

		$this->dbConnect($db_user, $db_password);

		$update = [];
		foreach (['default_lang', 'default_timezone', 'default_theme'] as $key) {
			$update[] = $key.'='.zbx_dbstr($this->getConfig($key));
		}
		DBexecute('UPDATE config SET '.implode(',', $update));
		$this->dbClose();

		$this->setConfig('ZBX_CONFIG_FILE_CORRECT', true);

		$config_file_name = APP::getRootDir().CConfigFile::CONFIG_FILE_PATH;
		$config = new CConfigFile($config_file_name);
		$config->config = [
			'DB' => [
				'TYPE' => $this->getConfig('DB_TYPE'),
				'SERVER' => $this->getConfig('DB_SERVER'),
				'PORT' => $this->getConfig('DB_PORT'),
				'DATABASE' => $this->getConfig('DB_DATABASE'),
				'SCHEMA' => $this->getConfig('DB_SCHEMA'),
				'ENCRYPTION' => $this->getConfig('DB_ENCRYPTION'),
				'KEY_FILE' => $this->getConfig('DB_KEY_FILE'),
				'CERT_FILE' => $this->getConfig('DB_CERT_FILE'),
				'CA_FILE' => $this->getConfig('DB_CA_FILE'),
				'VERIFY_HOST' => $this->getConfig('DB_VERIFY_HOST'),
				'CIPHER_LIST' => $this->getConfig('DB_CIPHER_LIST')
			] + $db_creds_config + $vault_config,
			'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
		];

		$error = false;

		/*
		 * Create session secret key for first installation. If installation already exists, don't make a new key
		 * because that will terminate the existing session.
		 */
		$db_connected = $this->dbConnect($db_user, $db_password);
		$is_superadmin = (CWebUser::$data && CWebUser::getType() == USER_TYPE_SUPER_ADMIN);

		$session_key_update_failed = $db_connected && !$is_superadmin
			? !CEncryptHelper::updateKey(CEncryptHelper::generateKey())
			: false;

		if (!$db_connected || $session_key_update_failed) {
			$this->step_failed = true;
			$this->setConfig('step', self::STAGE_DB_CONNECTION);

			return $this->stageDbConnection();
		}

		$this->dbClose();

		$messages = [];

		if (!$config->save()) {
			$error = true;
			$messages[] = [
				'type' => 'error',
				'message' => $config->error
			];
		}

		if ($error) {
			$this->show_retry_button = true;

			$this->setConfig('ZBX_CONFIG_FILE_CORRECT', false);

			$message_box = makeMessageBox(ZBX_STYLE_MSG_BAD, $messages, _('Cannot create the configuration file.'),
				false, true
			);
			$message = [
				new CTag('p', true, _('Alternatively, you can install it manually:')),
				new CTag('ol', true, [
					new CTag('li', true, new CLink(_('Download the configuration file'), 'setup.php?save_config=1')),
					new CTag('li', true, _s('Save it as "%1$s"', $config_file_name))
				])
			];

			return [
				new CTag('h1', true, _('Install')),
				(new CDiv([$message_box, $message]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
			];
		}

		// Clear session after success install.
		CSessionHelper::clear();

		return $this->stageInstalled();
	}

	private function stageInstalled() {
		$this->disable_cancel_button = true;
		$this->disable_back_button = true;

		$message_box = null;
		$message = [
			(new CTag('h1', true, _('Congratulations! You have successfully installed Zabbix frontend.')))
				->addClass(ZBX_STYLE_GREEN),
			new CTag('p', true, _s('Configuration file "%1$s" created.', ltrim(CConfigFile::CONFIG_FILE_PATH, '/')))
		];

		return [
			new CTag('h1', true, _('Install')),
			(new CDiv([$message_box, $message]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	private function getConfig($name, $default = null) {
		return CSessionHelper::has($name) ? CSessionHelper::get($name) : $default;
	}

	private function setConfig($name, $value): void {
		CSessionHelper::set($name, $value);
	}

	private function unsetConfig(array $keys): void {
		CSessionHelper::unset($keys);
	}

	private function getList(): CList {
		$list = new CList();

		foreach ($this->stages as $id => $data) {
			$list->addItem($data['title'], $id <= $this->getStep() ? ZBX_STYLE_SETUP_LEFT_CURRENT : null);
		}

		return $list;
	}

	private function dbConnect(?string $username = null, ?string $password = null) {
		global $DB;

		if (!$this->getConfig('check_fields_result')) {
			return false;
		}

		$DB['TYPE'] = $this->getConfig('DB_TYPE');
		if ($DB['TYPE'] === null) {
			return false;
		}

		$DB['SERVER'] = $this->getConfig('DB_SERVER', 'localhost');
		$DB['PORT'] = $this->getConfig('DB_PORT', '0');
		$DB['DATABASE'] = $this->getConfig('DB_DATABASE', 'zabbix');
		$DB['USER'] = $username ?? $this->getConfig('DB_USER', 'root');
		$DB['PASSWORD'] = $password ?? $this->getConfig('DB_PASSWORD', '');
		$DB['SCHEMA'] = $this->getConfig('DB_SCHEMA', '');
		$DB['ENCRYPTION'] = (bool) $this->getConfig('DB_ENCRYPTION', true);
		$DB['VERIFY_HOST'] = (bool) $this->getConfig('DB_VERIFY_HOST', true);
		$DB['KEY_FILE'] = $this->getConfig('DB_KEY_FILE', '');
		$DB['CERT_FILE'] = $this->getConfig('DB_CERT_FILE', '');
		$DB['CA_FILE'] = $this->getConfig('DB_CA_FILE', '');
		$DB['CIPHER_LIST'] = $this->getConfig('DB_CIPHER_LIST', '');

		$error = '';

		// Check certificate files exists.
		if ($DB['ENCRYPTION']) {
			if (($this->getConfig('DB_ENCRYPTION_ADVANCED') || $DB['CA_FILE'] !== '') && !file_exists($DB['CA_FILE'])) {
				error(_s('Incorrect file path for "%1$s": %2$s.', _('Database TLS CA file'), $DB['CA_FILE']));

				return false;
			}

			if ($DB['KEY_FILE'] !== '' && !file_exists($DB['KEY_FILE'])) {
				error(_s('Incorrect file path for "%1$s": %2$s.', _('Database TLS key file'), $DB['KEY_FILE']));

				return false;
			}

			if ($DB['CERT_FILE'] !== '' && !file_exists($DB['CERT_FILE'])) {
				error(_s('Incorrect file path for "%1$s": %2$s.', _('Database TLS certificate file'),
					$DB['CERT_FILE']
				));

				return false;
			}
		}

		if (!DBconnect($error)) {
			error($error);

			return false;
		}

		return true;
	}

	private function dbClose(): void {
		global $DB;

		DBclose();

		$DB = null;
	}

	private function checkConnection() {
		global $DB;

		$result = true;

		if ($DB['TYPE'] === ZBX_DB_POSTGRESQL && $DB['SCHEMA'] !== '') {
			$db_schema = DBselect(
				'SELECT NULL'.
					' FROM information_schema.schemata'.
					' WHERE schema_name='.zbx_dbstr($DB['SCHEMA'])
			);
			$result = (bool) DBfetch($db_schema);
		}

		$db = DB::getDbBackend();

		if (!$db->checkEncoding()) {
			error($db->getWarning());

			return false;
		}

		return $result;
	}
}
