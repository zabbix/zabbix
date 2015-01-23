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


$slideshowWidget = new CWidget('hat_slides');

// create header form
$slideHeaderForm = new CForm('get');
$slideHeaderForm->setName('slideHeaderForm');

$configComboBox = new CComboBox('config', 'slides.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('screens.php', _('Screens'));
$configComboBox->addItem('slides.php', _('Slide shows'));
$slideHeaderForm->addItem($configComboBox);

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

	$slideshowWidget->addPageHeader(
		_('SLIDE SHOWS'),
		array(
			$slideHeaderForm,
			SPACE,
			$favouriteIcon,
			SPACE,
			$refreshIcon,
			SPACE,
			get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
		)
	);

	$slideshowForm = new CForm('get');
	$slideshowForm->setName('slideForm');
	$slideshowForm->addVar('fullscreen', $this->data['fullscreen']);

	$slideshowsComboBox = new CComboBox('elementid', $this->data['elementId'], 'submit()');
	foreach ($this->data['slideshows'] as $slideshow) {
		$slideshowsComboBox->addItem($slideshow['slideshowid'], $slideshow['name']);
	}
	$slideshowForm->addItem(array(_('Slide show').SPACE, $slideshowsComboBox));

	$slideshowWidget->addHeader($this->data['slideshows'][$this->data['elementId']]['name'], $slideshowForm);

	if ($this->data['screen']) {
		if (isset($this->data['isDynamicItems'])) {
			$slideshowForm->addItem(array(SPACE, _('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()));
			$slideshowForm->addItem(array(SPACE, _('Host'), SPACE, $this->data['pageFilter']->getHostsCB()));
		}

		$scrollDiv = new CDiv();
		$scrollDiv->setAttribute('id', 'scrollbar_cntr');
		$slideshowWidget->addFlicker($scrollDiv, CProfile::get('web.slides.filter.state', 1));
		$slideshowWidget->addFlicker(BR(), CProfile::get('web.slides.filter.state', 1));
		$slideshowWidget->addItem(new CSpan(_('Loading...'), 'textcolorstyles'));
	}
	else {
		$slideshowWidget->addItem(new CTableInfo(_('No slides found.')));
	}
}
else {
	$slideshowWidget->addPageHeader(
		_('SLIDE SHOWS'),
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
