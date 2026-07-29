/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_ZBX_BRIDGE_ADAPTER_CONSTANTS_H
#define ZABBIX_ZBX_BRIDGE_ADAPTER_CONSTANTS_H

#define ZBX_BRIDGE_ADAPTER_TIMEOUT		60
#define ZBX_BRIDGE_ADAPTER_SERVICE_NAME		"bridge-adapter"
#define ZBX_BRIDGE_ADAPTER_HTTPS_SCHEME		"https://"
#define ZBX_BRIDGE_ERROR_CODE_LEN	32
#define ZBX_BRIDGE_MESSAGE_LEN		256
#define ZBX_BRIDGE_TIME_LEN		64

#define ZBX_PUSH_BA_ERR_NOT_CONFIGURED	\
	"Cannot deliver mobile device notification, bridge-adapter is not configured."
#define ZBX_PUSH_BA_ERR_CONNECT	\
	"Cannot deliver mobile device notification, cannot connect to bridge-adapter."
#define ZBX_PUSH_BA_ERR_INVALID_RESPONSE	\
	"Cannot deliver mobile device notification, bridge-adapter returned an invalid response."
#define ZBX_PUSH_BA_ERR_RETURNED_ERROR	\
	"Cannot deliver mobile device notification, bridge-adapter returned an error."

#define ZBX_PUSH_ALERT_ERR_INVALID_UUID	\
	"Cannot deliver notification, recipient is not a valid device ID."
#define ZBX_PUSH_ALERT_ERR_DEVICE_UNKNOWN	\
	"Cannot deliver notification, device id is not known."
#define ZBX_PUSH_ALERT_ERR_DEVICE_NOT_ACTIVE	\
	"Cannot deliver notification, target device is not in Active state."
#define ZBX_PUSH_TEST_ERR_DEVICE_NOT_FOUND	\
	"Cannot find enabled device for push media type test."

#define ZBX_BRIDGE_ADAPTER_DEVICE_KEY_SCOPE_MOBILE_ENCRYPTION	1

#define ZBX_DEVICE_STATUS_ACTIVATED	1

#define ZBX_DEVICE_KEY_ACTIVE		0

#endif
