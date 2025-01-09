<?php declare(strict_types = 0);
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


use Zabbix\Core\CModule;

use CController as CAction;

require_once dirname(__FILE__).'/CAutoloader.php';

class ZBase {

	const EXEC_MODE_DEFAULT = 'default';
	const EXEC_MODE_SETUP = 'setup';
	const EXEC_MODE_API = 'api';

	/**
	 * An instance of the current APP object.
	 *
	 * @var APP
	 */
	protected static $instance;

	/**
	 * The absolute path to the root directory.
	 *
	 * @var string
	 */
	protected $root_dir;

	/**
	 * @var array of config data from zabbix config file
	 */
	protected $config = [];

	/**
	 * @var CVault
	 */
	protected $vault;

	/**
	 * @var CAutoloader
	 */
	protected $autoloader;

	/**
	 * @var CComponentRegistry
	 */
	private $component_registry;

	/**
	 * Application mode.
	 *
	 * @var string
	 */
	private $mode;

	private CModuleManager $module_manager;

	private ?CView $view = null;

	/**
	 * Returns the current instance of APP.
	 *
	 * @return APP
	 */
	public static function getInstance(): APP {
		if (self::$instance === null) {
			self::$instance = new static;
		}

		return self::$instance;
	}

	/**
	 * Get component registry.
	 *
	 * @return CComponentRegistry
	 */
	public static function Component(): CComponentRegistry {
		return self::getInstance()->component_registry;
	}

	/**
	 * Get module manager.
	 *
	 * @return CModuleManager
	 */
	public static function ModuleManager(): CModuleManager {
		return self::getInstance()->module_manager;
	}

	/**
	 * @return CView|null
	 */
	public static function View(): ?CView {
		return self::getInstance()->view;
	}

	/**
	 * Init modules required to run frontend.
	 */
	protected function init() {
		$this->root_dir = $this->findRootDir();
		$this->initAutoloader();
		$this->component_registry = new CComponentRegistry;

		// initialize API classes
		$apiServiceFactory = new CApiServiceFactory();

		$client = new CLocalApiClient();
		$client->setServiceFactory($apiServiceFactory);
		$wrapper = new CFrontendApiWrapper($client);
		$wrapper->setProfiler(CProfiler::getInstance());
		API::setWrapper($wrapper);
		API::setApiServiceFactory($apiServiceFactory);

		// system includes
		require_once 'include/debug.inc.php';
		require_once 'include/gettextwrapper.inc.php';
		require_once 'include/defines.inc.php';
		require_once 'include/func.inc.php';
		require_once 'include/html.inc.php';
		require_once 'include/perm.inc.php';
		require_once 'include/js.inc.php';
		require_once 'include/users.inc.php';
		require_once 'include/validate.inc.php';
		require_once 'include/locales.inc.php';
		require_once 'include/db.inc.php';
		require_once 'vendor/autoload.php';

		// page specific includes
		require_once 'include/actions.inc.php';
		require_once 'include/discovery.inc.php';
		require_once 'include/draw.inc.php';
		require_once 'include/events.inc.php';
		require_once 'include/graphs.inc.php';
		require_once 'include/hostgroups.inc.php';
		require_once 'include/hosts.inc.php';
		require_once 'include/httptest.inc.php';
		require_once 'include/images.inc.php';
		require_once 'include/items.inc.php';
		require_once 'include/maps.inc.php';
		require_once 'include/sounds.inc.php';
		require_once 'include/triggers.inc.php';
	}

