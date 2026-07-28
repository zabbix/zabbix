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

#include "history.h"
#include "zbxhistory.h"
#include "history_option.h"
#include "history_sql.h"
#include "history_elastic.h"
#include "history_clickhouse.h"

#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxprof.h"
#include "zbxvariant.h"
#include "zbxjson.h"

ZBX_VECTOR_IMPL(history_record, zbx_history_record_t)
ZBX_PTR_VECTOR_IMPL(dc_history_ptr, zbx_dc_history_t *)
ZBX_VECTOR_IMPL(item_history, zbx_item_history_t)
ZBX_VECTOR_IMPL(history_provider_value_type_info, zbx_history_provider_value_type_info_t)

void	zbx_dc_history_shallow_free(zbx_dc_history_t *dc_history)
{
	zbx_free(dc_history);
}

ZBX_PTR_VECTOR_DECL(history_provider_ptr, zbx_history_provider_t *)
ZBX_PTR_VECTOR_IMPL(history_provider_ptr, zbx_history_provider_t *)

ZBX_VECTOR_DECL(history_provider_info, zbx_history_provider_info_t)
ZBX_VECTOR_IMPL(history_provider_info, zbx_history_provider_info_t)


/* history provider registry, initialized during startup */
typedef struct
{
	char				*name;
	zbx_vector_history_option_t	options;
	zbx_uint64_t			traits;
}
zbx_history_registry_t;

ZBX_PTR_VECTOR_DECL(history_registry_ptr, zbx_history_registry_t *)
ZBX_PTR_VECTOR_IMPL(history_registry_ptr, zbx_history_registry_t *)

typedef struct zbx_history_manager zbx_history_manager_t;

/* history session, used to access history while reusing history providers */
typedef struct
{
	zbx_history_manager_t	*manager;
	zbx_history_provider_t	*providers[ITEM_VALUE_TYPE_COUNT];
}
zbx_history_session_t;

struct zbx_history_manager
{
	/* configured history provider registry */
	zbx_vector_history_registry_ptr_t	registry;

	/* value type -> registry mapping for all value types */
	int					type_index[ITEM_VALUE_TYPE_COUNT];

	/* Opened providers by type index, 1 provider per index in standalone processes */
	/* and <threads num> providers per index in multi-threaded processes.           */
	/* One provider per index is opened during initialization, more can be opened   */
	/* as necessary later.                                                          */
	zbx_vector_history_provider_ptr_t	*providers;

	zbx_uint64_t				precache_flags;
	zbx_uint64_t				trends_flags;
	zbx_uint64_t				housekeep_flags;
	zbx_uint64_t				default_type_flags;
};

static zbx_history_manager_t	history_manager;

static void	history_session_clear(zbx_history_session_t *session);

/*******************************************************************************
 *                                                                             *
 * Purpose: clear history provider info                                        *
 *                                                                             *
 *******************************************************************************/
