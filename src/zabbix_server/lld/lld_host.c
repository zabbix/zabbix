/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "lld.h"

#include "log.h"
#include "zbxserver.h"
#include "../../libs/zbxaudit/audit.h"
#include "../../libs/zbxaudit/audit_host.h"

typedef struct
{
	zbx_uint64_t	hostmacroid;
	char		*macro;
	char		*value;
	char		*value_orig;
	char		*description;
	char		*description_orig;
	unsigned char	type;
	unsigned char	type_orig;
#define ZBX_FLAG_LLD_HMACRO_UPDATE_VALUE		__UINT64_C(0x00000001)
#define ZBX_FLAG_LLD_HMACRO_UPDATE_DESCRIPTION		__UINT64_C(0x00000002)
#define ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE			__UINT64_C(0x00000004)
#define ZBX_FLAG_LLD_HMACRO_UPDATE								\
		(ZBX_FLAG_LLD_HMACRO_UPDATE_VALUE | ZBX_FLAG_LLD_HMACRO_UPDATE_DESCRIPTION |	\
		ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE)
#define ZBX_FLAG_LLD_HMACRO_REMOVE			__UINT64_C(0x00000008)
	zbx_uint64_t	flags;
}
zbx_lld_hostmacro_t;

static void	lld_hostmacro_free(zbx_lld_hostmacro_t *hostmacro)
{
	zbx_free(hostmacro->macro);
	zbx_free(hostmacro->value);
	zbx_free(hostmacro->description);
	zbx_free(hostmacro->value_orig);
	zbx_free(hostmacro->description_orig);
	zbx_free(hostmacro);
}

typedef struct
{
	char		*community;
	char		*community_orig;
	char		*securityname;
	char		*securityname_orig;
	char		*authpassphrase;
	char		*authpassphrase_orig;
	char		*privpassphrase;
	char		*privpassphrase_orig;
	char		*contextname;
	char		*contextname_orig;
	unsigned char	securitylevel;
	unsigned char	securitylevel_orig;
	unsigned char	authprotocol;
	unsigned char	authprotocol_orig;
	unsigned char	privprotocol;
	unsigned char	privprotocol_orig;
	unsigned char	version;
	unsigned char	version_orig;
	unsigned char	bulk;
	unsigned char	bulk_orig;
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE		__UINT64_C(0x00000001)	/* interface_snmp.type */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK		__UINT64_C(0x00000002)	/* interface_snmp.bulk */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY	__UINT64_C(0x00000004)	/* interface_snmp.community */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME	__UINT64_C(0x00000008)	/* interface_snmp.securityname */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL	__UINT64_C(0x00000010)	/* interface_snmp.securitylevel */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS	__UINT64_C(0x00000020)	/* interface_snmp.authpassphrase */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS	__UINT64_C(0x00000040)	/* interface_snmp.privpassphrase */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL	__UINT64_C(0x00000080)	/* interface_snmp.authprotocol */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL	__UINT64_C(0x00000100)	/* interface_snmp.privprotocol */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT	__UINT64_C(0x00000200)	/* interface_snmp.contextname */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE									\
		(ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE | ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK |		\
		ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY | ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME |	\
		ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL | ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS |	\
		ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS | ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL |	\
		ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL | ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT)
#define ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE		__UINT64_C(0x00000400)	/* new snmp data record*/
	zbx_uint64_t	flags;
}
zbx_lld_interface_snmp_t;

typedef struct
{
	zbx_uint64_t	interfaceid;
	zbx_uint64_t	parent_interfaceid;
	char		*ip;
	char		*ip_orig;
	char		*dns;
	char		*dns_orig;
	char		*port;
	char		*port_orig;
	unsigned char	main;
	unsigned char	main_orig;
	unsigned char	type;
	unsigned char	type_orig;
	unsigned char	useip;
	unsigned char	useip_orig;
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE	__UINT64_C(0x00000001)	/* interface.type field should be updated  */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN	__UINT64_C(0x00000002)	/* interface.main field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP	__UINT64_C(0x00000004)	/* interface.useip field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_IP	__UINT64_C(0x00000008)	/* interface.ip field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS	__UINT64_C(0x00000010)	/* interface.dns field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT	__UINT64_C(0x00000020)	/* interface.port field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE								\
		(ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE | ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN |	\
		ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP | ZBX_FLAG_LLD_INTERFACE_UPDATE_IP |	\
		ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS | ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT)
#define ZBX_FLAG_LLD_INTERFACE_REMOVE		__UINT64_C(0x00000080)	/* interfaces which should be deleted */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_REMOVE	__UINT64_C(0x00000100)	/* snmp data which should be deleted */
#define ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS	__UINT64_C(0x00000200)	/* there is snmp data */
	zbx_uint64_t	flags;
	union _data
	{
		zbx_lld_interface_snmp_t *snmp;
	}
	data;
}
zbx_lld_interface_t;

static void	lld_interface_free(zbx_lld_interface_t *interface)
{
	zbx_free(interface->port);
	zbx_free(interface->dns);
	zbx_free(interface->ip);
	zbx_free(interface->port_orig);
	zbx_free(interface->dns_orig);
	zbx_free(interface->ip_orig);

	if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS))
	{
		zbx_free(interface->data.snmp->community);
		zbx_free(interface->data.snmp->securityname);
		zbx_free(interface->data.snmp->authpassphrase);
		zbx_free(interface->data.snmp->privpassphrase);
		zbx_free(interface->data.snmp->contextname);

		zbx_free(interface->data.snmp->community_orig);
		zbx_free(interface->data.snmp->securityname_orig);
		zbx_free(interface->data.snmp->authpassphrase_orig);
		zbx_free(interface->data.snmp->privpassphrase_orig);
		zbx_free(interface->data.snmp->contextname_orig);

		zbx_free(interface->data.snmp);
	}

	zbx_free(interface);
}

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	new_groupids;		/* host groups which should be added */
	zbx_vector_uint64_t	lnk_templateids;	/* templates which should be linked */
	zbx_vector_uint64_t	del_templateids;	/* templates which should be unlinked */
	zbx_vector_ptr_t	new_hostmacros;		/* host macros which should be added, deleted or updated */
	zbx_vector_ptr_t	interfaces;
	zbx_vector_db_tag_ptr_t	tags;
	char			*host_proto;
	char			*host;
	char			*host_orig;
	char			*name;
	char			*name_orig;
	int			lastcheck;
	int			ts_delete;
#define ZBX_FLAG_LLD_HOST_DISCOVERED			__UINT64_C(0x00000001)	/* hosts which should be updated or added */
#define ZBX_FLAG_LLD_HOST_UPDATE_HOST			__UINT64_C(0x00000002)	/* hosts.host and host_discovery.host fields should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_NAME			__UINT64_C(0x00000004)	/* hosts.name field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_PROXY			__UINT64_C(0x00000008)	/* hosts.proxy_hostid field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH		__UINT64_C(0x00000010)	/* hosts.ipmi_authtype field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV		__UINT64_C(0x00000020)	/* hosts.ipmi_privilege field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER		__UINT64_C(0x00000040)	/* hosts.ipmi_username field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS		__UINT64_C(0x00000080)	/* hosts.ipmi_password field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT		__UINT64_C(0x00000100)	/* hosts.tls_connect field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT		__UINT64_C(0x00000200)	/* hosts.tls_accept field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER		__UINT64_C(0x00000400)	/* hosts.tls_issuer field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT		__UINT64_C(0x00000800)	/* hosts.tls_subject field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY	__UINT64_C(0x00001000)	/* hosts.tls_psk_identity field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK		__UINT64_C(0x00002000)	/* hosts.tls_psk field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES	__UINT64_C(0x00004000)	/* hosts.custom_interfaces field should be updated */

#define ZBX_FLAG_LLD_HOST_UPDATE									\
		(ZBX_FLAG_LLD_HOST_UPDATE_HOST | ZBX_FLAG_LLD_HOST_UPDATE_NAME |			\
		ZBX_FLAG_LLD_HOST_UPDATE_PROXY | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH |			\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER |		\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS | ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT |		\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT | ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER |		\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT | ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY |	\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK | ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES)
	zbx_uint64_t		flags;
	const struct zbx_json_parse	*jp_row;
	signed char		inventory_mode;
	signed char		inventory_mode_orig;
	unsigned char		status;
	unsigned char		custom_interfaces;
	unsigned char		custom_interfaces_orig;
	zbx_uint64_t		proxy_hostid_orig;
	signed char		ipmi_authtype_orig;
	unsigned char		ipmi_privilege_orig;
	char			*ipmi_username_orig;
	char			*ipmi_password_orig;
	char			*tls_issuer_orig;
	char			*tls_subject_orig;
	char			*tls_psk_identity_orig;
	char			*tls_psk_orig;
	char			tls_connect_orig;
	char			tls_accept_orig;
}
zbx_lld_host_t;

static void	lld_host_free(zbx_lld_host_t *host)
{
	zbx_vector_uint64_destroy(&host->new_groupids);
	zbx_vector_uint64_destroy(&host->lnk_templateids);
	zbx_vector_uint64_destroy(&host->del_templateids);
	zbx_vector_ptr_clear_ext(&host->new_hostmacros, (zbx_clean_func_t)lld_hostmacro_free);
	zbx_vector_ptr_destroy(&host->new_hostmacros);
	zbx_vector_db_tag_ptr_clear_ext(&host->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host->tags);
	zbx_vector_ptr_clear_ext(&host->interfaces, (zbx_clean_func_t)lld_interface_free);
	zbx_vector_ptr_destroy(&host->interfaces);
	zbx_free(host->host_proto);
	zbx_free(host->host);
	zbx_free(host->host_orig);
	zbx_free(host->name);
	zbx_free(host->name_orig);
	zbx_free(host->ipmi_username_orig);
	zbx_free(host->ipmi_password_orig);
	zbx_free(host->tls_issuer_orig);
	zbx_free(host->tls_subject_orig);
	zbx_free(host->tls_psk_identity_orig);
	zbx_free(host->tls_psk_orig);
	zbx_free(host);
}

typedef struct
{
	zbx_uint64_t	group_prototypeid;
	char		*name;
}
zbx_lld_group_prototype_t;

static void	lld_group_prototype_free(zbx_lld_group_prototype_t *group_prototype)
{
	zbx_free(group_prototype->name);
	zbx_free(group_prototype);
}

typedef struct
{
	zbx_uint64_t		groupid;
	zbx_uint64_t		group_prototypeid;
	zbx_vector_ptr_t	hosts;
	char			*name_proto;
	char			*name;
	char			*name_orig;
	int			lastcheck;
	int			ts_delete;
#define ZBX_FLAG_LLD_GROUP_DISCOVERED		__UINT64_C(0x00000001)	/* groups which should be updated or added */
#define ZBX_FLAG_LLD_GROUP_UPDATE_NAME		__UINT64_C(0x00000002)	/* groups.name field should be updated */
#define ZBX_FLAG_LLD_GROUP_UPDATE		ZBX_FLAG_LLD_GROUP_UPDATE_NAME
	zbx_uint64_t		flags;
}
zbx_lld_group_t;

static void	lld_group_free(zbx_lld_group_t *group)
{
	/* zbx_vector_ptr_clear_ext(&group->hosts, (zbx_clean_func_t)lld_host_free); is not missing here */
	zbx_vector_ptr_destroy(&group->hosts);
	zbx_free(group->name_proto);
	zbx_free(group->name);
	zbx_free(group->name_orig);
	zbx_free(group);
}

typedef struct
{
	char				*name;
	/* permission pair (usrgrpid, permission) */
	zbx_vector_uint64_pair_t	rights;
	/* reference to the inherited rights */
	zbx_vector_uint64_pair_t	*prights;
}
zbx_lld_group_rights_t;

static void	lld_host_update_tags(zbx_lld_host_t *host, const zbx_vector_db_tag_ptr_t *tags,
		const zbx_vector_ptr_t *lld_macros, char **info);

typedef struct
{
	zbx_uint64_t	id;
	char		*name;
}
zbx_id_name_pair_t;

