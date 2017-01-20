/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
#include "zbxjson.h"

static const DWORD	service_states[7] = {SERVICE_RUNNING, SERVICE_PAUSED, SERVICE_START_PENDING,
	SERVICE_PAUSE_PENDING, SERVICE_CONTINUE_PENDING, SERVICE_STOP_PENDING, SERVICE_STOPPED};
static const DWORD	start_types[4] = {SERVICE_AUTO_START, SERVICE_AUTO_START, SERVICE_DEMAND_START,
	SERVICE_DISABLED};

static const char	*get_state_string(DWORD state)
{
	switch (state)
	{
		case SERVICE_RUNNING:
			return "running";
		case SERVICE_PAUSED:
			return "paused";
		case SERVICE_START_PENDING:
			return "start pending";
		case SERVICE_PAUSE_PENDING:
			return "pause pending";
		case SERVICE_CONTINUE_PENDING:
			return "continue pending";
		case SERVICE_STOP_PENDING:
			return "stop pending";
		case SERVICE_STOPPED:
			return "stopped";
		default:
			return "unknown";
	}
}

static const char	*get_startup_string(DWORD startup)
{
	switch (startup)
	{
		case SERVICE_AUTO_START:
			return "automatic";
		case SERVICE_DEMAND_START:
			return "manual";
		case SERVICE_DISABLED:
			return "disabled";
		default:
			return "unknown";
	}
}

static int	check_delayed_start(SC_HANDLE h_srv)
{
	SERVICE_DELAYED_AUTO_START_INFO	*sds = NULL;
	DWORD				sz = 0;
	int 				ret = FAIL;

	QueryServiceConfig2(h_srv, SERVICE_CONFIG_DELAYED_AUTO_START_INFO, NULL, 0, &sz);

	if (ERROR_INSUFFICIENT_BUFFER == GetLastError())
	{
		sds = (SERVICE_DELAYED_AUTO_START_INFO *)zbx_malloc(sds, sz);

		if (0 != QueryServiceConfig2(h_srv, SERVICE_CONFIG_DELAYED_AUTO_START_INFO, (LPBYTE)sds, sz, &sz) &&
				TRUE == sds->fDelayedAutostart)
		{
			ret = SUCCEED;
		}

		zbx_free(sds);
	}

	return ret;
}

