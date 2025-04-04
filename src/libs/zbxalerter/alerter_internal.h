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

#ifndef ZABBIX_ALERTER_INTERNAL_H
#define ZABBIX_ALERTER_INTERNAL_H

#include "zbxtypes.h"

/* For token status, bit mask */
#define ZBX_OAUTH_TOKEN_INVALID		0
#define ZBX_OAUTH_TOKEN_ACCESS_VALID	1
#define ZBX_OAUTH_TOKEN_REFRESH_VALID	2

#define ZBX_OAUTH_TOKEN_VALID		(ZBX_OAUTH_TOKEN_ACCESS_VALID | ZBX_OAUTH_TOKEN_REFRESH_VALID)

typedef struct
{
	char	*token_url;
	char	*client_id;
	char	*client_secret;

	char	*old_refresh_token;
	char	*refresh_token;

	unsigned char	old_tokens_status;
	unsigned char	tokens_status;

	char	*old_access_token;
	char	*access_token;

	time_t	old_access_token_updated;
	time_t	access_token_updated;

	int	old_access_expires_in;
	int	access_expires_in;
} zbx_oauth_data_t;

void	zbx_oauth_clean(zbx_oauth_data_t *data);

int	zbx_oauth_fetch_from_db(zbx_uint64_t mediatypeid, const char *mediatype_name, zbx_oauth_data_t *data,
		char **error);
int	zbx_oauth_access_refresh(zbx_oauth_data_t *data, const char *mediatype_name, long timeout,
			const char *config_source_ip, const char *config_ssl_ca_location, char **error);
void	zbx_oauth_update(zbx_uint64_t mediatypeid, zbx_oauth_data_t *data, int fetch_result);
void	zbx_oauth_audit(int audit_context_mode, zbx_uint64_t mediatypeid, const char *mediatype_name,
		const zbx_oauth_data_t *data, int fetch_result);

#endif
