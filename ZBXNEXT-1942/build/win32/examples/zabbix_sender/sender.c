/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include <stdio.h>
#include <stdlib.h>

#include "zabbix_sender.h"

/*
 * This is a simple Zabbix sender utility implemented with
 * Zabbix sender dynamic link library to illustrate the
 * library usage.
 *
 * See zabbix_sender.h header file for API specifications.
 *
 * This utility can be built in Microsoft Windows build
 * environment with the following command: nmake /f Makefile
 */

int main(int argc, const char *argv[])
{
	if (5 == argc)
	{
		char			*result = NULL;
		zabbix_sender_info_t	info;
		zabbix_sender_value_t	value = {argv[2], argv[3], argv[4]};
		int			response;

		/* send one value to the argv[1] IP address and the default trapper port 10051 */
		if (-1 == zabbix_sender_send_values(argv[1], 10051, NULL, &value, 1, &result))
		{
			printf("Sending failed: %s\n", result);
		}
		else
		{
			printf("Sending succeeded:\n");

			/* parse the server response */
			if (0 == zabbix_sender_parse_result(result, &response, &info))
			{
				printf("\tResult: %s\n", 0 == response ? "OK" : "failed");
				printf("\tTotal: %d; failed: %d; time spent: %lf\n",
						info.total, info.failed, info.time_spent);
			}
			else
			{
				printf("\tFailed to parse server response\n");
			}
		}

		/* free the server response */
		zabbix_sender_free_result(result);
	}
	else
	{
		printf("Simple zabbix_sender implementation with zabbix_sender library\n");
		printf("\tUsage: %s <address> <host> <key> <value>\n", argv[0]);
		printf("\t\t<address> - Zabbix server IP address\n");
		printf("\t\t<host> - host name\n");
		printf("\t\t<key> - item key\n");
		printf("\t\t<value> - item value\n");
	}

	return EXIT_SUCCESS;
}