int	SERVICE_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ENUM_SERVICE_STATUS_PROCESS	*ssp = NULL;
	QUERY_SERVICE_CONFIG		*qsc = NULL;
	SERVICE_DESCRIPTION		*scd = NULL;
	SC_HANDLE			h_mgr;
	DWORD				sz = 0, szn, i, k, services, resume_handle = 0;
	char				*utf8;
	struct zbx_json			j;

	if (NULL == (h_mgr = OpenSCManager(NULL, NULL, GENERIC_READ)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	while (0 != EnumServicesStatusEx(h_mgr, SC_ENUM_PROCESS_INFO, SERVICE_WIN32, SERVICE_STATE_ALL,
			(LPBYTE)ssp, sz, &szn, &services, &resume_handle, NULL) || ERROR_MORE_DATA == GetLastError())
	{
		for (i = 0; i < services; i++)
		{
			SC_HANDLE	h_srv;
			DWORD		current_state;

			if (NULL == (h_srv = OpenService(h_mgr, ssp[i].lpServiceName, SERVICE_QUERY_CONFIG)))
				continue;

			QueryServiceConfig(h_srv, NULL, 0, &sz);

			if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain configuration of service \"%s\": %s",
						ssp[i].lpServiceName, strerror_from_system(GetLastError()));
				goto next;
			}

			qsc = (QUERY_SERVICE_CONFIG *)zbx_malloc(qsc, sz);

			if (0 == QueryServiceConfig(h_srv, qsc, sz, &sz))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain configuration of service \"%s\": %s",
						ssp[i].lpServiceName, strerror_from_system(GetLastError()));
				goto next;
			}

			QueryServiceConfig2(h_srv, SERVICE_CONFIG_DESCRIPTION, NULL, 0, &sz);

			if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain description of service \"%s\": %s",
						ssp[i].lpServiceName, strerror_from_system(GetLastError()));
				goto next;
			}

			scd = (SERVICE_DESCRIPTION *)zbx_malloc(scd, sz);

			if (0 == QueryServiceConfig2(h_srv, SERVICE_CONFIG_DESCRIPTION, (LPBYTE)scd, sz, &sz))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain description of service \"%s\": %s",
						ssp[i].lpServiceName, strerror_from_system(GetLastError()));
				goto next;
			}

			zbx_json_addobject(&j, NULL);

			utf8 = zbx_unicode_to_utf8(ssp[i].lpServiceName);
			zbx_json_addstring(&j, "{#SERVICE.NAME}", utf8, ZBX_JSON_TYPE_STRING);
			zbx_free(utf8);

			utf8 = zbx_unicode_to_utf8(ssp[i].lpDisplayName);
			zbx_json_addstring(&j, "{#SERVICE.DISPLAYNAME}", utf8, ZBX_JSON_TYPE_STRING);
			zbx_free(utf8);

			if (NULL != scd->lpDescription)
			{
				utf8 = zbx_unicode_to_utf8(scd->lpDescription);
				zbx_json_addstring(&j, "{#SERVICE.DESCRIPTION}", utf8, ZBX_JSON_TYPE_STRING);
				zbx_free(utf8);
			}
			else
				zbx_json_addstring(&j, "{#SERVICE.DESCRIPTION}", "", ZBX_JSON_TYPE_STRING);

			current_state = ssp[i].ServiceStatusProcess.dwCurrentState;
			for (k = 0; k < ARRSIZE(service_states) && current_state != service_states[k]; k++)
				;

			zbx_json_adduint64(&j, "{#SERVICE.STATE}", k);
			zbx_json_addstring(&j, "{#SERVICE.STATENAME}", get_state_string(current_state),
					ZBX_JSON_TYPE_STRING);

			utf8 = zbx_unicode_to_utf8(qsc->lpBinaryPathName);
			zbx_json_addstring(&j, "{#SERVICE.PATH}", utf8, ZBX_JSON_TYPE_STRING);
			zbx_free(utf8);

			utf8 = zbx_unicode_to_utf8(qsc->lpServiceStartName);
			zbx_json_addstring(&j, "{#SERVICE.USER}", utf8, ZBX_JSON_TYPE_STRING);
			zbx_free(utf8);

			if (SERVICE_AUTO_START == qsc->dwStartType)
			{
				if (SUCCEED == check_delayed_start(h_srv))
				{
					zbx_json_adduint64(&j, "{#SERVICE.STARTUP}", 1);
					zbx_json_addstring(&j, "{#SERVICE.STARTUPNAME}", "automatic delayed",
							ZBX_JSON_TYPE_STRING);
				}
				else
				{
					zbx_json_adduint64(&j, "{#SERVICE.STARTUP}", 0);
					zbx_json_addstring(&j, "{#SERVICE.STARTUPNAME}", "automatic",
							ZBX_JSON_TYPE_STRING);
				}
			}
			else
			{
				for (k = 2; k < ARRSIZE(start_types) &&	qsc->dwStartType != start_types[k]; k++)
					;

				zbx_json_adduint64(&j, "{#SERVICE.STARTUP}", k);
				zbx_json_addstring(&j, "{#SERVICE.STARTUPNAME}", get_startup_string(qsc->dwStartType),
						ZBX_JSON_TYPE_STRING);
			}

			zbx_json_close(&j);
next:
			zbx_free(scd);
			zbx_free(qsc);

			CloseServiceHandle(h_srv);
		}

		if (0 == szn)
			break;

		if (NULL == ssp)
		{
			sz = szn;
			ssp = (ENUM_SERVICE_STATUS_PROCESS *)zbx_malloc(ssp, sz);
		}
	}

	zbx_free(ssp);

	CloseServiceHandle(h_mgr);

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