static zbx_hash_t	zbx_ids_names_hash_func(const void *data)
{
	const zbx_id_name_pair_t	*id_name_pair_entry = (const zbx_id_name_pair_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(id_name_pair_entry->id), sizeof(id_name_pair_entry->id),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_ids_names_compare_func(const void *d1, const void *d2)
{
	const zbx_id_name_pair_t	*id_name_pair_entry_1 = (const zbx_id_name_pair_t *)d1;
	const zbx_id_name_pair_t	*id_name_pair_entry_2 = (const zbx_id_name_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(id_name_pair_entry_1->id, id_name_pair_entry_2->id);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves tags of the existing hosts                              *
 *                                                                            *
 * Parameters: hosts - [IN/OUT] list of hosts                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_get_tags(zbx_vector_ptr_t *hosts)
{
	zbx_vector_uint64_t	hostids;
	int			i;
	zbx_lld_host_t		*host;
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		hostid;
	zbx_db_tag_t		*tag;

	zbx_vector_ptr_sort(hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];
		zbx_vector_uint64_append(&hostids, host->hostid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hosttagid,hostid,tag,value from host_tag where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = DBselect("%s", sql);

	i = 0;
	host = (zbx_lld_host_t *)hosts->values[i];

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[1]);
		while (hostid != host->hostid)
		{
			if (++i == hosts->values_num)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				goto out;
			}
			host = (zbx_lld_host_t *)hosts->values[i];
		}

		tag = zbx_db_tag_create(row[2], row[3]);
		ZBX_STR2UINT64(tag->tagid, row[0]);

		zbx_vector_db_tag_ptr_append(&host->tags, tag);
	}
out:
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];
		zbx_vector_db_tag_ptr_sort(&host->tags, zbx_db_tag_compare_func);
	}

	DBfree_result(result);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&hostids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves existing hosts for the specified host prototype         *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identifier                 *
 *             hosts         - [OUT] list of hosts                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, zbx_uint64_t proxy_hostid,
		signed char ipmi_authtype, unsigned char ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		unsigned char tls_connect, unsigned char tls_accept, const char *tls_issuer,
		const char *tls_subject, const char *tls_psk_identity, const char *tls_psk)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_host_t		*host;
	zbx_uint64_t		db_proxy_hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select hd.hostid,hd.host,hd.lastcheck,hd.ts_delete,h.host,h.name,h.proxy_hostid,"
				"h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password,hi.inventory_mode,"
				"h.tls_connect,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk,"
				"h.custom_interfaces"
			" from host_discovery hd"
				" join hosts h"
					" on hd.hostid=h.hostid"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where hd.parent_hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		host = (zbx_lld_host_t *)zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		ZBX_STR2UINT64(host->hostid, row[0]);
		host->host_proto = zbx_strdup(NULL, row[1]);
		host->lastcheck = atoi(row[2]);
		host->ts_delete = atoi(row[3]);
		host->host = zbx_strdup(NULL, row[4]);
		host->host_orig = NULL;
		host->name = zbx_strdup(NULL, row[5]);
		host->name_orig = NULL;
		host->ipmi_username_orig = NULL;
		host->ipmi_password_orig = NULL;
		host->tls_issuer_orig = NULL;
		host->tls_subject_orig = NULL;
		host->tls_psk_identity_orig = NULL;
		host->tls_psk_orig = NULL;
		host->jp_row = NULL;
		host->inventory_mode = HOST_INVENTORY_DISABLED;
		host->status = 0;
		host->custom_interfaces_orig = 0;
		host->proxy_hostid_orig = 0;
		host->ipmi_authtype_orig = 0;
		host->ipmi_privilege_orig = 0;
		host->tls_connect_orig = 0;
		host->tls_accept_orig = 0;
		host->flags = 0x00;
		ZBX_STR2UCHAR(host->custom_interfaces, row[18]);

		ZBX_DBROW2UINT64(db_proxy_hostid, row[6]);
		if (db_proxy_hostid != proxy_hostid)
		{
			host->proxy_hostid_orig = db_proxy_hostid;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_PROXY;
		}

		if ((signed char)atoi(row[7]) != ipmi_authtype)
		{
			host->ipmi_authtype_orig = (signed char)atoi(row[7]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH;
		}

		if ((unsigned char)atoi(row[8]) != ipmi_privilege)
		{
			host->ipmi_privilege_orig = (unsigned char)atoi(row[8]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV;
		}

		if (0 != strcmp(row[9], ipmi_username))
		{
			host->ipmi_username_orig = zbx_strdup(NULL, row[9]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER;
		}

		if (0 != strcmp(row[10], ipmi_password))
		{
			host->ipmi_password_orig = zbx_strdup(NULL, row[10]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS;
		}

		if (atoi(row[12]) != tls_connect)
		{
			host->tls_connect_orig = (char)atoi(row[12]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT;
		}

		if (atoi(row[13]) != tls_accept)
		{
			host->tls_accept_orig = (char)atoi(row[13]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT;
		}

		if (0 != strcmp(tls_issuer, row[14]))
		{
			host->tls_issuer_orig = zbx_strdup(NULL, row[14]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER;
		}

		if (0 != strcmp(tls_subject, row[15]))
		{
			host->tls_subject_orig = zbx_strdup(NULL, row[15]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT;
		}

		if (0 != strcmp(tls_psk_identity, row[16]))
		{
			host->tls_psk_identity_orig = zbx_strdup(NULL, row[16]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY;
		}

		if (0 != strcmp(tls_psk, row[17]))
		{
			host->tls_psk_orig = zbx_strdup(NULL, row[17]);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK;
		}

		if (SUCCEED == DBis_null(row[11]))
			host->inventory_mode_orig = HOST_INVENTORY_DISABLED;
		else
			host->inventory_mode_orig = (signed char)atoi(row[11]);

		zbx_vector_uint64_create(&host->new_groupids);
		zbx_vector_uint64_create(&host->lnk_templateids);
		zbx_vector_uint64_create(&host->del_templateids);
		zbx_vector_ptr_create(&host->new_hostmacros);
		zbx_vector_db_tag_ptr_create(&host->tags);
		zbx_vector_ptr_create(&host->interfaces);

		zbx_vector_ptr_append(hosts, host);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: hosts - [IN] list of hosts; should be sorted by hostid         *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_validate(zbx_vector_ptr_t *hosts, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_host_t		*host, *host_b;
	zbx_vector_uint64_t	hostids;
	zbx_vector_str_t	tnames, vnames;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_str_create(&tnames);		/* list of technical host names */
	zbx_vector_str_create(&vnames);		/* list of visible host names */

	/* checking a host name validity */
	for (i = 0; i < hosts->values_num; i++)
	{
		char	*ch_error;

		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		/* host name is valid? */
		if (SUCCEED == zbx_check_hostname(host->host, &ch_error))
			continue;

		*error = zbx_strdcatf(*error, "Cannot %s host \"%s\": %s.\n",
				(0 != host->hostid ? "update" : "create"), host->host, ch_error);

		zbx_free(ch_error);

		if (0 != host->hostid)
		{
			lld_field_str_rollback(&host->host, &host->host_orig, &host->flags,
					ZBX_FLAG_LLD_HOST_UPDATE_HOST);
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking a visible host name validity */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed visible name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		/* visible host name is valid utf8 sequence and has a valid length */
		if (SUCCEED == zbx_is_utf8(host->name) && '\0' != *host->name &&
				HOST_NAME_LEN >= zbx_strlen_utf8(host->name))
		{
			continue;
		}

		zbx_replace_invalid_utf8(host->name);
		*error = zbx_strdcatf(*error, "Cannot %s host: invalid visible host name \"%s\".\n",
				(0 != host->hostid ? "update" : "create"), host->name);

		if (0 != host->hostid)
		{
			lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
					ZBX_FLAG_LLD_HOST_UPDATE_NAME);
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking duplicated host names */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		for (j = 0; j < hosts->values_num; j++)
		{
			host_b = (zbx_lld_host_t *)hosts->values[j];

			if (0 == (host_b->flags & ZBX_FLAG_LLD_HOST_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(host->host, host_b->host))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s host:"
					" host with the same name \"%s\" already exists.\n",
					(0 != host->hostid ? "update" : "create"), host->host);

			if (0 != host->hostid)
			{
				lld_field_str_rollback(&host->host, &host->host_orig, &host->flags,
						ZBX_FLAG_LLD_HOST_UPDATE_HOST);
			}
			else
				host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
		}
	}

	/* checking duplicated visible host names */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed visible name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		for (j = 0; j < hosts->values_num; j++)
		{
			host_b = (zbx_lld_host_t *)hosts->values[j];

			if (0 == (host_b->flags & ZBX_FLAG_LLD_HOST_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(host->name, host_b->name))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s host:"
					" host with the same visible name \"%s\" already exists.\n",
					(0 != host->hostid ? "update" : "create"), host->name);

			if (0 != host->hostid)
			{
				lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
						ZBX_FLAG_LLD_HOST_UPDATE_NAME);
			}
			else
				host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
		}
	}

	/* checking duplicated host names and visible host names in DB */

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);

		if (0 == host->hostid || 0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			zbx_vector_str_append(&tnames, host->host);

		if (0 == host->hostid || 0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			zbx_vector_str_append(&vnames, host->name);
	}

	if (0 != tnames.values_num || 0 != vnames.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select host,name"
				" from hosts"
				" where status in (%d,%d,%d)"
					" and flags<>%d"
					" and",
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE,
				ZBX_FLAG_DISCOVERY_PROTOTYPE);

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " (");

		if (0 != tnames.values_num)
		{
			DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "host",
					(const char **)tnames.values, tnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " or");

		if (0 != vnames.values_num)
		{
			DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
					(const char **)vnames.values, vnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

		if (0 != hostids.values_num)
		{
			zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
					hostids.values, hostids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < hosts->values_num; i++)
			{
				host = (zbx_lld_host_t *)hosts->values[i];

				if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
					continue;

				if (0 == strcmp(host->host, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same name \"%s\" already exists.\n",
							(0 != host->hostid ? "update" : "create"), host->host);

					if (0 != host->hostid)
					{
						lld_field_str_rollback(&host->host, &host->host_orig, &host->flags,
								ZBX_FLAG_LLD_HOST_UPDATE_HOST);
					}
					else
						host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
				}

				if (0 == strcmp(host->name, row[1]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same visible name \"%s\" already exists.\n",
							(0 != host->hostid ? "update" : "create"), host->name);

					if (0 != host->hostid)
					{
						lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
								ZBX_FLAG_LLD_HOST_UPDATE_NAME);
					}
					else
						host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&vnames);
	zbx_vector_str_destroy(&tnames);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_lld_host_t	*lld_host_make(zbx_vector_ptr_t *hosts, const char *host_proto, const char *name_proto,
		signed char inventory_mode_proto, unsigned char status_proto, unsigned char discover_proto,
		zbx_vector_db_tag_ptr_t *tags, const zbx_lld_row_t *lld_row, const zbx_vector_ptr_t *lld_macros,
		char **info, unsigned char custom_iface)
{
	char			*buffer = NULL;
	int			i, host_found = 0;
	zbx_lld_host_t		*host = NULL;
	zbx_vector_db_tag_ptr_t	tmp_tags;
	zbx_vector_uint64_t	lnk_templateids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&lnk_templateids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 != (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		buffer = zbx_strdup(buffer, host->host_proto);
		substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 == strcmp(host->host, buffer))
		{
			host_found = 1;
			break;
		}
	}

	zbx_vector_db_tag_ptr_create(&tmp_tags);

	if (0 == host_found)
	{
		host = (zbx_lld_host_t *)zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		host->hostid = 0;
		host->host_proto = NULL;
		host->lastcheck = 0;
		host->ts_delete = 0;
		host->host = zbx_strdup(NULL, host_proto);
		host->host_orig = NULL;
		substitute_lld_macros(&host->host, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(host->host, ZBX_WHITESPACE);

		host->status = status_proto;
		host->inventory_mode = inventory_mode_proto;
		host->custom_interfaces = custom_iface;
		host->ipmi_username_orig = NULL;
		host->ipmi_password_orig = NULL;
		host->tls_issuer_orig = NULL;
		host->tls_subject_orig = NULL;
		host->tls_psk_identity_orig = NULL;
		host->tls_psk_orig = NULL;
		host->tls_connect_orig = 0;
		host->tls_accept_orig = 0;

		zbx_vector_uint64_create(&host->lnk_templateids);

		lld_override_host(&lld_row->overrides, host->host, &lnk_templateids, &host->inventory_mode,
				&tmp_tags, &host->status, &discover_proto);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover_proto)
		{
			zbx_vector_uint64_destroy(&host->lnk_templateids);
			zbx_free(host->host);
			zbx_free(host);
			goto out;
		}
		else
		{
			host->name = zbx_strdup(NULL, name_proto);
			substitute_lld_macros(&host->name, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(host->name, ZBX_WHITESPACE);
			host->name_orig = NULL;
			zbx_vector_uint64_create(&host->new_groupids);
			zbx_vector_uint64_create(&host->del_templateids);
			zbx_vector_ptr_create(&host->new_hostmacros);
			zbx_vector_db_tag_ptr_create(&host->tags);
			zbx_vector_ptr_create(&host->interfaces);
			host->flags = ZBX_FLAG_LLD_HOST_DISCOVERED;
			host->jp_row = NULL;
			host->inventory_mode_orig = host->inventory_mode;
			host->custom_interfaces_orig = host->custom_interfaces;
			host->proxy_hostid_orig = 0;
			host->ipmi_authtype_orig = 0;
			host->ipmi_privilege_orig = 0;

			zbx_vector_ptr_append(hosts, host);
		}
	}
	else
	{
		zbx_free(buffer);
		/* host technical name */
		if (0 != strcmp(host->host_proto, host_proto))	/* the new host prototype differs */
		{
			buffer = zbx_strdup(buffer, host_proto);
			substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(buffer, ZBX_WHITESPACE);
		}

		lld_override_host(&lld_row->overrides, NULL != buffer ? buffer : host->host, &lnk_templateids,
				&inventory_mode_proto, &tmp_tags, NULL, &discover_proto);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover_proto)
		{
			host = NULL;
			goto out;
		}

		if (NULL != buffer)
		{
			host->host_orig = host->host;
			host->host = buffer;
			buffer = NULL;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_HOST;
		}

		host->inventory_mode = inventory_mode_proto;

		if (host->custom_interfaces != custom_iface)
		{
			host->custom_interfaces_orig = host->custom_interfaces;
			host->custom_interfaces = custom_iface;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES;
		}

		/* host visible name */
		buffer = zbx_strdup(buffer, name_proto);
		substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(host->name, buffer))
		{
			host->name_orig = host->name;
			host->name = buffer;
			buffer = NULL;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_NAME;
		}

		host->flags |= ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	host->jp_row = &lld_row->jp_row;

	if (0 != (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
	{
		zbx_vector_db_tag_ptr_append_array(&tmp_tags, tags->values, tags->values_num);
		lld_host_update_tags(host, &tmp_tags, lld_macros, info);

		if (0 != lnk_templateids.values_num)
		{
			zbx_vector_uint64_append_array(&host->lnk_templateids, lnk_templateids.values,
					lnk_templateids.values_num);
		}
	}
out:
	zbx_vector_db_tag_ptr_destroy(&tmp_tags);
	zbx_vector_uint64_destroy(&lnk_templateids);

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)host);

	return host;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of host groups which should be present on the each  *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identifier                 *
 *             groupids      - [OUT] sorted list of host groups               *
 *                                                                            *
 ******************************************************************************/
static void	lld_simple_groups_get(zbx_uint64_t parent_hostid, zbx_vector_uint64_t *groupids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	groupid;

	result = DBselect(
			"select groupid"
			" from group_prototype"
			" where groupid is not null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(groupids, groupid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Parameters: groupids         - [IN] sorted list of host group ids which    *
 *                                     should be present on the each          *
 *                                     discovered host (Groups)               *
 *             hosts            - [IN/OUT] list of hosts                      *
 *                                         should be sorted by hostid         *
 *             groups           - [IN]  list of host groups (Group prototypes)*
 *             del_hostgroupids - [OUT] sorted list of host groups which      *
 *                                      should be deleted                     *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostgroups_make(const zbx_vector_uint64_t *groupids, zbx_vector_ptr_t *hosts,
		const zbx_vector_ptr_t *groups, zbx_vector_uint64_t *del_hostgroupids)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostgroupid, hostid, groupid;
	zbx_lld_host_t		*host;
	const zbx_lld_group_t	*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->new_groupids, groupids->values_num);
		for (j = 0; j < groupids->values_num; j++)
			zbx_vector_uint64_append(&host->new_groupids, groupids->values[j]);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED) || 0 == group->groupid)
			continue;

		for (j = 0; j < group->hosts.values_num; j++)
		{
			host = (zbx_lld_host_t *)group->hosts.values[j];

			zbx_vector_uint64_append(&host->new_groupids, group->groupid);
		}
	}

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];
		zbx_vector_uint64_sort(&host->new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,groupid,hostgroupid"
				" from hosts_groups"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(groupid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->new_groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* host groups which should be unlinked */
				ZBX_STR2UINT64(hostgroupid, row[2]);
				zbx_vector_uint64_append(del_hostgroupids, hostgroupid);

				zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE, hostid,
						(NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_hostgroup_update_json_delete_group(hostid, hostgroupid, groupid);
			}
			else
			{
				/* host groups which are already added */
				zbx_vector_uint64_remove(&host->new_groupids, i);
			}
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(del_hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of group prototypes                                 *
 *                                                                            *
 * Parameters: parent_hostid    - [IN] host prototype identifier              *
 *             group_prototypes - [OUT] sorted list of group prototypes       *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_prototypes_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *group_prototypes)
{
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_group_prototype_t	*group_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select group_prototypeid,name"
			" from group_prototype"
			" where groupid is null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		group_prototype = (zbx_lld_group_prototype_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_prototype_t));

		ZBX_STR2UINT64(group_prototype->group_prototypeid, row[0]);
		group_prototype->name = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(group_prototypes, group_prototype);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(group_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves existing groups for the specified host prototype        *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identifier                 *
 *             groups        - [OUT] list of groups                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *groups)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_group_t	*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select gd.groupid,gp.group_prototypeid,gd.name,gd.lastcheck,gd.ts_delete,g.name"
			" from group_prototype gp,group_discovery gd"
				" join hstgrp g"
					" on gd.groupid=g.groupid"
			" where gp.group_prototypeid=gd.parent_group_prototypeid"
				" and gp.hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		group = (zbx_lld_group_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_t));

		ZBX_STR2UINT64(group->groupid, row[0]);
		ZBX_STR2UINT64(group->group_prototypeid, row[1]);
		zbx_vector_ptr_create(&group->hosts);
		group->name_proto = zbx_strdup(NULL, row[2]);
		group->lastcheck = atoi(row[3]);
		group->ts_delete = atoi(row[4]);
		group->name = zbx_strdup(NULL, row[5]);
		group->name_orig = NULL;
		group->flags = 0x00;

		zbx_vector_ptr_append(groups, group);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(groups, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_lld_group_t	*lld_group_make(zbx_vector_ptr_t *groups, zbx_uint64_t group_prototypeid,
		const char *name_proto, const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macros)
{
	char		*buffer = NULL;
	int		i, group_found = 0;
	zbx_lld_group_t	*group = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (group->group_prototypeid != group_prototypeid)
			continue;

		if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		buffer = zbx_strdup(buffer, group->name_proto);
		substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 == strcmp(group->name, buffer))
		{
			group_found = 1;
			break;
		}
	}

	if (0 == group_found)
	{
		/* trying to find an already existing group */

		buffer = zbx_strdup(buffer, name_proto);
		substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		for (i = 0; i < groups->values_num; i++)
		{
			group = (zbx_lld_group_t *)groups->values[i];

			if (group->group_prototypeid != group_prototypeid)
				continue;

			if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
				continue;

			if (0 == strcmp(group->name, buffer))
				goto out;
		}

		/* otherwise create a new group */

		group = (zbx_lld_group_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_t));

		group->groupid = 0;
		group->group_prototypeid = group_prototypeid;
		zbx_vector_ptr_create(&group->hosts);
		group->name_proto = NULL;
		group->name = zbx_strdup(NULL, name_proto);
		substitute_lld_macros(&group->name, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(group->name, ZBX_WHITESPACE);
		group->name_orig = NULL;
		group->lastcheck = 0;
		group->ts_delete = 0;
		group->flags = 0x00;
		group->flags = ZBX_FLAG_LLD_GROUP_DISCOVERED;

		zbx_vector_ptr_append(groups, group);
	}
	else
	{
		/* update an already existing group */

		/* group name */
		buffer = zbx_strdup(buffer, name_proto);
		substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(group->name, buffer))
		{
			group->name_orig = group->name;
			group->name = buffer;
			buffer = NULL;
			group->flags |= ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
		}

		group->flags |= ZBX_FLAG_LLD_GROUP_DISCOVERED;
	}
out:
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)group);

	return group;
}

static void	lld_groups_make(zbx_lld_host_t *host, zbx_vector_ptr_t *groups, const zbx_vector_ptr_t *group_prototypes,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macros)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < group_prototypes->values_num; i++)
	{
		const zbx_lld_group_prototype_t	*group_prototype;
		zbx_lld_group_t			*group;

		group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[i];

		group = lld_group_make(groups, group_prototype->group_prototypeid, group_prototype->name, jp_row,
				lld_macros);

		zbx_vector_ptr_append(&group->hosts, host);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCEED - the group name is valid                            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	lld_validate_group_name(const char *name)
{
	/* group name cannot be empty */
	if ('\0' == *name)
		return FAIL;

	/* group name must contain valid utf8 characters */
	if (SUCCEED != zbx_is_utf8(name))
		return FAIL;

	/* group name cannot exceed field limits */
	if (GROUP_NAME_LEN < zbx_strlen_utf8(name))
		return FAIL;

	/* group name cannot contain trailing and leading slashes (/) */
	if ('/' == *name || '/' == name[strlen(name) - 1])
		return FAIL;

	/* group name cannot contain several slashes (/) in a row */
	if (NULL != strstr(name, "//"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: groups - [IN] list of groups; should be sorted by groupid      *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_validate(zbx_vector_ptr_t *groups, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_group_t		*group, *group_b;
	zbx_vector_uint64_t	groupids;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&groupids);
	zbx_vector_str_create(&names);		/* list of group names */

	/* checking a group name validity */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		/* only new groups or groups with changed group name will be validated */
		if (0 != group->groupid && 0 == (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			continue;

		if (SUCCEED == lld_validate_group_name(group->name))
			continue;

		zbx_replace_invalid_utf8(group->name);
		*error = zbx_strdcatf(*error, "Cannot %s group: invalid group name \"%s\".\n",
				(0 != group->groupid ? "update" : "create"), group->name);

		if (0 != group->groupid)
		{
			lld_field_str_rollback(&group->name, &group->name_orig, &group->flags,
					ZBX_FLAG_LLD_GROUP_UPDATE_NAME);
		}
		else
			group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
	}

	/* checking duplicated group names */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		/* only new groups or groups with changed group name will be validated */
		if (0 != group->groupid && 0 == (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			continue;

		for (j = 0; j < groups->values_num; j++)
		{
			group_b = (zbx_lld_group_t *)groups->values[j];

			if (0 == (group_b->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(group->name, group_b->name))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s group:"
					" group with the same name \"%s\" already exists.\n",
					(0 != group->groupid ? "update" : "create"), group->name);

			if (0 != group->groupid)
			{
				lld_field_str_rollback(&group->name, &group->name_orig, &group->flags,
						ZBX_FLAG_LLD_GROUP_UPDATE_NAME);
			}
			else
				group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
		}
	}

	/* checking duplicated group names and group names in DB */

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 != group->groupid)
			zbx_vector_uint64_append(&groupids, group->groupid);

		if (0 == group->groupid || 0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			zbx_vector_str_append(&names, group->name);
	}

	if (0 != names.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select name from hstgrp where");
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
				(const char **)names.values, names.values_num);

		if (0 != groupids.values_num)
		{
			zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
					groupids.values, groupids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < groups->values_num; i++)
			{
				group = (zbx_lld_group_t *)groups->values[i];

				if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
					continue;

				if (0 == strcmp(group->name, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s group:"
							" group with the same name \"%s\" already exists.\n",
							(0 != group->groupid ? "update" : "create"), group->name);

					if (0 != group->groupid)
					{
						lld_field_str_rollback(&group->name, &group->name_orig, &group->flags,
								ZBX_FLAG_LLD_GROUP_UPDATE_NAME);
					}
					else
						group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&names);
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort group rights vector by name              *
 *                                                                            *
 ******************************************************************************/
static int	lld_group_rights_compare(const void *d1, const void *d2)
{
	const zbx_lld_group_rights_t	*r1 = *(const zbx_lld_group_rights_t **)d1;
	const zbx_lld_group_rights_t	*r2 = *(const zbx_lld_group_rights_t **)d2;

	return strcmp(r1->name, r2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: append a new item to group rights vector                          *
 *                                                                            *
 * Return value: Index of the added item.                                     *
 *                                                                            *
 ******************************************************************************/
static int	lld_group_rights_append(zbx_vector_ptr_t *group_rights, const char *name)
{
	zbx_lld_group_rights_t	*rights;

	rights = (zbx_lld_group_rights_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_rights_t));
	rights->name = zbx_strdup(NULL, name);
	zbx_vector_uint64_pair_create(&rights->rights);
	rights->prights = NULL;

	zbx_vector_ptr_append(group_rights, rights);

	return group_rights->values_num - 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees group rights data                                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_rights_free(zbx_lld_group_rights_t *rights)
{
	zbx_free(rights->name);
	zbx_vector_uint64_pair_destroy(&rights->rights);
	zbx_free(rights);
}

/******************************************************************************
 *                                                                            *
 * Parameters: groups - [IN] list of new groups                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_save_rights(zbx_vector_ptr_t *groups)
{
	int			i, j;
	DB_ROW			row;
	DB_RESULT		result;
	char			*ptr, *name, *sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, offset;
	zbx_lld_group_t		*group;
	zbx_vector_str_t	group_names;
	zbx_vector_ptr_t	group_rights;
	zbx_db_insert_t		db_insert;
	zbx_lld_group_rights_t	*rights, rights_local, *parent_rights;
	zbx_uint64_pair_t	pair;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&group_names);
	zbx_vector_ptr_create(&group_rights);

	/* make a list of direct parent group names and a list of new group rights */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (NULL == (ptr = strrchr(group->name, '/')))
			continue;

		lld_group_rights_append(&group_rights, group->name);

		name = zbx_strdup(NULL, group->name);
		name[ptr - group->name] = '\0';

		if (FAIL != zbx_vector_str_search(&group_names, name, ZBX_DEFAULT_STR_COMPARE_FUNC))
		{
			zbx_free(name);
			continue;
		}

		zbx_vector_str_append(&group_names, name);
	}

	if (0 == group_names.values_num)
		goto out;

	/* read the parent group rights */

	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "id", "permission", "groupid", NULL);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select g.name,r.permission,r.groupid from hstgrp g,rights r"
				" where r.id=g.groupid"
				" and");

	DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.name", (const char **)group_names.values,
			group_names.values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		rights_local.name = row[0];
		if (FAIL == (i = zbx_vector_ptr_search(&group_rights, &rights_local, lld_group_rights_compare)))
			i = lld_group_rights_append(&group_rights, row[0]);

		rights = (zbx_lld_group_rights_t *)group_rights.values[i];
		rights->prights = &rights->rights;

		ZBX_STR2UINT64(pair.first, row[2]);
		pair.second = atoi(row[1]);

		zbx_vector_uint64_pair_append(&rights->rights, pair);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(&group_rights, lld_group_rights_compare);

	/* assign rights for the new groups */
	for (i = 0; i < group_rights.values_num; i++)
	{
		rights = (zbx_lld_group_rights_t *)group_rights.values[i];

		if (NULL != rights->prights)
			continue;

		if (NULL == (ptr = strrchr(rights->name, '/')))
			continue;

		offset = ptr - rights->name;

		for (j = 0; j < i; j++)
		{
			parent_rights = (zbx_lld_group_rights_t *)group_rights.values[j];

			if (strlen(parent_rights->name) != offset)
				continue;

			if (0 != strncmp(parent_rights->name, rights->name, offset))
				continue;

			rights->prights = parent_rights->prights;
			break;
		}
	}

	/* save rights for the new groups */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		rights_local.name = group->name;
		if (FAIL == (j = zbx_vector_ptr_bsearch(&group_rights, &rights_local, lld_group_rights_compare)))
			continue;

		rights = (zbx_lld_group_rights_t *)group_rights.values[j];

		if (NULL == rights->prights)
			continue;

		for (j = 0; j < rights->prights->values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), group->groupid,
					(int)rights->prights->values[j].second, rights->prights->values[j].first);
		}
	}

	zbx_db_insert_autoincrement(&db_insert, "rightid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
	zbx_vector_ptr_clear_ext(&group_rights, (zbx_clean_func_t)lld_group_rights_free);
	zbx_vector_str_clear_ext(&group_names, zbx_str_free);
out:
	zbx_vector_ptr_destroy(&group_rights);
	zbx_vector_str_destroy(&group_names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: groups           - [IN/OUT] list of groups; should be sorted   *
 *                                         by groupid                         *
 *             group_prototypes - [IN] list of group prototypes; should be    *
 *                                     sorted by group_prototypeid            *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_save(zbx_vector_ptr_t *groups, const zbx_vector_ptr_t *group_prototypes)
{
	int				i, j, upd_groups_num = 0;
	zbx_lld_group_t			*group;
	const zbx_lld_group_prototype_t	*group_prototype;
	zbx_lld_host_t			*host;
	zbx_uint64_t			groupid = 0;
	char				*sql = NULL, *name_esc, *name_proto_esc;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t			db_insert, db_insert_gdiscovery;
	zbx_vector_ptr_t		new_groups;
	zbx_vector_uint64_t		new_group_prototype_ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&new_group_prototype_ids);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
			zbx_vector_uint64_append(&new_group_prototype_ids, group->group_prototypeid);
		else if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
			upd_groups_num++;
	}

	if (0 == new_group_prototype_ids.values_num && 0 == upd_groups_num)
		goto out;

	DBbegin();

	if (0 != new_group_prototype_ids.values_num)
	{
		if (SUCCEED != DBlock_group_prototypeids(&new_group_prototype_ids))
		{
			/* the host group prototype was removed while processing lld rule */
			DBrollback();
			goto out;
		}

		groupid = DBget_maxid_num("hstgrp", new_group_prototype_ids.values_num);

		zbx_db_insert_prepare(&db_insert, "hstgrp", "groupid", "name", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_gdiscovery, "group_discovery", "groupid", "parent_group_prototypeid",
				"name", NULL);

		zbx_vector_ptr_create(&new_groups);
	}

	if (0 != upd_groups_num)
	{
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
		{
			group->groupid = groupid++;

			zbx_db_insert_add_values(&db_insert, group->groupid, group->name,
					(int)ZBX_FLAG_DISCOVERY_CREATED);
			zbx_audit_host_group_create_entry(AUDIT_ACTION_ADD, group->groupid, group->name);

			zbx_audit_host_group_update_json_add_details(group->groupid, group->name,
					(int)ZBX_FLAG_DISCOVERY_CREATED);

			if (FAIL != (j = zbx_vector_ptr_bsearch(group_prototypes, &group->group_prototypeid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[j];

				zbx_db_insert_add_values(&db_insert_gdiscovery, group->groupid,
						group->group_prototypeid, group_prototype->name);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;

			for (j = 0; j < group->hosts.values_num; j++)
			{
				host = (zbx_lld_host_t *)group->hosts.values[j];

				/* hosts will be linked to a new host groups */
				zbx_vector_uint64_append(&host->new_groupids, group->groupid);
			}

			zbx_vector_ptr_append(&new_groups, group);
		}
		else
		{
			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hstgrp set ");
				zbx_audit_host_group_create_entry(AUDIT_ACTION_UPDATE, group->groupid, group->name);

				if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
				{
					name_esc = DBdyn_escape_string(group->name);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);

					zbx_audit_host_group_update_json_update_name(group->groupid,group->name_orig,
							name_esc);

					zbx_free(name_esc);
				}
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						" where groupid=" ZBX_FS_UI64 ";\n", group->groupid);
			}

			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			{
				if (FAIL != (j = zbx_vector_ptr_bsearch(group_prototypes, &group->group_prototypeid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[j];

					name_proto_esc = DBdyn_escape_string(group_prototype->name);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"update group_discovery"
							" set name='%s'"
							" where groupid=" ZBX_FS_UI64 ";\n",
							name_proto_esc, group->groupid);

					zbx_free(name_proto_esc);
				}
				else
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}
	}

	if (0 != upd_groups_num)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	if (0 != new_group_prototype_ids.values_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_gdiscovery);
		zbx_db_insert_clean(&db_insert_gdiscovery);

		lld_groups_save_rights(&new_groups);
		zbx_vector_ptr_destroy(&new_groups);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&new_group_prototype_ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of host macros which should be present on the each  *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: hostmacros - [OUT] list of host macros                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_masterhostmacros_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *hostmacros)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_hostmacro_t	*hostmacro;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select hm.macro,hm.value,hm.description,hm.type"
			" from hostmacro hm,items i"
			" where hm.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

		hostmacro->hostmacroid = 0;
		hostmacro->macro = zbx_strdup(NULL, row[0]);
		hostmacro->value = zbx_strdup(NULL, row[1]);
		hostmacro->value_orig = NULL;
		hostmacro->description = zbx_strdup(NULL, row[2]);
		hostmacro->description_orig = NULL;
		hostmacro->type_orig = 0;
		hostmacro->flags = 0;
		ZBX_STR2UCHAR(hostmacro->type, row[3]);

		zbx_vector_ptr_append(hostmacros, hostmacro);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare the name of host macros for search in vector              *
 *                                                                            *
 * Parameters: d1 - [IN] first zbx_lld_hostmacro_t                            *
 *             d2 - [IN] second zbx_lld_hostmacro_t                           *
 *                                                                            *
 * Return value: 0 if name of macros are equal                                *
 *                                                                            *
 ******************************************************************************/
static int	macro_str_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_hostmacro_t *hostmacro1 = *(const zbx_lld_hostmacro_t **)d1;
	const zbx_lld_hostmacro_t *hostmacro2 = *(const zbx_lld_hostmacro_t **)d2;

	return strcmp(hostmacro1->macro, hostmacro2->macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of host macros which should be present on the each  *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: parent_hostid    - [IN] host prototype id                      *
 *             masterhostmacros - [IN] list of master host macros             *
 *             hostmacros       - [OUT] list of host macros                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostmacros_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *masterhostmacros,
		zbx_vector_ptr_t *hostmacros)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_hostmacro_t	*hostmacro;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select hm.macro,hm.value,hm.description,hm.type"
			" from hostmacro hm"
			" where hm.hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

		hostmacro->hostmacroid = 0;
		hostmacro->macro = zbx_strdup(NULL, row[0]);
		hostmacro->value = zbx_strdup(NULL, row[1]);
		hostmacro->value_orig = NULL;
		hostmacro->description = zbx_strdup(NULL, row[2]);
		hostmacro->description_orig = NULL;
		ZBX_STR2UCHAR(hostmacro->type, row[3]);
		hostmacro->type_orig = hostmacro->type;
		hostmacro->flags = 0;

		zbx_vector_ptr_append(hostmacros, hostmacro);
	}
	DBfree_result(result);

	for (i = 0; i < masterhostmacros->values_num; i++)
	{
		const zbx_lld_hostmacro_t	*masterhostmacro;

		if (FAIL != zbx_vector_ptr_search(hostmacros, masterhostmacros->values[i], macro_str_compare_func))
			continue;

		hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

		masterhostmacro = (const zbx_lld_hostmacro_t *)masterhostmacros->values[i];
		hostmacro->hostmacroid = 0;
		hostmacro->macro = zbx_strdup(NULL, masterhostmacro->macro);
		hostmacro->value = zbx_strdup(NULL, masterhostmacro->value);
		hostmacro->value_orig = NULL;
		hostmacro->description = zbx_strdup(NULL, masterhostmacro->description);
		hostmacro->description_orig = NULL;
		hostmacro->type = masterhostmacro->type;
		hostmacro->type_orig = hostmacro->type;
		hostmacro->flags = 0;
		zbx_vector_ptr_append(hostmacros, hostmacro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_hostmacro_make(zbx_vector_ptr_t *hostmacros, zbx_uint64_t hostmacroid, const char *macro,
		const char *value, const char *description, unsigned char type)
{
	zbx_lld_hostmacro_t	*hostmacro;
	int			i;

	for (i = 0; i < hostmacros->values_num; i++)
	{
		hostmacro = (zbx_lld_hostmacro_t *)hostmacros->values[i];

		/* check if host macro has already been added */
		if (0 == hostmacro->hostmacroid && 0 == strcmp(hostmacro->macro, macro))
		{
			hostmacro->hostmacroid = hostmacroid;
			if (0 != strcmp(hostmacro->value, value))
			{
				hostmacro->flags |= ZBX_FLAG_LLD_HMACRO_UPDATE_VALUE;
				hostmacro->value_orig = zbx_strdup(NULL, value);
			}
			if (0 != strcmp(hostmacro->description, description))
			{
				hostmacro->flags |= ZBX_FLAG_LLD_HMACRO_UPDATE_DESCRIPTION;
				hostmacro->description_orig = zbx_strdup(NULL, description);
			}
			if (hostmacro->type != type)
			{
				hostmacro->type_orig = type;
				hostmacro->flags |= ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE;
			}
			return;
		}
	}

	/* host macro is present on the host but not in new list, it should be removed */
	hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));
	hostmacro->hostmacroid = hostmacroid;
	hostmacro->macro = NULL;
	hostmacro->value = NULL;
	hostmacro->value_orig = NULL;
	hostmacro->description = NULL;
	hostmacro->description_orig = NULL;
	hostmacro->flags = ZBX_FLAG_LLD_HMACRO_REMOVE;
	hostmacro->type = 0;
	hostmacro->type_orig = 0;

	zbx_vector_ptr_append(hostmacros, hostmacro);
}

/******************************************************************************
 *                                                                            *
 * Parameters: hostmacros       - [IN] list of host macros which              *
 *                                     should be present on the each          *
 *                                     discovered host                        *
 *             hosts            - [IN/OUT] list of hosts                      *
 *                                         should be sorted by hostid         *
 *             del_hostmacroids - [OUT] list of host macros which should be   *
 *                                      deleted                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostmacros_make(const zbx_vector_ptr_t *hostmacros, zbx_vector_ptr_t *hosts,
		const zbx_vector_ptr_t *lld_macros)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostmacroid, hostid;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_ptr_reserve(&host->new_hostmacros, (size_t)hostmacros->values_num);
		for (j = 0; j < hostmacros->values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

			hostmacro->hostmacroid = 0;
			hostmacro->macro = zbx_strdup(NULL, ((zbx_lld_hostmacro_t *)hostmacros->values[j])->macro);
			hostmacro->value = zbx_strdup(NULL, ((zbx_lld_hostmacro_t *)hostmacros->values[j])->value);
			hostmacro->value_orig = NULL;
			hostmacro->type = ((zbx_lld_hostmacro_t *)hostmacros->values[j])->type;
			hostmacro->type_orig = ((zbx_lld_hostmacro_t *)hostmacros->values[j])->type_orig;
			hostmacro->description = zbx_strdup(NULL,
					((zbx_lld_hostmacro_t *)hostmacros->values[j])->description);
			hostmacro->description_orig = NULL;
			hostmacro->flags = 0x00;
			substitute_lld_macros(&hostmacro->value, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			substitute_lld_macros(&hostmacro->description, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);

			zbx_vector_ptr_append(&host->new_hostmacros, hostmacro);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostmacroid,hostid,macro,value,description,type"
				" from hostmacro"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			unsigned char	type;

			ZBX_STR2UINT64(hostid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			ZBX_STR2UINT64(hostmacroid, row[0]);
			ZBX_STR2UCHAR(type, row[5]);

			lld_hostmacro_make(&host->new_hostmacros, hostmacroid, row[2], row[3], row[4], type);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of host tags which should be present on the each    *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype id                         *
 *             tags          - [OUT] list of host tags                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_proto_tags_get(zbx_uint64_t parent_hostid, zbx_vector_db_tag_ptr_t *tags)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_tag_t	*tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select tag,value"
			" from host_tag"
			" where hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		tag = zbx_db_tag_create(row[0], row[1]);

		zbx_vector_db_tag_ptr_append(tags, tag);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate host tag field                                           *
 *                                                                            *
 * Parameters: name      - [IN] the field name (tag, value)                   *
 *             field     - [IN] the field value                               *
 *             field_len - [IN] the field length                              *
 *             info      - [OUT] error information                            *
 *                                                                            *
 ******************************************************************************/
static int	lld_tag_validate_field(const char *name, const char *field, size_t field_len, char **info)
{
	if (SUCCEED != zbx_is_utf8(field))
	{
		char	*field_utf8;

		field_utf8 = zbx_strdup(NULL, field);
		zbx_replace_invalid_utf8(field_utf8);
		*info = zbx_strdcatf(*info, "Cannot create host tag: %s \"%s\" has invalid UTF-8 sequence.\n",
				name, field_utf8);
		zbx_free(field_utf8);
		return FAIL;
	}

	if (zbx_strlen_utf8(field) > field_len)
	{
		*info = zbx_strdcatf(*info, "Cannot create host tag: %s \"%128s...\" is too long.\n", name, field);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate host tag                                                 *
 *                                                                            *
 * Parameters: tags     - [IN] the current host tags                          *
 *             tags_num - [IN] the number of host tags                        *
 *             name     - [IN] the new tag name                               *
 *             value    - [IN] the new tag value                              *
 *             info     - [OUT] error information                             *
 *                                                                            *
 ******************************************************************************/
static int	lld_tag_validate(zbx_db_tag_t **tags, int tags_num, const char *name, const char *value, char **info)
{
	int	i;

	if ('\0' == *name)
	{
		*info = zbx_strdcatf(*info, "Cannot create host tag: empty tag name.\n");
		return FAIL;
	}

	if (SUCCEED != lld_tag_validate_field("name", name, TAG_NAME_LEN, info))
		return FAIL;

	if (SUCCEED != lld_tag_validate_field("value", value, TAG_VALUE_LEN, info))
		return FAIL;

	for (i = 0; i < tags_num; i++)
	{
		if (0 == strcmp(tags[i]->tag, name) && 0 == strcmp(tags[i]->value, value))
		{
			*info = zbx_strdcatf(*info, "Cannot create host tag: tag \"%s\",\"%s\" already exists.\n",
					name, value);

			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update host tags                                                  *
 *                                                                            *
 * Parameters: host       - [IN] a host with existing tags, sorted by tag +   *
 *                               value.                                       *
 *                        - [OUT] a host with updated tags, sorted by tagid   *
 *             tags       - [IN] the new tags, sorted by tag + value          *
 *             lld_macros - [IN] a vector of LLD macros                       *
 *             info       - [OUT] error information                           *
 *                                                                            *
 * Comments: No database changes are made if host tags are equal to the new   *
 *           tags. Otherwise the tags are updated starting with the first not *
 *           matching tag. If there are more host tags than new tags then the *
 *           extra host tags are marked for removal. If there are more new    *
 *           tags than host tags, then new tags are appended to the host tags.*
 *                                                                            *
 ******************************************************************************/
static void	lld_host_update_tags(zbx_lld_host_t *host, const zbx_vector_db_tag_ptr_t *tags,
		const zbx_vector_ptr_t *lld_macros, char **info)
{
	int			i;
	zbx_db_tag_t		*host_tag, *proto_tag;
	zbx_vector_db_tag_ptr_t	new_tags;
	char			*tag = NULL, *value = NULL;

	zbx_vector_db_tag_ptr_create(&new_tags);

	/* create vector of new tags with expanded lld macros */
	for (i = 0; i < tags->values_num; i++)
	{
		proto_tag = (zbx_db_tag_t *)tags->values[i];

		tag = zbx_strdup(tag, proto_tag->tag);
		value = zbx_strdup(value, proto_tag->value);
		substitute_lld_macros(&tag, host->jp_row, lld_macros, ZBX_MACRO_FUNC, NULL, 0);
		substitute_lld_macros(&value, host->jp_row, lld_macros, ZBX_MACRO_FUNC, NULL, 0);

		if (SUCCEED != lld_tag_validate(new_tags.values, new_tags.values_num, tag, value, info))
			continue;

		proto_tag = zbx_db_tag_create(tag, value);
		zbx_vector_db_tag_ptr_append(&new_tags, proto_tag);

		zbx_free(tag);
		zbx_free(value);
	}

	zbx_vector_db_tag_ptr_sort(&new_tags, zbx_db_tag_compare_func);

	zbx_vector_db_tag_ptr_reserve(&host->tags, (size_t)new_tags.values_num);

	/* update host tags or flag them for removal */
	for (i = 0; i < host->tags.values_num; i++)
	{
		host_tag = (zbx_db_tag_t *)host->tags.values[i];
		if (i < new_tags.values_num)
		{
			proto_tag = (zbx_db_tag_t *)new_tags.values[i];

			if (0 != strcmp(host_tag->tag, proto_tag->tag))
			{
				host_tag->tag_orig = zbx_strdup(NULL, host_tag->tag);
				host_tag->tag = zbx_strdup(host_tag->tag, proto_tag->tag);
				host_tag->flags |= ZBX_FLAG_DB_TAG_UPDATE_TAG;
			}

			if (0 != strcmp(host_tag->value, proto_tag->value))
			{
				host_tag->value_orig = zbx_strdup(NULL, host_tag->value);
				host_tag->value = zbx_strdup(host_tag->value, proto_tag->value);
				host_tag->flags |= ZBX_FLAG_DB_TAG_UPDATE_VALUE;
			}
		}
		else
			host_tag->flags = ZBX_FLAG_DB_TAG_REMOVE;
	}

	/* add missing tags */
	if (i < new_tags.values_num)
	{
		int	j;

		/* set uninitialized properties of new tags that will be moved to host */
		for (j = i; j < new_tags.values_num; j++)
		{
			proto_tag = (zbx_db_tag_t *)new_tags.values[j];
			proto_tag->tagid = 0;
			proto_tag->flags = 0;
		}

		zbx_vector_db_tag_ptr_append_array(&host->tags, new_tags.values + i, new_tags.values_num - i);
		new_tags.values_num = i;
	}

	zbx_free(tag);
	zbx_free(value);

	/* sort existing tags by their ids for update operations */
	zbx_vector_db_tag_ptr_sort(&host->tags, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zbx_vector_db_tag_ptr_clear_ext(&new_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&new_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets templates from a host prototype                              *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identifier                 *
 *             hosts         - [IN/OUT] list of hosts                         *
 *                                      should be sorted by hostid            *
 *                                                                            *
 ******************************************************************************/
static void	lld_templates_make(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	templateids, hostids;
	zbx_uint64_t		templateid, hostid;
	zbx_lld_host_t		*host;
	int			i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_create(&hostids);

	/* select templates which should be linked */

	result = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		zbx_vector_uint64_append(&templateids, templateid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* select list of already created hosts */

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->lnk_templateids, templateids.values_num);
		for (j = 0; j < templateids.values_num; j++)
			zbx_vector_uint64_append(&host->lnk_templateids, templateids.values[j]);

		/* sort templates which should be linked by override */
		zbx_vector_uint64_sort(&host->lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&host->lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		/* select already linked templates */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,templateid"
				" from hosts_templates"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(templateid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->lnk_templateids, templateid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* templates which should be unlinked */
				zbx_vector_uint64_append(&host->del_templateids, templateid);
			}
			else
			{
				/* templates which are already linked */
				zbx_vector_uint64_remove(&host->lnk_templateids, i);
			}
		}
		DBfree_result(result);

		for (i = 0; i < hosts->values_num; i++)
		{
			host = (zbx_lld_host_t *)hosts->values[i];

			if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
				continue;

			zbx_vector_uint64_sort(&host->del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		}
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare sql for update record of interface_snmp table             *
 *                                                                            *
 * Parameters: hostid      - [IN] host identifier                             *
 *             interfaceid - [IN] snmp interface id;                          *
 *             snmp        - [IN] snmp values for update                      *
 *             sql         - [IN/OUT] sql string                              *
 *             sql_alloc   - [IN/OUT] size of sql string                      *
 *             sql_offset  - [IN/OUT] offset in sql string                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_interface_snmp_prepare_sql(zbx_uint64_t hostid, const zbx_uint64_t interfaceid,
		const zbx_lld_interface_snmp_t *snmp, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	const char	*d = "";
	char		*value_esc;

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update interface_snmp set ");

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "version=%d", (int)snmp->version);
		d = ",";

		zbx_audit_host_update_json_update_interface_version(hostid, interfaceid,
						snmp->version_orig, snmp->version);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sbulk=%d", d, (int)snmp->bulk);
		d = ",";

		zbx_audit_host_update_json_update_interface_bulk(hostid, interfaceid, snmp->bulk_orig, snmp->bulk);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY))
	{
		value_esc = DBdyn_escape_string(snmp->community);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scommunity='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_community(hostid, interfaceid, snmp->community_orig,
				snmp->community);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME))
	{
		value_esc = DBdyn_escape_string(snmp->securityname);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssecurityname='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_securityname(hostid, interfaceid,
				snmp->securityname_orig, snmp->securityname);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssecuritylevel=%d", d, (int)snmp->securitylevel);
		d = ",";

		zbx_audit_host_update_json_update_interface_securitylevel(hostid, interfaceid,
				snmp->securitylevel_orig, snmp->securitylevel);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS))
	{
		value_esc = DBdyn_escape_string(snmp->authpassphrase);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sauthpassphrase='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_authpassphrase(hostid, interfaceid,
						snmp->authpassphrase_orig, snmp->authpassphrase);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS))
	{
		value_esc = DBdyn_escape_string(snmp->privpassphrase);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sprivpassphrase='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_privpassphrase(hostid, interfaceid,
						snmp->privpassphrase_orig, snmp->privpassphrase);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sauthprotocol=%d", d, (int)snmp->authprotocol);
		d = ",";

		zbx_audit_host_update_json_update_interface_authprotocol(hostid, interfaceid,
				snmp->authprotocol_orig, snmp->authprotocol);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sprivprotocol=%d", d, (int)snmp->privprotocol);
		d = ",";

		zbx_audit_host_update_json_update_interface_privprotocol(hostid, interfaceid,
				snmp->privprotocol_orig, snmp->privprotocol);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT))
	{
		value_esc = DBdyn_escape_string(snmp->contextname);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scontextname='%s'", d, value_esc);
		zbx_free(value_esc);

		zbx_audit_host_update_json_update_interface_contextname(hostid, interfaceid,
				snmp->contextname_orig, snmp->contextname);
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where interfaceid=" ZBX_FS_UI64 ";\n", interfaceid);
}

/******************************************************************************
 *                                                                            *
 * Parameters: parent_hostid    - [IN] parent host id                         *
 *             hosts            - [IN] list of hosts;                         *
 *             host_proto       - [IN] host proto                             *
 *             proxy_hostid     - [IN] proxy host id                          *
 *             ipmi_authtype    - [IN] ipmi authtype                          *
 *             ipmi_privilege   - [IN] ipmi privilege                         *
 *             ipmi_username    - [IN] ipmi username                          *
 *             ipmi_password    - [IN] ipmi password                          *
 *             status           - [IN] host status                            *
 *             inventory_mode   - [IN] host inventory mode                    *
 *             tls_connect      - [IN] tls connect                            *
 *             tls_accept       - [IN] tls accept                             *
 *             tls_issuer       - [IN] tls cert issuer                        *
 *             tls_subject      - [IN] tls cert subject                       *
 *             tls_psk_identity - [IN] tls psk identity                       *
 *             tls_psk          - [IN] tls psk                                *
 *             del_hostgroupids - [IN] host groups which should be deleted    *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_save(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, const char *host_proto,
		zbx_uint64_t proxy_hostid, signed char ipmi_authtype, unsigned char ipmi_privilege,
		const char *ipmi_username, const char *ipmi_password, unsigned char tls_connect,
		unsigned char tls_accept, const char *tls_issuer, const char *tls_subject, const char *tls_psk_identity,
		const char *tls_psk, const zbx_vector_uint64_t *del_hostgroupids)
{
	int			i, j, new_hosts = 0, new_host_inventories = 0, upd_hosts = 0, new_hostgroups = 0,
				new_hostmacros = 0, upd_hostmacros = 0, new_interfaces = 0, upd_interfaces = 0,
				new_snmp = 0, upd_snmp = 0, new_tags = 0, upd_tags = 0;
	zbx_uint64_t		hosttagid = 0;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro;
	zbx_lld_interface_t	*interface;
	zbx_vector_uint64_t	upd_manual_host_inventory_hostids, upd_auto_host_inventory_hostids,
				del_host_inventory_hostids, del_interfaceids,
				del_snmp_ids, del_hostmacroids, del_tagids;
	zbx_uint64_t		hostid = 0, hostgroupid = 0, hostmacroid = 0, interfaceid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *value_esc;
	size_t			sql1_alloc = 0, sql1_offset = 0,
				sql2_alloc = 0, sql2_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_hdiscovery, db_insert_hinventory, db_insert_hgroups,
				db_insert_hmacro, db_insert_interface, db_insert_idiscovery, db_insert_snmp,
				db_insert_tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&upd_manual_host_inventory_hostids);
	zbx_vector_uint64_create(&upd_auto_host_inventory_hostids);
	zbx_vector_uint64_create(&del_host_inventory_hostids);
	zbx_vector_uint64_create(&del_interfaceids);
	zbx_vector_uint64_create(&del_hostmacroids);
	zbx_vector_uint64_create(&del_snmp_ids);
	zbx_vector_uint64_create(&del_tagids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
		{
			new_hosts++;
			if (HOST_INVENTORY_DISABLED != host->inventory_mode)
				new_host_inventories++;
		}
		else
		{
			zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE, host->hostid,
					(NULL == host->host_orig) ? host->host : host->host_orig);

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
				upd_hosts++;

			if (host->inventory_mode_orig != host->inventory_mode)
			{
				zbx_audit_host_update_json_update_inventory_mode(host->hostid,
						(int)host->inventory_mode_orig, (int)host->inventory_mode);

				if (HOST_INVENTORY_DISABLED == host->inventory_mode)
					zbx_vector_uint64_append(&del_host_inventory_hostids, host->hostid);
				else if (HOST_INVENTORY_DISABLED == host->inventory_mode_orig)
					new_host_inventories++;
				else
				{
					switch (host->inventory_mode)
					{
						case HOST_INVENTORY_MANUAL:
							zbx_vector_uint64_append(&upd_manual_host_inventory_hostids,
									host->hostid);
							break;
						case HOST_INVENTORY_AUTOMATIC:
							zbx_vector_uint64_append(&upd_auto_host_inventory_hostids,
									host->hostid);
							break;
					}
				}
			}
		}

		new_hostgroups += host->new_groupids.values_num;

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == interface->interfaceid)
			{
				new_interfaces++;
			}
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE))
			{
				upd_interfaces++;
			}
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
			{
				zbx_vector_uint64_append(&del_interfaceids, interface->interfaceid);

				zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_interface(
						host->hostid, interface->interfaceid);
			}

			if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_REMOVE))
			{
				zbx_vector_uint64_append(&del_snmp_ids, interface->interfaceid);

				zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_interface(
						host->hostid, interface->interfaceid);
			}

			if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS))
			{
				if (0 == interface->interfaceid)
					interface->data.snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE;

				if (0 != (interface->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE))
					new_snmp++;
				else if (0 != (interface->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE))
					upd_snmp++;
			}
		}

		for (j = 0; j < host->new_hostmacros.values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)host->new_hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
			{
				new_hostmacros++;
			}
			else if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE))
			{
				upd_hostmacros++;
			}
			else if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_REMOVE))
			{
				zbx_vector_uint64_append(&del_hostmacroids, hostmacro->hostmacroid);

				zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_hostmacro(
						host->hostid, hostmacro->hostmacroid);
			}
		}

		for (j = 0; j < host->tags.values_num; j++)
		{
			if (0 == host->tags.values[j]->tagid)
			{
				new_tags++;
			}
			else if (0 != (host->tags.values[j]->flags & ZBX_FLAG_DB_TAG_UPDATE))
			{
				upd_tags++;
			}
			else if (0 != (host->tags.values[j]->flags & ZBX_FLAG_DB_TAG_REMOVE))
			{
				zbx_vector_uint64_append(&del_tagids, host->tags.values[j]->tagid);

				zbx_audit_host_prototype_create_entry(AUDIT_ACTION_UPDATE, host->hostid,
						host->host);
				zbx_audit_host_update_json_delete_tag(host->hostid, host->tags.values[j]->tagid);
			}
		}
	}

	if (0 == new_hosts && 0 == new_host_inventories && 0 == upd_hosts && 0 == upd_interfaces &&
			0 == upd_hostmacros && 0 == new_hostgroups && 0 == new_hostmacros && 0 == new_interfaces &&
			0 == del_hostgroupids->values_num && 0 == del_hostmacroids.values_num &&
			0 == upd_auto_host_inventory_hostids.values_num &&
			0 == upd_manual_host_inventory_hostids.values_num &&
			0 == del_host_inventory_hostids.values_num && 0 == del_interfaceids.values_num &&
			0 == new_snmp && 0 == upd_snmp && 0 == del_snmp_ids.values_num &&
			0 == new_tags && 0 == upd_tags && 0 == del_tagids.values_num)
	{
		goto out;
	}

	DBbegin();

	if (SUCCEED != DBlock_hostid(parent_hostid))
	{
		/* the host prototype was removed while processing lld rule */
		DBrollback();
		goto out;
	}

	if (0 != new_hosts)
	{
		hostid = DBget_maxid_num("hosts", new_hosts);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "host", "name", "proxy_hostid", "ipmi_authtype",
				"ipmi_privilege", "ipmi_username", "ipmi_password", "status", "flags", "tls_connect",
				"tls_accept", "tls_issuer", "tls_subject", "tls_psk_identity", "tls_psk",
				"custom_interfaces", NULL);

		zbx_db_insert_prepare(&db_insert_hdiscovery, "host_discovery", "hostid", "parent_hostid", "host", NULL);
	}

	if (0 != new_host_inventories)
	{
		zbx_db_insert_prepare(&db_insert_hinventory, "host_inventory", "hostid", "inventory_mode", NULL);
	}

	if (0 != upd_hosts || 0 != upd_interfaces || 0 != upd_snmp || 0 != upd_hostmacros || 0 != upd_tags)
	{
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
	}

	if (0 != new_hostgroups)
	{
		hostgroupid = DBget_maxid_num("hosts_groups", new_hostgroups);

		zbx_db_insert_prepare(&db_insert_hgroups, "hosts_groups", "hostgroupid", "hostid", "groupid", NULL);
	}

	if (0 != new_hostmacros)
	{
		hostmacroid = DBget_maxid_num("hostmacro", new_hostmacros);

		zbx_db_insert_prepare(&db_insert_hmacro, "hostmacro", "hostmacroid", "hostid", "macro", "value",
				"description", "type", NULL);
	}

	if (0 != new_interfaces)
	{
		interfaceid = DBget_maxid_num("interface", new_interfaces);

		zbx_db_insert_prepare(&db_insert_interface, "interface", "interfaceid", "hostid", "type", "main",
				"useip", "ip", "dns", "port", NULL);

		zbx_db_insert_prepare(&db_insert_idiscovery, "interface_discovery", "interfaceid",
				"parent_interfaceid", NULL);
	}

	if (0 != new_snmp)
	{
		zbx_db_insert_prepare(&db_insert_snmp, "interface_snmp", "interfaceid", "version", "bulk", "community",
				"securityname", "securitylevel", "authpassphrase", "privpassphrase", "authprotocol",
				"privprotocol", "contextname", NULL);
	}

	if (0 != new_tags)
	{
		hosttagid = DBget_maxid_num("host_tag", new_tags);

		zbx_db_insert_prepare(&db_insert_tag, "host_tag", "hosttagid", "hostid", "tag", "value", NULL);
	}

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
		{
			host->hostid = hostid++;

			zbx_db_insert_add_values(&db_insert, host->hostid, host->host, host->name, proxy_hostid,
					(int)ipmi_authtype, (int)ipmi_privilege, ipmi_username, ipmi_password,
					(int)host->status, (int)ZBX_FLAG_DISCOVERY_CREATED, (int)tls_connect,
					(int)tls_accept, tls_issuer, tls_subject, tls_psk_identity, tls_psk,
					(int)host->custom_interfaces);

			zbx_audit_host_create_entry(AUDIT_ACTION_ADD, host->hostid, host->host);

			zbx_db_insert_add_values(&db_insert_hdiscovery, host->hostid, parent_hostid, host_proto);

			if (HOST_INVENTORY_DISABLED != host->inventory_mode)
			{
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid,
						(int)host->inventory_mode);
			}

			zbx_audit_host_update_json_add_details(host->hostid, host->host, proxy_hostid,
					(int)ipmi_authtype, (int)ipmi_privilege, ipmi_username, ipmi_password,
					(int)host->status, (int)ZBX_FLAG_DISCOVERY_CREATED, (int)tls_connect,
					(int)tls_accept, tls_issuer, tls_subject, tls_psk_identity, tls_psk,
					host->custom_interfaces, (int)host->inventory_mode);
		}
		else
		{
			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hosts set ");

				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
				{
					value_esc = DBdyn_escape_string(host->host);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "host='%s'", value_esc);
					d = ",";

					zbx_audit_host_update_json_update_host(host->hostid,
							host->host_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
				{
					value_esc = DBdyn_escape_string(host->name);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sname='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_name(host->hostid,
							host->name_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_PROXY))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sproxy_hostid=%s", d, DBsql_id_ins(proxy_hostid));
					d = ",";

					zbx_audit_host_update_json_update_proxy_hostid(host->hostid,
							host->proxy_hostid_orig, proxy_hostid);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_authtype=%d", d, (int)ipmi_authtype);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_authtype(host->hostid,
							(int)host->ipmi_authtype_orig, (int)ipmi_authtype);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_privilege=%d", d, (int)ipmi_privilege);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_privilege(host->hostid,
							host->ipmi_privilege_orig, (int)ipmi_privilege);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER))
				{
					value_esc = DBdyn_escape_string(ipmi_username);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_username='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_username(host->hostid,
							host->ipmi_username_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS))
				{
					value_esc = DBdyn_escape_string(ipmi_password);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_password='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_password(host->hostid,
							host->ipmi_password_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_connect=%d", d, tls_connect);
					d = ",";

					zbx_audit_host_update_json_update_tls_connect(host->hostid,
							host->tls_connect_orig, (int)tls_connect);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_accept=%d", d, tls_accept);
					d = ",";

					zbx_audit_host_update_json_update_tls_accept(host->hostid,
							host->tls_accept_orig, (int)tls_accept);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER))
				{
					value_esc = DBdyn_escape_string(tls_issuer);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_issuer='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_tls_issuer(host->hostid,
							host->tls_issuer_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT))
				{
					value_esc = DBdyn_escape_string(tls_subject);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_subject='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_tls_subject(host->hostid,
							host->tls_subject_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY))
				{
					value_esc = DBdyn_escape_string(tls_psk_identity);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_psk_identity='%s'", d, value_esc);
					d = ",";
					zbx_free(value_esc);

					zbx_audit_host_update_json_update_tls_psk_identity(host->hostid,
							(0 == strcmp("", host->tls_psk_identity_orig) ?
							"" : ZBX_MACRO_SECRET_MASK),
							(0 == strcmp("", tls_psk_identity) ?
							"" : ZBX_MACRO_SECRET_MASK));
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK))
				{
					value_esc = DBdyn_escape_string(tls_psk);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_psk='%s'", d, value_esc);
					d = ",";
					zbx_free(value_esc);

					zbx_audit_host_update_json_update_tls_psk(host->hostid,
							(0 == strcmp("", host->tls_psk_orig) ?
							"" : ZBX_MACRO_SECRET_MASK),
							(0 == strcmp("", tls_psk) ? "" : ZBX_MACRO_SECRET_MASK));
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%scustom_interfaces=%d", d, (int)host->custom_interfaces);

					zbx_audit_host_update_json_update_custom_interfaces(host->hostid,
							host->custom_interfaces_orig, (int)host->custom_interfaces);
				}

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, " where hostid=" ZBX_FS_UI64 ";\n",
						host->hostid);
			}

			if (host->inventory_mode_orig != host->inventory_mode &&
					HOST_INVENTORY_DISABLED == host->inventory_mode_orig)
			{
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid, (int)host->inventory_mode);
			}

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			{
				value_esc = DBdyn_escape_string(host_proto);

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update host_discovery"
						" set host='%s'"
						" where hostid=" ZBX_FS_UI64 ";\n",
						value_esc, host->hostid);

				zbx_free(value_esc);
			}
		}

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == interface->interfaceid)
			{
				interface->interfaceid = interfaceid++;

				zbx_db_insert_add_values(&db_insert_interface, interface->interfaceid, host->hostid,
						(int)interface->type, (int)interface->main, (int)interface->useip,
						interface->ip, interface->dns, interface->port);

				zbx_audit_host_update_json_add_interfaces(host->hostid,
						interface->interfaceid, interface->main, interface->type,
						interface->useip, interface->ip, interface->dns, atoi(interface->port));

				zbx_db_insert_add_values(&db_insert_idiscovery, interface->interfaceid,
						interface->parent_interfaceid);
			}
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update interface set ");
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "type=%d",
							(int)interface->type);
					d = ",";
					zbx_audit_host_update_json_update_interface_type(host->hostid,
							interface->interfaceid, interface->type_orig, interface->type);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%smain=%d",
							d, (int)interface->main);
					d = ",";
					zbx_audit_host_update_json_update_interface_main(host->hostid,
							interface->interfaceid, interface->main_orig, interface->main);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%suseip=%d",
							d, (int)interface->useip);
					d = ",";
					zbx_audit_host_update_json_update_interface_useip(host->hostid,
							interface->interfaceid, (uint64_t)interface->useip_orig,
							interface->useip);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_IP))
				{
					value_esc = DBdyn_escape_string(interface->ip);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sip='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
					zbx_audit_host_update_json_update_interface_ip(host->hostid,
							interface->interfaceid, interface->ip_orig, interface->ip);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS))
				{
					value_esc = DBdyn_escape_string(interface->dns);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdns='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
					zbx_audit_host_update_json_update_interface_dns(host->hostid,
							interface->interfaceid, interface->dns_orig, interface->dns);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT))
				{
					value_esc = DBdyn_escape_string(interface->port);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sport='%s'",
							d, value_esc);
					zbx_audit_host_update_json_update_interface_port(host->hostid,
							interface->interfaceid, atoi(interface->port_orig),
							atoi(interface->port));
					zbx_free(value_esc);
				}
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where interfaceid=" ZBX_FS_UI64 ";\n", interface->interfaceid);
			}

			if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS))
			{
				if (0 != (interface->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE))
				{
					zbx_db_insert_add_values(&db_insert_snmp, interface->interfaceid,
							(int)interface->data.snmp->version,
							(int)interface->data.snmp->bulk,
							interface->data.snmp->community,
							interface->data.snmp->securityname,
							(int)interface->data.snmp->securitylevel,
							interface->data.snmp->authpassphrase,
							interface->data.snmp->privpassphrase,
							(int)interface->data.snmp->authprotocol,
							(int)interface->data.snmp->privprotocol,
							interface->data.snmp->contextname);
					zbx_audit_host_update_json_add_snmp_interface(host->hostid,
							interface->data.snmp->version, interface->data.snmp->bulk,
							interface->data.snmp->community,
							interface->data.snmp->securityname,
							interface->data.snmp->securitylevel,
							interface->data.snmp->authpassphrase,
							interface->data.snmp->privpassphrase,
							interface->data.snmp->authprotocol,
							interface->data.snmp->privprotocol,
							interface->data.snmp->contextname,
							interface->interfaceid);
				}
				else if (0 != (interface->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE))
				{
					lld_interface_snmp_prepare_sql(host->hostid, interface->interfaceid,
							interface->data.snmp, &sql1, &sql1_alloc, &sql1_offset);
				}
			}
		}

		for (j = 0; j < host->new_groupids.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_hgroups, hostgroupid, host->hostid,
					host->new_groupids.values[j]);
			zbx_audit_hostgroup_update_json_add_group(host->hostid, hostgroupid,
					host->new_groupids.values[j]);
			hostgroupid++;
		}

		for (j = 0; j < host->new_hostmacros.values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)host->new_hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
			{
				zbx_db_insert_add_values(&db_insert_hmacro, hostmacroid, host->hostid,
						hostmacro->macro, hostmacro->value, hostmacro->description,
						(int)hostmacro->type);
				zbx_audit_host_update_json_add_hostmacro(host->hostid,
						hostmacroid, hostmacro->macro, (ZBX_MACRO_VALUE_SECRET ==
						(int)hostmacro->type) ? ZBX_MACRO_SECRET_MASK : hostmacro->value,
						hostmacro->description, (int)hostmacro->type);
				hostmacroid++;
			}
			else if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hostmacro set ");

				zbx_audit_host_update_json_update_hostmacro_create_entry(host->hostid,
						hostmacro->hostmacroid);

				if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_VALUE))
				{
					value_esc = DBdyn_escape_string(hostmacro->value);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "value='%s'", value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_update_json_update_hostmacro_value(
							host->hostid, hostmacro->hostmacroid,
							((0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE) &&
							ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type_orig) ||
							(0 == (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE) &&
							ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type)) ?
							ZBX_MACRO_SECRET_MASK : hostmacro->value_orig,
							(ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type) ?
							ZBX_MACRO_SECRET_MASK : hostmacro->value);
				}
				if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_DESCRIPTION))
				{
					value_esc = DBdyn_escape_string(hostmacro->description);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdescription='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_update_json_update_hostmacro_description(
							host->hostid, hostmacro->hostmacroid,
							hostmacro->description_orig, hostmacro->description);
				}
				if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%stype=%d",
							d, hostmacro->type);

					zbx_audit_host_update_json_update_hostmacro_type(
							host->hostid, hostmacro->hostmacroid,
							hostmacro->type_orig, hostmacro->type);
				}
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where hostmacroid=" ZBX_FS_UI64 ";\n", hostmacro->hostmacroid);
			}
		}

		for (j = 0; j < host->tags.values_num; j++)
		{
			zbx_db_tag_t	*tag = host->tags.values[j];

			if (0 == tag->tagid)
			{
				zbx_db_insert_add_values(&db_insert_tag, hosttagid, host->hostid, tag->tag,
						tag->value);
				zbx_audit_host_update_json_add_tag(host->hostid, hosttagid,
						tag->tag, tag->value);
				hosttagid++;
			}
			else if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
			{
				char	delim = ' ';

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update host_tag set");

				zbx_audit_host_update_json_update_tag_create_entry(host->hostid, tag->tagid);

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
				{
					value_esc = DBdyn_escape_string(tag->tag);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%ctag='%s'", delim,
							value_esc);
					delim = ',';

					zbx_audit_host_update_json_update_tag_tag(host->hostid, tag->tagid,
							tag->tag_orig, value_esc);
					zbx_free(value_esc);
				}

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
				{
					value_esc = DBdyn_escape_string(tag->value);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%cvalue='%s'", delim,
							value_esc);

					zbx_audit_host_update_json_update_tag_value(host->hostid, tag->tagid,
							tag->value_orig, value_esc);
					zbx_free(value_esc);
				}

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where hosttagid=" ZBX_FS_UI64 ";\n", tag->tagid);
			}
		}
	}

	if (0 != new_hosts)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_hdiscovery);
		zbx_db_insert_clean(&db_insert_hdiscovery);
	}

	if (0 != new_host_inventories)
	{
		zbx_db_insert_execute(&db_insert_hinventory);
		zbx_db_insert_clean(&db_insert_hinventory);
	}

	if (0 != new_hostgroups)
	{
		zbx_db_insert_execute(&db_insert_hgroups);
		zbx_db_insert_clean(&db_insert_hgroups);
	}

	if (0 != new_hostmacros)
	{
		zbx_db_insert_execute(&db_insert_hmacro);
		zbx_db_insert_clean(&db_insert_hmacro);
	}

	if (0 != new_interfaces)
	{
		zbx_db_insert_execute(&db_insert_interface);
		zbx_db_insert_clean(&db_insert_interface);

		zbx_db_insert_execute(&db_insert_idiscovery);
		zbx_db_insert_clean(&db_insert_idiscovery);
	}

	if (0 != new_snmp)
	{
		zbx_db_insert_execute(&db_insert_snmp);
		zbx_db_insert_clean(&db_insert_snmp);
	}

	if (0 != new_tags)
	{
		zbx_db_insert_execute(&db_insert_tag);
		zbx_db_insert_clean(&db_insert_tag);
	}

	if (NULL != sql1)
	{
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);

		/* in ORACLE always present begin..end; */
		if (16 < sql1_offset)
			DBexecute("%s", sql1);

		zbx_free(sql1);
	}

	if (0 != del_hostgroupids->values_num || 0 != del_hostmacroids.values_num ||
			0 != upd_auto_host_inventory_hostids.values_num ||
			0 != upd_manual_host_inventory_hostids.values_num ||
			0 != del_host_inventory_hostids.values_num ||
			0 != del_interfaceids.values_num || 0 != del_snmp_ids.values_num || 0 != del_tagids.values_num)
	{
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);

		if (0 != del_hostgroupids->values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hosts_groups where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostgroupid",
					del_hostgroupids->values, del_hostgroupids->values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_hostmacroids.values_num)
		{
			zbx_vector_uint64_sort(&del_hostmacroids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hostmacro where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostmacroid",
					del_hostmacroids.values, del_hostmacroids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != upd_manual_host_inventory_hostids.values_num)
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
				"update host_inventory set inventory_mode=%d where", HOST_INVENTORY_MANUAL);
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					upd_manual_host_inventory_hostids.values,
					upd_manual_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != upd_auto_host_inventory_hostids.values_num)
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
				"update host_inventory set inventory_mode=%d where", HOST_INVENTORY_AUTOMATIC);
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					upd_auto_host_inventory_hostids.values,
					upd_auto_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_host_inventory_hostids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_inventory where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					del_host_inventory_hostids.values, del_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_snmp_ids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface_snmp where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
					del_snmp_ids.values, del_snmp_ids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_interfaceids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
					del_interfaceids.values, del_interfaceids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_tagids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_tag where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hosttagid", del_tagids.values,
					del_tagids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql2);
		zbx_free(sql2);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&del_tagids);
	zbx_vector_uint64_destroy(&del_snmp_ids);
	zbx_vector_uint64_destroy(&del_interfaceids);
	zbx_vector_uint64_destroy(&del_hostmacroids);
	zbx_vector_uint64_destroy(&del_host_inventory_hostids);
	zbx_vector_uint64_destroy(&upd_auto_host_inventory_hostids);
	zbx_vector_uint64_destroy(&upd_manual_host_inventory_hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_templates_link(const zbx_vector_ptr_t *hosts, char **error)
{
	int		i;
	zbx_lld_host_t	*host;
	char		*err = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->del_templateids.values_num)
		{
			if (SUCCEED != DBdelete_template_elements(host->hostid, host->host,
					&host->del_templateids, &err))
			{
				*error = zbx_strdcatf(*error, "Cannot unlink template: %s.\n", err);
				zbx_free(err);
			}
		}

		if (0 != host->lnk_templateids.values_num)
		{
			if (SUCCEED != DBcopy_template_elements(host->hostid, &host->lnk_templateids, &err))
			{
				*error = zbx_strdcatf(*error, "Cannot link template(s) %s.\n", err);
				zbx_free(err);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates host_discovery.lastcheck and host_discovery.ts_delete     *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_remove(const zbx_vector_ptr_t *hosts, int lifetime, int lastcheck)
{
	int			i;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	const zbx_lld_host_t	*host;
	zbx_vector_uint64_t	del_hostids, lc_hostids, ts_hostids;
	zbx_vector_str_t	del_hosts;
	zbx_hashset_t		ids_names;
	zbx_id_name_pair_t	local_id_name_pair;

	if (0 == hosts->values_num)
		return;

#define	IDS_NAMES_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&ids_names, IDS_NAMES_HASHSET_DEF_SIZE,
			zbx_ids_names_hash_func,
			zbx_ids_names_compare_func);
#undef IDS_NAMES_HASHSET_DEF_SIZE

	zbx_vector_uint64_create(&del_hostids);
	zbx_vector_str_create(&del_hosts);
	zbx_vector_uint64_create(&lc_hostids);
	zbx_vector_uint64_create(&ts_hostids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == host->hostid)
			continue;

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
		{
			int	ts_delete = lld_end_of_life(host->lastcheck, lifetime);

			if (lastcheck > ts_delete)
			{
				zbx_vector_uint64_append(&del_hostids, host->hostid);
				local_id_name_pair.id = host->hostid;
				local_id_name_pair.name = zbx_strdup(NULL, host->host);
				zbx_hashset_insert(&ids_names, &local_id_name_pair, sizeof(local_id_name_pair));
			}
			else if (host->ts_delete != ts_delete)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update host_discovery"
						" set ts_delete=%d"
						" where hostid=" ZBX_FS_UI64 ";\n",
						ts_delete, host->hostid);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_hostids, host->hostid);
			if (0 != host->ts_delete)
				zbx_vector_uint64_append(&ts_hostids, host->hostid);
		}
	}

	if (0 != lc_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				lc_hostids.values, lc_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ts_hostids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				ts_hostids.values, ts_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBbegin();

		DBexecute("%s", sql);

		DBcommit();
	}

	zbx_free(sql);

	if (0 != del_hostids.values_num)
	{
		zbx_vector_uint64_sort(&del_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < del_hostids.values_num; i++)
		{
			zbx_id_name_pair_t	*found, temp_t;
			temp_t.id = del_hostids.values[i];

			if (NULL != (found = (zbx_id_name_pair_t *)zbx_hashset_search(&ids_names, &temp_t)))
			{
				zbx_vector_str_append(&del_hosts, zbx_strdup(NULL, found->name));
				zbx_free(found->name);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}

		DBbegin();

		DBdelete_hosts(&del_hostids, &del_hosts);

		DBcommit();
	}

	zbx_vector_uint64_destroy(&ts_hostids);
	zbx_vector_uint64_destroy(&lc_hostids);
	zbx_vector_uint64_destroy(&del_hostids);
	zbx_vector_str_clear_ext(&del_hosts, zbx_str_free);
	zbx_vector_str_destroy(&del_hosts);
	zbx_hashset_destroy(&ids_names);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates group_discovery.lastcheck and group_discovery.ts_delete   *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_remove(const zbx_vector_ptr_t *groups, int lifetime, int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	const zbx_lld_group_t	*group;
	zbx_vector_uint64_t	del_groupids, lc_groupids, ts_groupids;
	int			i;

	if (0 == groups->values_num)
		return;

	zbx_vector_uint64_create(&del_groupids);
	zbx_vector_uint64_create(&lc_groupids);
	zbx_vector_uint64_create(&ts_groupids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == group->groupid)
			continue;

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
		{
			int	ts_delete = lld_end_of_life(group->lastcheck, lifetime);

			if (lastcheck > ts_delete)
			{
				zbx_vector_uint64_append(&del_groupids, group->groupid);
			}
			else if (group->ts_delete != ts_delete)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update group_discovery"
						" set ts_delete=%d"
						" where groupid=" ZBX_FS_UI64 ";\n",
						ts_delete, group->groupid);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_groupids, group->groupid);
			if (0 != group->ts_delete)
				zbx_vector_uint64_append(&ts_groupids, group->groupid);
		}
	}

	if (0 != lc_groupids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
				lc_groupids.values, lc_groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ts_groupids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
				ts_groupids.values, ts_groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBbegin();

		DBexecute("%s", sql);

		DBcommit();
	}

	zbx_free(sql);

	if (0 != del_groupids.values_num)
	{
		zbx_vector_uint64_sort(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBbegin();

		DBdelete_groups(&del_groupids);

		DBcommit();
	}

	zbx_vector_uint64_destroy(&ts_groupids);
	zbx_vector_uint64_destroy(&lc_groupids);
	zbx_vector_uint64_destroy(&del_groupids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves either the list of interfaces from the lld rule's host  *
 *          or the list of custom interfaces defined for the host prototype   *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_get(zbx_uint64_t id, zbx_vector_ptr_t *interfaces, unsigned char custom_interfaces)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_interface_t	*interface;

	if (ZBX_HOST_PROT_INTERFACES_INHERIT == custom_interfaces)
	{
		result = DBselect(
				"select hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,hi.dns,hi.port,s.version,s.bulk,"
				"s.community,s.securityname,s.securitylevel,s.authpassphrase,s.privpassphrase,"
				"s.authprotocol,s.privprotocol,s.contextname"
				" from interface hi"
				" inner join items i"
					" on hi.hostid=i.hostid "
				" left join interface_snmp s"
					" on hi.interfaceid=s.interfaceid"
				" where i.itemid=" ZBX_FS_UI64,
				id);
	}
	else
	{
		result = DBselect(
				"select hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,hi.dns,hi.port,s.version,s.bulk,"
				"s.community,s.securityname,s.securitylevel,s.authpassphrase,s.privpassphrase,"
				"s.authprotocol,s.privprotocol,s.contextname"
				" from interface hi"
				" left join interface_snmp s"
					" on hi.interfaceid=s.interfaceid"
				" where hi.hostid=" ZBX_FS_UI64,
				id);
	}

	while (NULL != (row = DBfetch(result)))
	{
		interface = (zbx_lld_interface_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

		ZBX_STR2UINT64(interface->interfaceid, row[0]);
		interface->type = (unsigned char)atoi(row[1]);
		interface->type_orig = interface->type;
		interface->main = (unsigned char)atoi(row[2]);
		interface->main_orig = interface->main;
		interface->useip = (unsigned char)atoi(row[3]);
		interface->useip_orig = interface->useip;
		interface->ip = zbx_strdup(NULL, row[4]);
		interface->dns = zbx_strdup(NULL, row[5]);
		interface->port = zbx_strdup(NULL, row[6]);
		interface->ip_orig = NULL;
		interface->dns_orig = NULL;
		interface->port_orig = NULL;
		interface->parent_interfaceid = 0;

		if (INTERFACE_TYPE_SNMP == interface->type)
		{
			zbx_lld_interface_snmp_t	*snmp;

			snmp = (zbx_lld_interface_snmp_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_snmp_t));
			ZBX_STR2UCHAR(snmp->version, row[7]);
			snmp->version_orig = snmp->version;
			ZBX_STR2UCHAR(snmp->bulk, row[8]);
			snmp->bulk_orig = snmp->bulk;
			snmp->community = zbx_strdup(NULL, row[9]);
			snmp->community_orig = NULL;
			snmp->securityname = zbx_strdup(NULL, row[10]);
			snmp->securityname_orig = NULL;
			ZBX_STR2UCHAR(snmp->securitylevel, row[11]);
			snmp->securitylevel_orig = snmp->securitylevel;
			snmp->authpassphrase = zbx_strdup(NULL, row[12]);
			snmp->authpassphrase_orig = NULL;
			snmp->privpassphrase = zbx_strdup(NULL, row[13]);
			snmp->privpassphrase_orig = NULL;
			ZBX_STR2UCHAR(snmp->authprotocol, row[14]);
			snmp->authprotocol_orig = snmp->authprotocol;
			ZBX_STR2UCHAR(snmp->privprotocol, row[15]);
			snmp->privprotocol_orig = snmp->privprotocol;
			snmp->contextname = zbx_strdup(NULL, row[16]);
			snmp->contextname_orig = NULL;
			snmp->flags = 0;
			interface->data.snmp = snmp;
			interface->flags = ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS;
		}
		else
		{
			interface->data.snmp = NULL;
			interface->flags = 0x00;
		}

		zbx_vector_ptr_append(interfaces, interface);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(interfaces, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

static void	lld_interface_make(zbx_vector_ptr_t *interfaces, zbx_uint64_t parent_interfaceid,
		zbx_uint64_t interfaceid, unsigned char type, unsigned char main, unsigned char useip, const char *ip,
		const char *dns, const char *port, unsigned char snmp_type, unsigned char bulk, const char *community,
		const char *securityname, unsigned char securitylevel, const char *authpassphrase,
		const char *privpassphrase, unsigned char authprotocol, unsigned char privprotocol,
		const char *contextname)
{
	zbx_lld_interface_t	*interface = NULL;
	int			i, interface_found = 0;

	for (i = 0; i < interfaces->values_num; i++)
	{
		interface = (zbx_lld_interface_t *)interfaces->values[i];

		if (0 != interface->interfaceid)
			continue;

		if (interface->parent_interfaceid == parent_interfaceid)
		{
			interface_found = 1;
			break;
		}
	}

	if (0 == interface_found)
	{
		/* interface should be deleted */
		interface = (zbx_lld_interface_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

		interface->interfaceid = interfaceid;
		interface->parent_interfaceid = 0;
		interface->type = type;
		interface->main = main;
		interface->useip = 0;
		interface->ip = NULL;
		interface->dns = NULL;
		interface->port = NULL;
		interface->ip_orig = NULL;
		interface->dns_orig = NULL;
		interface->port_orig = NULL;
		interface->data.snmp = NULL;
		interface->main_orig = main;
		interface->type_orig = type;
		interface->useip_orig = 0;
		interface->flags = ZBX_FLAG_LLD_INTERFACE_REMOVE;

		zbx_vector_ptr_append(interfaces, interface);
	}
	else
	{
		/* interface already has been added */
		if (interface->type != type)
		{
			interface->type_orig = type;
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE;

			if (INTERFACE_TYPE_SNMP == type)
				interface->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_REMOVE;

			if (INTERFACE_TYPE_SNMP == interface->type)
				interface->data.snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE;
		}
		if (interface->main != main)
		{
			interface->main_orig = main;
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
		}
		if (interface->useip != useip)
		{
			interface->useip_orig = useip;
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP;
		}
		if (0 != strcmp(interface->ip, ip))
		{
			interface->ip_orig = zbx_strdup(NULL, ip);
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_IP;
		}
		if (0 != strcmp(interface->dns, dns))
		{
			interface->dns_orig = zbx_strdup(NULL, dns);
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS;
		}
		if (0 != strcmp(interface->port, port))
		{
			interface->port_orig = zbx_strdup(NULL, port);
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT;
		}

		if (INTERFACE_TYPE_SNMP == interface->type && interface->type == type)
		{
			zbx_lld_interface_snmp_t *snmp = interface->data.snmp;

			if (snmp->version != snmp_type)
			{
				snmp->version_orig = snmp_type;
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE;
			}
			if (snmp->bulk != bulk)
			{
				snmp->bulk_orig = bulk;
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK;
			}
			if (0 != strcmp(snmp->community, community))
			{
				snmp->community_orig = zbx_strdup(NULL, community);
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY;
			}
			if (0 != strcmp(snmp->securityname, securityname))
			{
				snmp->securityname_orig = zbx_strdup(NULL, securityname);
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME;
			}
			if (snmp->securitylevel != securitylevel)
			{
				snmp->securitylevel_orig = securitylevel;
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL;
			}
			if (0 != strcmp(snmp->authpassphrase, authpassphrase))
			{
				snmp->authpassphrase_orig = zbx_strdup(NULL, authpassphrase);
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS;
			}
			if (0 != strcmp(snmp->privpassphrase, privpassphrase))
			{
				snmp->privpassphrase_orig = zbx_strdup(NULL, privpassphrase);
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS;
			}
			if (snmp->authprotocol != authprotocol)
			{
				snmp->authprotocol_orig = authprotocol;
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL;
			}
			if (snmp->privprotocol != privprotocol)
			{
				snmp->privprotocol_orig = privprotocol;
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL;
			}
			if (0 != strcmp(snmp->contextname, contextname))
			{
				snmp->contextname_orig = zbx_strdup(NULL, contextname);
				snmp->flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT;
			}
		}
	}

	interface->interfaceid = interfaceid;
}

/******************************************************************************
 *                                                                            *
 * Parameters: interfaces - [IN] sorted list of interfaces which              *
 *                               should be present on the each                *
 *                               discovered host                              *
 *             hosts      - [IN/OUT] sorted list of hosts                     *
 *             lld_macros - [IN] list of LLD macros                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_make(const zbx_vector_ptr_t *interfaces, zbx_vector_ptr_t *hosts,
		const zbx_vector_ptr_t *lld_macros)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		parent_interfaceid, hostid, interfaceid;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*new_interface, *interface;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_ptr_reserve(&host->interfaces, (size_t)interfaces->values_num);

		for (j = 0; j < interfaces->values_num; j++)
		{
			interface = (zbx_lld_interface_t *)interfaces->values[j];

			new_interface = (zbx_lld_interface_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

			new_interface->interfaceid = 0;
			new_interface->parent_interfaceid = interface->interfaceid;
			new_interface->type = interface->type;
			new_interface->type_orig = interface->type_orig;
			new_interface->main = interface->main;
			new_interface->main_orig = interface->main_orig;
			new_interface->useip = interface->useip;
			new_interface->useip_orig = interface->useip_orig;
			new_interface->ip = zbx_strdup(NULL, interface->ip);
			new_interface->ip_orig = NULL;
			new_interface->dns = zbx_strdup(NULL, interface->dns);
			new_interface->dns_orig = NULL;
			new_interface->port = zbx_strdup(NULL, interface->port);
			new_interface->port_orig = NULL;

			substitute_lld_macros(&new_interface->ip, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			substitute_lld_macros(&new_interface->dns, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			substitute_lld_macros(&new_interface->port, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);

			if (INTERFACE_TYPE_SNMP == interface->type)
			{
				zbx_lld_interface_snmp_t *snmp;

				snmp = (zbx_lld_interface_snmp_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_snmp_t));
				snmp->version = interface->data.snmp->version;
				snmp->bulk = interface->data.snmp->bulk;
				snmp->community = zbx_strdup(NULL, interface->data.snmp->community);
				snmp->securityname = zbx_strdup(NULL, interface->data.snmp->securityname);
				snmp->securitylevel = interface->data.snmp->securitylevel;
				snmp->authpassphrase = zbx_strdup(NULL, interface->data.snmp->authpassphrase);
				snmp->privpassphrase = zbx_strdup(NULL, interface->data.snmp->privpassphrase);
				snmp->authprotocol = interface->data.snmp->authprotocol;
				snmp->privprotocol = interface->data.snmp->privprotocol;
				snmp->contextname = zbx_strdup(NULL, interface->data.snmp->contextname);
				snmp->community_orig = NULL;
				snmp->securityname_orig = NULL;
				snmp->authpassphrase_orig = NULL;
				snmp->privpassphrase_orig = NULL;
				snmp->contextname_orig = NULL;
				snmp->securitylevel_orig = snmp->securitylevel;
				snmp->authprotocol_orig = snmp->authprotocol;
				snmp->privprotocol_orig = snmp->privprotocol;
				snmp->version_orig = snmp->version;
				snmp->bulk_orig = snmp->bulk;
				snmp->flags = 0x00;
				new_interface->flags = ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS;
				new_interface->data.snmp = snmp;

				substitute_lld_macros(&snmp->community, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				substitute_lld_macros(&snmp->securityname, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				substitute_lld_macros(&snmp->authpassphrase, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				substitute_lld_macros(&snmp->privpassphrase, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				substitute_lld_macros(&snmp->contextname, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
			}
			else
			{
				new_interface->flags = 0x00;
				new_interface->data.snmp = NULL;
			}

			zbx_vector_ptr_append(&host->interfaces, new_interface);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hi.hostid,id.parent_interfaceid,hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,"
					"hi.dns,hi.port,s.version,s.bulk,s.community,s.securityname,s.securitylevel,"
					"s.authpassphrase,s.privpassphrase,s.authprotocol,s.privprotocol,s.contextname"
				" from interface hi"
					" left join interface_discovery id"
						" on hi.interfaceid=id.interfaceid"
					" left join interface_snmp s"
						" on hi.interfaceid=s.interfaceid"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			unsigned char	interface_type;

			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_DBROW2UINT64(parent_interfaceid, row[1]);
			ZBX_DBROW2UINT64(interfaceid, row[2]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];
			ZBX_STR2UCHAR(interface_type, row[3]);

			if (INTERFACE_TYPE_SNMP == interface_type)
			{
				lld_interface_make(&host->interfaces, parent_interfaceid, interfaceid,
						interface_type, (unsigned char)atoi(row[4]),
						(unsigned char)atoi(row[5]), row[6], row[7], row[8],
						(unsigned char)atoi(row[9]), (unsigned char)atoi(row[10]), row[11],
						row[12], (unsigned char)atoi(row[13]), row[14], row[15],
						(unsigned char)atoi(row[16]), (unsigned char)atoi(row[17]), row[18]);
			}
			else
			{
				lld_interface_make(&host->interfaces, parent_interfaceid, interfaceid,
						interface_type, (unsigned char)atoi(row[4]),
						(unsigned char)atoi(row[5]), row[6], row[7], row[8],
						0, 0, NULL, NULL, 0, NULL, NULL,0, 0, NULL);
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCEED if interface with same type exists in the list of    *
 *               interfaces; FAIL - otherwise                                 *
 *                                                                            *
 * Comments: interfaces with ZBX_FLAG_LLD_INTERFACE_REMOVE flag are ignored   *
 *           auxiliary function for lld_interfaces_validate()                 *
 *                                                                            *
 ******************************************************************************/
static int	another_main_interface_exists(const zbx_vector_ptr_t *interfaces, const zbx_lld_interface_t *interface)
{
	const zbx_lld_interface_t	*interface_b;
	int				i;

	for (i = 0; i < interfaces->values_num; i++)
	{
		interface_b = (zbx_lld_interface_t *)interfaces->values[i];

		if (interface_b == interface)
			continue;

		if (0 != (interface_b->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
			continue;

		if (interface_b->type != interface->type)
			continue;

		if (1 == interface_b->main)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Parameters: hosts - [IN/OUT] list of hosts                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_validate(zbx_vector_ptr_t *hosts, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	interfaceids;
	zbx_uint64_t		interfaceid;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*interface;
	unsigned char		type;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* validate changed types */

	zbx_vector_uint64_create(&interfaceids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
				continue;

			zbx_vector_uint64_append(&interfaceids, interface->interfaceid);
		}
	}

	if (0 != interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select interfaceid,type from items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid,type");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			type = get_interface_type_by_item_type((unsigned char)atoi(row[1]));

			if (type != INTERFACE_TYPE_ANY && type != INTERFACE_TYPE_UNKNOWN && type != INTERFACE_TYPE_OPT)
			{
				ZBX_STR2UINT64(interfaceid, row[0]);

				for (i = 0; i < hosts->values_num; i++)
				{
					host = (zbx_lld_host_t *)hosts->values[i];

					for (j = 0; j < host->interfaces.values_num; j++)
					{
						interface = (zbx_lld_interface_t *)host->interfaces.values[j];

						if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
							continue;

						if (interface->interfaceid != interfaceid)
							continue;

						*error = zbx_strdcatf(*error,
								"Cannot update \"%s\" interface on host \"%s\":"
								" the interface is used by items.\n",
								zbx_interface_type_string(interface->type_orig),
								host->host);

						/* return an original interface type and drop the corresponding flag */
						interface->type = interface->type_orig;
						interface->flags &= ~ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE;
					}
				}
			}
		}
		DBfree_result(result);
	}

	/* validate interfaces which should be deleted */

	zbx_vector_uint64_clear(&interfaceids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
				continue;

			zbx_vector_uint64_append(&interfaceids, interface->interfaceid);
		}
	}

	if (0 != interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select interfaceid from items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(interfaceid, row[0]);

			for (i = 0; i < hosts->values_num; i++)
			{
				host = (zbx_lld_host_t *)hosts->values[i];

				for (j = 0; j < host->interfaces.values_num; j++)
				{
					interface = (zbx_lld_interface_t *)host->interfaces.values[j];

					if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
						continue;

					if (interface->interfaceid != interfaceid)
						continue;

					*error = zbx_strdcatf(*error, "Cannot delete \"%s\" interface on host \"%s\":"
							" the interface is used by items.\n",
							zbx_interface_type_string(interface->type), host->host);

					/* drop the corresponding flag */
					interface->flags &= ~ZBX_FLAG_LLD_INTERFACE_REMOVE;

					if (SUCCEED == another_main_interface_exists(&host->interfaces, interface))
					{
						if (1 == interface->main)
						{
							/* drop main flag */
							interface->main_orig = interface->main;
							interface->main = 0;
							interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
						}
					}
					else if (1 != interface->main)
					{
						/* set main flag */
						interface->main_orig = interface->main;
						interface->main = 1;
						interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
					}
				}
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&interfaceids);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add or update low-level discovered hosts                          *
 *                                                                            *
 ******************************************************************************/
void	lld_update_hosts(zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	hosts, group_prototypes, groups, interfaces, masterhostmacros, hostmacros;
	zbx_vector_db_tag_ptr_t	tags;
	zbx_vector_uint64_t	groupids;		/* list of host groups which should be added */
	zbx_vector_uint64_t	del_hostgroupids;	/* list of host groups which should be deleted */
	zbx_uint64_t		proxy_hostid;
	char			*ipmi_username = NULL, *ipmi_password, *tls_issuer, *tls_subject, *tls_psk_identity,
				*tls_psk;
	signed char		ipmi_authtype, inventory_mode_proto;
	unsigned char		ipmi_privilege, tls_connect, tls_accept;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select h.proxy_hostid,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password,"
				"h.tls_connect,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk"
			" from hosts h,items i"
			" where h.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(proxy_hostid, row[0]);
		ipmi_authtype = (signed char)atoi(row[1]);
		ZBX_STR2UCHAR(ipmi_privilege, row[2]);
		ipmi_username = zbx_strdup(NULL, row[3]);
		ipmi_password = zbx_strdup(NULL, row[4]);

		ZBX_STR2UCHAR(tls_connect, row[5]);
		ZBX_STR2UCHAR(tls_accept, row[6]);
		tls_issuer = zbx_strdup(NULL, row[7]);
		tls_subject = zbx_strdup(NULL, row[8]);
		tls_psk_identity = zbx_strdup(NULL, row[9]);
		tls_psk = zbx_strdup(NULL, row[10]);
	}
	DBfree_result(result);

	if (NULL == row)
	{
		*error = zbx_strdcatf(*error, "Cannot process host prototypes: a parent host not found.\n");
		return;
	}

	zbx_vector_ptr_create(&hosts);
	zbx_vector_uint64_create(&groupids);
	zbx_vector_ptr_create(&group_prototypes);
	zbx_vector_ptr_create(&groups);
	zbx_vector_uint64_create(&del_hostgroupids);
	zbx_vector_ptr_create(&interfaces);
	zbx_vector_ptr_create(&masterhostmacros);
	zbx_vector_ptr_create(&hostmacros);
	zbx_vector_db_tag_ptr_create(&tags);

	lld_interfaces_get(lld_ruleid, &interfaces, 0);
	lld_masterhostmacros_get(lld_ruleid, &masterhostmacros);

	result = DBselect(
			"select h.hostid,h.host,h.name,h.status,h.discover,hi.inventory_mode,h.custom_interfaces"
			" from hosts h,host_discovery hd"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where h.hostid=hd.hostid"
				" and hd.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		parent_hostid;
		const char		*host_proto, *name_proto;
		zbx_lld_host_t		*host;
		unsigned char		status, discover, use_custom_interfaces;
		int			i;
		zbx_vector_ptr_t	interfaces_custom;

		ZBX_STR2UINT64(parent_hostid, row[0]);
		host_proto = row[1];
		name_proto = row[2];
		ZBX_STR2UCHAR(status, row[3]);
		ZBX_STR2UCHAR(discover, row[4]);
		ZBX_STR2UCHAR(use_custom_interfaces, row[6]);

		if (SUCCEED == DBis_null(row[5]))
			inventory_mode_proto = HOST_INVENTORY_DISABLED;
		else
			inventory_mode_proto = (signed char)atoi(row[5]);

		lld_hosts_get(parent_hostid, &hosts, proxy_hostid, ipmi_authtype, ipmi_privilege, ipmi_username,
				ipmi_password, tls_connect, tls_accept, tls_issuer, tls_subject,
				tls_psk_identity, tls_psk);

		if (0 != hosts.values_num)
			lld_hosts_get_tags(&hosts);
		lld_proto_tags_get(parent_hostid, &tags);

		lld_simple_groups_get(parent_hostid, &groupids);
		lld_group_prototypes_get(parent_hostid, &group_prototypes);
		lld_groups_get(parent_hostid, &groups);

		lld_hostmacros_get(parent_hostid, &masterhostmacros, &hostmacros);

		for (i = 0; i < lld_rows->values_num; i++)
		{
			const zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

			if (NULL == (host = lld_host_make(&hosts, host_proto, name_proto, inventory_mode_proto,
					status, discover, &tags, lld_row, lld_macro_paths, error, use_custom_interfaces)))
			{
				continue;
			}

			lld_groups_make(host, &groups, &group_prototypes, &lld_row->jp_row, lld_macro_paths);
		}

		zbx_vector_ptr_sort(&hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		lld_groups_validate(&groups, error);
		lld_hosts_validate(&hosts, error);

		if (ZBX_HOST_PROT_INTERFACES_CUSTOM == use_custom_interfaces)
		{
			zbx_vector_ptr_create(&interfaces_custom);
			lld_interfaces_get(parent_hostid, &interfaces_custom, 1);
			lld_interfaces_make(&interfaces_custom, &hosts, lld_macro_paths);
		}
		else
			lld_interfaces_make(&interfaces, &hosts, lld_macro_paths);

		lld_interfaces_validate(&hosts, error);

		lld_hostgroups_make(&groupids, &hosts, &groups, &del_hostgroupids);
		lld_templates_make(parent_hostid, &hosts);

		lld_hostmacros_make(&hostmacros, &hosts, lld_macro_paths);

		lld_groups_save(&groups, &group_prototypes);
		lld_hosts_save(parent_hostid, &hosts, host_proto, proxy_hostid, ipmi_authtype, ipmi_privilege,
				ipmi_username, ipmi_password, tls_connect, tls_accept,
				tls_issuer, tls_subject, tls_psk_identity, tls_psk, &del_hostgroupids);

		/* linking of the templates */
		lld_templates_link(&hosts, error);

		lld_hosts_remove(&hosts, lifetime, lastcheck);
		lld_groups_remove(&groups, lifetime, lastcheck);

		zbx_vector_db_tag_ptr_clear_ext(&tags, zbx_db_tag_free);
		zbx_vector_ptr_clear_ext(&hostmacros, (zbx_clean_func_t)lld_hostmacro_free);
		zbx_vector_ptr_clear_ext(&groups, (zbx_clean_func_t)lld_group_free);
		zbx_vector_ptr_clear_ext(&group_prototypes, (zbx_clean_func_t)lld_group_prototype_free);
		zbx_vector_ptr_clear_ext(&hosts, (zbx_clean_func_t)lld_host_free);

		zbx_vector_uint64_clear(&groupids);
		zbx_vector_uint64_clear(&del_hostgroupids);

		if (ZBX_HOST_PROT_INTERFACES_CUSTOM == use_custom_interfaces)
		{
			zbx_vector_ptr_clear_ext(&interfaces_custom, (zbx_clean_func_t)lld_interface_free);
			zbx_vector_ptr_destroy(&interfaces_custom);
		}
	}
	DBfree_result(result);

	zbx_vector_ptr_clear_ext(&masterhostmacros, (zbx_clean_func_t)lld_hostmacro_free);
	zbx_vector_ptr_clear_ext(&interfaces, (zbx_clean_func_t)lld_interface_free);

	zbx_vector_db_tag_ptr_clear_ext(&tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&tags);
	zbx_vector_ptr_destroy(&hostmacros);
	zbx_vector_ptr_destroy(&masterhostmacros);
	zbx_vector_ptr_destroy(&interfaces);
	zbx_vector_uint64_destroy(&del_hostgroupids);
	zbx_vector_ptr_destroy(&groups);
	zbx_vector_ptr_destroy(&group_prototypes);
	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_ptr_destroy(&hosts);

	zbx_free(tls_psk);
	zbx_free(tls_psk_identity);
	zbx_free(tls_subject);
	zbx_free(tls_issuer);
	zbx_free(ipmi_password);
	zbx_free(ipmi_username);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
