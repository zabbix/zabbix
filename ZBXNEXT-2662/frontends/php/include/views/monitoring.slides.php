<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$slideshowWidget = new CWidget('hat_slides');
$slideshowWidget->setTitle(_('Slide shows'));

// create header form
$slideHeaderForm = new CForm('get');
$slideHeaderForm->setName('slideHeaderForm');

$controls = new CList();

$configComboBox = new CComboBox('config', 'slides.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('screens.php', _('Screens'));
$configComboBox->addItem('slides.php', _('Slide shows'));
$controls->addItem($configComboBox);

if ($this->data['slideshows']) {
	$favouriteIcon = $this->data['screen']
		? get_icon('favourite', array(
			'fav' => 'web.favorite.screenids',
			'elname' => 'slideshowid',
			'elid' => $this->data['elementId']
		))
		: new CIcon(_('Favourites'), 'iconplus');

	$refreshIcon = new CIcon(_('Menu'), 'iconmenu');

	if ($this->data['screen']) {
		$refreshIcon->setMenuPopup(CMenuPopupHelper::getRefresh(
			WIDGET_SLIDESHOW,
			'x'.$this->data['refreshMultiplier'],
			true,
			array(
				'elementid' => $this->data['elementId']
			)
		));
	}

	$controls->addItem($favouriteIcon);
	$controls->addItem($refreshIcon);
	$controls->addItem(get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])));

	$slideHeaderForm->addVar('fullscreen', $this->data['fullscreen']);

	$slideshowsComboBox = new CComboBox('elementid', $this->data['elementId'], 'submit()');
	foreach ($this->data['slideshows'] as $slideshow) {
		$slideshowsComboBox->addItem($slideshow['slideshowid'], $slideshow['name']);
	}
	$controls->addItem(array(_('Slide show').SPACE, $slideshowsComboBox));

	if ($this->data['screen']) {
		if (isset($this->data['isDynamicItems'])) {
			$controls->addItem(array(SPACE, _('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()));
			$controls->addItem(array(SPACE, _('Host'), SPACE, $this->data['pageFilter']->getHostsCB()));
		}
		$slideHeaderForm->addItem($controls);
		$slideshowWidget->setControls($slideHeaderForm);

		$scrollDiv = new CDiv();
		$scrollDiv->setAttribute('id', 'scrollbar_cntr');
		$slideshowWidget->addFlicker($scrollDiv, CProfile::get('web.slides.filter.state', 1));
		$slideshowWidget->addFlicker(BR(), CProfile::get('web.slides.filter.state', 1));
		$slideshowWidget->addItem(new CSpan(_('Loading...'), 'textcolorstyles'));
	}
	else {
		$slideHeaderForm->addItem($controls);
		$slideshowWidget->setControls($slideHeaderForm);
		$slideshowWidget->addItem(new CTableInfo(_('No slides found.')));
	}
}
else {
	$slideshowWidget->setControls(
		array(
			$slideHeaderForm,
			SPACE,
			get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
		)
	);
	$slideshowWidget->addItem(BR());
	$slideshowWidget->addItem(new CTableInfo(_('No slide shows found.')));
}

if ($this->data['elementId'] && isset($this->data['element'])) {
	require_once dirname(__FILE__).'/js/monitoring.slides.js.php';
}

return $slideshowWidget;
