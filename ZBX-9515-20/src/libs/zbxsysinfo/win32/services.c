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

#include "common.h"
#include "sysinfo.h"
#include "log.h"

int	SERVICE_STATE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SC_HANDLE	mgr, service;
	char		name[MAX_STRING_LEN];
	LPTSTR		wname;
	TCHAR		service_name[MAX_STRING_LEN];
	DWORD		max_len_name = MAX_STRING_LEN;
	int		i, ret = SYSINFO_RET_FAIL;
	SERVICE_STATUS	status;

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (get_param(param, 1, name, sizeof(name)) != 0)
		return SYSINFO_RET_FAIL;

	if ('\0' == *name)
		return SYSINFO_RET_FAIL;

	if (NULL == (mgr = OpenSCManager(NULL,NULL,GENERIC_READ)) )
		return SYSINFO_RET_FAIL;

	wname = zbx_utf8_to_unicode(name);

	service = OpenService(mgr, wname, SERVICE_QUERY_STATUS);
	if (NULL == service && 0 != GetServiceKeyName(mgr, wname, service_name, &max_len_name))
		service = OpenService(mgr, service_name, SERVICE_QUERY_STATUS);
	zbx_free(wname);

	if(NULL == service)
	{
		SET_UI64_RESULT(result, 255);
	}
	else
	{
		if (QueryServiceStatus(service, &status))
		{
			static DWORD states[7] = {SERVICE_RUNNING, SERVICE_PAUSED, SERVICE_START_PENDING, SERVICE_PAUSE_PENDING,
					SERVICE_CONTINUE_PENDING, SERVICE_STOP_PENDING, SERVICE_STOPPED};

			for (i = 0; i < 7 && status.dwCurrentState != states[i]; i++)
				;

			SET_UI64_RESULT(result, i);
		}
		else
		{
			SET_UI64_RESULT(result, 7);
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	return SYSINFO_RET_OK;
}

#define	ZBX_SRV_STARTTYPE_ALL		0x00
#define	ZBX_SRV_STARTTYPE_AUTOMATIC	0x01
#define	ZBX_SRV_STARTTYPE_MANUAL	0x02
#define	ZBX_SRV_STARTTYPE_DISABLED	0x03
static int	check_service_starttype(SC_HANDLE h_srv, int start_type)
{
	int			ret = FAIL;
	DWORD			sz;
	QUERY_SERVICE_CONFIG	*qsc = NULL;

	if (ZBX_SRV_STARTTYPE_ALL == start_type)
		return SUCCEED;

	QueryServiceConfig(h_srv, qsc, 0, &sz);

	if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		return FAIL;

	qsc = (QUERY_SERVICE_CONFIG *)zbx_malloc((void *)qsc, sz);

	if (0 != QueryServiceConfig(h_srv, qsc, sz, &sz))
	{
		switch (start_type) {
		case ZBX_SRV_STARTTYPE_AUTOMATIC:
			if (qsc->dwStartType == SERVICE_AUTO_START)
				ret = SUCCEED;
			break;
		case ZBX_SRV_STARTTYPE_MANUAL:
			if (qsc->dwStartType == SERVICE_DEMAND_START)
				ret = SUCCEED;
			break;
		case ZBX_SRV_STARTTYPE_DISABLED:
			if (qsc->dwStartType == SERVICE_DISABLED)
				ret = SUCCEED;
			break;
		}
	}

	zbx_free(qsc);

	return ret;
}

#define ZBX_SRV_STATE_STOPPED		0x0001
#define ZBX_SRV_STATE_START_PENDING	0x0002
#define ZBX_SRV_STATE_STOP_PENDING	0x0004
#define ZBX_SRV_STATE_RUNNING		0x0008
#define ZBX_SRV_STATE_CONTINUE_PENDING	0x0010
#define ZBX_SRV_STATE_PAUSE_PENDING	0x0020
#define ZBX_SRV_STATE_PAUSED		0x0040
#define ZBX_SRV_STATE_STARTED		0x007e	/* ZBX_SRV_STATE_START_PENDING | ZBX_SRV_STATE_STOP_PENDING |
						 * ZBX_SRV_STATE_RUNNING | ZBX_SRV_STATE_CONTINUE_PENDING |
						 * ZBX_SRV_STATE_PAUSE_PENDING | ZBX_SRV_STATE_PAUSED
						 */
#define ZBX_SRV_STATE_ALL		0x007f  /* ZBX_SRV_STATE_STOPPED | ZBX_SRV_STATE_STARTED
						 */
static int	check_service_state(SC_HANDLE h_srv, int service_state)
{
	SERVICE_STATUS	status;

	if (0 != QueryServiceStatus(h_srv, &status))
	{
		switch (status.dwCurrentState) {
		case SERVICE_STOPPED:
			if (service_state & ZBX_SRV_STATE_STOPPED)
				return SUCCEED;
			break;
		case SERVICE_START_PENDING:
			if (service_state & ZBX_SRV_STATE_START_PENDING)
				return SUCCEED;
			break;
		case SERVICE_STOP_PENDING:
			if (service_state & ZBX_SRV_STATE_STOP_PENDING)
				return SUCCEED;
			break;
		case SERVICE_RUNNING:
			if (service_state & ZBX_SRV_STATE_RUNNING)
				return SUCCEED;
			break;
		case SERVICE_CONTINUE_PENDING:
			if (service_state & ZBX_SRV_STATE_CONTINUE_PENDING)
				return SUCCEED;
			break;
		case SERVICE_PAUSE_PENDING:
			if (service_state & ZBX_SRV_STATE_PAUSE_PENDING)
				return SUCCEED;
			break;
		case SERVICE_PAUSED:
			if (service_state & ZBX_SRV_STATE_PAUSED)
				return SUCCEED;
			break;
		}
	}

	return FAIL;
}

int	SERVICES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int				start_type, service_state, ret;
	char				type[16], state[24], *buf = NULL, *utf8,
					exclude[MAX_STRING_LEN];
	SC_HANDLE			h_mgr;
	ENUM_SERVICE_STATUS_PROCESS	*ssp = NULL;
	DWORD				sz = 0, szn, i, services, resume_handle = 0;

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, type, sizeof(type)))
		*type = '\0';

	if ('\0' == *type || 0 == strcmp(type, "all"))	/* default parameter */
		start_type = ZBX_SRV_STARTTYPE_ALL;
	else if (0 == strcmp(type, "automatic"))
		start_type = ZBX_SRV_STARTTYPE_AUTOMATIC;
	else if (0 == strcmp(type, "manual"))
		start_type = ZBX_SRV_STARTTYPE_MANUAL;
	else if (0 == strcmp(type, "disabled"))
		start_type = ZBX_SRV_STARTTYPE_DISABLED;
	else
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, state, sizeof(state)))
		*state = '\0';

	if ('\0' == *state || 0 == strcmp(state, "all"))	/* default parameter */
		service_state = ZBX_SRV_STATE_ALL;
	else if (0 == strcmp(state, "stopped"))
		service_state = ZBX_SRV_STATE_STOPPED;
	else if (0 == strcmp(state, "started"))
		service_state = ZBX_SRV_STATE_STARTED;
	else if (0 == strcmp(state, "start_pending"))
		service_state = ZBX_SRV_STATE_START_PENDING;
	else if (0 == strcmp(state, "stop_pending"))
		service_state = ZBX_SRV_STATE_STOP_PENDING;
	else if (0 == strcmp(state, "running"))
		service_state = ZBX_SRV_STATE_RUNNING;
	else if (0 == strcmp(state, "continue_pending"))
		service_state = ZBX_SRV_STATE_CONTINUE_PENDING;
	else if (0 == strcmp(state, "pause_pending"))
		service_state = ZBX_SRV_STATE_PAUSE_PENDING;
	else if (0 == strcmp(state, "paused"))
		service_state = ZBX_SRV_STATE_PAUSED;
	else
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, exclude, sizeof(exclude)))
		*exclude = '\0';

	if (NULL == (h_mgr = OpenSCManager(NULL, NULL, GENERIC_READ)))
		return SYSINFO_RET_FAIL;

	while (0 != (ret = EnumServicesStatusEx(h_mgr, SC_ENUM_PROCESS_INFO, SERVICE_WIN32, SERVICE_STATE_ALL,
			(LPBYTE)ssp, sz, &szn, &services, &resume_handle, NULL)) || ERROR_MORE_DATA == GetLastError())
	{
		for (i = 0; i < services; i++)
		{
			SC_HANDLE	h_srv;

			if (NULL == (h_srv = OpenService(h_mgr, ssp[i].lpServiceName, SERVICE_QUERY_STATUS | SERVICE_QUERY_CONFIG)))
				continue;

			if (SUCCEED == check_service_starttype(h_srv, start_type))
				if (SUCCEED == check_service_state(h_srv, service_state))
				{
					utf8 = zbx_unicode_to_utf8(ssp[i].lpServiceName);
					if (FAIL == str_in_list(exclude, utf8, ','))
						buf = zbx_strdcatf(buf, "%s\n", utf8);
					zbx_free(utf8);
				}

			CloseServiceHandle(h_srv);
		}

		if (0 == szn)
			break;

		if (NULL == ssp)
		{
			sz = szn;
			ssp = (ENUM_SERVICE_STATUS_PROCESS *)zbx_malloc((void *)ssp, sz);
		}
	}

	zbx_free(ssp);

	CloseServiceHandle(h_mgr);

	if (NULL == buf)
		buf = strdup("0");

	SET_STR_RESULT(result, buf);

	return SYSINFO_RET_OK;
}