	/**
	 * Initializes the application.
	 *
	 * @param string $mode  Application initialization mode.
	 *
	 * @throws Exception
	 */
	public function run($mode) {
		$this->mode = $mode;

		$this->init();

		$this->setMaintenanceMode();

		ini_set('display_errors', 'Off');
		set_error_handler('zbx_err_handler');

		switch ($mode) {
			case self::EXEC_MODE_DEFAULT:
				$file = basename($_SERVER['SCRIPT_NAME']);
				$action_name = ($file === 'zabbix.php') ? getRequest('action', '') : $file;

				if ($action_name === 'notifications.get') {
					CWebUser::disableSessionExtension();
				}

				$this->loadConfigFile();
				$this->initVault();
				$this->initDB();
				$this->setServerAddress();
				$this->authenticateUser();

				$this->initMessages();
				$this->setLayoutModeByUrl();
				$this->initComponents();
				$this->initModuleManager();

				/** @var CRouter $router */
				$router = $this->component_registry->get('router');
				$router->addActions($this->module_manager->getActions());

				$validator = new CNewValidator(['action' => $action_name], ['action' => 'fatal|required|string']);
				$errors = $validator->getAllErrors();

				if ($errors) {
					CCookieHelper::set('system-message-details', base64_encode(json_encode(
						['type' => 'error', 'messages' => $errors]
					)));

					redirect('zabbix.php?action=system.warning');
				}

				$router->setAction($action_name);

				if (CWebUser::isLoggedIn()) {
					$this->component_registry->get('menu.main')
						->setSelectedByAction($action_name, $_REQUEST,
							CViewHelper::loadSidebarMode() != ZBX_SIDEBAR_VIEW_MODE_COMPACT
						);

					$this->component_registry->get('menu.user')
						->setSelectedByAction($action_name, $_REQUEST,
							CViewHelper::loadSidebarMode() != ZBX_SIDEBAR_VIEW_MODE_COMPACT
						);
				}

				CProfiler::getInstance()->start();

				$this->processRequest($router);
				break;

			case self::EXEC_MODE_API:
				$this->loadConfigFile();
				$this->initVault();
				$this->initDB();
				$this->setServerAddress();
				$this->initLocales('en_us');
				break;

			case self::EXEC_MODE_SETUP:
				try {
					// try to load config file, if it exists we need to init db and authenticate user to check permissions
					$this->loadConfigFile();
					$this->initVault();
					$this->initDB();
					$this->authenticateUser();
					$this->initComponents();
				}
				catch (ConfigFileException $e) {
					if ($e->getCode() == CConfigFile::CONFIG_VAULT_ERROR) {
						echo (new CView('general.warning', [
							'header' => _('Vault connection failed.'),
							'messages' => [$e->getMessage()],
							'theme' => ZBX_DEFAULT_THEME
						]))->getOutput();

						session_write_close();
						exit;
					}
					else {
						$session = new CCookieSession();
						$sessionid = $session->extractSessionId() ?: CEncryptHelper::generateKey();

						if (!$session->session_start($sessionid)) {
							throw new Exception(_('Session initialization error.'));
						}

						CSessionHelper::set('sessionid', $sessionid);
					}
				}
				break;
		}
	}

	/**
	 * Returns the application mode.
	 *
	 * @return string
	 */
	public static function getMode(): string {
		return self::getInstance()->mode;
	}

	/**
	 * Returns the absolute path to the root dir.
	 */
	public static function getRootDir(): string {
		return self::getInstance()->root_dir;
	}

	/**
	 * Returns the path to the frontend's root dir.
	 *
	 * @return string
	 */
	private function findRootDir() {
		return realpath(dirname(__FILE__).'/../../..');
	}

