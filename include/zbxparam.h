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

#ifndef ZABBIX_PARAM_H
#define ZABBIX_PARAM_H

#include "zbxcommon.h"

int	zbx_get_param(const char *p, int num, char *buf, size_t max_len, zbx_request_parameter_type_t *type);
int	zbx_num_param(const char *p);
char	*zbx_get_param_dyn(const char *p, int num, zbx_request_parameter_type_t *type);
/******************************************************************************
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters                *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN] an item key, SNMP OID or their parameter             *
 *      key_type  - [IN] ZBX_KEY_TYPE_*                                       *
 *      level     - [IN] for item keys and OIDs the level will be 0;          *
 *                       for their parameters - 1 or higher (for arrays)      *
 *      num       - [IN] parameter number; for item keys and OIDs the level   *
 *                       will be 0; for their parameters - 1 or higher        *
 *      quoted    - [IN] 1 if parameter is quoted; 0 - otherwise              *
 *      cb_data   - [IN] callback function custom data                        *
 *      param     - [OUT] replaced item key string                            *
 *                                                                            *
 * Return value: SUCCEED - if parameter doesn't change or has been changed    *
 *                         successfully                                       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The new string should be quoted if it contains special           *
 *           characters                                                       *
 *                                                                            *
 ******************************************************************************/
typedef int	(*zbx_replace_key_param_f)(const char *data, int key_type, int level, int num, int quoted,
		void *cb_data, char **param);
#define ZBX_KEY_TYPE_ITEM	0
#define ZBX_KEY_TYPE_OID	1
int	zbx_replace_key_params_dyn(char **data, int key_type, zbx_replace_key_param_f cb, void *cb_data, char *error,
		size_t maxerrlen);
int	zbx_get_key_param(char *param, int num, char *buf, size_t max_len);
int	zbx_num_key_param(char *param);

void	zbx_unquote_key_param(char *param);
int	zbx_quote_key_param(char **param, int forced);
#endif /* ZABBIX_PARAM_H */