#define ZBX_SRV_PARAM_STATE		0x01
#define ZBX_SRV_PARAM_DISPLAYNAME	0x02
#define ZBX_SRV_PARAM_PATH		0x03
#define ZBX_SRV_PARAM_USER		0x04
#define ZBX_SRV_PARAM_STARTUP		0x05
#define ZBX_SRV_PARAM_DESCRIPTION	0x06

int	SERVICE_INFO(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	QUERY_SERVICE_CONFIG	*qsc = NULL;
	SERVICE_DESCRIPTION	*scd = NULL;
	SERVICE_STATUS		status;
	SC_HANDLE		h_mgr, h_srv;
	DWORD			sz = 0;
	int			param_type, i;
	char			*name, *param;
	wchar_t			*wname, service_name[MAX_STRING_LEN];
	DWORD			max_len_name = MAX_STRING_LEN;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	name = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL == name || '\0' == *name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "state"))	/* default second parameter */
		param_type = ZBX_SRV_PARAM_STATE;
	else if (0 == strcmp(param, "displayname"))
		param_type = ZBX_SRV_PARAM_DISPLAYNAME;
	else if (0 == strcmp(param, "path"))
		param_type = ZBX_SRV_PARAM_PATH;
	else if (0 == strcmp(param, "user"))
		param_type = ZBX_SRV_PARAM_USER;
	else if (0 == strcmp(param, "startup"))
		param_type = ZBX_SRV_PARAM_STARTUP;
	else if (0 == strcmp(param, "description"))
		param_type = ZBX_SRV_PARAM_DESCRIPTION;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (h_mgr = OpenSCManager(NULL, NULL, GENERIC_READ)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	wname = zbx_utf8_to_unicode(name);

	h_srv = OpenService(h_mgr, wname, SERVICE_QUERY_STATUS | SERVICE_QUERY_CONFIG);
	if (NULL == h_srv && 0 != GetServiceKeyName(h_mgr, wname, service_name, &max_len_name))
		h_srv = OpenService(h_mgr, service_name, SERVICE_QUERY_STATUS | SERVICE_QUERY_CONFIG);

	zbx_free(wname);

	if (NULL == h_srv)
	{
		int	ret;

		if (ZBX_SRV_PARAM_STATE == param_type)
		{
			SET_UI64_RESULT(result, 255);
			ret = SYSINFO_RET_OK;
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot find the specified service."));
			ret = SYSINFO_RET_FAIL;
		}

		CloseServiceHandle(h_mgr);
		return ret;
	}

	if (ZBX_SRV_PARAM_STATE == param_type)
	{
		if (0 != QueryServiceStatus(h_srv, &status))
		{
			for (i = 0; i < ARRSIZE(service_states) && status.dwCurrentState != service_states[i]; i++)
				;

			SET_UI64_RESULT(result, i);
		}
		else
			SET_UI64_RESULT(result, 7);
	}
	else if (ZBX_SRV_PARAM_DESCRIPTION == param_type)
	{
		QueryServiceConfig2(h_srv, SERVICE_CONFIG_DESCRIPTION, NULL, 0, &sz);

		if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain service description: %s",
					strerror_from_system(GetLastError())));
			CloseServiceHandle(h_srv);
			CloseServiceHandle(h_mgr);
			return SYSINFO_RET_FAIL;
		}

		scd = (SERVICE_DESCRIPTION *)zbx_malloc(scd, sz);

		if (0 == QueryServiceConfig2(h_srv, SERVICE_CONFIG_DESCRIPTION, (LPBYTE)scd, sz, &sz))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain service description: %s",
					strerror_from_system(GetLastError())));
			zbx_free(scd);
			CloseServiceHandle(h_srv);
			CloseServiceHandle(h_mgr);
			return SYSINFO_RET_FAIL;
		}

		if (NULL == scd->lpDescription)
			SET_TEXT_RESULT(result, zbx_strdup(NULL, ""));
		else
			SET_TEXT_RESULT(result, zbx_unicode_to_utf8(scd->lpDescription));

		zbx_free(scd);
	}
	else
	{
		QueryServiceConfig(h_srv, NULL, 0, &sz);

		if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain service configuration: %s",
					strerror_from_system(GetLastError())));
			CloseServiceHandle(h_srv);
			CloseServiceHandle(h_mgr);
			return SYSINFO_RET_FAIL;
		}

		qsc = (QUERY_SERVICE_CONFIG *)zbx_malloc(qsc, sz);

		if (0 == QueryServiceConfig(h_srv, qsc, sz, &sz))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain service configuration: %s",
					strerror_from_system(GetLastError())));
			zbx_free(qsc);
			CloseServiceHandle(h_srv);
			CloseServiceHandle(h_mgr);
			return SYSINFO_RET_FAIL;
		}

		switch (param_type)
		{
			case ZBX_SRV_PARAM_DISPLAYNAME:
				SET_STR_RESULT(result, zbx_unicode_to_utf8(qsc->lpDisplayName));
				break;
			case ZBX_SRV_PARAM_PATH:
				SET_STR_RESULT(result, zbx_unicode_to_utf8(qsc->lpBinaryPathName));
				break;
			case ZBX_SRV_PARAM_USER:
				SET_STR_RESULT(result, zbx_unicode_to_utf8(qsc->lpServiceStartName));
				break;
			case ZBX_SRV_PARAM_STARTUP:
				if (SERVICE_AUTO_START == qsc->dwStartType)
				{
					if (SUCCEED == check_delayed_start(h_srv))
						SET_UI64_RESULT(result, 1);
					else
						SET_UI64_RESULT(result, 0);
				}
				else
				{
					for (i = 2; i < ARRSIZE(start_types) && qsc->dwStartType != start_types[i]; i++)
						;

					SET_UI64_RESULT(result, i);
				}
				break;
		}

		zbx_free(qsc);
	}

	CloseServiceHandle(h_srv);
	CloseServiceHandle(h_mgr);

	return SYSINFO_RET_OK;
}

