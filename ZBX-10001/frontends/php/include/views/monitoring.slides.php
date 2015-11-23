<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$slideshowWidget = (new CWidget())->setTitle(_('Slide shows'));

// create header form
$slideHeaderForm = (new CForm('get'))
	->setName('slideHeaderForm');

$controls = new CList();
$controls->addItem(new CComboBox('config', 'slides.php', 'redirect(this.options[this.selectedIndex].value);',
	[
		'screens.php' => _('Screens'),
		'slides.php' => _('Slide shows')
	]
));

if ($this->data['slideshows']) {
	$favouriteIcon = $this->data['screen']
		? get_icon('favourite', [
			'fav' => 'web.favorite.screenids',
			'elname' => 'slideshowid',
			'elid' => $this->data['elementId']
		])
		: (new CIcon(_('Favourites')))->addClass('iconplus');

	$refreshIcon = get_icon('screenconf');

	if ($this->data['screen']) {
		$refreshIcon->setMenuPopup(CMenuPopupHelper::getRefresh(
			WIDGET_SLIDESHOW,
			'x'.$this->data['refreshMultiplier'],
			true,
			[
				'elementid' => $this->data['elementId']
			]
		));
	}

	$slideHeaderForm->addVar('fullscreen', $this->data['fullscreen']);

	$slideshowsComboBox = new CComboBox('elementid', $this->data['elementId'], 'submit()');
	foreach ($this->data['slideshows'] as $slideshow) {
		$slideshowsComboBox->addItem($slideshow['slideshowid'], $slideshow['name']);
	}
	$controls->addItem([_('Slide show').SPACE, $slideshowsComboBox]);

	if ($this->data['screen']) {
		if (isset($this->data['isDynamicItems'])) {
			$controls->addItem([SPACE, _('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()]);
			$controls->addItem([SPACE, _('Host'), SPACE, $this->data['pageFilter']->getHostsCB()]);
		}
		$controls
			->addItem($favouriteIcon)
			->addItem($refreshIcon)
			->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));
		$slideHeaderForm->addItem($controls);
		$slideshowWidget->setControls($slideHeaderForm);

		$formFilter = (new CFilter('web.slides.filter.state'))
			->addNavigator();
		$slideshowWidget->addItem($formFilter);

		$slideshowWidget->addItem(
			(new CDiv((new CDiv())->addClass('preloader')))
				->setId(WIDGET_SLIDESHOW)
		);
	}
	else {
		$controls
			->addItem($favouriteIcon)
			->addItem($refreshIcon)
			->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));
		$slideHeaderForm->addItem($controls);
		$slideshowWidget->setControls($slideHeaderForm)
			->addItem(new CTableInfo());
	}
}
else {
	$slideshowWidget->setControls(
		[
			$slideHeaderForm,
			SPACE,
			get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']])
		]
	);
	$slideshowWidget->addItem(BR());
	$slideshowWidget->addItem(new CTableInfo());
}

if ($this->data['elementId'] && isset($this->data['element'])) {
	require_once dirname(__FILE__).'/js/monitoring.slides.js.php';
}

return $slideshowWidget;
