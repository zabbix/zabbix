//{{NO_DEPENDENCIES}}
// Microsoft Developer Studio generated include file.
// Used by resource.rc
//
#ifndef _RESOURCE_H_
#define _RESOURCE_H_

#include "..\..\..\include\version.h"

#if defined(ZABBIX_AGENT)
#	include "zabbix_agent_desc.h"
#elif defined(ZABBIX_GET)
#	include "zabbix_get_desc.h"
#elif defined(ZABBIX_SENDER)
#	include "zabbix_sender_desc.h"
#elif defined(ZABBIX_AGENT2)
#	include "zabbix_agent2_desc.h"
#endif

#define VER_FILEVERSION		ZABBIX_VERSION_MAJOR,ZABBIX_VERSION_MINOR,ZABBIX_VERSION_PATCH,ZABBIX_VERSION_RC_NUM
#define VER_FILEVERSION_STR	ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
					ZBX_STR(ZABBIX_VERSION_PATCH) "." ZBX_STR(ZABBIX_VERSION_REVISION) "\0"
#define VER_PRODUCTVERSION	ZABBIX_VERSION_MAJOR,ZABBIX_VERSION_MINOR,ZABBIX_VERSION_PATCH
#define VER_PRODUCTVERSION_STR	ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
					ZBX_STR(ZABBIX_VERSION_PATCH) ZABBIX_VERSION_RC "\0"
#define VER_COMPANYNAME_STR	"Zabbix SIA\0"
#define VER_LEGALCOPYRIGHT_STR	"Copyright (C) 2001-2022 " VER_COMPANYNAME_STR
#define VER_PRODUCTNAME_STR	"Zabbix\0"

// Next default values for new objects
//
#ifdef APSTUDIO_INVOKED
#ifndef APSTUDIO_READONLY_SYMBOLS
#define _APS_NEXT_RESOURCE_VALUE	105
#define _APS_NEXT_COMMAND_VALUE		40001
#define _APS_NEXT_CONTROL_VALUE		1000
#define _APS_NEXT_SYMED_VALUE		101
#endif
#endif

#endif	/* _RESOURCE_H_ */