	/**
	 * An array of directories to add to the autoloader include paths.
	 *
	 * @return array
	 */
	private function getIncludePaths() {
		return [
			$this->root_dir.'/include/classes/api',
			$this->root_dir.'/include/classes/api/services',
			$this->root_dir.'/include/classes/api/helpers',
			$this->root_dir.'/include/classes/api/item_types',
			$this->root_dir.'/include/classes/api/managers',
			$this->root_dir.'/include/classes/api/clients',
			$this->root_dir.'/include/classes/api/wrappers',
			$this->root_dir.'/include/classes/core',
			$this->root_dir.'/include/classes/data',
			$this->root_dir.'/include/classes/mvc',
			$this->root_dir.'/include/classes/db',
			$this->root_dir.'/include/classes/debug',
			$this->root_dir.'/include/classes/validators',
			$this->root_dir.'/include/classes/validators/schema',
			$this->root_dir.'/include/classes/validators/string',
			$this->root_dir.'/include/classes/validators/object',
			$this->root_dir.'/include/classes/validators/hostgroup',
			$this->root_dir.'/include/classes/validators/host',
			$this->root_dir.'/include/classes/validators/hostprototype',
			$this->root_dir.'/include/classes/validators/event',
			$this->root_dir.'/include/classes/export',
			$this->root_dir.'/include/classes/export/writers',
			$this->root_dir.'/include/classes/export/elements',
			$this->root_dir.'/include/classes/graph',
			$this->root_dir.'/include/classes/graphdraw',
			$this->root_dir.'/include/classes/import',
			$this->root_dir.'/include/classes/import/converters',
			$this->root_dir.'/include/classes/import/importers',
			$this->root_dir.'/include/classes/import/preprocessors',
			$this->root_dir.'/include/classes/import/readers',
			$this->root_dir.'/include/classes/import/validators',
			$this->root_dir.'/include/classes/items',
			$this->root_dir.'/include/classes/triggers',
			$this->root_dir.'/include/classes/server',
			$this->root_dir.'/include/classes/screens',
			$this->root_dir.'/include/classes/services',
			$this->root_dir.'/include/classes/sysmaps',
			$this->root_dir.'/include/classes/helpers',
			$this->root_dir.'/include/classes/helpers/trigger',
			$this->root_dir.'/include/classes/macros',
			$this->root_dir.'/include/classes/html',
			$this->root_dir.'/include/classes/html/svg',
			$this->root_dir.'/include/classes/html/widgets',
			$this->root_dir.'/include/classes/html/widgets/fields',
			$this->root_dir.'/include/classes/html/interfaces',
			$this->root_dir.'/include/classes/parsers',
			$this->root_dir.'/include/classes/parsers/results',
			$this->root_dir.'/include/classes/controllers',
			$this->root_dir.'/include/classes/routing',
			$this->root_dir.'/include/classes/json',
			$this->root_dir.'/include/classes/user',
			$this->root_dir.'/include/classes/setup',
			$this->root_dir.'/include/classes/regexp',
			$this->root_dir.'/include/classes/ldap',
			$this->root_dir.'/include/classes/pagefilter',
			$this->root_dir.'/include/classes/xml',
			$this->root_dir.'/include/classes/vaults',
			$this->root_dir.'/local/app/controllers',
			$this->root_dir.'/app/controllers'
		];
	}

	/**
	 * An array of available themes.
	 *
	 * @return array
	 */
	public static function getThemes() {
		return [
			'blue-theme' => _('Blue'),
			'dark-theme' => _('Dark'),
			'hc-light' => _('High-contrast light'),
			'hc-dark' => _('High-contrast dark')
		];
	}

	public static function getColorScheme(string $theme): string {
		return match ($theme) {
			'dark-theme', 'hc-dark' => ZBX_COLOR_SCHEME_DARK,
			default => ZBX_COLOR_SCHEME_LIGHT
		};
	}

	/**
	 * Check if maintenance mode is enabled.
	 *
	 * @throws Exception
	 */
	protected function setMaintenanceMode() {
		require_once 'conf/maintenance.inc.php';

		if (defined('ZBX_DENY_GUI_ACCESS')) {
			if (!isset($ZBX_GUI_ACCESS_IP_RANGE) || !in_array(CWebUser::getIp(), $ZBX_GUI_ACCESS_IP_RANGE)) {
				throw new Exception($ZBX_GUI_ACCESS_MESSAGE ?? 'Zabbix is under maintenance.');
			}
		}
	}

	/**
	 * Load zabbix config file.
	 */
	protected function loadConfigFile(): void {
		$configFile = $this->root_dir.CConfigFile::CONFIG_FILE_PATH;

		$config = new CConfigFile($configFile);

		$this->config = $config->load();
	}

	/**
	 * Initialize classes autoloader.
	 */
	protected function initAutoloader() {
		// Register base directory path for 'include' and 'require' functions.
		set_include_path(get_include_path().PATH_SEPARATOR.$this->root_dir);
		$autoloader = new CAutoloader;
		$autoloader->addNamespace('', $this->getIncludePaths());
		$autoloader->addNamespace('Zabbix\\Core', [$this->root_dir.'/include/classes/core']);
		$autoloader->addNamespace('Zabbix\\Widgets', [$this->root_dir.'/include/classes/widgets']);
		$autoloader->register();
		$this->autoloader = $autoloader;
	}

