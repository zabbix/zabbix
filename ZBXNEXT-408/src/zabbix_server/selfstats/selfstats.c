/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
#include "daemon.h"
#include "zbxself.h"
#include "log.h"

void	main_selfstats_loop()
{
	const char	*__function_name = "main_selfstats_loop";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	set_child_signal_handler();

	zbx_setproctitle("self-monitoring collector");

	for (;;)
	{
		collect_selfstats();
		sleep(1);
	}
}
