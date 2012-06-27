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
#endif

#define VER_PRODUCTVERSION		ZABBIX_VERSION_WIN
#define VER_PRODUCTVERSION_STR		ZABBIX_VERSION "\000"
#define VER_FILEVERSION_STR		ZABBIX_VERSION " (" ZABBIX_REVISION ")\000"
#define VER_LEGALCOPYRIGHT_STR		"Copyright (C) 2000-2012 Zabbix SIA\000"
#define VER_PRODUCTNAME_STR		"Zabbix\000"
#define VER_COMPANYNAME_STR		"Zabbix SIA\000"

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