int	SERVICE_STATE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SC_HANDLE	mgr, service;
	char		*name;
	wchar_t		*wname;
	wchar_t		service_name[MAX_STRING_LEN];
	DWORD		max_len_name = MAX_STRING_LEN;
	int		i;
	SERVICE_STATUS	status;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	name = get_rparam(request, 0);

	if (NULL == name || '\0' == *name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (mgr = OpenSCManager(NULL, NULL, GENERIC_READ)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	wname = zbx_utf8_to_unicode(name);

	service = OpenService(mgr, wname, SERVICE_QUERY_STATUS);
	if (NULL == service && 0 != GetServiceKeyName(mgr, wname, service_name, &max_len_name))
		service = OpenService(mgr, service_name, SERVICE_QUERY_STATUS);

	zbx_free(wname);

	if (NULL == service)
	{
		SET_UI64_RESULT(result, 255);
	}
	else
	{
		if (0 != QueryServiceStatus(service, &status))
		{
			for (i = 0; i < ARRSIZE(service_states) && status.dwCurrentState != service_states[i]; i++)
				;

			SET_UI64_RESULT(result, i);
		}
		else
			SET_UI64_RESULT(result, 7);

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

	QueryServiceConfig(h_srv, NULL, 0, &sz);

	if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		return FAIL;

	qsc = (QUERY_SERVICE_CONFIG *)zbx_malloc(qsc, sz);

	if (0 != QueryServiceConfig(h_srv, qsc, sz, &sz))
	{
		switch (start_type)
		{
			case ZBX_SRV_STARTTYPE_AUTOMATIC:
				if (SERVICE_AUTO_START == qsc->dwStartType)
					ret = SUCCEED;
				break;
			case ZBX_SRV_STARTTYPE_MANUAL:
				if (SERVICE_DEMAND_START == qsc->dwStartType)
					ret = SUCCEED;
				break;
			case ZBX_SRV_STARTTYPE_DISABLED:
				if (SERVICE_DISABLED == qsc->dwStartType)
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
		switch (status.dwCurrentState)
		{
			case SERVICE_STOPPED:
				if (0 != (service_state & ZBX_SRV_STATE_STOPPED))
					return SUCCEED;
				break;
			case SERVICE_START_PENDING:
				if (0 != (service_state & ZBX_SRV_STATE_START_PENDING))
					return SUCCEED;
				break;
			case SERVICE_STOP_PENDING:
				if (0 != (service_state & ZBX_SRV_STATE_STOP_PENDING))
					return SUCCEED;
				break;
			case SERVICE_RUNNING:
				if (0 != (service_state & ZBX_SRV_STATE_RUNNING))
					return SUCCEED;
				break;
			case SERVICE_CONTINUE_PENDING:
				if (0 != (service_state & ZBX_SRV_STATE_CONTINUE_PENDING))
					return SUCCEED;
				break;
			case SERVICE_PAUSE_PENDING:
				if (0 != (service_state & ZBX_SRV_STATE_PAUSE_PENDING))
					return SUCCEED;
				break;
			case SERVICE_PAUSED:
				if (0 != (service_state & ZBX_SRV_STATE_PAUSED))
					return SUCCEED;
				break;
		}
	}

	return FAIL;
}

int	SERVICES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int				start_type, service_state;
	char				*type, *state, *exclude, *buf = NULL, *utf8;
	SC_HANDLE			h_mgr;
	ENUM_SERVICE_STATUS_PROCESS	*ssp = NULL;
	DWORD				sz = 0, szn, i, services, resume_handle = 0;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	type = get_rparam(request, 0);
	state = get_rparam(request, 1);
	exclude = get_rparam(request, 2);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "all"))	/* default parameter */
		start_type = ZBX_SRV_STARTTYPE_ALL;
	else if (0 == strcmp(type, "automatic"))
		start_type = ZBX_SRV_STARTTYPE_AUTOMATIC;
	else if (0 == strcmp(type, "manual"))
		start_type = ZBX_SRV_STARTTYPE_MANUAL;
	else if (0 == strcmp(type, "disabled"))
		start_type = ZBX_SRV_STARTTYPE_DISABLED;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == state || '\0' == *state || 0 == strcmp(state, "all"))	/* default parameter */
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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (h_mgr = OpenSCManager(NULL, NULL, GENERIC_READ)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	while (0 != EnumServicesStatusEx(h_mgr, SC_ENUM_PROCESS_INFO, SERVICE_WIN32, SERVICE_STATE_ALL,
			(LPBYTE)ssp, sz, &szn, &services, &resume_handle, NULL) || ERROR_MORE_DATA == GetLastError())
	{
		for (i = 0; i < services; i++)
		{
			SC_HANDLE	h_srv;

			if (NULL == (h_srv = OpenService(h_mgr, ssp[i].lpServiceName,
					SERVICE_QUERY_STATUS | SERVICE_QUERY_CONFIG)))
			{
				continue;
			}

			if (SUCCEED == check_service_starttype(h_srv, start_type))
			{
				if (SUCCEED == check_service_state(h_srv, service_state))
				{
					utf8 = zbx_unicode_to_utf8(ssp[i].lpServiceName);

					if (NULL == exclude || FAIL == str_in_list(exclude, utf8, ','))
						buf = zbx_strdcatf(buf, "%s\n", utf8);

					zbx_free(utf8);
				}
			}

			CloseServiceHandle(h_srv);
		}

		if (0 == szn)
			break;

		if (NULL == ssp)
		{
			sz = szn;
			ssp = (ENUM_SERVICE_STATUS_PROCESS *)zbx_malloc(ssp, sz);
		}
	}

	zbx_free(ssp);

	CloseServiceHandle(h_mgr);

	if (NULL == buf)
		buf = zbx_strdup(buf, "0");

	SET_STR_RESULT(result, buf);

	return SYSINFO_RET_OK;
}
