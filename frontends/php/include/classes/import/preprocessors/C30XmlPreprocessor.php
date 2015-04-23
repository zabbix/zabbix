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


/**
 * Zabbix 3.x preprocessor.
 */
class C30XmlPreprocessor extends CXmlPreprocessorGeneral {

	public function __construct() {
		parent::__construct(array(
			array('^zabbix_export$', '^(groups|hosts|templates|triggers|graphs|screens|images|maps)$'),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*',
				'^(templates|groups|interfaces|applications|items|discovery_rules|macros|inventory)$'
			),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*', '^items$', '^item[0-9]*', '^(applications|valuemap)$'),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*', '^discovery_rules$', '^discovery_rule[0-9]*',
				'^(item_prototypes|trigger_prototypes|graph_prototypes|host_prototypes)$'
			),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*', '^discovery_rules$', '^discovery_rule[0-9]*',
				'^host_prototypes$', '^host_prototype[0-9]*', '^(group_prototypes|templates)$'
			),
			array('^zabbix_export$', '^hosts$', '^host[0-9]*', '^discovery_rules$', '^discovery_rule[0-9]*',
				'^item_prototypes', '^item_prototype[0-9]*', '^(applications|valuemap)$'
			),
			array('^zabbix_export$', '^templates$', '^template[0-9]*',
				'^(templates|groups|applications|items|discovery_rules|macros|screens)$'
			),
			array('^zabbix_export$', '^templates$', '^template[0-9]*', '^items$', '^item[0-9]*',
				'^(applications|valuemap)$'
			),
			array('^zabbix_export$', '^screens$', '^screen[0-9]*', '^screen_items$'),
			array('^zabbix_export$', '^maps$', '^map[0-9]*', '^(background|urls|iconmap|selements|links)$'),
			array('^zabbix_export$', '^maps$', '^map[0-9]*', '^selements$', '^selement[0-9]*',
				'^(icon_off|icon_on|icon_disabled|icon_maintenance|urls)$'
			),
			array('^zabbix_export$', '^maps$', '^map[0-9]*', '^links$', '^link[0-9]*', '^linktriggers$'),
			array('^zabbix_export$', '^triggers$', '^trigger[0-9]*', '^dependencies$')
		));
	}
}