static void	history_provider_info_clear(zbx_history_provider_info_t *info)
{
	zbx_vector_history_provider_value_type_info_destroy(&info->value_types);
	zbx_free(info->database);
	zbx_free(info->provider);
	zbx_free(info->friendly_current_version);
	zbx_free(info->friendly_min_version);
	zbx_free(info->friendly_max_version);
	zbx_free(info->friendly_min_supported_version);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: free history provider                                              *
 *                                                                             *
 *******************************************************************************/
static void	history_provider_free(zbx_history_provider_t *provider)
{
	provider->impl.close(provider->data);
	zbx_free(provider->name);
	zbx_free(provider);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: open and initialize history provider by name                       *
 *                                                                             *
 * Parameters: name        - [IN] provider name                                *
 *             options     - [IN] provider options array                       *
 *             options_num - [IN] number of options                            *
 *             error       - [OUT] error message                               *
 *                                                                             *
 * Return value: Opened history provider or NULL on error                      *
 *                                                                             *
 ******************************************************************************/
static zbx_history_provider_t	*history_provider_open(const char *name, zbx_history_option_t *options,
		int options_num, char **error)
{
	zbx_history_provider_t	*provider = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() provider:%s", __func__, name);

	history_log_options(options, options_num);

	if (0 == strcmp(name, HISTORY_PROVIDER_SQL))
		provider = history_sql_open(options, options_num, error);
	else if (0 == strcmp(name, HISTORY_PROVIDER_ELASTICSEARCH))
		provider = history_elastic_open(options, options_num, error);
	else if (0 == strcmp(name, HISTORY_PROVIDER_CLICKHOUSE))
		provider = history_clickhouse_open(options, options_num, error);
	else
		*error = zbx_dsprintf(NULL, "unsupported history storage provider \"%s\"", name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() provider:%p error:%s", __func__, (void *)provider,
			ZBX_NULL2EMPTY_STR(*error));

	return provider;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: register a history provider                                        *
 *                                                                             *
 * Parameters: manager    - [IN/OUT] history manager                           *
 *             name       - [IN] provider name                                 *
 *             options    - [IN] vector of provider options                    *
 *                                                                             *
 * Return value: provider registry index                                       *
 *                                                                             *
 *******************************************************************************/
static int	history_manager_register_provider(zbx_history_manager_t *manager, const char *name,
		zbx_vector_history_option_t *options)
{
	zbx_history_registry_t	*registry;

	registry = (zbx_history_registry_t *)zbx_malloc(NULL, sizeof(zbx_history_registry_t));
	registry->name = zbx_strdup(NULL, name);
	registry->traits = 0;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "registered history provider \"%s\":", name);
		history_log_options(options->values, options->values_num);
	}

	zbx_vector_history_option_create(&registry->options);
	zbx_vector_history_option_append_array(&registry->options, options->values, options->values_num);
	zbx_vector_history_option_clear(options);

	zbx_vector_history_registry_ptr_append(&manager->registry, registry);

	return manager->registry.values_num - 1;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: clear the history manager and free allocated resources             *
 *                                                                             *
 ******************************************************************************/
static void	history_manager_clear(zbx_history_manager_t *manager)
{
	for (int i = 0; i < manager->registry.values_num; i++)
	{
		zbx_history_registry_t	*registry = manager->registry.values[i];

		zbx_vector_history_provider_ptr_clear_ext(&manager->providers[i], history_provider_free);
		zbx_vector_history_provider_ptr_destroy(&manager->providers[i]);

		for (int j = 0; j < registry->options.values_num; j++)
		{
			zbx_free(registry->options.values[j].name);
			zbx_free(registry->options.values[j].value);
		}
		zbx_vector_history_option_destroy(&registry->options);

		zbx_free(registry->name);
		zbx_free(registry);
	}
	zbx_vector_history_registry_ptr_destroy(&manager->registry);

	zbx_free(manager->providers);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: retrieve opened or open new history provider                       *
 *                                                                             *
 * Parameters: manager - [IN] history manager                                  *
 *             index   - [IN] index of the provider in the registry            *
 *             error   - [OUT] error message if provider cannot be opened      *
 *                                                                             *
 * Return value: history provider or NULL in the case of an error              *
 *                                                                             *
 * Comments: If no provider is available, a new one is opened. Otherwise, an   *
 *           existing provider is returned from the pool.                      *
 *                                                                             *
 *******************************************************************************/
static zbx_history_provider_t	*history_manager_get_provider(zbx_history_manager_t *manager, int index, char **error)
{
	zbx_history_provider_t			*provider;
	zbx_vector_history_provider_ptr_t	*providers = &manager->providers[index];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() name:%s opened:%d", __func__, manager->registry.values[index]->name,
			providers->values_num);

	if (0 == providers->values_num)
	{
		zbx_history_registry_t	*registry = manager->registry.values[index];

		provider = history_provider_open(registry->name, registry->options.values, registry->options.values_num,
				error);
	}
	else
	{
		provider = providers->values[providers->values_num - 1];
		providers->values_num--;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return provider;
}

static void	history_manager_map_value_types(zbx_history_manager_t *manager, int index, zbx_uint64_t type_mask)
{
	for (int i = 0; i < ITEM_VALUE_TYPE_COUNT; i++)
	{
		if (0 != ((__UINT64_C(1) << i) & type_mask))
			manager->type_index[i] = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update the types option in the provider registry                  *
 *                                                                            *
 * Parameters: manager   - [IN/OUT] history manager                           *
 *             index     - [IN] provider registry index                       *
 *             type_mask - [IN] bitmask of value types to set                 *
 *                                                                            *
 * Comments: Removes existing types option if present and adds a new one with *
 *           the specified type mask.                                         *
 *                                                                            *
 ******************************************************************************/
static void	history_manager_registry_update_types(zbx_history_manager_t *manager, int index, zbx_uint64_t type_mask)
{
	zbx_history_registry_t	*registry = manager->registry.values[index];

	for (int i = 0; i < registry->options.values_num; i++)
	{
		zbx_history_option_t	*option = &registry->options.values[i];

		if (0 == strcmp(option->name, HISTORY_PROVIDER_OPTION_VALUE_TYPES))
		{
			history_options_clear(option, 1);
			zbx_vector_history_option_remove_noorder(&registry->options, i);
			break;
		}
	}

	zbx_vector_history_option_append(&registry->options, history_option_types(type_mask));
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the default history provider index from the registry          *
 *                                                                            *
 * Parameters: manager - [IN] history manager                                 *
 *                                                                            *
 * Return value: index of the default provider or FAIL if not registered      *
 *                                                                            *
 * Comments: Default history provider is SQL.                                 *
 *                                                                            *
 ******************************************************************************/
static int	history_manager_get_default_provider(zbx_history_manager_t *manager)
{
	for (int i = 0; i < manager->registry.values_num; i++)
	{
		if (0 == strcmp(manager->registry.values[i]->name, HISTORY_PROVIDER_SQL))
			return i;
	}

	return FAIL;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: initialize the history manager                                     *
 *                                                                             *
 * Parameters: manager                    - [OUT] history manager              *
 *             config_history_storage_url - [IN] history storage URL           *
 *             config_history_storage_opts- [IN] history storage options       *
 *             config_history_storage_pipelines - [IN]                         *
 *             providers                  - [IN] array of history provider     *
 *                                               configuration strings         *
 *             config_log_slow_queries    - [IN] slow query logging flag       *
 *             config_source_ip           - [IN] source IP address             *
 *             config_ssl_ca_location     - [IN] SSL CA certificate location   *
 *             config_ssl_cert_location   - [IN] SSL certificate location      *
 *             config_ssl_key_location    - [IN] SSL key location              *
 *             error                      - [OUT] error message                *
 *                                                                             *
 * Return value: SUCCEED - manager initialized successfully                    *
 *               FAIL    - an error occurred                                   *
 *                                                                             *
 * Comments: This function sets up the history manager based on configuration  *
 *           and available providers.                                          *
 *           All providers will be opened and their handles will be cached per *
 *           type index.                                                       *
 *                                                                             *
 *******************************************************************************/
static int	history_manager_init(zbx_history_manager_t *manager, const char *config_history_storage_url,
		const char *config_history_storage_opts, int config_history_storage_pipelines,
		char **providers, int config_log_slow_queries, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	zbx_vector_history_option_t	options;
	int				ret = FAIL, index;
	zbx_uint64_t			value_type_mask = 0, mask;

	memset(manager, 0, sizeof(zbx_history_manager_t));
	zbx_vector_history_registry_ptr_create(&manager->registry);
	zbx_vector_history_option_create(&options);

	/* register elasticsearch history provider using deprecated configuration parameters */
	if (NULL != config_history_storage_url && NULL != config_history_storage_opts)
	{
		zbx_vector_history_option_append(&options, history_option_str(HISTORY_PROVIDER_OPTION_URL,
				config_history_storage_url));
		zbx_vector_history_option_append(&options, history_option_str(HISTORY_PROVIDER_OPTION_VALUE_TYPES,
				config_history_storage_opts));
		zbx_vector_history_option_append(&options, history_option_int(HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES,
				config_log_slow_queries));

		if (1 == config_history_storage_pipelines)
		{
			zbx_vector_history_option_append(&options,
					history_option_int(HISTORY_PROVIDER_OPTION_DATE_INDEX,
							config_history_storage_pipelines));
		}

		mask = history_options_type_mask(options.values, options.values_num);
		value_type_mask |= mask;

		index = history_manager_register_provider(manager, HISTORY_PROVIDER_ELASTICSEARCH, &options);
		history_manager_map_value_types(manager, index, mask);

		zbx_vector_history_option_clear(&options);
	}

	if (NULL != providers)
	{
		/* register custom history providers configured with HistoryProvider configuration parameters */
		for (char **provider = providers; NULL != *provider; provider++)
		{
			char	*name = NULL, *errmsg = NULL;

			if (SUCCEED != history_provider_parse_options(*provider, &name, &options, error))
				goto out;

			if (SUCCEED != history_options_validate_common_settings(options.values, options.values_num,
					&errmsg))
			{
				*error = zbx_dsprintf(NULL, "invalid history provider \"%s\" configuration: %s",
						name, errmsg);
				zbx_free(name);
				zbx_free(errmsg);

				goto out;
			}

			mask = history_options_type_mask(options.values, options.values_num);
			if (0 != (value_type_mask & mask))
			{
				zbx_free(name);
				*error = zbx_dsprintf(NULL, "duplicate type configured for history provider '%s'",
						*provider);
				goto out;
			}
			value_type_mask |= mask;

			if (0 == strcmp(name, HISTORY_PROVIDER_SQL))
				manager->default_type_flags |= mask;

			if (FAIL == history_options_add_common_params(&options, config_source_ip,
					config_log_slow_queries, config_ssl_ca_location, config_ssl_cert_location,
					config_ssl_key_location, error))
			{
				zbx_free(name);
				goto out;
			}

			index = history_manager_register_provider(manager, name, &options);
			history_manager_map_value_types(manager, index, mask);

			zbx_free(name);
			zbx_vector_history_option_clear(&options);
		}
	}

	mask = value_type_mask ^ ((__UINT64_C(1) << ITEM_VALUE_TYPE_COUNT) - 1);

	/* register default SQL provider for unhandled value types */
	if (0 != mask)
	{
		if (FAIL == (index = history_manager_get_default_provider(manager)))
			index = history_manager_register_provider(manager, HISTORY_PROVIDER_SQL, &options);

		history_manager_registry_update_types(manager, index, mask);
		history_manager_map_value_types(manager, index, mask);

		manager->default_type_flags |= mask;
	}

	manager->providers = (zbx_vector_history_provider_ptr_t *)zbx_malloc(NULL,
			(size_t)manager->registry.values_num * sizeof(zbx_vector_history_provider_ptr_t));

	for (int i = 0; i < manager->registry.values_num; i++)
		zbx_vector_history_provider_ptr_create(&manager->providers[i]);

	/* open all providers */
	for (int i = 0; i < manager->registry.values_num; i++)
	{
		zbx_history_provider_t	*provider;
		zbx_history_registry_t	*registry = manager->registry.values[i];

		if (NULL == (provider = history_provider_open(registry->name, registry->options.values,
				registry->options.values_num, error)))
		{
			history_manager_clear(manager);
			goto out;
		}

		registry->traits = provider->traits;
		zbx_vector_history_provider_ptr_append(&manager->providers[i], provider);
	}

	/* check for supported value types */
	for (unsigned char value_type = 0; value_type < ITEM_VALUE_TYPE_COUNT; value_type++)
	{
		index = manager->type_index[value_type];

		if (index >= manager->registry.values_num || 0 == manager->providers[index].values_num)
		{
			*error = zbx_dsprintf(NULL, "uninitialized history provider for value type %s",
					history_value_type_desc(value_type));
			goto out;
		}

		zbx_uint64_t	traits = manager->providers[index].values[0]->traits;
		zbx_uint64_t	type_mask = UINT64_C(1) << value_type;

		if (0 == (traits & type_mask))
		{
			*error = zbx_dsprintf(NULL, "history provider \"%s\" does not support value type %s",
					manager->registry.values[index]->name, history_value_type_desc(value_type));
			goto out;
		}

		if (0 != (traits & ZBX_HISTORY_TRAIT_REQUIRES_PRECACHING))
			manager->precache_flags |= type_mask;

		if (0 != (traits & ZBX_HISTORY_TRAIT_REQUIRES_TRENDS))
			manager->trends_flags |= type_mask;

		if (0 != (traits & ZBX_HISTORY_TRAIT_REQUIRES_HOUSEKEEPING))
			manager->housekeep_flags |= type_mask;

	}

	ret = SUCCEED;
out:
	history_options_clear(options.values, options.values_num);
	zbx_vector_history_option_destroy(&options);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about all registered custom history          *
 *          providers                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     manager  - [IN] history manager                                        *
 *     info     - [OUT] array of history provider information structures      *
 *     info_num - [OUT] number of elements in the info array                  *
 *     error    - [OUT] error message in case of failure                      *
 *                                                                            *
 * Return value: SUCCEED - information retrieved successfully                 *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 * Comments: The default SQL provider information is not returned since it    *
 *           has been already handled during main DB version checks.          *
 *                                                                            *
 *           Note that this function is called before forking processes, so   *
 *           any handles that are not safe to be forked must be freed before  *
 *           returning.                                                       *
 *                                                                            *
 ******************************************************************************/
static int	history_manager_get_info(zbx_history_manager_t *manager, zbx_history_provider_info_t **info,
		int *info_num, char **error)
{
	int					ret = FAIL;
	zbx_vector_history_provider_info_t	custom;

	zbx_vector_history_provider_info_create(&custom);

	for (int i = 0; i < manager->registry.values_num; i++)
	{
		char				*errmsg = NULL;
		zbx_history_provider_t		*provider;
		zbx_history_provider_info_t	mi = {0};

		if (0 != (manager->registry.values[i]->traits & ZBX_HISTORY_TRAIT_DEFAULT_PROVIDER))
			continue;

		if (0 == manager->providers[i].values_num)
		{
			*error = zbx_dsprintf(NULL, "history provider \"%s\" is already in use",
					manager->registry.values[i]->name);
			goto out;
		}

		provider = manager->providers[i].values[0];

		zabbix_log(LOG_LEVEL_INFORMATION, "retrieving history provider \"%s\" information",
				manager->registry.values[i]->name);

		if (FAIL == (ret = provider->impl.get_info(provider->data, &mi, &errmsg)))
		{
			*error = zbx_dsprintf(NULL, "cannot retrieve history provider \"%s\" information: %s",
					manager->registry.values[i]->name, errmsg);
			zbx_free(errmsg);

			goto out;
		}

		zabbix_log(LOG_LEVEL_INFORMATION, "history provider \"%s\" version \"%s\"",
				manager->registry.values[i]->name, mi.friendly_current_version);

		zbx_vector_history_provider_info_append(&custom, mi);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		for (int i = 0; i < custom.values_num; i++)
			history_provider_info_clear(&custom.values[i]);

		zbx_vector_history_provider_info_destroy(&custom);

	}
	else
	{
		*info_num = custom.values_num;
		*info = custom.values;
	}

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: initializes history storage                                             *
 *                                                                                  *
 * Comments: History interfaces are created for all values types based on           *
 *           configuration. Every value type can have different history storage     *
 *           provider. (Binary value type is not supported for Elasticsearch and    *
 *           ClickHouse)                                                            *
 ************************************************************************************/
int	zbx_history_init(const char *config_history_storage_url, const char *config_history_storage_opts,
		int config_history_storage_pipelines, char **providers, int config_log_slow_queries,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	return history_manager_init(&history_manager, config_history_storage_url, config_history_storage_opts,
			config_history_storage_pipelines, providers, config_log_slow_queries, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, error);
}

/************************************************************************************
 *                                                                                  *
 * Purpose: destroy all created history backends                                    *
 *                                                                                  *
 ************************************************************************************/
void	zbx_history_destroy(void)
{
	history_manager_clear(&history_manager);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: initialize a history session                                       *
 *                                                                             *
 *******************************************************************************/
static void	history_manager_init_session(zbx_history_manager_t *manager, zbx_history_session_t *session)
{
	session->manager = manager;
	memset(session->providers, 0, sizeof(session->providers));
}

/*******************************************************************************
 *                                                                             *
 * Purpose: clear a history session and return used history providers to the   *
 *          manager                                                            *
 *                                                                             *
 *******************************************************************************/
static void	history_session_clear(zbx_history_session_t *session)
{
	if (NULL == session->manager)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("clearing uninitialized history session");
		return;
	}

	/* return providers to the manager */
	for (int i = 0; i < session->manager->registry.values_num; i++)
	{
		if (NULL != session->providers[i])
		{
			zbx_vector_history_provider_ptr_append(&session->manager->providers[i], session->providers[i]);
			session->providers[i] = NULL;
		}
	}

	session->manager = NULL;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: retrieve a history provider for a specific value type from history *
 *          session                                                            *
 *                                                                             *
 * Parameters: session    - [IN] history session                               *
 *             value_type - [IN] item value type                               *
 *                                                                             *
 * Return value: history provider or NULL if not available                     *
 *                                                                             *
 * Comments: If the provider for the specified value type has not been used by *
 *           session, it is retrieved from the manager and cached in the       *
 *           session for future use.                                           *
 *                                                                             *
 *******************************************************************************/
static zbx_history_provider_t	*history_session_get_provider(zbx_history_session_t *session, unsigned char value_type)
{
	int			index;
	zbx_history_provider_t	*provider = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	index = session->manager->type_index[value_type];

	if (NULL == session->providers[index])
	{
		char	*error = NULL;

		if (NULL == (session->providers[index] = history_manager_get_provider(session->manager, index, &error)))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("failed to open backend storage provider %s: %s",
					session->manager->registry.values[index]->name, error);
			zbx_free(error);

			goto out;
		}
	}

	provider = session->providers[index];
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return provider;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: write history entries using corresponding history provider         *
 *                                                                             *
 * Parameters: session    - [IN] history session                               *
 *             value_type - [IN] item value type                               *
 *             entries    - [IN] array of history entries to write             *
 *             entries_num- [IN] number of entries in the array                *
 *                                                                             *
 *******************************************************************************/
static void	history_session_write(zbx_history_session_t *session, unsigned char value_type,
		const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_history_provider_t	*provider;

	if (NULL == session->manager)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("writing value_type %d to uninitialized history session", value_type);
		return;
	}

	if (NULL == (provider = history_session_get_provider(session, value_type)))
		return;

	/* history_sql_write() or history_clickhouse_write() or history_elastic_write() */
	provider->impl.write(provider->data, value_type, entries, entries_num);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: extract error status for a specific value type from combined flush *
 *          error                                                              *
 *                                                                             *
 * Parameters: error_mask - [IN] bitmask containing flush error statuses       *
 *             value_type - [IN] item value type                               *
 *                                                                             *
 * Return value: FLUSH_SUCCEED     - flush succeeded                           *
 *               FLUSH_FAIL        - flush failed                              *
 *               FLUSH_DUPL_REJECTED - duplicate values were rejected          *
 *               FLUSH_UNKNOWN     - unknown error occurred                    *
 *                                                                             *
 *******************************************************************************/
int	zbx_history_get_flush_error(zbx_uint64_t error_mask, unsigned char value_type)
{
	static int	flush_errors[] = {ZBX_HISTORY_FLUSH_SUCCEED, ZBX_HISTORY_FLUSH_FAIL,
			ZBX_HISTORY_FLUSH_DUPL_REJECTED};
	zbx_uint64_t	err;

	error_mask >>= (value_type * ZBX_HISTORY_FLUSH_ERR_BITS);
	err = error_mask & ((1 << ZBX_HISTORY_FLUSH_ERR_BITS) - 1);

	if (ZBX_HISTORY_FLUSH_RET_FAIL_UNKNOWN <= err)
		return ZBX_HISTORY_FLUSH_UNKNOWN;

	return flush_errors[err];
}

/*******************************************************************************
 *                                                                             *
 * Purpose: create a flush error mask for a specific value type                *
 *                                                                             *
 * Parameters: ret        - [IN] flush result (FLUSH_SUCCEED, FLUSH_FAIL,      *
 *                              FLUSH_DUPL_REJECTED)                           *
 *             value_type - [IN] item value type                               *
 *                                                                             *
 * Return value: 64-bit integer with error bits set for the specified          *
 *               value type                                                    *
 *                                                                             *
 *******************************************************************************/
zbx_uint64_t	history_make_flush_error(int ret, unsigned char value_type)
{
	zbx_uint64_t	err;

	switch (ret)
	{
		case ZBX_HISTORY_FLUSH_SUCCEED:
			err = ZBX_HISTORY_FLUSH_RET_SUCCEED;
			break;
		case ZBX_HISTORY_FLUSH_FAIL:
			err = ZBX_HISTORY_FLUSH_RET_FAIL;
			break;
		case ZBX_HISTORY_FLUSH_DUPL_REJECTED:
			err = ZBX_HISTORY_FLUSH_RET_FAIL_DUPL;
			break;
		default:
			err = ZBX_HISTORY_FLUSH_RET_FAIL_UNKNOWN;
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected flush result %d", ret);
			break;
	}

	return (err << (value_type * ZBX_HISTORY_FLUSH_ERR_BITS));
}

/*******************************************************************************
 *                                                                             *
 * Purpose: flush all history providers used in the session                    *
 *                                                                             *
 * Parameters: session - [IN] history session                                  *
 *                                                                             *
 * Return value: error_mask - bitmask containing flush error statuses for each *
 *                            value type                                       *
 *                                                                             *
 * Comments: This function flushes all history providers that have been used   *
 *           in the session. It combines the flush results into a single       *
 *           error_mask, where each value type's status is represented by      *
 *           ZBX_HISTORY_FLUSH_ERR_BITS bits.                                  *
 *                                                                             *
 *******************************************************************************/
static zbx_uint64_t	history_session_flush(zbx_history_session_t *session)
{
	zbx_uint64_t	error_mask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == session->manager)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("flushing uninitialized history session");
		return HISTORY_FLUSH_RET_FAIL_ALL;
	}

	for (int i = 0; i < session->manager->registry.values_num; i++)
	{
		if (NULL != session->providers[i])
			error_mask |= session->providers[i]->impl.flush(session->providers[i]->data);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): ret:%lx", __func__, error_mask);

	return error_mask;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: fetch history data for a specific item                             *
 *                                                                             *
 * Parameters: session    - [IN] history session                               *
 *             itemid     - [IN]                                               *
 *             value_type - [IN] item value type                               *
 *             start      - [IN] start timestamp of the requested period,      *
 *                               0 - ignored                                   *
 *             end        - [IN] end timestamp of the requested period         *
 *             count      - [IN] maximum number of values to retrieve,         *
 *                               0 - ignored                                   *
 *             values     - [OUT] array of retrieved history records           *
 *                                                                             *
 * Return value: SUCCEED - data fetched successfully                           *
 *               FAIL    - an error occurred during data retrieval             *
 *                                                                             *
 *******************************************************************************/
static int	history_session_fetch(zbx_history_session_t *session, zbx_uint64_t itemid, unsigned char value_type,
		time_t start, time_t end, int count, zbx_history_record_t **values)
{
	zbx_history_provider_t	*provider;
	char			*error = NULL;
	int			ret;

	if (NULL == session->manager)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("fetching data from uninitialized history session");
		return FAIL;
	}

	if (NULL == (provider = history_session_get_provider(session, value_type)))
		return FAIL;

	if (FAIL == (ret = provider->impl.fetch(provider->data, itemid, value_type, start, end, count, values, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Failed to fetch data from %s for item \"" ZBX_FS_UI64 "\": %s",
				provider->name, itemid, error);
		zbx_free(error);
	}

	return ret;
}

static void	history_session_fetch_batch(zbx_history_session_t *session, zbx_vector_item_history_t *results,
		unsigned char value_type, time_t start, int limit)
{
	zbx_history_provider_t	*provider;
	char			*error = NULL;

	if (NULL == session->manager)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("fetching data from uninitialized history session");
		return;
	}

	if (NULL == (provider = history_session_get_provider(session, value_type)))
		return;

	if (FAIL == provider->impl.fetch_batch(provider->data, results, value_type, start, limit, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Failed to fetch batch data from %s: %s",
				provider->name, error);
		zbx_free(error);
	}
}

/*******************************************************************************
 *                                                                             *
 * Purpose: add history values to the history storage                          *
 *                                                                             *
 * Parameters: history   - [IN] vector of history data to add                  *
 *             flush_err - [OUT] bitmask containing flush error statuses per   *
 *                               value types                                   *
 *                                                                             *
 * Return value: SUCCEED - values added successfully                           *
 *               FAIL    - an error occurred while adding values               *
 *                                                                             *
 * Comments: This function writes history data to appropriate providers based  *
 *           on value types, then flushes all used providers. The flush errors *
 *           are combined into a single bitmask returned via flush_err         *
 *           parameter.                                                        *
 *                                                                             *
 *******************************************************************************/
int	zbx_history_add_values(const zbx_vector_dc_history_ptr_t *history, zbx_uint64_t *flush_err)
{
	const zbx_history_entry_t	**entries;
	zbx_history_session_t		session;

	zbx_prof_start(__func__, ZBX_PROF_PROCESSING);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	history_manager_init_session(&history_manager, &session);

	entries = (const zbx_history_entry_t **)zbx_malloc(NULL,
			sizeof(zbx_history_entry_t *) * (size_t)history->values_num);

	for (unsigned char value_type = 0; value_type <= ITEM_VALUE_TYPE_JSON; value_type++)
	{
		int	entries_num = 0;

		for (int i = 0; i < history->values_num; i++)
		{
			if (history->values[i]->entry.value_type == value_type)
				entries[entries_num++] = &history->values[i]->entry;
		}

		if (0 != entries_num)
			history_session_write(&session, value_type, entries, entries_num);
	}

	*flush_err = history_session_flush(&session);

	history_session_clear(&session);
	zbx_free(entries);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): err:%lx", __func__, *flush_err);

	zbx_prof_end();

	return (0 == *flush_err ? SUCCEED : FAIL);
}

/************************************************************************************
 *                                                                                  *
 * Purpose: gets item values from history storage                                   *
 *                                                                                  *
 * Parameters:  itemid     - [IN] the itemid                                        *
 *              value_type - [IN] the item value type                               *
 *              start      - [IN] the period start timestamp                        *
 *              count      - [IN] the number of values to read                      *
 *              end        - [IN] the period end timestamp                          *
 *              values     - [OUT] the item history data values                     *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	int			ret;
	zbx_history_session_t	session;
	zbx_history_record_t	*records = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d start:%d count:%d end:%d",
			__func__, itemid, value_type, start, count, end);

	history_manager_init_session(&history_manager, &session);

	ret = history_session_fetch(&session, itemid, (unsigned char)value_type, start, end, count, &records);

	if (SUCCEED <= ret)
	{
		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		{
			int	i;
			char	buffer[MAX_STRING_LEN];


			for (i = 0; i < ret; i++)
			{
				zbx_history_record_t	*h = &records[i];

				zbx_history_value2str(buffer, sizeof(buffer), &h->value, value_type);
				zabbix_log(LOG_LEVEL_TRACE, "  %d.%09d %s", h->timestamp.sec, h->timestamp.ns, buffer);
			}
		}

		if (0 != ret)
			zbx_vector_history_record_append_array(values, records, ret);

		zbx_free(records);

		ret = SUCCEED;
	}

	history_session_clear(&session);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s values:%d", __func__, zbx_result_string(ret), ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve history values for multiple items in a single batch      *
 *                                                                            *
 * Parameters: results    - [IN/OUT] vector of item history structures        *
 *             value_type - [IN] item value type                              *
 *             start      - [IN] start timestamp of the requested period      *
 *             limit      - [IN] maximum number of values to retrieve per     *
 *                               item                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_get_batch(zbx_vector_item_history_t *results, int value_type, int start, int limit)
{
	zbx_history_session_t	session;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() items:%d value_type:%d start:%d limit:%d",
			__func__, results->values_num, value_type, start, limit);

	history_manager_init_session(&history_manager, &session);

	history_session_fetch_batch(&session, results, (unsigned char)value_type, start, limit);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		char	buffer[MAX_STRING_LEN];

		for (int i = 0; i < results->values_num; i++)
		{
			zbx_item_history_t	*hist = &results->values[i];

			zabbix_log(LOG_LEVEL_TRACE, "  itemid:" ZBX_FS_UI64, hist->itemid);

			for (int j = 0; j < hist->rows.values_num; j++)
			{
				zbx_history_record_t	*h = &hist->rows.values[j];

				zbx_history_value2str(buffer, sizeof(buffer), &h->value, value_type);
				zabbix_log(LOG_LEVEL_TRACE, "    %d.%09d %s", h->timestamp.sec, h->timestamp.ns,
						buffer);
			}
		}
	}

	history_session_clear(&session);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__ );
}

/******************************************************************************
 *                                                                            *
 * Purpose: get precaching flags for all value types                          *
 *                                                                            *
 *                                                                            *
 * Return value: precaching flags, with 1 << value_type bits set for value    *
 *               types supporting precaching                                  *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_history_get_precache_flags(void)
{
	return history_manager.precache_flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get trends flags for all value types                              *
 *                                                                            *
 *                                                                            *
 * Return value: trends flags, with 1 << value_type bits set for value        *
 *               types requiring trends                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_history_get_trends_flags(void)
{
	return history_manager.trends_flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get housekeeping flags for all value types                        *
 *                                                                            *
 *                                                                            *
 * Return value: trends flags, with 1 << value_type bits set for value        *
 *               types requiring trends                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_history_get_housekeep_flags(void)
{
	return history_manager.housekeep_flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees history log and all resources allocated for it              *
 *                                                                            *
 * Parameters: log   - [IN] the history log to free                           *
 *                                                                            *
 ******************************************************************************/
static void	history_logfree(zbx_log_value_t *log)
{
	if (NULL == log)
		return;

	zbx_free(log->source);
	zbx_free(log->value);
	zbx_free(log);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys value vector and frees resources allocated for it        *
 *                                                                            *
 * Parameters: vector    - [IN] the value vector                              *
 *                                                                            *
 * Comments: Use this function to destroy value vectors created by            *
 *           zbx_vc_get_values_by_* functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_destroy(zbx_vector_history_record_t *vector, int value_type)
{
	if (NULL != vector->values)
	{
		zbx_history_record_vector_clean(vector, value_type);
		zbx_vector_history_record_destroy(vector);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by a cached value                       *
 *                                                                            *
 * Parameters: value      - [IN] the cached value to clear                    *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_clear(zbx_history_record_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_JSON:
			zbx_free(value->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			history_logfree(value->value.log);
			break;
		case ITEM_VALUE_TYPE_UINT64:
		case ITEM_VALUE_TYPE_FLOAT:
			break;
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to string format                           *
 *                                                                            *
 * Parameters: buffer     - [OUT] output buffer                               *
 *             size       - [IN] output buffer size                           *
 *             value      - [IN] value to convert                             *
 *             value_type - [IN] history value type                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value2str(char *buffer, size_t size, const zbx_history_value_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, size, ZBX_FS_DBL64, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, size, ZBX_FS_UI64, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_strlcpy_utf8(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_BIN:
			zbx_strlcpy(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_JSON:
			zbx_strlcpy_utf8(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_strlcpy_utf8(buffer, value->log->value, size);
			break;
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to string format (double type printed in   *
 *          human friendly format)                                            *
 *                                                                            *
 * Parameters: buffer     - [OUT] the output buffer                           *
 *             size       - [IN] the output buffer size                       *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value_print(char *buffer, size_t size, const zbx_history_value_t *value, int value_type)
{
	if (ITEM_VALUE_TYPE_FLOAT == value_type)
		zbx_print_double(buffer, size, value->dbl);
	else
		zbx_history_value2str(buffer, size, value, value_type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated to store history records             *
 *                                                                            *
 * Parameters: vector      - [IN] the history record vector                   *
 *             value_type  - [IN] the type of vector values                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_clean(zbx_vector_history_record_t *vector, int value_type)
{
	int	i;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_JSON:
			for (i = 0; i < vector->values_num; i++)
				zbx_free(vector->values[i].value.str);

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < vector->values_num; i++)
				history_logfree(vector->values[i].value.log);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			break;
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_exit(EXIT_FAILURE);
	}

	zbx_vector_history_record_clear(vector);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares two cache values by their timestamps                     *
 *                                                                            *
 * Parameters: a1   - [IN] first value                                        *
 *             a2   - [IN] second value                                       *
 *                                                                            *
 * Return value:   <0 - the first value timestamp is less than second         *
 *                 =0 - the first value timestamp is equal to the second      *
 *                 >0 - the first value timestamp is greater than second      *
 *                                                                            *
 * Comments: This function is commonly used to sort value vector in ascending *
 *           order.                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_record_compare_asc(const void *a1, const void *a2)
{
	const	zbx_history_record_t	*d1 = (const zbx_history_record_t *)a1;
	const	zbx_history_record_t	*d2 = (const zbx_history_record_t *)a2;

	if (d1->timestamp.sec == d2->timestamp.sec)
		return d1->timestamp.ns - d2->timestamp.ns;

	return d1->timestamp.sec - d2->timestamp.sec;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: compares two cache values by their timestamps                      *
 *                                                                             *
 * Parameters: a1   - [IN] first value                                         *
 *             a2   - [IN] second value                                        *
 *                                                                             *
 * Return value:   >0 - first value timestamp is less than second              *
 *                 =0 - first value timestamp is equal to the second           *
 *                 <0 - first value timestamp is greater than second           *
 *                                                                             *
 * Comments: This function is commonly used to sort value vector in descending *
 *           order.                                                            *
 *                                                                             *
 *******************************************************************************/
int	zbx_history_record_compare_desc(const void *a1, const void *a2)
{
	const	zbx_history_record_t	*d1 = (const zbx_history_record_t *)a1;
	const	zbx_history_record_t	*d2 = (const zbx_history_record_t *)a2;

	if (d1->timestamp.sec == d2->timestamp.sec)
		return d2->timestamp.ns - d1->timestamp.ns;

	return d2->timestamp.sec - d1->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to variant value                           *
 *                                                                            *
 * Parameters: value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *             var        - [IN] the output value                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value2variant(const zbx_history_value_t *value, unsigned char value_type, zbx_variant_t *var)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_variant_set_dbl(var, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_variant_set_ui64(var, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_variant_set_str(var, zbx_strdup(NULL, value->str));
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_variant_set_str(var, zbx_strdup(NULL, value->log->value));
			break;
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_JSON:
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history module version information to JSON                    *
 *                                                                            *
 * Parameters: json - [IN/OUT] JSON object to add version info to             *
 *             info - [IN] history module information                         *
 *             flag - [IN] database version validation status                 *
 *                                                                            *
 ******************************************************************************/
static void	history_add_version_info(struct zbx_json *json, zbx_history_provider_info_t *info,
		zbx_db_version_status_t flag)
{
	zbx_json_addobject(json, NULL);

	zbx_json_addstring(json, "database", info->database, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "provider", info->provider, ZBX_JSON_TYPE_STRING);

	if (NULL != info->friendly_current_version)
		zbx_json_addstring(json, "current_version", info->friendly_current_version, ZBX_JSON_TYPE_STRING);

	if (NULL != info->friendly_min_version)
		zbx_json_addstring(json, "min_version", info->friendly_min_version, ZBX_JSON_TYPE_STRING);

	if (NULL != info->friendly_max_version)
		zbx_json_addstring(json, "max_version", info->friendly_max_version, ZBX_JSON_TYPE_STRING);

	if (NULL != info->friendly_min_supported_version)
	{
		zbx_json_addstring(json, "min_supported_version", info->friendly_min_supported_version,
				ZBX_JSON_TYPE_STRING);
	}

	zbx_json_addint64(json, "flag", flag);

	if (0 != info->value_types.values_num)
	{
		zbx_json_addarray(json, "value_types");

		for (int i = 0; i < info->value_types.values_num; i++)
		{
			zbx_history_provider_value_type_info_t	*type_info = &info->value_types.values[i];

			zbx_json_addobject(json, NULL);

			zbx_json_addstring(json, "type", history_option_value_type_str(type_info->value_type),
					ZBX_JSON_TYPE_STRING);

			if (0 != type_info->ttl)
				zbx_json_adduint64(json, "ttl", (zbx_uint64_t)type_info->ttl);

			zbx_json_close(json);
		}

		zbx_json_close(json);
	}

	zbx_json_close(json);

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if a database entry represents a history storage database  *
 *                                                                            *
 * Parameters: jp       - [IN] JSON object containing database information    *
 *             info     - [IN] history provider information                   *
 *             info_num - [IN] number of history providers                    *
 *                                                                            *
 ******************************************************************************/
static int	history_dbversion_is_history(zbx_json_parse_t *jp, zbx_history_provider_info_t *info, int info_num)
{
	char	dbname[MAX_STRING_LEN];

	if (SUCCEED == zbx_json_value_by_name(jp, "database", dbname, sizeof(dbname), NULL))
	{
		for (int i = 0; i < info_num; i++)
		{
			if (0 == strcmp(dbname, info[i].database))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies database version status entries to JSON, excluding history *
 *          provider databases                                                *
 *                                                                            *
 * Parameters: j        - [IN/OUT] output JSON object for filtered entries    *
 *             info     - [IN] history provider information                   *
 *             info_num - [IN] number of history providers                    *
 *                                                                            *
 ******************************************************************************/
static void	history_copy_dbversion_status(struct zbx_json *j, zbx_history_provider_info_t *info, int info_num)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_json_parse_t	jp, jp_db;
	char			*str = NULL;
	size_t			str_alloc = 0, str_offset;

	result = zbx_db_select("select value_str from settings where name='dbversion_status'");

	if (NULL == (row = zbx_db_fetch(result)))
		goto out;

	if (SUCCEED != zbx_json_open(row[0], &jp))
		goto out;

	for (const char *p = zbx_json_next(&jp, NULL); NULL != p; p = zbx_json_next(&jp, p))
	{
		if (SUCCEED != zbx_json_brackets_open(p, &jp_db))
			continue;

		if (SUCCEED == history_dbversion_is_history(&jp_db, info, info_num))
			continue;

		str_offset = 0;
		zbx_strncpy_alloc(&str, &str_alloc, &str_offset, jp_db.start, jp_db.end - jp_db.start + 1);
		zbx_json_addraw(j, NULL, str);
	}
out:
	zbx_db_free_result(result);
	zbx_free(str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: relays the version retrieval logic to the history implementation  *
 *          functions                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_check_version(int config_allow_unsupported_db_versions, unsigned char program_type)
{
	zbx_history_provider_info_t	*info = NULL;
	int				info_num, ret = SUCCEED;
	char				*error = NULL;
	struct zbx_json			json;

	if (FAIL == history_manager_get_info(&history_manager, &info, &info_num, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);
		zbx_free(error);
		return FAIL;
	}

	zbx_json_initarray(&json, 1024);

	history_copy_dbversion_status(&json, info, info_num);

	for (int i = 0; i < info_num; i++)
	{
		struct zbx_db_version_info_t	vi = {
				.database = info[i].database,
				.friendly_current_version = info[i].friendly_current_version,
				.friendly_max_version = info[i].friendly_max_version,
				.friendly_min_version = info[i].friendly_min_version,
				.friendly_min_supported_version = info[i].friendly_min_supported_version
		};

		vi.flag = zbx_db_version_check(info[i].database, info[i].current_version, info[i].min_version,
				info[i].max_version, info[i].min_supported_version);

		if (FAIL == zbx_db_verify_version_info(&vi, config_allow_unsupported_db_versions, program_type))
			ret = FAIL;
		else
			history_add_version_info(&json, info + i, vi.flag);

		history_provider_info_clear(info + i);
	}

	zbx_free(info);

	(void)zbx_db_settings_set_value("dbversion_status", json.buffer, ZBX_SETTING_TYPE_STR);

	zbx_json_free(&json);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: duplicates history log by allocating necessary resources and      *
 *          copying the target log values.                                    *
 *                                                                            *
 * Parameters: log   - [IN] history log to duplicate                          *
 *                                                                            *
 * Return value: the duplicated history log                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_log_value_t	*log_value_dup(const zbx_log_value_t *log)
{
	zbx_log_value_t	*plog;

	plog = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));

	plog->timestamp = log->timestamp;
	plog->logeventid = log->logeventid;
	plog->severity = log->severity;
	plog->source = (NULL == log->source ? NULL : zbx_strdup(NULL, log->source));
	plog->value = zbx_strdup(NULL, log->value);

	return plog;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies history value                                              *
 *                                                                            *
 * Parameters: dst        - [OUT] pointer to the destination value            *
 *             src        - [IN] pointer to the source value                  *
 *             value_type - [IN] value type (see ITEM_VALUE_TYPE_* defs)      *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_copy(zbx_history_record_t *dst, const zbx_history_record_t *src, int value_type)
{
	dst->timestamp = src->timestamp;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			dst->value.str = zbx_strdup(NULL, src->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			dst->value.log = log_value_dup(src->value.log);
			break;
		default:
			dst->value = src->value;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get description of history value type                             *
 *                                                                            *
 * Parameters: value_type - [IN] history value type                           *
 *                                                                            *
 * Return value: description of the history value type                        *
 *                                                                            *
 ******************************************************************************/
const char	*history_value_type_desc(unsigned char value_type)
{
	static char	*value_types_desc[] = {"Numeric (float)", "Character", "Log", "Numeric (unsigned)", "Text",
			"Binary", "JSON"};

	if (value_type >= ARRSIZE(value_types_desc))
		return "Unknown";

	return value_types_desc[value_type];
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert history value type string to its numeric representation   *
 *                                                                            *
 * Parameters: value_type_str - [IN] history value type string                *
 *                                                                            *
 * Return value: value type or FAIL if unknown                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_value_type_from_str(const char *value_type_str)
{
	return history_option_value_type_from_str(value_type_str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two item history structures by item identifier            *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_history_compare_by_itemid(const void *d1, const void *d2)
{
	const zbx_item_history_t	*h1 = (const zbx_item_history_t *)d1;
	const zbx_item_history_t	*h2 = (const zbx_item_history_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->itemid, h2->itemid);

	return 0;
}


/******************************************************************************
 *                                                                            *
 * Purpose: compare two item history structures by item index                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_history_compare_by_index_desc(const void *d1, const void *d2)
{
	const zbx_item_history_t	*h1 = (const zbx_item_history_t *)d1;
	const zbx_item_history_t	*h2 = (const zbx_item_history_t *)d2;

	return h2->index - h1->index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get bitmask of value types handled by the default history         *
 *          provider                                                          *
 *                                                                            *
 * Return value: bitmask with bits set for value types handled by the default *
 *               provider (SQL), 0 if default provider is not found           *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_history_get_default_type_flags(void)
{
	return history_manager.default_type_flags;
}
