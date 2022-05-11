<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


use Core\CModule,
	CController as CAction;

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
	protected $rootDir;

	/**
	 * @var array of config data from zabbix config file
	 */
	protected $config = [];

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

	/**
	 * @var CModuleManager
	 */
	private $module_manager;

	/**
	 * Returns the current instance of APP.
	 *
	 * @static
	 *
	 * @return APP
	 */
	public static function getInstance() {
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
	public static function Component() {
		return self::getInstance()->component_registry;
	}

	/**
	 * Get module manager.
	 *
	 * @return CModuleManager
	 */
	public static function ModuleManager() {
		return self::getInstance()->module_manager;
	}

	/**
	 * Init modules required to run frontend.
	 */
	protected function init() {
		$this->rootDir = $this->findRootDir();
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
		require_once 'include/audit.inc.php';
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
		require_once 'include/maintenances.inc.php';
		require_once 'include/maps.inc.php';
		require_once 'include/media.inc.php';
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
				$this->initDB();
				$this->setServerAddress();
				$this->authenticateUser();

				$this->initMessages();
				$this->setLayoutModeByUrl();
				$this->initComponents();
				$this->initModuleManager();

				$router = $this->component_registry->get('router');
				$router->addActions($this->module_manager->getActions());
				$router->setAction($action_name);

				$this->component_registry->get('menu.main')
					->setSelectedByAction($action_name, $_REQUEST,
						CViewHelper::loadSidebarMode() != ZBX_SIDEBAR_VIEW_MODE_COMPACT
					);

				$this->component_registry->get('menu.user')
					->setSelectedByAction($action_name, $_REQUEST,
						CViewHelper::loadSidebarMode() != ZBX_SIDEBAR_VIEW_MODE_COMPACT
					);

				CProfiler::getInstance()->start();

				$this->processRequest($router);
				break;

			case self::EXEC_MODE_API:
				$this->loadConfigFile();
				$this->initDB();
				$this->setServerAddress();
				$this->initLocales('en_us');
				break;

			case self::EXEC_MODE_SETUP:
				try {
					// try to load config file, if it exists we need to init db and authenticate user to check permissions
					$this->loadConfigFile();
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
	 *
	 * @return string
	 */
	public static function getRootDir() {
		return self::getInstance()->rootDir;
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
			$this->rootDir.'/include/classes/api',
			$this->rootDir.'/include/classes/api/services',
			$this->rootDir.'/include/classes/api/helpers',
			$this->rootDir.'/include/classes/api/managers',
			$this->rootDir.'/include/classes/api/clients',
			$this->rootDir.'/include/classes/api/wrappers',
			$this->rootDir.'/include/classes/core',
			$this->rootDir.'/include/classes/data',
			$this->rootDir.'/include/classes/mvc',
			$this->rootDir.'/include/classes/db',
			$this->rootDir.'/include/classes/debug',
			$this->rootDir.'/include/classes/validators',
			$this->rootDir.'/include/classes/validators/schema',
			$this->rootDir.'/include/classes/validators/string',
			$this->rootDir.'/include/classes/validators/object',
			$this->rootDir.'/include/classes/validators/hostgroup',
			$this->rootDir.'/include/classes/validators/host',
			$this->rootDir.'/include/classes/validators/hostprototype',
			$this->rootDir.'/include/classes/validators/event',
			$this->rootDir.'/include/classes/export',
			$this->rootDir.'/include/classes/export/writers',
			$this->rootDir.'/include/classes/export/elements',
			$this->rootDir.'/include/classes/graph',
			$this->rootDir.'/include/classes/graphdraw',
			$this->rootDir.'/include/classes/import',
			$this->rootDir.'/include/classes/import/converters',
			$this->rootDir.'/include/classes/import/importers',
			$this->rootDir.'/include/classes/import/preprocessors',
			$this->rootDir.'/include/classes/import/readers',
			$this->rootDir.'/include/classes/import/validators',
			$this->rootDir.'/include/classes/items',
			$this->rootDir.'/include/classes/triggers',
			$this->rootDir.'/include/classes/server',
			$this->rootDir.'/include/classes/screens',
			$this->rootDir.'/include/classes/services',
			$this->rootDir.'/include/classes/sysmaps',
			$this->rootDir.'/include/classes/helpers',
			$this->rootDir.'/include/classes/helpers/trigger',
			$this->rootDir.'/include/classes/macros',
			$this->rootDir.'/include/classes/html',
			$this->rootDir.'/include/classes/html/pageheader',
			$this->rootDir.'/include/classes/html/svg',
			$this->rootDir.'/include/classes/html/widget',
			$this->rootDir.'/include/classes/html/interfaces',
			$this->rootDir.'/include/classes/parsers',
			$this->rootDir.'/include/classes/parsers/results',
			$this->rootDir.'/include/classes/controllers',
			$this->rootDir.'/include/classes/routing',
			$this->rootDir.'/include/classes/json',
			$this->rootDir.'/include/classes/user',
			$this->rootDir.'/include/classes/setup',
			$this->rootDir.'/include/classes/regexp',
			$this->rootDir.'/include/classes/ldap',
			$this->rootDir.'/include/classes/pagefilter',
			$this->rootDir.'/include/classes/widgets/fields',
			$this->rootDir.'/include/classes/widgets/forms',
			$this->rootDir.'/include/classes/widgets',
			$this->rootDir.'/include/classes/xml',
			$this->rootDir.'/local/app/controllers',
			$this->rootDir.'/app/controllers'
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

	/**
	 * Check if maintenance mode is enabled.
	 *
	 * @throws Exception
	 */
	protected function setMaintenanceMode() {
		require_once 'conf/maintenance.inc.php';

		if (defined('ZBX_DENY_GUI_ACCESS')) {
			if (!isset($ZBX_GUI_ACCESS_IP_RANGE) || !in_array(CWebUser::getIp(), $ZBX_GUI_ACCESS_IP_RANGE)) {
				throw new Exception($_REQUEST['warning_msg']);
			}
		}
	}

	/**
	 * Load zabbix config file.
	 */
	protected function loadConfigFile() {
		$configFile = $this->getRootDir().CConfigFile::CONFIG_FILE_PATH;
		$config = new CConfigFile($configFile);
		$this->config = $config->load();
	}

	/**
	 * Initialize classes autoloader.
	 */
	protected function initAutoloader() {
		// Register base directory path for 'include' and 'require' functions.
		set_include_path(get_include_path().PATH_SEPARATOR.$this->rootDir);
		$autoloader = new CAutoloader;
		$autoloader->addNamespace('', $this->getIncludePaths());
		$autoloader->addNamespace('Core', [$this->rootDir.'/include/classes/core']);
		$autoloader->register();
		$this->autoloader = $autoloader;
	}

	/**
	 * Check if frontend can connect to DB.
	 * @throws DBException
	 */
	protected function initDB() {
		$error = null;
		if (!DBconnect($error)) {
			CDataCacheHelper::clearValues(['db_username', 'db_password']);

			throw new DBException($error);
		}
	}

	/**
	 * Initialize translations, set up translated date and time constants.
	 *
	 * @param string $lang  Locale variant prefix like en_US, ru_RU etc.
	 */
	public function initLocales(string $language): void {
		if (!setupLocale($language, $error) && $error !== '') {
			error($error);
		}

		require_once $this->getRootDir().'/include/translateDefines.inc.php';
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

		if (!CWebUser::checkAuthentication($session->extractSessionId() ?: '')) {
			CWebUser::setDefault();
		}

		$this->initLocales(CWebUser::$data['lang']);

		if (!$session->session_start(CWebUser::$data['sessionid'])) {
			throw new Exception(_('Session initialization error.'));
		}

		CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);

		// Set the authentication token for the API.
		API::getWrapper()->auth = CWebUser::$data['sessionid'];

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
			if (!class_exists($action_class, true)) {
				throw new Exception(_s('Class %1$s not found for action %2$s.', $action_class, $action_name));
			}

			$action = new $action_class();

			if (!is_subclass_of($action, CAction::class)) {
				throw new Exception(_s('Action class %1$s must extend %2$s class.', $action_class, CAction::class));
			}

			$action->setAction($action_name);

			$modules = $this->module_manager->getModules();

			$action_module = $this->module_manager->getModuleByActionName($action_name);

			if ($action_module) {
				$modules = array_replace([$action_module->getId() => $action_module], $modules);
			}

			foreach (array_reverse($modules) as $module) {
				if (is_subclass_of($module, CModule::class)) {
					CView::registerDirectory($module->getDir().'/views');
					CPartial::registerDirectory($module->getDir().'/partials');
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
		catch (Exception $e) {
			echo (new CView('general.warning', [
				'header' => $e->getMessage(),
				'messages' => [],
				'theme' => ZBX_DEFAULT_THEME
			]))->getOutput();

			session_write_close();
			exit();
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
				if ($key !== 'sid') {
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
				$view = new CView($router->getView(), $response->getData());

				$layout_data = array_replace($layout_data_defaults, [
					'main_block' => $view->getOutput(),
					'javascript' => [
						'files' => $view->getJsFiles()
					],
					'stylesheet' => [
						'files' => $view->getCssFiles()
					],
					'web_layout_mode' => $view->getLayoutMode()
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
	private function initComponents() {
		$this->component_registry->register('router', new CRouter());
		$this->component_registry->register('menu.main', CMenuHelper::getMainMenu());
		$this->component_registry->register('menu.user', CMenuHelper::getUserMenu());
	}

	/**
	 * Initialize module manager and load all enabled and allowed modules according to user role settings.
	 */
	private function initModuleManager() {
		$this->module_manager = new CModuleManager($this->rootDir.'/modules');

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

			$manifest = $this->module_manager->addModule($db_module['relative_path'], $db_module['id'],
				$db_module['config']
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
	 *
	 * @return void
	 */
	private function setServerAddress(): void {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if ($ZBX_SERVER !== null && $ZBX_SERVER_PORT !== null) {
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
	}
}
