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

#include "zbxcommon.h"
#include "zbxsnmp.h"

#if defined(HAVE_NETSNMP)

#	define SNMP_NO_DEBUGGING		/* disabling debugging messages from Net-SNMP library */
#	include <net-snmp/net-snmp-config.h>
#	include <net-snmp/net-snmp-includes.h>

static int	zbx_snmp_init_done;

void	zbx_snmp_init(const char *progname)
{
	sigset_t	mask, orig_mask;

	if (1 == zbx_snmp_init_done)
		return;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_LOAD, 1);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_SAVE, 1);

	init_snmp(progname);

	netsnmp_init_mib();

	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_OIDS, 1);
	netsnmp_ds_set_int(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_OID_OUTPUT_FORMAT, NETSNMP_OID_OUTPUT_NUMERIC);

	zbx_snmp_init_done = 1;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}

void	zbx_snmp_clear(const char *progname)
{
	sigset_t	mask, orig_mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	snmp_shutdown(progname);
	zbx_snmp_init_done = 0;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}

#endif
