/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_HISTORY_OPTION_H
#define ZABBIX_HISTORY_OPTION_H

#include "history.h"
#include "zbxtypes.h"
#include "zbxalgo.h"

#define HISTORY_PROVIDER_SQL		"sql"	/* default provider */
#define HISTORY_PROVIDER_ELASTICSEARCH	"elasticsearch"
#define HISTORY_PROVIDER_CLICKHOUSE	"clickhouse"

#define HISTORY_PROVIDER_OPTION_NAME			"name"
#define HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES	"log_slow_queries"
#define HISTORY_PROVIDER_OPTION_URL			"url"
#define HISTORY_PROVIDER_OPTION_USERNAME		"username"
#define HISTORY_PROVIDER_OPTION_PASSWORD		"password"
#define HISTORY_PROVIDER_OPTION_DB			"db"
#define HISTORY_PROVIDER_OPTION_VALUE_TYPES		"value_types"
#define HISTORY_PROVIDER_OPTION_DATE_INDEX		"date_index"
#define HISTORY_PROVIDER_OPTION_PRECACHE		"precache"
#define HISTORY_PROVIDER_OPTION_SOURCE_IP		"source_ip"
#define HISTORY_PROVIDER_OPTION_SSL_CERT_FILE		"ssl_cert_file"
#define HISTORY_PROVIDER_OPTION_SSL_KEY_FILE		"ssl_key_file"
#define HISTORY_PROVIDER_OPTION_SSL_KEY_PASSWORD	"ssl_key_password"
#define HISTORY_PROVIDER_OPTION_SSL_VERIFY_PEER		"ssl_verify_peer"
#define HISTORY_PROVIDER_OPTION_SSL_VERIFY_HOST		"ssl_verify_host"
#define HISTORY_PROVIDER_OPTION_SSL_CA_LOCATION		"ssl_ca_location"
#define HISTORY_PROVIDER_OPTION_SSL_CERT_LOCATION	"ssl_cert_location"
#define HISTORY_PROVIDER_OPTION_SSL_KEY_LOCATION	"ssl_key_location"

ZBX_VECTOR_DECL(history_option, zbx_history_option_t)

const char	*history_option_value_type_str(unsigned char value_type);
int		history_option_value_type_from_str(const char *value_type_str);

zbx_history_option_t	history_option_str(const char *name, const char *value);
zbx_history_option_t	history_option_int(const char *name, int value);
const char	*history_option_value(const zbx_history_option_t *options, int options_num, const char *name);

int	history_provider_parse_options(const char *conf, char **name, zbx_vector_history_option_t *options,
		char **error);
void	history_options_clear(zbx_history_option_t *options, int options_num);
zbx_uint64_t	history_options_type_mask(const zbx_history_option_t *options, int options_num);
zbx_uint64_t	history_options_precache(const zbx_history_option_t *options, int options_num);
zbx_history_option_t	history_option_types(zbx_uint64_t mask);

int	history_options_validate_common_settings(const zbx_history_option_t *options, int options_num, char **error);

int	history_options_add_common_params(zbx_vector_history_option_t *options, const char *config_source_ip,
		int config_log_slow_queries, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);

void	history_log_options(zbx_history_option_t *options, int options_num);
#endif

