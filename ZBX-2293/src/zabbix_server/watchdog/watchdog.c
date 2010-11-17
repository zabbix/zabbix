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

#include "cfg.h"
#include "db.h"
#include "zbxdb.h"
#include "log.h"
#include "zlog.h"

#include "../alerter/alerter.h"

#include "zlog.h"
#include "watchdog.h"

typedef struct
{
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;
}
ZBX_RECIPIENT;

#define	ZBX_MAX_RECIPIENTS	32

ZBX_RECIPIENT	recipients[ZBX_MAX_RECIPIENTS];

static int	num = 0;
static int	lastsent = 0;

/******************************************************************************
 *                                                                            *
 * Function: send_alerts                                                      *
 *                                                                            *
 * Purpose: send warning message to all interested                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	send_alerts()
{
	int	i, now;
	char	error[MAX_STRING_LEN];

	now = time(NULL);

	if (now > lastsent + 15 * SEC_PER_MIN)
	{
		for (i = 0; i < num; i++)
		{
			execute_action(&recipients[i].alert, &recipients[i].mediatype, error, sizeof(error));
		}

		lastsent = now;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: init_config                                                      *
 *                                                                            *
 * Purpose: init list of medias to send notifications in case if DB is down   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	init_config()
{
	const char	*__function_name = "init_config";

	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select mt.mediatypeid,mt.type,mt.description,"
				"mt.smtp_server,mt.smtp_helo,mt.smtp_email,"
				"mt.exec_path,mt.gsm_modem,"
				"mt.username,mt.passwd,m.sendto"
				" from media m,users_groups u,config c,media_type mt"
				" where m.userid=u.userid"
					" and u.usrgrpid=c.alert_usrgrpid"
					" and m.mediatypeid=mt.mediatypeid"
					" and m.active=%d",
				MEDIA_STATUS_ACTIVE);

	while (NULL != (row = DBfetch(result)))
	{
		if (num >= ZBX_MAX_RECIPIENTS)
			break;

		memset(&recipients[num].mediatype, 0, sizeof(DB_MEDIATYPE));
		memset(&recipients[num].alert, 0, sizeof(DB_ALERT));

		ZBX_STR2UINT64(recipients[num].mediatype.mediatypeid, row[0]);
		recipients[num].mediatype.type = atoi(row[1]);
		recipients[num].mediatype.description = strdup(row[2]);
		recipients[num].mediatype.smtp_server = strdup(row[3]);
		recipients[num].mediatype.smtp_helo = strdup(row[4]);
		recipients[num].mediatype.smtp_email = strdup(row[5]);
		recipients[num].mediatype.exec_path = strdup(row[6]);
		recipients[num].mediatype.gsm_modem = strdup(row[7]);
		recipients[num].mediatype.username = strdup(row[8]);
		recipients[num].mediatype.passwd = strdup(row[9]);

		recipients[num].alert.sendto = strdup(row[10]);
		recipients[num].alert.subject = strdup("Zabbix database is down.");
		recipients[num].alert.message = strdup("Zabbix database is down.");

		num++;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: ping_database                                                    *
 *                                                                            *
 * Purpose: check availability of database                                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	ping_database()
{
	const char	*__function_name = "ping_database";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == DBping()) /* check whether a connection to the database can be made */
	{
		zabbix_log(LOG_LEVEL_WARNING, "Watchdog: Database is down");
		send_alerts();
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Watchdog: Database is up");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: main_watchdog_loop                                               *
 *                                                                            *
 * Purpose: periodically checks availability of database and alerts admins if *
 *          down                                                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: check database availability every 60 seconds (hardcoded)         *
 *                                                                            *
 ******************************************************************************/
void	main_watchdog_loop()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In main_watchdog_loop()");

	/* Disable writing to database in zabbix_syslog() */
	CONFIG_ENABLE_LOG = 0;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	init_config();

	DBclose();

	for (;;)
	{
		ping_database();

		sleep(60);
	}

	/* We will never reach this point */
}
