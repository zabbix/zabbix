/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "dbcache.h"
#include "zbxself.h"
#include "daemon.h"
#include "log.h"

#include "snmptrapper.h"

static char		tmp_trap_file[MAX_STRING_LEN];

/******************************************************************************
 *                                                                            *
 * Function: process_traps                                                    *
 *                                                                            *
 * Purpose: process the traps from tmp_trap_file                              *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_traps()
{
	const char	*__function_name = "process_value";
	FILE		*f = NULL;
	char 		line[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_ERR, "In %s(), tmp file [%s]", __function_name, tmp_trap_file);

	if (NULL == (f = fopen(tmp_trap_file, "r")))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): could not open [%s]", __function_name, tmp_trap_file);
		goto close;
	}

	while (NULL != fgets(line, sizeof(line), f))
	{
		zbx_rtrim(line, " \r\n");
		zabbix_log(LOG_LEVEL_ERR, "trap: %s", line);
	}

close:
	zbx_fclose(f);
	remove(tmp_trap_file);

	zabbix_log(LOG_LEVEL_ERR, "End of %s()", __function_name);
}

void	main_snmptrapper_loop(int server_num)
{
	const char	*__function_name = "main_snmptrapper_loop";

	zabbix_log(LOG_LEVEL_ERR, "In %s(), trapfile [%s]", __function_name, CONFIG_SNMPTRAP_FILE);

	zbx_snprintf(tmp_trap_file, sizeof(tmp_trap_file), "%s_%d", CONFIG_SNMPTRAP_FILE, server_num);

	set_child_signal_handler();

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		/* if file exists, process the new traps */
		while (0 == rename(CONFIG_SNMPTRAP_FILE, tmp_trap_file))
			process_traps();

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		zbx_setproctitle("%s [waiting for traps]", get_process_type_string(process_type));
		zbx_setproctitle("snmptrapper [sleeping for 1 second]");
		zbx_sleep(1);
	}
}
