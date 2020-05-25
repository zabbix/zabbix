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

		/** @var CMenu $menu */
		$menu = APP::Component()->get('menu.main');

		$menu
			->findOrAdd(_('Top level test'))
			->setIcon('icon-administration')
			->getSubmenu()
			->add((new \CMenuItem('first sub menu'))->setAction('dashboard.view'));

		$menu
			->find(_('Top level test'))
			->getSubmenu()
			->insertBefore('', (new \CMenuItem('before'))->setAction('dashboard.view'))
			->insertAfter('', (new \CMenuItem('after'))->setAction('dashboard.view'));
	}
}