	/**
	 * Vault provider initialisation if it exists in configuration file.
	 */
	protected function initVault(): void {
		switch ($this->config['DB']['VAULT']) {
			case CVaultCyberArk::NAME:
				$this->vault = new CVaultCyberArk($this->config['DB']['VAULT_URL'], $this->config['DB']['VAULT_PREFIX'],
					$this->config['DB']['VAULT_DB_PATH'], $this->config['DB']['VAULT_CERT_FILE'],
					$this->config['DB']['VAULT_KEY_FILE']
				);
				break;

			case CVaultHashiCorp::NAME:
				$this->vault = new CVaultHashiCorp($this->config['DB']['VAULT_URL'],
					$this->config['DB']['VAULT_PREFIX'], $this->config['DB']['VAULT_DB_PATH'],
					$this->config['DB']['VAULT_TOKEN']
				);
				break;
		}
	}

	/**
	 * Check if frontend can connect to DB.
	 *
	 * @throws DBException
	 */
	protected function initDB(): void {
		global $DB;

		$error = null;

		if ($this->vault !== null) {
			$db_user = $this->config['DB']['VAULT_CACHE'] ? CDataCacheHelper::getValue('db_user', '') : '';
			$db_password = $this->config['DB']['VAULT_CACHE'] ? CDataCacheHelper::getValue('db_password', '') : '';

			if ($db_user === '' || $db_password === '') {
				$db_credentials = $this->vault->getCredentials();

				if ($db_credentials === null) {
					throw new DBException(_('Unable to load database credentials from Vault.'), DB::INIT_ERROR);
				}

				['user' => $db_user, 'password' => $db_password] = $db_credentials;
			}

			if ($this->config['DB']['VAULT_CACHE'] && $db_user !== '' && $db_password !== '') {
				CDataCacheHelper::setValueArray([
					'db_user' => $db_user,
					'db_password' => $db_password
				]);
			}
			else {
				CDataCacheHelper::clearValues(['db_user', 'db_password']);
			}

			$this->config['DB']['USER'] = $db_user;
			$this->config['DB']['PASSWORD'] = $db_password;

			$DB = $this->config['DB'];
		}

		if (!DBconnect($error)) {
			CDataCacheHelper::clearValues(['db_user', 'db_password']);

			throw new DBException($error, DB::INIT_ERROR);
		}
	}

	/**
	 * Initialize translations, set up translated date and time constants.
	 *
	 * @param string|null $language  Locale variant prefix like en_US, ru_RU etc.
	 */
	public function initLocales(?string $language): void {
		if (!setupLocale($language, $error)) {
			error($error);
		}

		require_once $this->root_dir.'/include/translateDefines.inc.php';
	}

	/**
	 * Set messages received in cookies.
	 */
	private function initMessages(): void {
		if (CCookieHelper::has('system-message-ok')) {
			CMessageHelper::setSuccessTitle(CCookieHelper::get('system-message-ok'));
			CCookieHelper::unset('system-message-ok');
		}
		if (CCookieHelper::has('system-message-error')) {
			CMessageHelper::setErrorTitle(CCookieHelper::get('system-message-error'));
			CCookieHelper::unset('system-message-error');
		}
		if (CCookieHelper::has('system-message-details')) {
			$details = json_decode(base64_decode(CCookieHelper::get('system-message-details')), true);
			if ($details['type'] === 'success') {
				foreach ($details['messages'] as $message) {
					CMessageHelper::addSuccess($message);
				}
			}
			else {
				foreach ($details['messages'] as $message) {
					CMessageHelper::addError($message);
				}
			}
			CCookieHelper::unset('system-message-details');
		}
	}

