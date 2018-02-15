/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_SENDER_H
#define ZABBIX_SENDER_H

#ifdef ZBX_EXPORT
#	define ZBX_API __declspec(dllexport)
#else
#	define ZBX_API __declspec(dllimport)
#endif

typedef struct
{
	/* host name, must match the name of target host in Zabbix */
	char	*host;
	/* the item key */
	char	*key;
	/* the item value */
	char	*value;
}
zabbix_sender_value_t;

typedef struct
{
	/* number of total values processed */
	int	total;
	/* number of failed values */
	int	failed;
	/* time in seconds the server spent processing the sent values */
	double	time_spent;
}
zabbix_sender_info_t;

/******************************************************************************
 *                                                                            *
 * Function: zabbix_sender_send_values                                        *
 *                                                                            *
 * Purpose: send values to Zabbix server/proxy                                *
 *                                                                            *
 * Parameters: address   - [IN] zabbix server/proxy address                   *
 *             port      - [IN] zabbix server/proxy trapper port              *
 *             source    - [IN] source IP, optional - can be NULL             *
 *             values    - [IN] array of values to send                       *
 *             count     - [IN] number of items in values array               *
 *             result    - [OUT] the server response/error message, optional  *
 *                         If result is specified it must always be freed     *
 *                         afterwards with zabbix_sender_free_result()        *
 *                         function.                                          *
 *                                                                            *
 * Return value: 0 - the values were sent successfully, result contains       *
 *                         server response                                    *
 *               -1 - an error occurred, result contains error message        *
 *                                                                            *
 ******************************************************************************/
ZBX_API int	zabbix_sender_send_values(const char *address, unsigned short port, const char *source,
		const zabbix_sender_value_t *values, int count, char **result);

/******************************************************************************
 *                                                                            *
 * Function: zabbix_sender_parse_result                                       *
 *                                                                            *
 * Purpose: parses the result returned from zabbix_sender_send_values()       *
 *          function                                                          *
 *                                                                            *
 * Parameters: result   - [IN] result to parse                                *
 *             response - [OUT] the operation response                        *
 *                           0 - operation was successful                     *
 *                          -1 - operation failed                             *
 *             info     - [OUT] the detailed information about processed      *
 *                        values, optional                                    *
 *                                                                            *
 * Return value:  0 - the result was parsed successfully                      *
 *               -1 - the result parsing failed                               *
 *                                                                            *
 * Comments: If info parameter was specified but the function failed to parse *
 *           the result info field, then info->total is set to -1.            *
 *                                                                            *
 ******************************************************************************/
ZBX_API int	zabbix_sender_parse_result(const char *result, int *response, zabbix_sender_info_t *info);

/******************************************************************************
 *                                                                            *
 * Function: zabbix_sender_free_result                                        *
 *                                                                            *
 * Purpose: free data allocated by zabbix_sender_send_values() function       *
 *                                                                            *
 * Parameters: ptr   - [IN] pointer to the data to free                       *
 *                                                                            *
 ******************************************************************************/
ZBX_API void	zabbix_sender_free_result(void *ptr);

#endif	/* ZABBIX_SENDER_H */
