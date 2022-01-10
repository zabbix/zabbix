<?php declare(strict_types = 1);
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


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.geomaps.edit.js.php');

$hintbox_tile_url = makeHelpIcon([
	_('The URL template is used to load and display the tile layer on geographical maps.'),
	BR(),
	BR(),
	_('Example'), ': ', (new CSpan('https://{s}.example.com/{z}/{x}/{y}{r}.png'))->addClass(ZBX_STYLE_MONOSPACE_FONT),
	BR(),
	BR(),
	_('The following placeholders are supported:'),
	(new CList([
		new CListItem([
			(new CSpan('{s}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents one of the available subdomains;')
		]),
		new CListItem([
			(new CSpan('{z}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents zoom level parameter in the URL;')
		]),
		new CListItem([
			(new CSpan('{x}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('and'), ' ',
			(new CSpan('{y}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('represent tile coordinates;')
		]),
		new CListItem([
			(new CSpan('{r}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('can be used to add "@2x" to the URL to load retina tiles.')
		])
	]))->addClass(ZBX_STYLE_LIST_DASHED)
]);

$hintbox_attribution = makeHelpIcon(
	_('Tile provider attribution data displayed in a small text box on the map.')
);

$hintbox_max_zoom = makeHelpIcon(_('Maximum zoom level of the map.'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Tile provider'), 'label-provider'))->setAsteriskMark(),
		new CFormField(
			(new CSelect('geomaps_tile_provider'))
				->setValue($data['geomaps_tile_provider'])
				->setFocusableElementId('label-provider')
				->addOptions(CSelect::createOptionsFromArray(
					array_combine(array_keys($data['tile_providers']), array_column($data['tile_providers'], 'name')) +
					['' => _('Other')]
				))
		)
	])
	->addItem([
		(new CLabel([_('Tile URL'), $hintbox_tile_url], 'geomaps_tile_url'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('geomaps_tile_url', $data['geomaps_tile_url'], false,
				DB::getFieldLength('config', 'geomaps_tile_url'))
			)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel([_('Attribution'), $hintbox_attribution], 'geomaps_attribution'),
		new CFormField(
			(new CTextArea('geomaps_attribution', $data['geomaps_attribution']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setMaxLength(DB::getFieldLength('config', 'geomaps_attribution'))
		)
	])
	->addItem([
		(new CLabel([_('Max zoom level'), $hintbox_max_zoom], 'geomaps_max_zoom'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('geomaps_max_zoom', $data['geomaps_max_zoom'], false,
				DB::getFieldLength('config', 'geomaps_max_zoom'))
			)
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		)
	]);

$form = (new CForm())
	->setId('geomaps-form')
	->setName('geomaps-form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'geomaps.update')
			->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem(
		(new CTabView())
			->addTab('geomaps_tab', _('Geographical maps'), $form_grid)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update'))
			))
	);

(new CWidget())
	->setTitle(_('Geographical maps'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('. json_encode([
		'tile_providers' => $data['tile_providers']
	]).');'
))
	->setOnDocumentReady()
	->show();
