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
#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxtypes.h"
#ifndef _WINDOWS
#	include "zbxnix.h"
#endif
/* unresolved symbols needed for linking */

static unsigned char	program_type	= 0;

unsigned char	get_program_type(void)
{
	return program_type;
}

static int	config_forks[ZBX_PROCESS_TYPE_COUNT] = {
	5, /* ZBX_PROCESS_TYPE_POLLER */
	1, /* ZBX_PROCESS_TYPE_UNREACHABLE */
	0, /* ZBX_PROCESS_TYPE_IPMIPOLLER */
	1, /* ZBX_PROCESS_TYPE_PINGER */
	0, /* ZBX_PROCESS_TYPE_JAVAPOLLER */
	1, /* ZBX_PROCESS_TYPE_HTTPPOLLER */
	5, /* ZBX_PROCESS_TYPE_TRAPPER */
	0, /* ZBX_PROCESS_TYPE_SNMPTRAPPER */
	1, /* ZBX_PROCESS_TYPE_PROXYPOLLER */
	1, /* ZBX_PROCESS_TYPE_ESCALATOR */
	4, /* ZBX_PROCESS_TYPE_HISTSYNCER */
	1, /* ZBX_PROCESS_TYPE_DISCOVERER */
	3, /* ZBX_PROCESS_TYPE_ALERTER */
	1, /* ZBX_PROCESS_TYPE_TIMER */
	1, /* ZBX_PROCESS_TYPE_HOUSEKEEPER */
	0, /* ZBX_PROCESS_TYPE_DATASENDER */
	1, /* ZBX_PROCESS_TYPE_CONFSYNCER */
	1, /* ZBX_PROCESS_TYPE_SELFMON */
	0, /* ZBX_PROCESS_TYPE_VMWARE */
	0, /* ZBX_PROCESS_TYPE_COLLECTOR */
	0, /* ZBX_PROCESS_TYPE_LISTENER */
	0, /* ZBX_PROCESS_TYPE_ACTIVE_CHECKS */
	1, /* ZBX_PROCESS_TYPE_TASKMANAGER */
	0, /* ZBX_PROCESS_TYPE_IPMIMANAGER */
	1, /* ZBX_PROCESS_TYPE_ALERTMANAGER */
	1, /* ZBX_PROCESS_TYPE_PREPROCMAN */
	16, /* ZBX_PROCESS_TYPE_PREPROCESSOR */
	1, /* ZBX_PROCESS_TYPE_LLDMANAGER */
	2, /* ZBX_PROCESS_TYPE_LLDWORKER */
	1, /* ZBX_PROCESS_TYPE_ALERTSYNCER */
	5, /* ZBX_PROCESS_TYPE_HISTORYPOLLER */
	1, /* ZBX_PROCESS_TYPE_AVAILMAN */
	0, /* ZBX_PROCESS_TYPE_REPORTMANAGER */
	0, /* ZBX_PROCESS_TYPE_REPORTWRITER */
	1, /* ZBX_PROCESS_TYPE_SERVICEMAN */
	1, /* ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER */
	5, /* ZBX_PROCESS_TYPE_ODBCPOLLER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORMANAGER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORWORKER */
	0, /* ZBX_PROCESS_TYPE_DISCOVERYMANAGER */
	1, /* ZBX_PROCESS_TYPE_HTTPAGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_AGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_SNMP_POLLER */
	0, /* ZBX_PROCESS_TYPE_INTERNAL_POLLER */
	0, /* ZBX_PROCESS_TYPE_DBCONFIGWORKER */
	0, /* ZBX_PROCESS_TYPE_PG_MANAGER */
	0, /* ZBX_PROCESS_TYPE_BROWSERPOLLER */
	0 /* ZBX_PROCESS_TYPE_HA_MANAGER */
};

int	get_config_forks(unsigned char process_type)
{
	return config_forks[process_type];
}

void	set_config_forks(unsigned char process_type, int forks)
{
	config_forks[process_type] = forks;
}

static zbx_uint64_t	zbx_config_value_cache_size	= 8 * 0;

zbx_uint64_t	get_zbx_config_value_cache_size(void)
{
	return zbx_config_value_cache_size;
}

void	set_zbx_config_value_cache_size(zbx_uint64_t cache_size)
{
	zbx_config_value_cache_size = cache_size;
}

zbx_uint64_t	CONFIG_TREND_FUNC_CACHE_SIZE	= 0;

/* not used in tests, defined for linking with comms.c */
int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

ZBX_GET_CONFIG_VAR2(const char *, const char *, zbx_progname, "mock_progname")

char	*CONFIG_HOSTNAME_ITEM		= NULL;

static ZBX_THREAD_LOCAL int	zbx_config_timeout = 3;
int	get_zbx_config_timeout(void)
{
	return zbx_config_timeout;
}

static const char	*zbx_config_source_ip = "127.0.0.1";
const char	*get_zbx_config_source_ip(void)
{
	return zbx_config_source_ip;
}

static int	zbx_config_enable_remote_commands = 0;
int	get_zbx_config_enable_remote_commands(void)
{
	return zbx_config_enable_remote_commands;
}

/* test itself */

int	main (void)
{
	const struct CMUnitTest tests[] =
	{
		cmocka_unit_test_setup_teardown(zbx_mock_test_entry, zbx_mock_data_init, zbx_mock_data_free)
	};

	zbx_set_log_level(LOG_LEVEL_TRACE);
	zbx_init_library_common(zbx_mock_log_impl, get_zbx_progname, zbx_backtrace);
#ifndef _WINDOWS
	zbx_init_library_nix(get_zbx_progname, NULL);
#endif
	return cmocka_run_group_tests(tests, NULL, NULL);
}
