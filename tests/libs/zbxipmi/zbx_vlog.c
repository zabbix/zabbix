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

/* zbx_vlog() is static, so the source file is included directly to reach it. It is OpenIPMI's log         */
/* callback (registered via os_hnd->set_log_handler() in zbx_init_ipmi()) - a pure format-string wrapper    */
/* around zabbix_log(), with no OpenIPMI object involved, so every ipmi_log_type_e value is exercised here. */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

static void	call_zbx_vlog(enum ipmi_log_type_e log_type, const char *format, ...)
{
	va_list	ap;

	va_start(ap, format);
	zbx_vlog(NULL, format, log_type, ap);
	va_end(ap);
}

static enum ipmi_log_type_e	log_type_by_name(const char *name)
{
	if (0 == strcmp(name, "info"))
		return IPMI_LOG_INFO;
	if (0 == strcmp(name, "warning"))
		return IPMI_LOG_WARNING;
	if (0 == strcmp(name, "severe"))
		return IPMI_LOG_SEVERE;
	if (0 == strcmp(name, "fatal"))
		return IPMI_LOG_FATAL;
	if (0 == strcmp(name, "err_info"))
		return IPMI_LOG_ERR_INFO;
	if (0 == strcmp(name, "debug_start"))
		return IPMI_LOG_DEBUG_START;
	if (0 == strcmp(name, "debug"))
		return IPMI_LOG_DEBUG;
	if (0 == strcmp(name, "debug_cont"))
		return IPMI_LOG_DEBUG_CONT;
	if (0 == strcmp(name, "debug_end"))
		return IPMI_LOG_DEBUG_END;

	fail_msg("unknown 'in.log_type' value: %s", name);
	return IPMI_LOG_INFO;
}

void	zbx_mock_test_entry(void **state)
{
	enum ipmi_log_type_e	log_type = log_type_by_name(zbx_mock_get_parameter_string("in.log_type"));

	ZBX_UNUSED(state);

	call_zbx_vlog(log_type, "value is %d and %s", 42, "text");
}
