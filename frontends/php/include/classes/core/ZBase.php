<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	protected static $component_registry;

	/**
	 * Getter for component registry instance.
	 *
	 * @return CComponentRegistry
	 */
	public static function Component() {
		return self::$component_registry;
	}

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
	 * Init modules required to run frontend.
	 */
	protected function init() {
		$this->rootDir = $this->findRootDir();
		// Register base directory path for 'include' and 'require' functions.
		set_include_path(get_include_path().PATH_SEPARATOR.$this->rootDir);
		$autoloader = new CAutoloader;
		$autoloader->addNamespace('', $this->getIncludePaths());
		$autoloader->register();
		$this->autoloader = $autoloader;
		static::$component_registry = new CComponentRegistry;

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
		require_once 'include/profiles.inc.php';
		require_once 'include/locales.inc.php';
		require_once 'include/db.inc.php';

		// page specific includes
		require_once 'include/actions.inc.php';
		require_once 'include/discovery.inc.php';
		require_once 'include/draw.inc.php';
		require_once 'include/events.inc.php';
		require_once 'include/graphs.inc.php';
		require_once 'include/hostgroups.inc.php';
		require_once 'include/hosts.inc.php';
		require_once 'include/httptest.inc.php';
		require_once 'include/ident.inc.php';
		require_once 'include/images.inc.php';
		require_once 'include/items.inc.php';
		require_once 'include/maintenances.inc.php';
		require_once 'include/maps.inc.php';
		require_once 'include/media.inc.php';
		require_once 'include/services.inc.php';
		require_once 'include/sounds.inc.php';
		require_once 'include/triggers.inc.php';
		require_once 'include/valuemap.inc.php';
	}

	/**
	 * Initializes the application.
	 */
	public function run($mode) {
		$this->init();

		$this->setMaintenanceMode();
		set_error_handler('zbx_err_handler');

		switch ($mode) {
			case self::EXEC_MODE_DEFAULT:
				$this->loadConfigFile();
				$this->initDB();
				$this->authenticateUser();
				$this->initLocales(CWebUser::$data);
				$this->setLayoutModeByUrl();
				$this->initMenu();
				$this->initModules();
				array_map('error', $this->module_manager->getErrors());

				$file = basename($_SERVER['SCRIPT_NAME']);
				$action = ($file === 'zabbix.php') ? getRequest('action', '') : $file;
				$router = new CRouter;
				$router->addActions($this->module_manager->getRoutes());
				$router->setAction($action);
				$this->module = $this->module_manager->getModuleByAction($router->getAction());
				static::Component()->get('menu.main')->setSelected($action);

				$view_paths = array_reduce($this->module_manager->getRegisteredNamespaces(), 'array_merge', []);
				CView::$viewsDir = array_merge($view_paths, CView::$viewsDir);

				if ($router->getController() !== null) {
					CProfiler::getInstance()->start();
					$this->processRequest($router);
					static::stop();
				}

				if (resourceAccessDenied($file)) {
					access_deny(ACCESS_DENY_PAGE);
				}
				break;

			case self::EXEC_MODE_API:
				$this->loadConfigFile();
				$this->initDB();
				$this->initLocales(['lang' => 'en_gb']);
				break;

			case self::EXEC_MODE_SETUP:
				try {
					// try to load config file, if it exists we need to init db and authenticate user to check permissions
					$this->loadConfigFile();
					$this->initDB();
					$this->authenticateUser();
					$this->initLocales(CWebUser::$data);
				}
				catch (ConfigFileException $e) {}
				break;
		}
	}

	/**
	 * Call beforeTerminate event for current module.
	 */
	public static function stop() {
		$app = static::getInstance();

		if ($app->module instanceof CModule && $app->action instanceof CController) {
			$app->module->beforeTerminate($app->action);
		}

		exit;
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
			$this->rootDir.'/include/classes/core',
			$this->rootDir.'/include/classes/mvc',
			$this->rootDir.'/include/classes/api',
			$this->rootDir.'/include/classes/api/services',
			$this->rootDir.'/include/classes/api/helpers',
			$this->rootDir.'/include/classes/api/managers',
			$this->rootDir.'/include/classes/api/clients',
			$this->rootDir.'/include/classes/api/wrappers',
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
			$this->rootDir.'/include/classes/tree',
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
			$user_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];
			if (!isset($ZBX_GUI_ACCESS_IP_RANGE) || !in_array($user_ip, $ZBX_GUI_ACCESS_IP_RANGE)) {
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
	 * Check if frontend can connect to DB.
	 * @throws DBException
	 */
	protected function initDB() {
		$error = null;
		if (!DBconnect($error)) {
			throw new DBException($error);
		}
	}

	/**
	 * Initialize translations.
	 *
	 * @param array  $user_data          Array of user data.
	 * @param string $user_data['lang']  Language.
	 */
	protected function initLocales(array $user_data) {
		init_mbstrings();

		$defaultLocales = [
			'C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'
		];

		if (function_exists('bindtextdomain')) {
			// initializing gettext translations depending on language selected by user
			$locales = zbx_locale_variants($user_data['lang']);
			$locale_found = false;
			foreach ($locales as $locale) {
				// since LC_MESSAGES may be unavailable on some systems, try to set all of the locales
				// and then revert some of them back
				putenv('LC_ALL='.$locale);
				putenv('LANG='.$locale);
				putenv('LANGUAGE='.$locale);
				setlocale(LC_TIME, $locale);

				if (setlocale(LC_ALL, $locale)) {
					$locale_found = true;
					break;
				}
			}

			// reset the LC_CTYPE locale so that case transformation functions would work correctly
			// it is also required for PHP to work with the Turkish locale (https://bugs.php.net/bug.php?id=18556)
			// WARNING: this must be done before executing any other code, otherwise code execution could fail!
			// this will be unnecessary in PHP 5.5
			setlocale(LC_CTYPE, $defaultLocales);

			if (!$locale_found && $user_data['lang'] != 'en_GB' && $user_data['lang'] != 'en_gb') {
				error('Locale for language "'.$user_data['lang'].'" is not found on the web server. Tried to set: '.implode(', ', $locales).'. Unable to translate Zabbix interface.');
			}
			bindtextdomain('frontend', 'locale');
			bind_textdomain_codeset('frontend', 'UTF-8');
			textdomain('frontend');
		}

		// reset the LC_NUMERIC locale so that PHP would always use a point instead of a comma for decimal numbers
		setlocale(LC_NUMERIC, $defaultLocales);

		// should be after locale initialization
		require_once 'include/translateDefines.inc.php';
	}

	/**
	 * Authenticate user.
	 */
	protected function authenticateUser() {
		$sessionid = CWebUser::checkAuthentication(CWebUser::getSessionCookie());

		if (!$sessionid) {
			CWebUser::setDefault();
		}

		// set the authentication token for the API
		API::getWrapper()->auth = $sessionid;

		// enable debug mode in the API
		API::getWrapper()->debug = CWebUser::getDebugMode();
	}

	/**
	 * Process request and generate response. Main entry for all processing.
	 *
	 * @param CRouter $rourer
	 */
	private function processRequest(CRouter $router) {
		$controller = $router->getController();
		$this->action = class_exists($controller, true) ? new $controller : null;

		if ($this->action instanceof CController == false) {
			$message = is_null($this->action)
				? _s('%s action class is not found.', $controller)
				: _s('%s must extend CController class', $controller);

			error($message);

			$response = new CControllerResponseData([
				'controller' => [
					'action' => $router->getAction()
				],
				'main_block' => '',
				'page' => 0,
				'javascript' => [
					'files' => [],
					'pre' => '',
					'post' => ''
				]
			]);
		}
		else {
			if ($this->module instanceof CModule) {
				$moduleid = $this->module->getManifest()['id'];
				array_unshift(CView::$viewsDir, $this->module_manager->getModuleRootDir($moduleid));
				$this->module->beforeAction($this->action);
			}

			$this->action->setAction($router->getAction());
			$response = $this->action->run();
		}

		// Controller returned data
		if ($response instanceof CControllerResponseData) {
			// if no view defined we pass data directly to layout
			if ($router->getView() === null || !$response->isViewEnabled()) {
				$layout = new CView($router->getLayout(), $response->getData());
				echo $layout->getOutput();
			}
			else {
				$view = new CView($router->getView(), $response->getData());
				$data['page']['title'] = $response->getTitle();
				$data['page']['file'] = $response->getFileName();
				$data['controller']['action'] = $router->getAction();
				$data['main_block'] = $view->getOutput();
				$data['javascript']['files'] = $view->getAddedJS();
				$data['javascript']['pre'] = $view->getIncludedJS();
				$data['javascript']['post'] = $view->getPostJS();
				$layout = new CView($router->getLayout(), $data);
				echo $layout->getOutput();
			}
		}
		// Controller returned redirect to another page
		else if ($response instanceof CControllerResponseRedirect) {
			header('Content-Type: text/html; charset=UTF-8');
			if ($response->getMessageOk() !== null) {
				CSession::setValue('messageOk', $response->getMessageOk());
			}
			if ($response->getMessageError() !== null) {
				CSession::setValue('messageError', $response->getMessageError());
			}
			global $ZBX_MESSAGES;
			if (isset($ZBX_MESSAGES)) {
				CSession::setValue('messages', $ZBX_MESSAGES);
			}
			if ($response->getFormData() !== null) {
				CSession::setValue('formData', $response->getFormData());
			}

			redirect($response->getLocation());
		}
		// Controller returned fatal error
		else if ($response instanceof CControllerResponseFatal) {
			header('Content-Type: text/html; charset=UTF-8');

			global $ZBX_MESSAGES;
			$messages = (isset($ZBX_MESSAGES) && $ZBX_MESSAGES) ? filter_messages($ZBX_MESSAGES) : [];
			foreach ($messages as $message) {
				$response->addMessage($message['message']);
			}

			$response->addMessage('Controller: '.$router->getAction());
			ksort($_REQUEST);
			foreach ($_REQUEST as $key => $value) {
				// do not output SID
				if ($key != 'sid') {
					$response->addMessage(is_scalar($value) ? $key.': '.$value : $key.': '.gettype($value));
				}
			}
			CSession::setValue('messages', $response->getMessages());

			redirect('zabbix.php?action=system.warning');
		}
	}

	/**
	 * Set layout to fullscreen or kiosk mode if URL contains 'fullscreen' and/or 'kiosk' arguments.
	 */
	private function setLayoutModeByUrl() {
		if (array_key_exists('kiosk', $_GET) && $_GET['kiosk'] === '1') {
			CView::setLayoutMode(ZBX_LAYOUT_KIOSKMODE);
		}
		elseif (array_key_exists('fullscreen', $_GET)) {
			CView::setLayoutMode($_GET['fullscreen'] === '1' ? ZBX_LAYOUT_FULLSCREEN : ZBX_LAYOUT_NORMAL);
		}

		// Remove $_GET arguments to prevent CUrl from generating URL with 'fullscreen'/'kiosk' arguments.
		unset($_GET['fullscreen'], $_GET['kiosk']);
	}

	/**
	 * Initialize module manager, load enabled modules and register module namespaces for autoloader. Also call init
	 * for enabled modules.
	 */
	protected function initModules() {
		$manager = new CModuleManager($this->rootDir);
		$modules = DB::select('module', [
			'output' => ['relative_path', 'id', 'config'],
			'filter' => ['status' => 1]
		]);

		if ($modules) {
			foreach ($modules as $module) {
				$manager->loadModule($module['relative_path']);

				if (!array_key_exists($module['id'], $manager->getErrors())) {
					$manager->enable($module['id']);
				}
			}

			foreach ($manager->getRegisteredNamespaces() as $namespace => $paths) {
				$this->autoloader->addNamespace($namespace, $paths);
			}

			foreach ($modules as $module) {
				$manager->initModule($module['id'], json_decode($module['config'], true));
			}
		}

		$this->module_manager = $manager;
	}

	/**
	 * Initialize menu for main navigation. Register instance as component with 'menu.main' key.
	 */
	protected function initMenu() {
		$menu = new CMenu('menu.main', []);
		static::Component()->register('menu.main', $menu);
		include 'include/menu.inc.php';
	}
}
