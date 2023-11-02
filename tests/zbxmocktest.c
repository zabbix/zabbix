/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxtypes.h"

/* unresolved symbols needed for linking */

static unsigned char	program_type	= 0;

unsigned char	get_program_type(void)
{
	return program_type;
}

static int	CONFIG_FORKS[ZBX_PROCESS_TYPE_COUNT] = {
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
	3, /* ZBX_PROCESS_TYPE_PREPROCESSOR */
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
};

int	get_config_forks(unsigned char process_type)
{
	return CONFIG_FORKS[process_type];
}

void	set_config_forks(unsigned char process_type, int forks)
{
	CONFIG_FORKS[process_type] = forks;
}

int	CONFIG_CONFSYNCER_FREQUENCY	= 60;

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

char	*CONFIG_EXTERNALSCRIPTS		= NULL;

char	*CONFIG_JAVA_GATEWAY		= NULL;
int	CONFIG_JAVA_GATEWAY_PORT	= 0;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

int	CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS = 0;

/* web monitoring */
char	*CONFIG_SSL_CA_LOCATION		= NULL;
char	*CONFIG_SSL_CERT_LOCATION	= NULL;
char	*CONFIG_SSL_KEY_LOCATION	= NULL;

char	*CONFIG_HISTORY_STORAGE_URL		= NULL;
char	*CONFIG_HISTORY_STORAGE_OPTS		= NULL;
int	CONFIG_HISTORY_STORAGE_PIPELINES	= 0;

/* not used in tests, defined for linking with comms.c */
int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

const char	title_message[] = "mock_title_message";
const char	*usage_message[] = {"mock_usage_message", NULL};
const char	*help_message[] = {"mock_help_message", NULL};
const char	*progname = "mock_progname";
const char	syslog_app_name[] = "mock_syslog_app_name";

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

	zbx_set_log_level(LOG_LEVEL_INFORMATION);
	zbx_init_library_common(zbx_mock_log_impl);

	return cmocka_run_group_tests(tests, NULL, NULL);
}
