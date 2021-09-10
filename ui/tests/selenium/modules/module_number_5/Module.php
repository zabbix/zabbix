<?php declare(strict_types = 1);

namespace Modules\Example_E;

use Core\CModule,
	APP,
	CMenu;

class Module extends CModule {

	/**
	 * Initialize module.
	 */
	public function init(): void {
		$menu = APP::Component()->get('menu.main');

		$menu
			->findOrAdd('Module 5 menu')
			->setIcon('icon-integrations')
			->getSubmenu()
			->add(
				(new \CMenuItem('пятый модуль'))->setAction('fifth.module')
			);

		$menu
			->find('Module 5 menu')
			->getSubmenu()
			->insertBefore('', (new \CMenuItem('Your profile'))->setAction('userprofile.edit'))
			->insertAfter('', (new \CMenuItem('Module list'))->setAction('module.list'));
	}
}
