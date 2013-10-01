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

#ifndef ZABBIXSENDER_H_
#define ZABBIXSENDER_H_

#ifdef ZBX_EXPORT
# define ZBX_API __declspec(dllexport)
#else
#define ZBX_API __declspec(dllimport)
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
zbx_sender_value_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_sender_send_values                                           *
 *                                                                            *
 * Purpose: send values to Zabbix server/proxy                                *
 *                                                                            *
 * Parameters: address   - [IN] zabbix server/proxy address                   *
 *             port      - [IN] zabbix server/proxy trapper port              *
 *             source    - [IN] source IP, optional - can be NULL             *
 *             values    - [IN] array of values to send                       *
 *             count     - [IN] number of items in values array               *
 *             result    - [OUT] the server response/error message. Optional, *
 *                         can be NULL.                                       *
 *                         If result is specified it must always be freed     *
 *                         afterwards with zbx_sender_result_free() function. *
 *                                                                            *
 * Return value: SUCCEED - the values were sent successfully, result contains *
 *                         server response                                    *
 *               FAIL - an error occurred, rsult contains error message       *
 *                                                                            *
 ******************************************************************************/
ZBX_API int zbx_sender_send_values(const char *address, unsigned short port, const char *source,
		const zbx_sender_value_t *values, int count, char **result);

/******************************************************************************
 *                                                                            *
 * Function: zbx_sender_result_free                                           *
 *                                                                            *
 * Purpose: free data allocated by zbx_sender_send_values() function          *
 *                                                                            *
 * Parameters: ptr   - [IN] pointer to the data to free                       *
 *                                                                            *
 ******************************************************************************/
ZBX_API void zbx_sender_result_free(void *ptr);


#endif /* ZABBIXSENDER_H_ */
