<?php

namespace Modules\CSRF;

use APP;
use CMenuItem;
use Zabbix\Core\CModule;

class Module extends CModule {

	public function init(): void {
		/** @var CMenu $menu */
		$menu = APP::Component()->get('menu.main');
		$menu
			->find('Administration')
			->getSubMenu()
				->add((new CMenuItem('CSRF test'))->setAction('csrftoken.form'));
	}
}
