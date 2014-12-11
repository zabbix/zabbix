/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "log.h"
#include "tls.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_init_parent                                              *
 *                                                                            *
 * Purpose: initialize TLS library in a parent process                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_init_parent(void)
{
	const char	*__function_name = "zbx_tls_init_parent";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	/* TODO fill in implementation */
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_init_child                                               *
 *                                                                            *
 * Purpose: initialize TLS library in a child process                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_init_child(void)
{
	const char	*__function_name = "zbx_tls_init_child";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	/* TODO fill in implementation */
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_free                                                     *
 *                                                                            *
 * Purpose: release TLS library resources allocated in zbx_tls_init_parent()  *
 *          and zbx_tls_init_child()                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_free(void)
{
	const char	*__function_name = "zbx_tls_free";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	/* TODO fill in implementation */
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