	/**
	 * Authenticate user, apply some user-specific settings.
	 *
	 * @throws Exception
	 */
	protected function authenticateUser(): void {
		$session = new CEncryptedCookieSession();
		$sessionid = $session->extractSessionId() ?: '';

		API::getWrapper()->auth = [
			'type' => CJsonRpc::AUTH_TYPE_COOKIE,
			'auth' => $sessionid
		];

		if (!CWebUser::checkAuthentication($sessionid)) {
			CWebUser::setDefault();

			API::getWrapper()->auth = [
				'type' => CJsonRpc::AUTH_TYPE_COOKIE,
				'auth' => CWebUser::$data['sessionid']
			];
		}

		$this->initLocales(CWebUser::$data['lang']);

		if (!$session->session_start(CWebUser::$data['sessionid'])) {
			throw new Exception(_('Session initialization error.'));
		}

		CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);

		if (CWebUser::isAutologinEnabled()) {
			$session->lifetime = time() + SEC_PER_MONTH;
		}

		// Enable debug mode in the API.
		API::getWrapper()->debug = CWebUser::getDebugMode();
	}

	/**
	 * Process request and generate response.
	 *
	 * @param CRouter $router  CRouter class instance.
	 */
	private function processRequest(CRouter $router): void {
		$action_name = $router->getAction();
		$action_class = $router->getController();

		try {
			if ($action_class === null) {
				throw new Exception(_('Page not found'));
			}

			if (!class_exists($action_class)) {
				$namespace_parts = explode('\\', $action_class);

				if (count($namespace_parts) > 1) {
					$action_class_fallback = end($namespace_parts);

					if (!class_exists($action_class_fallback)) {
						throw new Exception(_s('Class %1$s not found for action %2$s.', $action_class, $action_name));
					}

					$action_class = $action_class_fallback;
				}
				else {
					throw new Exception(_s('Class %1$s not found for action %2$s.', $action_class, $action_name));
				}
			}

			$action = new $action_class();

			if (!is_subclass_of($action, CAction::class)) {
				throw new Exception(_s('Action class %1$s must extend %2$s class.', $action_class, CAction::class));
			}

			$action->setAction($action_name);
			$this->module_manager->setActionName($action_name);

			$modules = $this->module_manager->getModules();

			$action_module = $this->module_manager->getActionModule();

			if ($action_module !== null) {
				$modules = array_replace([$action_module->getId() => $action_module], $modules);

				if ($action_module->getType() === CModule::TYPE_WIDGET) {
					CView::registerDirectory($this->root_dir.'/'.$action_module->getRelativePath().'/views');
					CPartial::registerDirectory($this->root_dir.'/'.$action_module->getRelativePath().'/partials');
				}
			}

			foreach (array_reverse($modules) as $module) {
				if ($module->getType() === CModule::TYPE_MODULE) {
					CView::registerDirectory($this->root_dir.'/'.$module->getRelativePath().'/views');
					CPartial::registerDirectory($this->root_dir.'/'.$module->getRelativePath().'/partials');
				}
			}

			register_shutdown_function(function() use ($action) {
				$this->module_manager->publishEvent($action, 'onTerminate');
			});

			$this->module_manager->publishEvent($action, 'onBeforeAction');

			$action->run();

			if (!($action instanceof CLegacyAction)) {
				$this->processResponseFinal($router, $action);
			}
		}
		catch (CAccessDeniedException $e) {
			$this->denyPageAccess($router);
		}
		catch (Exception $e) {
			self::terminateWithError($router, $e->getMessage());
		}
	}

	private function processResponseFinal(CRouter $router, CAction $action): void {
		$response = $action->getResponse();

		// Controller returned redirect to another page?
		if ($response instanceof CControllerResponseRedirect) {
			header('Content-Type: text/html; charset=UTF-8');

			filter_messages();

			$response->redirect();
		}
		// Controller returned fatal error?
		elseif ($response instanceof CControllerResponseFatal) {
			header('Content-Type: text/html; charset=UTF-8');

			filter_messages();

			CMessageHelper::addError('Controller: '.$router->getAction());
			ksort($_REQUEST);
			foreach ($_REQUEST as $key => $value) {
				if ($key !== CSRF_TOKEN_NAME) {
					CMessageHelper::addError(is_scalar($value) ? $key.': '.$value : $key.': '.gettype($value));
				}
			}

			$response->redirect();
		}
		// Action has layout?
		if ($router->getLayout() !== null) {
			if (!($response instanceof CControllerResponseData)) {
				throw new Exception(_s('Unexpected response for action %1$s.', $router->getAction()));
			}

			$layout_data_defaults = [
				'page' => [
					'title' => $response->getTitle(),
					'file' => $response->getFileName()
				],
				'controller' => [
					'action' => $router->getAction()
				],
				'main_block' => '',
				'javascript' => [
					'files' => []
				],
				'stylesheet' => [
					'files' => []
				],
				'web_layout_mode' => ZBX_LAYOUT_NORMAL,
				'config' => [
					'server_check_interval' => CSettingsHelper::get(CSettingsHelper::SERVER_CHECK_INTERVAL),
					'x_frame_options' => CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS)
				]
			];

			if ($router->getView() !== null && $response->isViewEnabled()) {
				$this->view = new CView($router->getView(), $response->getData());

				$module = $this->module_manager->getActionModule();

				if ($module !== null) {
					$this->view->setAssetsPath($module->getRelativePath().'/assets');
				}

				$layout_data = array_replace($layout_data_defaults, [
					'main_block' => $this->view->getOutput(),
					'javascript' => [
						'files' => $this->view->getJsFiles()
					],
					'stylesheet' => [
						'files' => $this->view->getCssFiles()
					],
					'web_layout_mode' => $this->view->getLayoutMode()
				]);
			}
			else {
				$layout_data = array_replace_recursive($layout_data_defaults, $response->getData());
			}

			echo (new CView($router->getLayout(), $layout_data))->getOutput();
		}

		session_write_close();
		exit();
	}

	private static function denyPageAccess(CRouter $router): void {
		$request_url = (new CUrl(array_key_exists('request', $_REQUEST) ? $_REQUEST['request'] : ''))
			->removeArgument(CSRF_TOKEN_NAME)
			->toString();

		if (CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_LOGIN_FORM) == ZBX_AUTH_FORM_HTTP
				&& CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_AUTH_ENABLED) == ZBX_AUTH_HTTP_ENABLED
				&& (!CWebUser::isLoggedIn() || CWebUser::isGuest())) {
			redirect(
				(new CUrl('index_http.php'))
					->setArgument('request', $request_url)
					->toString()
			);
		}

		$view = [
			'messages' => [],
			'buttons' => [],
			'theme' => getUserTheme(CWebUser::$data)
		];

		if (CWebUser::isLoggedIn()) {
			$view['header'] = _('Access denied');
			$view['messages'][] = _s('You are logged in as "%1$s".', CWebUser::$data['username']).' '.
				_('You have no permissions to access this page.');
		}
		else {
			$view['header'] = _('You are not logged in');
			$view['messages'][] = _('You must login to view this page.');
			$view['messages'][] = _('Possibly the session has expired or the password was changed.');
		}

		$view['messages'][] = _('If you think this message is wrong, please consult your administrators about getting the necessary permissions.');

		if (!CWebUser::isLoggedIn() || CWebUser::isGuest()) {
			$view['buttons'][] = (new CButton('login', _('Login')))
				->setAttribute('data-login-url',
					(new CUrl('index.php'))
						->setArgument('request', $request_url)
						->toString()
				)
				->onClick('document.location = this.dataset.loginUrl;');
		}

		if (CWebUser::isLoggedIn()) {
			$view['buttons'][] = (new CButton('back', _s('Go to "%1$s"', CMenuHelper::getFirstLabel())))
				->setAttribute('data-home-url', CMenuHelper::getFirstUrl())
				->onClick('document.location = this.dataset.homeUrl;');
		}

		switch ($router->getLayout()) {
			case 'layout.json':
			case 'layout.widget':
				echo (new CView('layout.json', [
					'main_block' => json_encode([
						'error' => [
							'title' => $view['header'],
							'messages' => $view['messages']
						]
					])
				]))->getOutput();

				break;

			default:
				echo (new CView('general.warning', $view))->getOutput();
		}

		session_write_close();
		exit();
	}

	private static function terminateWithError(CRouter $router, string $error): void {
		switch ($router->getLayout()) {
			case 'layout.json':
			case 'layout.widget':
				$layout = 'layout.json';
				break;

			case null:
				if ((array_key_exists('CONTENT_TYPE', $_SERVER) && $_SERVER['CONTENT_TYPE'] === 'application/json')
						|| (array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER)
							&& strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') == 0)) {
					$layout = 'layout.json';
				}
				else {
					$layout = 'general.warning';
				}
				break;

			default:
				$layout = 'general.warning';
		}

		switch ($layout) {
			case 'layout.json':
				echo (new CView('layout.json', [
					'main_block' => json_encode([
						'error' => [
							'title' => $error
						]
					])
				]))->getOutput();

				break;

			default:
				echo (new CView('general.warning', [
					'header' => $error,
					'messages' => [],
					'theme' => getUserTheme(CWebUser::$data)
				]))->getOutput();
		}

		session_write_close();
		exit();
	}

	/**
	 * Set layout mode using URL parameters.
	 */
	private function setLayoutModeByUrl() {
		if (hasRequest('kiosk')) {
			CViewHelper::saveLayoutMode(getRequest('kiosk') === '1' ? ZBX_LAYOUT_KIOSKMODE : ZBX_LAYOUT_NORMAL);

			// Remove $_GET arguments to prevent CUrl from generating URL with 'kiosk' arguments.
			unset($_GET['kiosk']);
		}
	}

	/**
	 * Initialize menu for main navigation. Register instance as component with 'menu.main' key.
	 */
	private function initComponents(): void {
		$this->component_registry->register('router', new CRouter());

		if (CWebUser::isLoggedIn()) {
			$this->component_registry->register('menu.main', CMenuHelper::getMainMenu());
			$this->component_registry->register('menu.user', CMenuHelper::getUserMenu());
		}
	}

	/**
	 * Initialize module manager and load all enabled and allowed modules according to user role settings.
	 */
	private function initModuleManager(): void {
		$this->module_manager = new CModuleManager($this->root_dir);

		$db_modules = API::getApiService('module')->get([
			'output' => ['moduleid', 'id', 'relative_path', 'config'],
			'filter' => ['status' => MODULE_STATUS_ENABLED],
			'sortfield' => 'relative_path'
		], false);

		$modules_missing = [];

		foreach ($db_modules as $db_module) {
			if (!CWebUser::checkAccess('modules.module.'.$db_module['moduleid'])) {
				continue;
			}

			$manifest = $this->module_manager->addModule($db_module['relative_path'], $db_module['moduleid'],
				$db_module['id'], $db_module['config']
			);

			if (!$manifest) {
				$modules_missing[] = $db_module['relative_path'];
			}
		}

		if ($modules_missing) {
			error(_n('Cannot load module at: %1$s.', 'Cannot load modules at: %1$s.', implode(', ', $modules_missing),
				count($modules_missing)
			));
		}

		foreach ($this->module_manager->getNamespaces() as $namespace => $paths) {
			$this->autoloader->addNamespace($namespace, $paths);
		}

		$this->module_manager->initModules();

		array_map('error', $this->module_manager->getErrors());
	}

	/**
	 * Check for High availability override to standalone mode, set server to use for system information checks.
	 */
	private function setServerAddress(): void {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if ($ZBX_SERVER !== null) {
			$ZBX_SERVER_PORT = $ZBX_SERVER_PORT !== null ? (int) $ZBX_SERVER_PORT : ZBX_SERVER_PORT_DEFAULT;

			return;
		}

		$ha_nodes = API::getApiService('hanode')->get([
			'output' => ['address', 'port', 'status'],
			'sortfield' => 'lastaccess',
			'sortorder' => 'DESC'
		], false);

		$active_node = null;

		if (count($ha_nodes) == 1) {
			$active_node = $ha_nodes[0];
		}
		else {
			foreach ($ha_nodes as $node) {
				if ($node['status'] == ZBX_NODE_STATUS_ACTIVE) {
					$active_node = $node;
					break;
				}
			}
		}

		if ($active_node !== null) {
			$ZBX_SERVER = $active_node['address'];
			$ZBX_SERVER_PORT = $active_node['port'];
		}

		if ($ZBX_SERVER_PORT !== null) {
			$ZBX_SERVER_PORT = (int) $ZBX_SERVER_PORT;
		}
	}
}
