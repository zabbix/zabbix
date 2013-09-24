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

#include "common.h"

/*
 * In zabbix alarm() function is often used to set a timeout in the following pattern:
 *   alarm(timeout);
 *   ... do some work
 *   alarm(0);
 *
 * In complex operations there is a chance that alarm will be signalled outside
 * blocking functions (read(), write(), select() and similar) and therefore will
 * be undetected.
 *
 * zbx_alarm_* functions are meant to replace the above pattern by providing
 * a way to check if the alarm has already happened:
 *   zbx_alarm_set(timeout);
 *   ... do some work, check if the alarm has been triggered with zbx_alarm_check()
 *   zbx_alarm_remove();
 *
 * TODO: instead of using time to check if the alarm has been triggered, set
 *       a flag inside alarm signal handler and check it. However there are
 *       some dependency problems which must be solved first.
 */


static int	alarm_time = 0;
static int	alarm_timeout = 0;

/******************************************************************************
 *                                                                            *
 * Function: zbx_alarm_set                                                    *
 *                                                                            *
 * Purpose: set alarm time                                                    *
 *                                                                            *
 * Parameters: seconds - [IN] the timeout period                              *
 *                                                                            *
 * Comments: The zbx_alarm_set() function is used to set timeout alarm.       *
 *                                                                            *
 ******************************************************************************/
void	zbx_alarm_set(int seconds)
{
	alarm_time = time(NULL);
	alarm_timeout = seconds;

#if !defined(_WINDOWS)
	alarm(seconds);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_alarm_remove                                                 *
 *                                                                            *
 * Purpose: removes previously set alarm                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_alarm_remove()
{
#if !defined(_WINDOWS)
	alarm(0);
#endif
	alarm_time = 0;
	alarm_timeout = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_alarm_check                                                  *
 *                                                                            *
 * Purpose: checks if the alarm was triggered                                 *
 *                                                                            *
 * Return value: 1 if the alarm was triggered, 0 otherwise                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_alarm_check()
{
	return (0 != alarm_time && time(NULL) - alarm_time >= alarm_timeout) ? 1 : 0;
}



