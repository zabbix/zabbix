<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CApiServiceFactory extends CRegistryFactory {

	/**
	 * Specific API services.
	 */
	public const API_SERVICES = [
		'action' => CAction::class,
		'alert' => CAlert::class,
		'apiinfo' => CAPIInfo::class,
		'auditlog' => CAuditLog::class,
		'authentication' => CAuthentication::class,
		'autoregistration' => CAutoregistration::class,
		'configuration' => CConfiguration::class,
		'connector' => CConnector::class,
		'correlation' => CCorrelation::class,
		'dashboard' => CDashboard::class,
		'dcheck' => CDCheck::class,
		'dhost' => CDHost::class,
		'discoveryrule' => CDiscoveryRule::class,
		'drule' => CDRule::class,
		'dservice' => CDService::class,
		'event' => CEvent::class,
		'graph' => CGraph::class,
		'graphitem' => CGraphItem::class,
		'graphprototype' => CGraphPrototype::class,
		'hanode' => CHaNode::class,
		'host' => CHost::class,
		'hostgroup' => CHostGroup::class,
		'hostprototype' => CHostPrototype::class,
		'history' => CHistory::class,
		'hostinterface' => CHostInterface::class,
		'housekeeping' => CHousekeeping::class,
		'httptest' => CHttpTest::class,
		'image' => CImage::class,
		'iconmap' => CIconMap::class,
		'item' => CItem::class,
		'itemprototype' => CItemPrototype::class,
		'maintenance' => CMaintenance::class,
		'map' => CMap::class,
		'mediatype' => CMediatype::class,
		'mfa' => CMfa::class,
		'module' => CModule::class,
		'problem' => CProblem::class,
		'proxy' => CProxy::class,
		'proxygroup' => CProxyGroup::class,
		'report' => CReport::class,
		'regexp' => CRegexp::class,
		'role' => CRole::class,
		'service' => CService::class,
		'sla' => CSla::class,
		'script' => CScript::class,
		'settings' => CSettings::class,
		'task' => CTask::class,
		'template' => CTemplate::class,
		'templatedashboard' => CTemplateDashboard::class,
		'templategroup' => CTemplateGroup::class,
		'token' => CToken::class,
		'trend' => CTrend::class,
		'trigger' => CTrigger::class,
		'triggerprototype' => CTriggerPrototype::class,
		'user' => CUser::class,
		'userdirectory' => CUserDirectory::class,
		'usergroup' => CUserGroup::class,
		'usermacro' => CUserMacro::class,
		'valuemap' => CValueMap::class
	];

	public function __construct(array $objects = []) {
		parent::__construct(array_merge([
			// a generic API class
			'api' => CApiService::class
		], self::API_SERVICES, $objects));
	}
}
