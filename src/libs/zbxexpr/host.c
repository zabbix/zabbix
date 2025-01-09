/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxexpr.h"

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - char is allowed in host name                      *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 * Comments: in host name allowed characters: '0-9a-zA-Z. _-'                 *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_hostname_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == ' ' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns hostname and key                                          *
 *          <hostname:>key                                                    *
 *                                                                            *
 * Parameters: exp  - [IN] pointer to first char of hostname                  *
 *                         host:key[key params]                               *
 *                         ^                                                  *
 *             host - [OUT]                                                   *
 *             key  - [OUT]                                                   *
 *                                                                            *
 * Return value: returns SUCCEED or FAIL                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_parse_host_key(char *exp, char **host, char **key)
{
	char	*p, *s;

	if (NULL == exp || '\0' == *exp)
		return FAIL;

	for (p = exp, s = exp; '\0' != *p; p++)	/* check for optional hostname */
	{
		if (':' == *p)	/* hostname:vfs.fs.size[/,total]
				 * --------^
				 */
		{
			*p = '\0';
			*host = zbx_strdup(NULL, s);
			*p++ = ':';

			s = p;
			break;
		}

		if (SUCCEED != zbx_is_hostname_char(*p))
			break;
	}

	*key = zbx_strdup(NULL, s);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces all not-allowed hostname characters in string            *
 *                                                                            *
 * Parameters: host - [IN] target C-style string                              *
 *                                                                            *
 * Comments: String must be null-terminated, otherwise not secure!            *
 *                                                                            *
 ******************************************************************************/
void	zbx_make_hostname(char *host)
{
	char	*c;

	assert(host);

	for (c = host; '\0' != *c; ++c)
	{
		if (FAIL == zbx_is_hostname_char(*c))
			*c = '_';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks byte stream for valid hostname                             *
 *                                                                            *
 * Parameters: hostname - [IN]  pointer to first char of hostname             *
 *             error    - [OUT] pointer to error message (can be NULL)        *
 *                                                                            *
 * Return value: SUCCEED - if hostname is valid                               *
 *               FAIL - If hostname contains invalid chars, is empty or is    *
 *                      longer than ZBX_MAX_HOSTNAME_LEN.                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_hostname(const char *hostname, char **error)
{
	int	len = 0;

	while ('\0' != hostname[len])
	{
		if (FAIL == zbx_is_hostname_char(hostname[len]))
		{
			if (NULL != error)
				*error = zbx_dsprintf(NULL, "name contains invalid character '%c'", hostname[len]);
			return FAIL;
		}

		len++;
	}

	if (0 == len)
	{
		if (NULL != error)
			*error = zbx_strdup(NULL, "name is empty");
		return FAIL;
	}

	if (ZBX_MAX_HOSTNAME_LEN < len)
	{
		if (NULL != error)
			*error = zbx_dsprintf(NULL, "name is too long (max %d characters)", ZBX_MAX_HOSTNAME_LEN);
		return FAIL;
	}

	return SUCCEED;
}
