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

#include "lld.h"

#include "../db_lengths_constants.h"

#include "zbxexpression.h"
#include "zbx_availability_constants.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_host.h"
#include "zbxnum.h"
#include "zbxdbwrap.h"
#include "zbx_host_constants.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"
#include "zbxhash.h"
#include "zbxinterface.h"
#include "../server_constants.h"

/* host macro discovery state */
#define ZBX_USERMACRO_MANUAL	0
#define ZBX_USERMACRO_AUTOMATIC	1

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
	unsigned char	automatic;
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

ZBX_PTR_VECTOR_DECL(lld_hostmacro_ptr, zbx_lld_hostmacro_t*)
ZBX_PTR_VECTOR_IMPL(lld_hostmacro_ptr, zbx_lld_hostmacro_t*)

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

ZBX_PTR_VECTOR_DECL(lld_interface_ptr, zbx_lld_interface_t *)
ZBX_PTR_VECTOR_IMPL(lld_interface_ptr, zbx_lld_interface_t *)

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
	zbx_uint64_t		hgsetid;
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	hgroupids;
#define ZBX_LLD_HGSET_OPT_REUSE		0
#define ZBX_LLD_HGSET_OPT_DELETE	1
#define ZBX_LLD_HGSET_OPT_INSERT	2
	int			opt;
} zbx_lld_hgset_t;

ZBX_PTR_VECTOR_DECL(lld_hgset_ptr, zbx_lld_hgset_t*)
ZBX_PTR_VECTOR_IMPL(lld_hgset_ptr, zbx_lld_hgset_t*)

static int	lld_hgset_compare(const void *d1, const void *d2)
{
	const zbx_lld_hgset_t	*h1 = *((const zbx_lld_hgset_t * const *)d1);
	const zbx_lld_hgset_t	*h2 = *((const zbx_lld_hgset_t * const *)d2);

	return strcmp(h1->hash_str, h2->hash_str);
}

static void	lld_hgset_free(zbx_lld_hgset_t *hgset)
{
	zbx_vector_uint64_destroy(&hgset->hgroupids);
	zbx_free(hgset);
}

static int	lld_hgset_hash_search(const void *d1, const void *d2)
{
	const zbx_lld_hgset_t	*h1 = *((const zbx_lld_hgset_t * const *)d1);
	const char		*h2 = *((const char * const *)d2);

	return strcmp(h1->hash_str, h2);
}

typedef struct
{
	zbx_uint64_t			hostid;
	zbx_vector_uint64_t		old_groupids;		/* current host groups */
	zbx_vector_uint64_t		new_groupids;		/* host groups which should be added */
	zbx_vector_uint64_t		groupids;		/* resulting host groups */
	zbx_vector_uint64_t		lnk_templateids;	/* templates which should be linked */
	zbx_vector_uint64_t		del_templateids;	/* templates which should be unlinked */
	zbx_vector_lld_hostmacro_ptr_t	new_hostmacros;	/* host macros which should be added, deleted or updated */
	zbx_vector_lld_interface_ptr_t	interfaces;
	zbx_vector_db_tag_ptr_t		tags;
	char				*host_proto;
	char				*host;
	char				*host_orig;
	char				*name;
	char				*name_orig;
	int				lastcheck;
	unsigned char			discovery_status;
	int				ts_delete;
	int				ts_disable;
	unsigned char			disable_source;

#define ZBX_FLAG_LLD_HOST_DISCOVERED			__UINT64_C(0x00000001)	/* hosts which should be updated or */
										/* added */
#define ZBX_FLAG_LLD_HOST_UPDATE_HOST			__UINT64_C(0x00000002)	/* hosts.host and */
										/* host_discovery.host fields should */
										/* be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_NAME			__UINT64_C(0x00000004)	/* hosts.name field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_PROXY			__UINT64_C(0x00000008)	/* hosts.proxyid field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH		__UINT64_C(0x00000010)	/* hosts.ipmi_authtype field should */
										/* be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV		__UINT64_C(0x00000020)	/* hosts.ipmi_privilege field should */
										/* be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER		__UINT64_C(0x00000040)	/* hosts.ipmi_username field should */
										/* be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS		__UINT64_C(0x00000080)	/* hosts.ipmi_password field should */
										/* be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT		__UINT64_C(0x00000100)	/* hosts.tls_connect field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT		__UINT64_C(0x00000200)	/* hosts.tls_accept field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER		__UINT64_C(0x00000400)	/* hosts.tls_issuer field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT		__UINT64_C(0x00000800)	/* hosts.tls_subject field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY	__UINT64_C(0x00001000)	/* hosts.tls_psk_identity field */
										/* should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK		__UINT64_C(0x00002000)	/* hosts.tls_psk field should be */
										/* updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES	__UINT64_C(0x00004000)	/* hosts.custom_interfaces field */
										/* should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_PROXY_GROUP		__UINT64_C(0x00008000)	/* hosts.proxy_groupid field */
										/* should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_MONITORED_BY		__UINT64_C(0x00010000)	/* hosts.proxy_groupid field */
										/* should be updated */

#define ZBX_FLAG_LLD_HOST_UPDATE									\
		(ZBX_FLAG_LLD_HOST_UPDATE_HOST | ZBX_FLAG_LLD_HOST_UPDATE_NAME |			\
		ZBX_FLAG_LLD_HOST_UPDATE_PROXY | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH |			\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER |		\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS | ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT |		\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT | ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER |		\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT | ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY |	\
		ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK | ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES |		\
		ZBX_FLAG_LLD_HOST_UPDATE_PROXY_GROUP | ZBX_FLAG_LLD_HOST_UPDATE_MONITORED_BY)
	zbx_uint64_t			flags;
	const struct zbx_json_parse	*jp_row;
	signed char			inventory_mode;
	signed char			inventory_mode_orig;
	unsigned char			status;
	unsigned char			custom_interfaces;
	unsigned char			custom_interfaces_orig;
	zbx_uint64_t			proxyid_orig;
	zbx_uint64_t			proxy_groupid_orig;
	signed char			ipmi_authtype_orig;
	unsigned char			ipmi_privilege_orig;
	unsigned char			monitored_by_orig;
	char				*ipmi_username_orig;
	char				*ipmi_password_orig;
	char				*tls_issuer_orig;
	char				*tls_subject_orig;
	char				*tls_psk_identity_orig;
	char				*tls_psk_orig;
	char				tls_connect_orig;
	char				tls_accept_orig;
	zbx_uint64_t			hgsetid_orig;
	zbx_lld_hgset_t			*hgset;

#define ZBX_LLD_HOST_HGSET_ACTION_IDLE		0
#define ZBX_LLD_HOST_HGSET_ACTION_ADD		1
#define ZBX_LLD_HOST_HGSET_ACTION_UPDATE	2
	unsigned char			hgset_action;
}
zbx_lld_host_t;

ZBX_PTR_VECTOR_DECL(lld_host_ptr, zbx_lld_host_t*)
ZBX_PTR_VECTOR_IMPL(lld_host_ptr, zbx_lld_host_t*)

static int	lld_host_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_host_t	*host_1 = *(const zbx_lld_host_t **)d1;
	const zbx_lld_host_t	*host_2 = *(const zbx_lld_host_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(host_1->hostid, host_2->hostid);

	return 0;
}

static void	lld_host_free(zbx_lld_host_t *host)
{
	zbx_vector_uint64_destroy(&host->new_groupids);
	zbx_vector_uint64_destroy(&host->old_groupids);
	zbx_vector_uint64_destroy(&host->groupids);
	zbx_vector_uint64_destroy(&host->lnk_templateids);
	zbx_vector_uint64_destroy(&host->del_templateids);
	zbx_vector_lld_hostmacro_ptr_clear_ext(&host->new_hostmacros, lld_hostmacro_free);
	zbx_vector_lld_hostmacro_ptr_destroy(&host->new_hostmacros);
	zbx_vector_db_tag_ptr_clear_ext(&host->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host->tags);
	zbx_vector_lld_interface_ptr_clear_ext(&host->interfaces, lld_interface_free);
	zbx_vector_lld_interface_ptr_destroy(&host->interfaces);
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

ZBX_PTR_VECTOR_DECL(lld_group_prototype_ptr, zbx_lld_group_prototype_t*)
ZBX_PTR_VECTOR_IMPL(lld_group_prototype_ptr, zbx_lld_group_prototype_t*)

static int	lld_group_prototype_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_group_prototype_t	*group_prototype_1 = *(const zbx_lld_group_prototype_t **)d1;
	const zbx_lld_group_prototype_t	*group_prototype_2 = *(const zbx_lld_group_prototype_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(group_prototype_1->group_prototypeid, group_prototype_2->group_prototypeid);

	return 0;
}

static void	lld_group_prototype_free(zbx_lld_group_prototype_t *group_prototype)
{
	zbx_free(group_prototype->name);
	zbx_free(group_prototype);
}

typedef struct
{
	zbx_uint64_t			groupdiscoveryid;
	zbx_uint64_t			parent_group_prototypeid;
	char				*name;
	unsigned char			discovery_status;
	int				ts_delete;
	int				lastcheck;
	const struct zbx_json_parse	*lld_row;

#define ZBX_FLAG_LLD_GROUP_DISCOVERY_DISCOVERED		__UINT64_C(0x00000001)
#define ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_NAME	__UINT64_C(0x00000002)
#define ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_GROUPID	__UINT64_C(0x00000004)
#define ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE		(ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_NAME |	\
							ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_GROUPID)
	zbx_uint64_t	flags;
}
zbx_lld_group_discovery_t;

static void	lld_group_discovery_free(zbx_lld_group_discovery_t *group_discovery)
{
	zbx_free(group_discovery->name);
	zbx_free(group_discovery);
}

ZBX_PTR_VECTOR_DECL(lld_group_discovery_ptr, zbx_lld_group_discovery_t *)
ZBX_PTR_VECTOR_IMPL(lld_group_discovery_ptr, zbx_lld_group_discovery_t *)

typedef struct
{
	zbx_uint64_t				groupid;
	zbx_vector_lld_group_discovery_ptr_t	discovery;
	zbx_vector_lld_host_ptr_t		hosts;
	char					*name;
	char					*name_orig;
	char					*name_inherit;	/* name of a group to inherit rights from */
#define ZBX_FLAG_LLD_GROUP_DISCOVERED		__UINT64_C(0x00000001)	/* groups which should be updated or added */
#define ZBX_FLAG_LLD_GROUP_UPDATE_NAME		__UINT64_C(0x00000002)	/* groups.name field should be updated */
#define ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE		__UINT64_C(0x80000000)	/* group is discovered by other prototypes */
									/* and cannot be changed                   */
#define ZBX_FLAG_LLD_GROUP_UPDATE		ZBX_FLAG_LLD_GROUP_UPDATE_NAME
	zbx_uint64_t				flags;
}
zbx_lld_group_t;

ZBX_PTR_VECTOR_DECL(lld_group_ptr, zbx_lld_group_t *)
ZBX_PTR_VECTOR_IMPL(lld_group_ptr, zbx_lld_group_t *)

static void	lld_group_free(zbx_lld_group_t *group)
{
	zbx_vector_lld_group_discovery_ptr_clear_ext(&group->discovery, lld_group_discovery_free);
	zbx_vector_lld_group_discovery_ptr_destroy(&group->discovery);

	/* zbx_vector_ptr_clear_ext(&group->hosts, (zbx_clean_func_t)lld_host_free); is not missing here */
	zbx_vector_lld_host_ptr_destroy(&group->hosts);
	zbx_free(group->name);
	zbx_free(group->name_orig);
	zbx_free(group->name_inherit);
	zbx_free(group);
}

typedef struct
{
	char				*name;
	/* permission pair (usrgrpid, permission) */
	zbx_vector_uint64_pair_t	rights;
}
zbx_lld_group_rights_t;

ZBX_PTR_VECTOR_DECL(lld_group_rights_ptr, zbx_lld_group_rights_t*)
ZBX_PTR_VECTOR_IMPL(lld_group_rights_ptr, zbx_lld_group_rights_t*)

typedef struct
{
	zbx_uint64_t		ugsetid;
	int			permission;
	zbx_lld_hgset_t		*hgset;
} zbx_lld_permission_t;

ZBX_VECTOR_DECL(lld_permission, zbx_lld_permission_t)
ZBX_VECTOR_IMPL(lld_permission, zbx_lld_permission_t)

static int	lld_permission_compare(const void *d1, const void *d2)
{
	const zbx_lld_permission_t	*p1 = (const zbx_lld_permission_t * )d1;
	const zbx_lld_permission_t	*p2 = (const zbx_lld_permission_t * )d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->ugsetid, p2->ugsetid);
	ZBX_RETURN_IF_NOT_EQUAL(p1->hgset, p2->hgset);

	return 0;
}

static zbx_hash_t	zbx_ids_names_hash_func(const void *data)
{
	const zbx_id_name_pair_t	*id_name_pair_entry = (const zbx_id_name_pair_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(id_name_pair_entry->id), sizeof(id_name_pair_entry->id),
			ZBX_DEFAULT_HASH_SEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves tags of existing hosts                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_get_tags(zbx_vector_lld_host_ptr_t *hosts)
{
	zbx_vector_uint64_t	hostids;
	int			i;
	zbx_lld_host_t		*host;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		hostid;
	zbx_db_tag_t		*tag;

	zbx_vector_lld_host_ptr_sort(hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];
		zbx_vector_uint64_append(&hostids, host->hostid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hosttagid,hostid,tag,value,automatic from host_tag"
		" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = zbx_db_select("%s", sql);

	i = 0;
	host = hosts->values[i];

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[1]);
		while (hostid != host->hostid)
		{
			if (++i == hosts->values_num)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				goto out;
			}
			host = hosts->values[i];
		}

		tag = zbx_db_tag_create(row[2], row[3]);
		tag->automatic = atoi(row[4]);
		ZBX_STR2UINT64(tag->tagid, row[0]);

		zbx_vector_db_tag_ptr_append(&host->tags, tag);
	}
out:
	zbx_db_free_result(result);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&hostids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves existing hosts for specified host prototype             *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype id                         *
 *             hosts         - [OUT]                                          *
 *             ...           - [IN] new values which should be updated if     *
 *                                  different from original                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_get(zbx_uint64_t parent_hostid, zbx_vector_lld_host_ptr_t *hosts, unsigned char monitored_by,
		zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid, signed char ipmi_authtype,
		unsigned char ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		unsigned char tls_connect, unsigned char tls_accept, const char *tls_issuer, const char *tls_subject,
		const char *tls_psk_identity, const char *tls_psk)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_host_t		*host;
	zbx_uint64_t		db_proxyid, db_proxy_groupid;
	unsigned char		db_monitored_by;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select hd.hostid,hd.host,hd.lastcheck,hd.ts_delete,h.host,h.name,h.proxyid,"
				"h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password,hi.inventory_mode,"
				"h.tls_connect,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk,"
				"h.custom_interfaces,hh.hgsetid,hd.status,hd.ts_disable,hd.disable_source,h.status,"
				"h.proxy_groupid,h.monitored_by"
			" from host_discovery hd"
				" join hosts h"
					" on hd.hostid=h.hostid"
				" left join host_hgset hh"
					" on hh.hostid=h.hostid"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where hd.parent_hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
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
		ZBX_STR2UCHAR(host->status, row[23]);
		host->custom_interfaces_orig = 0;
		host->monitored_by_orig = 0;
		host->proxyid_orig = 0;
		host->proxy_groupid_orig = 0;
		host->ipmi_authtype_orig = 0;
		host->ipmi_privilege_orig = 0;
		host->tls_connect_orig = 0;
		host->tls_accept_orig = 0;
		host->flags = 0x00;
		ZBX_STR2UCHAR(host->custom_interfaces, row[18]);
		host->hgset_action = ZBX_LLD_HOST_HGSET_ACTION_IDLE;
		ZBX_STR2UCHAR(host->discovery_status, row[20]);
		host->ts_disable = atoi(row[21]);
		ZBX_STR2UCHAR(host->disable_source, row[22]);

		ZBX_STR2UCHAR(db_monitored_by, row[25]);
		if (db_monitored_by != monitored_by)
		{
			host->monitored_by_orig = db_monitored_by;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_MONITORED_BY;
		}

		ZBX_DBROW2UINT64(db_proxyid, row[6]);
		if (db_proxyid != proxyid)
		{
			host->proxyid_orig = db_proxyid;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_PROXY;
		}

		ZBX_DBROW2UINT64(db_proxy_groupid, row[24]);
		if (db_proxy_groupid != proxy_groupid)
		{
			host->proxy_groupid_orig = db_proxy_groupid;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_PROXY_GROUP;
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

		if (SUCCEED == zbx_db_is_null(row[11]))
			host->inventory_mode_orig = HOST_INVENTORY_DISABLED;
		else
			host->inventory_mode_orig = (signed char)atoi(row[11]);

		if (SUCCEED == zbx_db_is_null(row[19]))
			host->hgsetid_orig = 0;
		else
			ZBX_STR2UINT64(host->hgsetid_orig, row[19]);

		zbx_vector_uint64_create(&host->groupids);
		zbx_vector_uint64_create(&host->old_groupids);
		zbx_vector_uint64_create(&host->new_groupids);
		zbx_vector_uint64_create(&host->lnk_templateids);
		zbx_vector_uint64_create(&host->del_templateids);
		zbx_vector_lld_hostmacro_ptr_create(&host->new_hostmacros);
		zbx_vector_db_tag_ptr_create(&host->tags);
		zbx_vector_lld_interface_ptr_create(&host->interfaces);

		zbx_vector_lld_host_ptr_append(hosts, host);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_hash_t	lld_host_host_hash(const void *d)
{
	const zbx_lld_host_t	*host = *(const zbx_lld_host_t *const *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(host->host);
}

static int	lld_host_host_compare(const void *d1, const void *d2)
{
	const zbx_lld_host_t	*host1 = *(const zbx_lld_host_t * const *)d1;
	const zbx_lld_host_t	*host2 = *(const zbx_lld_host_t * const *)d2;

	return strcmp(host1->host, host2->host);
}

static zbx_hash_t	lld_host_name_hash(const void *d)
{
	const zbx_lld_host_t	*host = *(const zbx_lld_host_t * const *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(host->name);
}

static int	lld_host_name_compare(const void *d1, const void *d2)
{
	const zbx_lld_host_t	*host1 = *(const zbx_lld_host_t * const *)d1;
	const zbx_lld_host_t	*host2 = *(const zbx_lld_host_t * const *)d2;

	return strcmp(host1->name, host2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates LLD hosts                                               *
 *                                                                            *
 * Parameters: hosts - [IN] list of hosts; should be sorted by hostid         *
 *             error - [OUT]                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_validate(zbx_vector_lld_host_ptr_t *hosts, char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_host_t		*host;
	zbx_vector_uint64_t	hostids;
	zbx_vector_str_t	tnames, vnames;
	zbx_hashset_t		host_hosts, host_names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&host_hosts, hosts->values_num, lld_host_host_hash, lld_host_host_compare);
	zbx_hashset_create(&host_names, hosts->values_num, lld_host_name_hash, lld_host_name_compare);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_str_create(&tnames);		/* list of technical host names */
	zbx_vector_str_create(&vnames);		/* list of visible host names */

	/* checking a host name validity */
	for (int i = 0; i < hosts->values_num; i++)
	{
		char	*ch_error;
		char	name_trunc[VALUE_ERRMSG_MAX + 1];

		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		/* host name is valid? */
		if (SUCCEED == zbx_check_hostname(host->host, &ch_error))
			continue;

		zbx_strlcpy(name_trunc, host->host, sizeof(name_trunc));

		*error = zbx_strdcatf(*error, "Cannot %s host \"%s\": %s.\n",
				(0 != host->hostid ? "update" : "create"), name_trunc, ch_error);

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
	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed visible name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		/* visible host name is valid utf8 sequence and has a valid length */
		if (SUCCEED != zbx_is_utf8(host->name) || '\0' == *host->name)
		{
			zbx_replace_invalid_utf8(host->name);
			*error = zbx_strdcatf(*error, "Cannot %s host \"%s\": invalid visible host name \"%s\".\n",
					(0 != host->hostid ? "update" : "create"), host->host, host->name);
		}
		else if (zbx_strlen_utf8(host->name) > ZBX_MAX_HOSTNAME_LEN)
		{
			*error = zbx_strdcatf(*error, "Cannot %s host \"%s\": visible name is too long.\n",
					(0 != host->hostid ? "update" : "create"), host->host);
		}
		else
			continue;

		if (0 != host->hostid)
		{
			lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
					ZBX_FLAG_LLD_HOST_UPDATE_NAME);
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* index existing hosts */
	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			zbx_hashset_insert(&host_hosts, &host, sizeof(zbx_lld_host_t *));

		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			zbx_hashset_insert(&host_names, &host, sizeof(zbx_lld_host_t *));
	}

	/* checking duplicated host names */
	for (int i = 0; i < hosts->values_num; i++)
	{
		int	num_data = host_hosts.num_data;

		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		zbx_hashset_insert(&host_hosts, &host, sizeof(zbx_lld_host_t *));

		if (num_data != host_hosts.num_data)
			continue;

		*error = zbx_strdcatf(*error, "Cannot %s host:"
				" host with the same name \"%s\" (\"%s\") already exists.\n",
				(0 != host->hostid ? "update" : "create"), host->host, host->name);

		if (0 != host->hostid)
		{
			lld_field_str_rollback(&host->host, &host->host_orig, &host->flags,
					ZBX_FLAG_LLD_HOST_UPDATE_HOST);
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking duplicated visible host names */
	for (int i = 0; i < hosts->values_num; i++)
	{
		int	num_data = host_names.num_data;

		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with changed visible name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		zbx_hashset_insert(&host_names, &host, sizeof(zbx_lld_host_t *));

		if (num_data != host_names.num_data)
			continue;

		*error = zbx_strdcatf(*error, "Cannot %s host \"%s\":"
				" host with the same visible name \"%s\" already exists.\n",
				(0 != host->hostid ? "update" : "create"), host->host, host->name);

		if (0 != host->hostid)
		{
			lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
					ZBX_FLAG_LLD_HOST_UPDATE_NAME);
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking duplicated host names and visible host names in DB */

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

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
			zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "host",
					(const char * const *)tnames.values, tnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " or");

		if (0 != vnames.values_num)
		{
			zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
					(const char * const *)vnames.values, vnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

		if (0 != hostids.values_num)
		{
			zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
					hostids.values, hostids.values_num);
		}

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_lld_host_t	host_local = {.host = row[0], .name = row[1]}, *phost_local = &host_local,
					**phost;

			if (NULL != (phost = zbx_hashset_search(&host_hosts, &phost_local)))
			{
				host = *phost;

				*error = zbx_strdcatf(*error, "Cannot %s host:"
						" host with the same name \"%s\" (\"%s\") already exists.\n",
						(0 != host->hostid ? "update" : "create"), host->host,
						host->name);

				if (0 != host->hostid)
				{
					lld_field_str_rollback(&host->host, &host->host_orig, &host->flags,
							ZBX_FLAG_LLD_HOST_UPDATE_HOST);
				}
				else
					host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
			}

			if (NULL != (phost = zbx_hashset_search(&host_names, &phost_local)))
			{
				host = *phost;

				*error = zbx_strdcatf(*error, "Cannot %s host \"%s\":"
						" host with the same visible name \"%s\" already exists.\n",
						(0 != host->hostid ? "update" : "create"), host->host, host->name);

				if (0 != host->hostid)
				{
					lld_field_str_rollback(&host->name, &host->name_orig, &host->flags,
							ZBX_FLAG_LLD_HOST_UPDATE_NAME);
				}
				else
					host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
			}
		}
		zbx_db_free_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&vnames);
	zbx_vector_str_destroy(&tnames);
	zbx_vector_uint64_destroy(&hostids);
	zbx_hashset_destroy(&host_hosts);
	zbx_hashset_destroy(&host_names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_lld_host_t	*lld_host_make(zbx_vector_lld_host_ptr_t *hosts, zbx_vector_lld_host_ptr_t *hosts_old,
		const char *host_proto, const char *name_proto,
		signed char inventory_mode_proto, unsigned char status_proto, unsigned char discover_proto,
		zbx_vector_db_tag_ptr_t *tags, const zbx_lld_row_t *lld_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, unsigned char custom_iface, char **error)
{
	char			*buffer = NULL;
	int			host_found = 0;
	zbx_lld_host_t		*host = NULL;
	zbx_vector_db_tag_ptr_t	override_tags;
	zbx_vector_uint64_t	lnk_templateids;
	zbx_vector_db_tag_ptr_t	new_tags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&lnk_templateids);
	zbx_vector_db_tag_ptr_create(&new_tags);

	for (int i = 0; i < hosts_old->values_num; i++)
	{
		host = hosts_old->values[i];

		if (0 != (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
			continue;

		buffer = zbx_strdup(buffer, host->host_proto);
		zbx_substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 == strcmp(host->host, buffer))
		{
			zbx_vector_lld_host_ptr_remove(hosts_old, i);
			host_found = 1;
			break;
		}
	}

	zbx_vector_db_tag_ptr_create(&override_tags);

	if (0 == host_found)
	{
		host = (zbx_lld_host_t *)zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		host->hostid = 0;
		host->host_proto = NULL;
		host->lastcheck = 0;
		host->discovery_status = ZBX_LLD_DISCOVERY_STATUS_NORMAL;
		host->ts_delete = 0;
		host->ts_disable = 0;
		host->disable_source = ZBX_DISABLE_SOURCE_DEFAULT;
		host->host = zbx_strdup(NULL, host_proto);
		host->host_orig = NULL;
		zbx_substitute_lld_macros(&host->host, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
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
				&override_tags, &host->status, &discover_proto);

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
			zbx_substitute_lld_macros(&host->name, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(host->name, ZBX_WHITESPACE);
			host->name_orig = NULL;
			zbx_vector_uint64_create(&host->groupids);
			zbx_vector_uint64_create(&host->old_groupids);
			zbx_vector_uint64_create(&host->new_groupids);
			zbx_vector_uint64_create(&host->del_templateids);
			zbx_vector_lld_hostmacro_ptr_create(&host->new_hostmacros);
			zbx_vector_db_tag_ptr_create(&host->tags);
			zbx_vector_lld_interface_ptr_create(&host->interfaces);
			host->flags = ZBX_FLAG_LLD_HOST_DISCOVERED;
			host->jp_row = NULL;
			host->inventory_mode_orig = host->inventory_mode;
			host->custom_interfaces_orig = host->custom_interfaces;
			host->monitored_by_orig = 0;
			host->proxyid_orig = 0;
			host->proxy_groupid_orig = 0;
			host->ipmi_authtype_orig = 0;
			host->ipmi_privilege_orig = 0;
			host->hgsetid_orig = 0;
			host->hgset_action = ZBX_LLD_HOST_HGSET_ACTION_IDLE;

			zbx_vector_lld_host_ptr_append(hosts, host);
		}
	}
	else
	{
		zbx_free(buffer);
		/* host technical name */
		if (0 != strcmp(host->host_proto, host_proto))	/* the new host prototype differs */
		{
			buffer = zbx_strdup(buffer, host_proto);
			zbx_substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(buffer, ZBX_WHITESPACE);
		}

		lld_override_host(&lld_row->overrides, NULL != buffer ? buffer : host->host, &lnk_templateids,
				&inventory_mode_proto, &override_tags, NULL, &discover_proto);

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
		zbx_substitute_lld_macros(&buffer, &lld_row->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
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
		zbx_db_tag_t	*db_tag;

		for (int i = 0; i < tags->values_num; i++)
		{
			db_tag = zbx_db_tag_create(tags->values[i]->tag, tags->values[i]->value);
			zbx_vector_db_tag_ptr_append(&new_tags, db_tag);
		}

		for (int i = 0; i < override_tags.values_num; i++)
		{
			db_tag = zbx_db_tag_create(override_tags.values[i]->tag, override_tags.values[i]->value);
			zbx_vector_db_tag_ptr_append(&new_tags, db_tag);
		}

		for (int i = 0; i < new_tags.values_num; i++)
		{
			zbx_substitute_lld_macros(&new_tags.values[i]->tag, host->jp_row, lld_macros, ZBX_MACRO_FUNC,
					NULL, 0);
			zbx_substitute_lld_macros(&new_tags.values[i]->value, host->jp_row, lld_macros, ZBX_MACRO_FUNC,
					NULL, 0);
		}

		if (SUCCEED != zbx_merge_tags(&host->tags, &new_tags, "host", error))
		{
			if (0 == host->hostid)
			{
				host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
				*error = zbx_strdcatf(*error, "Cannot create host \"%s\": tag validation failed.\n",
						host->name);
			}
		}

		/* sort existing tags by their ids for update operations */
		zbx_vector_db_tag_ptr_sort(&host->tags, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_db_tag_ptr_clear_ext(&new_tags, zbx_db_tag_free);

		if (0 != lnk_templateids.values_num)
		{
			zbx_vector_uint64_append_array(&host->lnk_templateids, lnk_templateids.values,
					lnk_templateids.values_num);
		}
	}
out:
	zbx_vector_db_tag_ptr_destroy(&new_tags);
	zbx_vector_db_tag_ptr_destroy(&override_tags);
	zbx_vector_uint64_destroy(&lnk_templateids);

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)host);

	return host;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of host groups which should be present on each     *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype id                         *
 *             groupids      - [OUT] sorted list of host groups               *
 *                                                                            *
 ******************************************************************************/
static void	lld_simple_groups_get(zbx_uint64_t parent_hostid, zbx_vector_uint64_t *groupids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	groupid;

	result = zbx_db_select(
			"select groupid"
			" from group_prototype"
			" where groupid is not null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(groupids, groupid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/*******************************************************************************
 *                                                                             *
 * Parameters: groupids         - [IN] Sorted list of host group ids which     *
 *                                     should be present on each discovered    *
 *                                     host (Groups).                          *
 *             hosts            - [IN/OUT] List of hosts which should be       *
 *                                         sorted by hostid.                   *
 *             groups           - [IN] list of host groups (Group prototypes)  *
 *             del_hostgroupids - [OUT] Sorted list of host groups which       *
 *                                      should be deleted.                     *
 *                                                                             *
 *******************************************************************************/
static void	lld_hostgroups_make(const zbx_vector_uint64_t *groupids, zbx_vector_lld_host_ptr_t *hosts,
		const zbx_vector_lld_group_ptr_t *groups, zbx_vector_uint64_t *del_hostgroupids)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostgroupid, hostid, groupid;
	zbx_lld_host_t		*host;
	const zbx_lld_group_t	*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groupids:%d hosts:%d", __func__, groupids->values_num, hosts->values_num);

	zbx_vector_uint64_create(&hostids);

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->new_groupids, (size_t)groupids->values_num);

		for (int j = 0; j < groupids->values_num; j++)
			zbx_vector_uint64_append(&host->new_groupids, groupids->values[j]);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	for (int i = 0; i < groups->values_num; i++)
	{
		group = groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		for (int j = 0; j < group->hosts.values_num; j++)
		{
			host = group->hosts.values[j];

			zbx_vector_uint64_append(&host->new_groupids, group->groupid);
		}
	}

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_sort(&host->new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_append_array(&host->groupids, host->new_groupids.values,
				host->new_groupids.values_num);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,groupid,hostgroupid"
				" from hosts_groups"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = zbx_db_select("%s", sql);

		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(groupid, row[1]);

			zbx_lld_host_t	cmp = {.hostid = hostid};

			int	j;

			if (FAIL == (j = zbx_vector_lld_host_ptr_bsearch(hosts, &cmp, lld_host_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = hosts->values[j];
			zbx_vector_uint64_append(&host->old_groupids, groupid);

			if (FAIL == (j = zbx_vector_uint64_bsearch(&host->new_groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* host groups which should be unlinked */
				ZBX_STR2UINT64(hostgroupid, row[2]);
				zbx_vector_uint64_append(del_hostgroupids, hostgroupid);

				zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, hostid,
						(NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_hostgroup_update_json_delete_group(ZBX_AUDIT_LLD_CONTEXT, hostid, hostgroupid,
						groupid);
			}
			else
			{
				/* host groups which are already added */
				zbx_vector_uint64_remove(&host->new_groupids, j);
			}
		}
		zbx_db_free_result(result);

		zbx_vector_uint64_sort(del_hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_lld_hgset_t	*lld_hgset_make(zbx_vector_uint64_t *groupids)
{
	zbx_lld_hgset_t	*hgset;

	hgset = zbx_malloc(NULL, sizeof(zbx_lld_hgset_t));
	zbx_vector_uint64_create(&hgset->hgroupids);
	zbx_hgset_hash_calculate(groupids, hgset->hash_str, sizeof(hgset->hash_str));

	return hgset;
}

static void	lld_hgsets_make(zbx_uint64_t parent_hostid, zbx_vector_lld_host_ptr_t *hosts,
		zbx_vector_lld_hgset_ptr_t *hgsets, zbx_vector_uint64_t *del_hgsetids)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	int				i;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_vector_str_t		hashes;
	zbx_vector_uint64_t		hostids;
	zbx_lld_host_t			*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	/* make hgsets and assign them to hosts */

	for (i = 0; i < hosts->values_num; i++)
	{
		int		k;
		zbx_lld_hgset_t	*hgset;

		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->groupids.values_num)
		{
			host->hgset = NULL;
			zabbix_log(LOG_LEVEL_WARNING, "%s() detected host without groups [parent_hostid=" ZBX_FS_UI64
					", hostid=" ZBX_FS_UI64 "], permissions will not be granted", __func__,
					parent_hostid, host->hostid);
			continue;
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);

		if (0 < host->old_groupids.values_num)
		{
			zbx_vector_uint64_sort(&host->old_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			hgset = lld_hgset_make(&host->old_groupids);

			if (FAIL != (k = zbx_vector_lld_hgset_ptr_search(hgsets, hgset, lld_hgset_compare)))
			{
				lld_hgset_free(hgset);

				if (ZBX_LLD_HGSET_OPT_INSERT == hgsets->values[k]->opt)
				{
					hgsets->values[k]->hgsetid = host->hgsetid_orig;
					hgsets->values[k]->opt = ZBX_LLD_HGSET_OPT_REUSE;
				}
			}
			else
			{
				hgset->hgsetid = host->hgsetid_orig;
				hgset->opt = ZBX_LLD_HGSET_OPT_DELETE;
				zbx_vector_uint64_append_array(&hgset->hgroupids, host->old_groupids.values,
						host->old_groupids.values_num);
				zbx_vector_lld_hgset_ptr_append(hgsets, hgset);
			}
		}

		hgset = lld_hgset_make(&host->groupids);

		if (FAIL != (k = zbx_vector_lld_hgset_ptr_search(hgsets, hgset, lld_hgset_compare)))
		{
			lld_hgset_free(hgset);

			if (ZBX_LLD_HGSET_OPT_DELETE == hgsets->values[k]->opt)
				hgsets->values[k]->opt = ZBX_LLD_HGSET_OPT_REUSE;

			host->hgset = hgsets->values[k];
		}
		else
		{
			hgset->hgsetid = 0;
			hgset->opt = ZBX_LLD_HGSET_OPT_INSERT;
			host->hgset = hgset;
			zbx_vector_uint64_append_array(&hgset->hgroupids, host->groupids.values,
					host->groupids.values_num);
			zbx_vector_lld_hgset_ptr_append(hgsets, hgset);
		}

		if (0 != host->hostid && 0 == host->new_groupids.values_num &&
				host->groupids.values_num == host->old_groupids.values_num)
		{
			continue;
		}

		if (0 == host->hostid || 0 == host->old_groupids.values_num)
			host->hgset_action = ZBX_LLD_HOST_HGSET_ACTION_ADD;
		else
			host->hgset_action = ZBX_LLD_HOST_HGSET_ACTION_UPDATE;
	}

	zbx_vector_str_create(&hashes);

	for (i = 0; i < hgsets->values_num; i++)
	{
		if (ZBX_LLD_HGSET_OPT_INSERT == hgsets->values[i]->opt)
			zbx_vector_str_append(&hashes, hgsets->values[i]->hash_str);
		else if (ZBX_LLD_HGSET_OPT_DELETE == hgsets->values[i]->opt)
			zbx_vector_uint64_append(del_hgsetids, hgsets->values[i]->hgsetid);
	}

	/* get ids of existing hgsets which are marked for insert */
	if (0 < hashes.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hgsetid,hash from hgset where");
		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "hash",
				(const char * const *)hashes.values, hashes.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			if (FAIL != (i = zbx_vector_lld_hgset_ptr_search(hgsets, (void*)row[1],
					lld_hgset_hash_search)))
			{
				ZBX_STR2UINT64(hgsets->values[i]->hgsetid, row[0]);
				hgsets->values[i]->opt = ZBX_LLD_HGSET_OPT_REUSE;
			}
		}
		zbx_db_free_result(result);
	}

	zbx_vector_str_destroy(&hashes);

	/* get hgset ids to be deleted */
	if (0 < del_hgsetids->values_num)
	{
		zbx_vector_uint64_sort(del_hgsetids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct hgsetid from host_hgset where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hgsetid", del_hgsetids->values,
				del_hgsetids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	hgsetid;

			ZBX_STR2UINT64(hgsetid, row[0]);

			if (FAIL == (i = zbx_vector_uint64_bsearch(del_hgsetids, hgsetid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			zbx_vector_uint64_remove(del_hgsetids, i);
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_permissions_make(zbx_vector_lld_permission_t *permissions, zbx_vector_lld_hgset_ptr_t *hgsets)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	int				i;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_hgset_t			*hgset;
	zbx_vector_uint64_t		hostgroupids;
	zbx_lld_permission_t		prm;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostgroupids);

	for (i = 0; i < hgsets->values_num; i++)
	{
		hgset = hgsets->values[i];

		if (ZBX_LLD_HGSET_OPT_INSERT == hgset->opt)
		{
			zbx_vector_uint64_append_array(&hostgroupids, hgset->hgroupids.values,
					hgset->hgroupids.values_num);
		}
	}

	if (0 == hostgroupids.values_num)
	{
		zbx_vector_uint64_destroy(&hostgroupids);
		return;
	}

	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct u.ugsetid,r.id,r.permission from ugset_group u"
			" join rights r on u.usrgrpid=r.groupid"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.id", hostgroupids.values, hostgroupids.values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&hostgroupids);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostgroupid;

		ZBX_STR2UINT64(prm.ugsetid, row[0]);
		ZBX_STR2UINT64(hostgroupid, row[1]);
		prm.permission = atoi(row[2]);

		for (i = 0; i < hgsets->values_num; i++)
		{
			int	j;

			hgset = hgsets->values[i];

			if (ZBX_LLD_HGSET_OPT_INSERT != hgset->opt ||
					FAIL == zbx_vector_uint64_bsearch(&hgset->hgroupids, hostgroupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				continue;
			}

			prm.hgset = hgset;

			if (FAIL != (j = zbx_vector_lld_permission_search(permissions, prm, lld_permission_compare)))
			{
				int	*permission_old = &permissions->values[j].permission;

				if (PERM_DENY != *permission_old && (PERM_DENY == prm.permission ||
						*permission_old < prm.permission))
				{
					*permission_old = prm.permission;
				}
			}
			else
				zbx_vector_lld_permission_append(permissions, prm);
		}
	}
	zbx_db_free_result(result);

	for (i = 0; i < permissions->values_num; i++)
	{
		if (PERM_DENY == permissions->values[i].permission)
			zbx_vector_lld_permission_remove_noorder(permissions, i--);
	}

	zbx_vector_lld_permission_sort(permissions, lld_permission_compare);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of group prototypes                                *
 *                                                                            *
 * Parameters: parent_hostid    - [IN] host prototype id                      *
 *             group_prototypes - [OUT] sorted list of group prototypes       *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_prototypes_get(zbx_uint64_t parent_hostid,
		zbx_vector_lld_group_prototype_ptr_t *group_prototypes)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_group_prototype_t	*group_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select group_prototypeid,name"
			" from group_prototype"
			" where groupid is null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		group_prototype = zbx_malloc(NULL, sizeof(zbx_lld_group_prototype_t));

		ZBX_STR2UINT64(group_prototype->group_prototypeid, row[0]);
		group_prototype->name = zbx_strdup(NULL, row[1]);

		zbx_vector_lld_group_prototype_ptr_append(group_prototypes, group_prototype);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_group_prototype_ptr_sort(group_prototypes, lld_group_prototype_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	lld_group_compare(const void *d1, const void *d2)
{
	const zbx_lld_group_t	*g1 = *(const zbx_lld_group_t * const *)d1;
	const zbx_lld_group_t	*g2 = *(const zbx_lld_group_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(g1->groupid, g2->groupid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves existing groups for specified host prototype            *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype id                         *
 *             groups        - [OUT] list of groups                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_get(zbx_uint64_t parent_hostid, zbx_vector_lld_group_ptr_t *groups)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_group_t		*group = NULL;
	zbx_vector_uint64_t	groupids, discoveryids;
	zbx_uint64_t		groupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&groupids);
	zbx_vector_uint64_create(&discoveryids);

	result = zbx_db_select(
			"select gd.groupid,gp.group_prototypeid,gd.name,gd.lastcheck,gd.status,gd.ts_delete,g.name,"
				"gd.groupdiscoveryid"
			" from group_prototype gp,group_discovery gd"
				" join hstgrp g"
					" on gd.groupid=g.groupid"
			" where gp.group_prototypeid=gd.parent_group_prototypeid"
				" and gp.hostid=" ZBX_FS_UI64
			" order by gd.groupid",
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_group_discovery_t	*discovery;

		ZBX_DBROW2UINT64(groupid, row[0]);
		if (NULL == group || group->groupid != groupid)
		{
			group = (zbx_lld_group_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_t));

			group->groupid = groupid;
			group->name_inherit = NULL;
			group->name = zbx_strdup(NULL, row[6]);
			group->name_orig = NULL;
			group->flags = 0x0;
			zbx_vector_lld_host_ptr_create(&group->hosts);
			zbx_vector_lld_group_discovery_ptr_create(&group->discovery);

			zbx_vector_lld_group_ptr_append(groups, group);

			zbx_vector_uint64_append(&groupids, groupid);
		}

		discovery = (zbx_lld_group_discovery_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_discovery_t));
		ZBX_DBROW2UINT64(discovery->groupdiscoveryid, row[7]);
		ZBX_DBROW2UINT64(discovery->parent_group_prototypeid, row[1]);
		discovery->name = zbx_strdup(NULL, row[2]);
		discovery->lastcheck = atoi(row[3]);
		ZBX_STR2UCHAR(discovery->discovery_status, row[4]);
		discovery->ts_delete = atoi(row[5]);
		discovery->flags = 0x0;
		discovery->lld_row = NULL;

		zbx_vector_lld_group_discovery_ptr_append(&group->discovery, discovery);

		zbx_vector_uint64_append(&discoveryids, discovery->groupdiscoveryid);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_group_ptr_sort(groups, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	/* mark groups linked also to other prototypes as discovered */
	if (0 != groupids.values_num)
	{
		char		*sql = NULL;
		size_t		sql_alloc = 0, sql_offset = 0;
		int		i;

		zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_sort(&discoveryids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct groupid from group_discovery where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids.values,
				groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupdiscoveryid", discoveryids.values,
				discoveryids.values_num);

		result = zbx_db_select("%s", sql);
		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_lld_group_t	group_local;

			ZBX_DBROW2UINT64(group_local.groupid, row[0]);

			if (FAIL == (i = zbx_vector_lld_group_ptr_bsearch(groups, &group_local,
					lld_group_compare)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			groups->values[i]->flags |= ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE;
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&discoveryids);
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_lld_group_t	*lld_group_make(zbx_uint64_t group_prototypeid, const char *name_proto,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	zbx_lld_group_t			*group;
	zbx_lld_group_discovery_t	*discovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	group = (zbx_lld_group_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_t));

	group->groupid = 0;
	group->name_inherit = NULL;
	zbx_vector_lld_host_ptr_create(&group->hosts);
	zbx_vector_lld_group_discovery_ptr_create(&group->discovery);
	group->name = zbx_strdup(NULL, name_proto);
	zbx_substitute_lld_macros(&group->name, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(group->name, ZBX_WHITESPACE);
	group->name_orig = NULL;
	group->flags = ZBX_FLAG_LLD_GROUP_DISCOVERED;

	discovery = (zbx_lld_group_discovery_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_discovery_t));
	discovery->groupdiscoveryid = 0;
	discovery->parent_group_prototypeid = group_prototypeid;
	discovery->name = zbx_strdup(NULL, name_proto);
	discovery->discovery_status = ZBX_LLD_DISCOVERY_STATUS_NORMAL;
	discovery->ts_delete = 0;
	discovery->lastcheck = 0;
	discovery->flags = ZBX_FLAG_LLD_GROUP_DISCOVERED;
	discovery->lld_row = jp_row;

	zbx_vector_lld_group_discovery_ptr_append(&group->discovery, discovery);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)group);

	return group;
}

static void	lld_groups_make(zbx_lld_host_t *host, zbx_vector_lld_group_ptr_t *groups,
		const zbx_vector_lld_group_prototype_ptr_t *group_prototypes, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < group_prototypes->values_num; i++)
	{
		const zbx_lld_group_prototype_t	*group_prototype;
		zbx_lld_group_t			*group;

		group_prototype = group_prototypes->values[i];

		group = lld_group_make(group_prototype->group_prototypeid, group_prototype->name, jp_row, lld_macros);

		zbx_vector_lld_host_ptr_append(&group->hosts, host);

		zbx_vector_lld_group_ptr_append(groups, group);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCEED - group name is valid                                *
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
 * Purpose: merges old discovery links with discovered ones                   *
 *                                                                            *
 ******************************************************************************/
static  void	lld_group_merge_group_discovery(zbx_vector_lld_group_discovery_ptr_t *dst,
		zbx_vector_lld_group_discovery_ptr_t *src)
{
	int	i, j;

	for (i = 0; i < src->values_num; i++)
	{
		for (j = 0; j < dst->values_num; j++)
		{
			if (src->values[i]->parent_group_prototypeid == dst->values[j]->parent_group_prototypeid)
			{
				dst->values[j]->groupdiscoveryid = src->values[i]->groupdiscoveryid;
				dst->values[j]->lastcheck = src->values[i]->lastcheck;
				dst->values[j]->ts_delete = src->values[i]->ts_delete;
				dst->values[j]->discovery_status = src->values[i]->discovery_status;

				lld_group_discovery_free(src->values[i]);
				zbx_vector_lld_group_discovery_ptr_remove_noorder(src, i--);
				break;
			}
		}
	}
}

static int	lld_group_add_group_discovery(zbx_lld_group_t *group, zbx_lld_group_discovery_t *discovery)
{
	for (int i = 0; i < group->discovery.values_num; i++)
	{
		if (group->discovery.values[i]->parent_group_prototypeid == discovery->parent_group_prototypeid)
			return FAIL;
	}

	zbx_vector_lld_group_discovery_ptr_append(&group->discovery, discovery);

	return SUCCEED;
}

static void 	lld_group_add_host(zbx_vector_lld_host_ptr_t *hosts, zbx_lld_host_t *host)
{
	for (int i = 0; i < hosts->values_num; i++)
	{
		if (hosts->values[i] == host)
			return;
	}

	zbx_vector_lld_host_ptr_append(hosts, host);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Merges group candidates with the same name by combining their     *
 *          host and discovery lists.                                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_candidates_merge_by_name(zbx_vector_lld_group_ptr_t *groups_in)
{
	for (int i = 0; i < groups_in->values_num; i++)
	{
		zbx_lld_group_t	*left = groups_in->values[i];

		for (int j = i + 1; j < groups_in->values_num; )
		{
			zbx_lld_group_t	*right = groups_in->values[j];

			if (0 == strcmp(left->name, right->name))
			{
				/* unmerged group candidates have only one discovery link */
				if (FAIL == lld_group_add_group_discovery(left, right->discovery.values[0]))
					lld_group_discovery_free(right->discovery.values[0]);

				zbx_vector_lld_group_discovery_ptr_clear(&right->discovery);

				lld_group_add_host(&left->hosts, right->hosts.values[0]);

				lld_group_free(right);
				zbx_vector_lld_group_ptr_remove_noorder(groups_in, j);
			}
			else
				j++;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates group candidate names                                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_candidates_validate(zbx_vector_lld_group_ptr_t *groups_in, char **error)
{
	/* validate syntax of group candidate names */
	for (int i = 0; i < groups_in->values_num; )
	{
		zbx_lld_group_t		*group = groups_in->values[i];

		if (SUCCEED != lld_validate_group_name(group->name))
		{
			zbx_replace_invalid_utf8(group->name);

			*error = zbx_strdcatf(*error, "Cannot discover group: invalid group name \"%s\".\n",
					group->name);

			zbx_vector_lld_group_ptr_remove_noorder(groups_in, i);
			lld_group_free(group);
		}
		else
			i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Merges groups with candidates by names and adds merged groups to  *
 *          discovered groups.                                                *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_merge_with_candidates(zbx_vector_lld_group_ptr_t *groups,
		zbx_vector_lld_group_ptr_t *groups_in, zbx_vector_lld_group_ptr_t *groups_out)
{
	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*left = groups->values[i];

		for (int j = 0; j < groups_in->values_num; j++)
		{
			zbx_lld_group_t	*right = groups_in->values[j];

			if (0 == (strcmp(left->name, right->name)))
			{
				right->groupid = left->groupid;
				lld_group_merge_group_discovery(&right->discovery, &left->discovery);

				zbx_vector_lld_group_ptr_append(groups_out, right);
				zbx_vector_lld_group_ptr_remove_noorder(groups_in, j);

				/* The matched group_discovery links were removed from original group    */
				/* during merge. If there are more group_discovery link left - leave the */
				/* original group with group_discovery leftovers as undiscovered to      */
				/* track possible prototype renames.                                     */
				if (0 == left->discovery.values_num)
				{
					zbx_vector_lld_group_ptr_remove_noorder(groups, i--);
					lld_group_free(left);
				}
				else
					left->flags |= ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE;

				break;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Checks database for groups having conflicting names with          *
 *          candidates.                                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_candidates_validate_db(zbx_vector_lld_group_ptr_t *groups_in,
		zbx_vector_lld_group_ptr_t *groups_out, char **error)
{

	zbx_vector_str_t	names;

	zbx_vector_str_create(&names);

	for (int i = 0; i < groups_in->values_num; i++)
		zbx_vector_str_append(&names, groups_in->values[i]->name);

	if (0 != names.values_num)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;
		char		*sql = NULL;
		size_t		sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select name,flags,groupid from hstgrp"
				" where type=%d"
					" and",
				HOSTGROUP_TYPE_HOST);

		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
				(const char * const *)names.values, names.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			for (int i = 0; i < groups_in->values_num; i++)
			{
				zbx_lld_group_t	*group = groups_in->values[i];

				if (0 == strcmp(group->name, row[0]))
				{
					if (ZBX_FLAG_DISCOVERY_NORMAL == atoi(row[1]))
					{
						*error = zbx_strdcatf(*error, "Cannot discover group:"
							" group with the same name \"%s\" already exists.\n",
							group->name);

						lld_group_free(group);
					}
					else
					{
						ZBX_STR2UINT64(group->groupid, row[2]);
						group->flags |= ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE;
						zbx_vector_lld_group_ptr_append(groups_out, group);
					}

					zbx_vector_lld_group_ptr_remove_noorder(groups_in, i);

					break;
				}
			}
		}
		zbx_db_free_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&names);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies renamed discovery link to new group                        *
 *                                                                            *
 * Return value: SUCCEED - discovery link was copied                          *
 *               FAIL   - otherwise                                           *
 *                                                                            *
 ******************************************************************************/
static int	lld_group_rename_discovery_link(zbx_lld_group_t *dst, const zbx_lld_group_t *src,
		zbx_lld_group_discovery_t *gd_src, const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	int	ret = FAIL;
	char	*name = NULL;

	for (int i = 0; i < dst->discovery.values_num; i++)
	{
		zbx_lld_group_discovery_t	*gd_dst = dst->discovery.values[i];

		if (gd_src->parent_group_prototypeid == gd_dst->parent_group_prototypeid &&
				0 == gd_dst->groupdiscoveryid)
		{
			name = zbx_strdup(name, gd_src->name);
			zbx_substitute_lld_macros(&name, gd_dst->lld_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);

			if (0 == strcmp(name, src->name))
			{
				gd_dst->groupdiscoveryid = gd_src->groupdiscoveryid;
				gd_dst->lastcheck = gd_src->lastcheck;
				gd_dst->ts_delete = gd_src->ts_delete;
				gd_dst->discovery_status = gd_src->discovery_status;
				gd_dst->flags |= ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_NAME;

				if (dst->groupid != src->groupid)
					gd_dst->flags |= ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_GROUPID;

				ret = SUCCEED;
				goto out;
			}
		}
	}
out:
	zbx_free(name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies renamed discovery link to new group                        *
 *                                                                            *
 * Return value: index of target group or FAIL                                *
 *                                                                            *
 * Comments: This function iterates through specified groups and looks for    *
 *           possible rename candidate. If found it copies the link to that   *
 *           group and returns.                                               *
 *                                                                            *
 ******************************************************************************/
static int	lld_groups_rename_discovery_link(zbx_vector_lld_group_ptr_t *groups, const zbx_lld_group_t *src,
		zbx_lld_group_discovery_t *discovery, const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*group = groups->values[i];

		if (SUCCEED == lld_group_rename_discovery_link(group, src, discovery, lld_macros))
			return i;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Detects prototype renames in group discovery links and copies the *
 *          old links to new groups.                                          *
 *                                                                            *
 * Comments: If possible the old group is renamed.                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_merge_renames(const zbx_vector_lld_group_prototype_ptr_t *group_prototypes,
		zbx_vector_lld_group_ptr_t *groups, zbx_vector_lld_group_ptr_t *groups_in,
		zbx_vector_lld_group_ptr_t *groups_out, const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*left = groups->values[i];
		int			k;

		for (int j = 0; j < left->discovery.values_num; j++)
		{
			zbx_lld_group_discovery_t	*discovery = left->discovery.values[j];

			zbx_lld_group_prototype_t	cmp = {.group_prototypeid =
					discovery->parent_group_prototypeid};

			if (FAIL == (k = zbx_vector_lld_group_prototype_ptr_bsearch(group_prototypes, &cmp,
					lld_group_prototype_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (0 == strcmp((group_prototypes->values[k])->name, discovery->name))
			{
				continue;
			}

			if (FAIL != lld_groups_rename_discovery_link(groups_out, left, discovery, lld_macros))
			{
				lld_group_discovery_free(discovery);
				zbx_vector_lld_group_discovery_ptr_remove_noorder(&left->discovery, j--);
			}
			else if (FAIL != (k = lld_groups_rename_discovery_link(groups_in, left, discovery, lld_macros)))
			{
				zbx_lld_group_t	*right = groups_in->values[k];

				lld_group_discovery_free(discovery);
				zbx_vector_lld_group_discovery_ptr_remove_noorder(&left->discovery, j--);

				if (0 == (left->flags & ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE))
				{
					left->flags |= ZBX_FLAG_LLD_GROUP_BLOCK_UPDATE;

					right->flags |= ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
					right->name_orig = zbx_strdup(NULL, left->name);
					right->groupid = left->groupid;

					zbx_vector_lld_group_ptr_append(groups_out, right);
					zbx_vector_lld_group_ptr_remove_noorder(groups_in, k);
				}
				else
					right->name_inherit = zbx_strdup(NULL, left->name);
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *             group_prototypes - [IN]                                        *
 *             groups           - [IN] list of existing groups                *
 *             groups_in        - [IN] list of group candidates               *
 *             groups_out       - [IN] list of discovered groups              *
 *             lld_macros       - [IN] LLD macros defined in LLD rule         *
 *             error            - [OUT]                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_validate(const zbx_vector_lld_group_prototype_ptr_t *group_prototypes,
		zbx_vector_lld_group_ptr_t *groups, zbx_vector_lld_group_ptr_t *groups_in,
		zbx_vector_lld_group_ptr_t *groups_out, const zbx_vector_lld_macro_path_ptr_t *lld_macros, char **error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	lld_group_candidates_merge_by_name(groups_in);
	lld_group_candidates_validate(groups_in, error);
	lld_groups_merge_with_candidates(groups, groups_in, groups_out);
	lld_group_candidates_validate_db(groups_in, groups_out, error);
	lld_groups_merge_renames(group_prototypes, groups, groups_in, groups_out, lld_macros);

	/* at this point candidate leftovers contains newly discovered groups */
	zbx_vector_lld_group_ptr_append_array(groups_out, groups_in->values, groups_in->values_num);
	zbx_vector_lld_group_ptr_clear(groups_in);

	/* at this point group leftovers contains lost groups and discovery links */
	zbx_vector_lld_group_ptr_append_array(groups_out, groups->values, groups->values_num);
	zbx_vector_lld_group_ptr_clear(groups);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort group rights vector by name              *
 *                                                                            *
 ******************************************************************************/
static int	lld_group_rights_compare(const void *d1, const void *d2)
{
	const zbx_lld_group_rights_t	*r1 = *(const zbx_lld_group_rights_t * const *)d1;
	const zbx_lld_group_rights_t	*r2 = *(const zbx_lld_group_rights_t * const *)d2;

	return strcmp(r1->name, r2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: appends new item to group rights vector                           *
 *                                                                            *
 * Return value: Index of the added item.                                     *
 *                                                                            *
 ******************************************************************************/
static int	lld_group_rights_append(zbx_vector_lld_group_rights_ptr_t *group_rights, const char *name)
{
	zbx_lld_group_rights_t	*rights;

	rights = (zbx_lld_group_rights_t *)zbx_malloc(NULL, sizeof(zbx_lld_group_rights_t));
	rights->name = zbx_strdup(NULL, name);
	zbx_vector_uint64_pair_create(&rights->rights);

	zbx_vector_lld_group_rights_ptr_append(group_rights, rights);

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
static void	lld_groups_save_rights(zbx_vector_lld_group_ptr_t *groups)
{
	int					i, j;
	zbx_db_row_t				row;
	zbx_db_result_t				result;
	char					*ptr, *name, *sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_lld_group_t				*group;
	zbx_vector_str_t			group_names;
	zbx_vector_lld_group_rights_ptr_t	group_rights;
	zbx_db_insert_t				db_insert;
	zbx_lld_group_rights_t			*rights, rights_local;
	zbx_uint64_pair_t			pair;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&group_names);
	zbx_vector_lld_group_rights_ptr_create(&group_rights);

	/* make a list of direct parent group rights */
	for (i = 0; i < groups->values_num; i++)
	{
		group = groups->values[i];

		if (NULL == group->name_inherit)
		{
			if (NULL == (ptr = strrchr(group->name, '/')))
				continue;

			name = zbx_strdup(NULL, group->name);
			name[ptr - group->name] = '\0';
		}
		else
			name = zbx_strdup(NULL, group->name_inherit);

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

	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "id", "permission", "groupid", (char *)NULL);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select g.name,r.permission,r.groupid from hstgrp g,rights r"
				" where r.id=g.groupid"
				" and");

	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.name",
			(const char * const *)group_names.values, group_names.values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		rights_local.name = row[0];

		if (FAIL == (i = zbx_vector_lld_group_rights_ptr_search(&group_rights, &rights_local,
				lld_group_rights_compare)))
		{
			i = lld_group_rights_append(&group_rights, row[0]);
		}

		rights = group_rights.values[i];

		ZBX_STR2UINT64(pair.first, row[2]);
		pair.second = (zbx_uint64_t)atoi(row[1]);

		zbx_vector_uint64_pair_append(&rights->rights, pair);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_group_rights_ptr_sort(&group_rights, lld_group_rights_compare);

	/* save rights for the new groups */
	for (i = 0; i < groups->values_num; i++)
	{
		group = groups->values[i];

		if (NULL == group->name_inherit)
		{
			if (NULL == (ptr = strrchr(group->name, '/')))
				continue;

			name = zbx_strdup(NULL, group->name);
			name[ptr - group->name] = '\0';
		}
		else
			name = zbx_strdup(NULL, group->name_inherit);

		rights_local.name = name;

		if (FAIL != (j = zbx_vector_lld_group_rights_ptr_bsearch(&group_rights, &rights_local,
				lld_group_rights_compare)))
		{
			rights = group_rights.values[j];

			for (j = 0; j < rights->rights.values_num; j++)
			{
				zbx_db_insert_add_values(&db_insert, __UINT64_C(0), group->groupid,
						(int)rights->rights.values[j].second, rights->rights.values[j].first);
			}
		}

		zbx_free(name);
	}

	zbx_db_insert_autoincrement(&db_insert, "rightid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
	zbx_vector_lld_group_rights_ptr_clear_ext(&group_rights, lld_group_rights_free);
	zbx_vector_str_clear_ext(&group_names, zbx_str_free);
out:
	zbx_vector_lld_group_rights_ptr_destroy(&group_rights);
	zbx_vector_str_destroy(&group_names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: groups           - [IN/OUT] list of groups; should be sorted   *
 *                                         by groupid                         *
 *             group_prototypes - [IN] list of group prototypes; should be    *
 *                                     sorted by group_prototypeid            *
 *             error            - [OUT] error message                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_save(zbx_vector_lld_group_ptr_t *groups,
		const zbx_vector_lld_group_prototype_ptr_t *group_prototypes, char **error)
{
	int				groups_insert_num = 0, groups_update_num = 0, gd_insert_num = 0,
					gd_update_num = 0;
	zbx_db_insert_t			db_insert_group, db_insert_gdiscovery;
	zbx_vector_uint64_t		groupids;
	zbx_uint64_t			next_groupid, next_gdid;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_lld_group_ptr_t	new_groups;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* check groups for any changed to be flushed to database */

	zbx_vector_uint64_create(&groupids);

	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*group = groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
		{
			groups_insert_num++;
		}
		else
		{
			zbx_vector_uint64_append(&groupids, group->groupid);

			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
				groups_update_num++;
		}

		for (int j = 0; j < group->discovery.values_num; j++)
		{
			zbx_lld_group_discovery_t	*discovery = group->discovery.values[j];

			if (0 == (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_DISCOVERED))
				continue;

			if (0 == discovery->groupdiscoveryid)
				gd_insert_num++;
			else if (0 != (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE))
				gd_update_num++;
		}
	}

	if (0 == groups_insert_num && 0 == groups_update_num && 0 == gd_insert_num && 0 == gd_update_num)
		goto out;

	/* flush discovery changes */

	zbx_db_begin();

	/* lock the groups so their discovery records can be safely added */
	if (0 != groupids.values_num)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;
		int		index;

		zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select groupid from hstgrp where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids.values,
				groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FOR_UPDATE);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	groupid;

			ZBX_STR2UINT64(groupid, row[0]);

			if (FAIL != (index = zbx_vector_uint64_search(&groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove_noorder(&groupids, index);
			}
		}
		zbx_db_free_result(result);

		/* if existing discovered groups were removed convert them to newly discovered - */
		for (int i = 0; i < groupids.values_num; i++)
		{
			for (int j = 0; j < groups->values_num; j++)
			{
				zbx_lld_group_t	*group = groups->values[j];

				if (group->groupid == groupids.values[i])
				{
					for (int k = 0; k > group->discovery.values_num; k++)
					{
						zbx_lld_group_discovery_t	*discovery = group->discovery.values[k];

						discovery->groupdiscoveryid = 0;
						gd_insert_num++;
					}

					group->groupid = 0;
					groups_insert_num++;

					if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
					{
						groups_update_num--;
						group->flags = ZBX_FLAG_LLD_GROUP_DISCOVERED;
					}

					break;
				}
			}
		}

		sql_offset = 0;
	}

	if (0 != groups_insert_num)
	{
		next_groupid = zbx_db_get_maxid_num("hstgrp", groups_insert_num);
		zbx_db_insert_prepare(&db_insert_group, "hstgrp", "groupid", "name", "flags", NULL);

		zbx_vector_lld_group_ptr_create(&new_groups);

		/* check if other process has not already created a group with the same name */

		zbx_vector_str_t	names;
		zbx_db_result_t		result;
		zbx_db_row_t		row;

		zbx_vector_str_create(&names);

		for (int i = 0; i < groups->values_num; i++)
		{
			if (0 == groups->values[i]->groupid)
				zbx_vector_str_append(&names, groups->values[i]->name);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select groupid,name,flags from hstgrp"
					" where type=%d"
						" and",
				HOSTGROUP_TYPE_HOST);
		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
				(const char * const *)names.values, names.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FOR_UPDATE);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			for (int i = 0; i < groups->values_num; i++)
			{
				zbx_lld_group_t	*group = groups->values[i];

				if (0 == group->groupid && 0 == strcmp(group->name, row[1]))
				{
					if (0 == (atoi(row[2]) & ZBX_FLAG_DISCOVERY_CREATED))
					{
						group->flags &= (~ZBX_FLAG_LLD_GROUP_DISCOVERED);

						*error = zbx_strdcatf(*error, "Cannot discover group:"
								" group with the same name \"%s\" already exists.\n",
								group->name);
					}
					else
						ZBX_STR2UINT64(group->groupid, row[0]);
				}
			}
		}
		zbx_db_free_result(result);

		zbx_vector_str_destroy(&names);
		sql_offset = 0;
	}

	if (0 != gd_insert_num)
	{
		next_gdid = zbx_db_get_maxid_num("group_discovery", gd_insert_num);

		zbx_db_insert_prepare(&db_insert_gdiscovery, "group_discovery", "groupdiscoveryid", "groupid",
				"parent_group_prototypeid", "name", NULL);
	}

	/* first handle groups before inserting group_discovery links */

	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*group = groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
		{
			group->groupid = next_groupid++;
			zbx_db_insert_add_values(&db_insert_group, group->groupid, group->name,
								(int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_host_group_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_ADD, group->groupid,
					group->name);
			zbx_audit_host_group_update_json_add_details(ZBX_AUDIT_LLD_CONTEXT, group->groupid, group->name,
					(int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_vector_lld_group_ptr_append(&new_groups, group);
		}
		else if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hstgrp set ");
			zbx_audit_host_group_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
					group->groupid, group->name);

			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			{
				char	*name_esc = zbx_db_dyn_escape_string(group->name);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
				zbx_audit_host_group_update_json_update_name(ZBX_AUDIT_LLD_CONTEXT, group->groupid,
						group->name_orig, name_esc);

				zbx_free(name_esc);
			}
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where groupid=" ZBX_FS_UI64 ";\n",
					group->groupid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != groups_insert_num)
	{
		zbx_db_insert_execute(&db_insert_group);
		zbx_db_insert_clean(&db_insert_group);

		lld_groups_save_rights(&new_groups);
		zbx_vector_lld_group_ptr_destroy(&new_groups);
	}

	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_lld_group_t	*group = groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		for (int j = 0; j < group->discovery.values_num; j++)
		{
			zbx_lld_group_discovery_t	*discovery = group->discovery.values[j];

			if (0 == (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_DISCOVERED))
				continue;

			if (0 == discovery->groupdiscoveryid)
			{
				zbx_lld_group_prototype_t	*group_prototype, cmp =
						{.group_prototypeid = discovery->parent_group_prototypeid};
				int				k;

				if (FAIL != (k = zbx_vector_lld_group_prototype_ptr_bsearch(group_prototypes,
						&cmp, lld_group_prototype_compare_func)))
				{
					discovery->groupdiscoveryid = next_gdid++;
					group_prototype = group_prototypes->values[k];

					zbx_db_insert_add_values(&db_insert_gdiscovery, discovery->groupdiscoveryid,
							group->groupid, discovery->parent_group_prototypeid,
							group_prototype->name);
				}
				else
					THIS_SHOULD_NEVER_HAPPEN;
			}
			else if (0 != (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE))
			{
				char	delim = ' ';

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set");

				if (0 != (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_NAME))
				{
					char	*name_esc = zbx_db_dyn_escape_string(discovery->name);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cname='%s'", delim,
							name_esc);

					zbx_free(name_esc);
					delim = ',';
				}

				if (0 != (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_UPDATE_NAME))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cgroupid=" ZBX_FS_UI64,
							delim, group->groupid);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where groupdiscoveryid="
						ZBX_FS_UI64 ";\n", discovery->groupdiscoveryid);

				zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}
		}
	}

	if (0 != gd_insert_num)
	{
		zbx_db_insert_execute(&db_insert_gdiscovery);
		zbx_db_insert_clean(&db_insert_gdiscovery);
	}

	if (0 != groups_update_num || 0 != gd_update_num)
	{
		zbx_db_execute("%s", sql);
	}

	zbx_db_commit();

	zbx_free(sql);
out:
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves list of host macros which should be present on each     *
 *          discovered host.                                                  *
 *                                                                            *
 * Parameters: lld_ruleid - [IN]                                              *
 *             hostmacros - [OUT]                                             *
 *                                                                            *
 ******************************************************************************/
static void	lld_masterhostmacros_get(zbx_uint64_t lld_ruleid, zbx_vector_lld_hostmacro_ptr_t *hostmacros)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_hostmacro_t	*hostmacro;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select hm.macro,hm.value,hm.description,hm.type"
			" from hostmacro hm,items i"
			" where hm.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
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
		hostmacro->automatic = ZBX_USERMACRO_AUTOMATIC;

		zbx_vector_lld_hostmacro_ptr_append(hostmacros, hostmacro);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares name of host macros for search in vector                 *
 *                                                                            *
 * Parameters: d1 - [IN] first zbx_lld_hostmacro_t                            *
 *             d2 - [IN] second zbx_lld_hostmacro_t                           *
 *                                                                            *
 * Return value: 0 if name of macros are equal                                *
 *                                                                            *
 ******************************************************************************/
static int	macro_str_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_hostmacro_t *hostmacro1 = *(const zbx_lld_hostmacro_t * const *)d1;
	const zbx_lld_hostmacro_t *hostmacro2 = *(const zbx_lld_hostmacro_t * const *)d2;

	return strcmp(hostmacro1->macro, hostmacro2->macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves list of host macros which should be present on each     *
 *          discovered host.                                                  *
 *                                                                            *
 * Parameters: parent_hostid    - [IN] host prototype id                      *
 *             masterhostmacros - [IN]                                        *
 *             hostmacros       - [OUT]                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostmacros_get(zbx_uint64_t parent_hostid, zbx_vector_lld_hostmacro_ptr_t *masterhostmacros,
		zbx_vector_lld_hostmacro_ptr_t *hostmacros)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_hostmacro_t	*hostmacro;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select hm.macro,hm.value,hm.description,hm.type"
			" from hostmacro hm"
			" where hm.hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
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
		hostmacro->automatic = ZBX_USERMACRO_AUTOMATIC;
		hostmacro->flags = 0;

		zbx_vector_lld_hostmacro_ptr_append(hostmacros, hostmacro);
	}
	zbx_db_free_result(result);

	for (i = 0; i < masterhostmacros->values_num; i++)
	{
		const zbx_lld_hostmacro_t	*masterhostmacro;

		if (FAIL != zbx_vector_lld_hostmacro_ptr_search(hostmacros, masterhostmacros->values[i],
				macro_str_compare_func))
		{
			continue;
		}

		hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

		masterhostmacro = masterhostmacros->values[i];
		hostmacro->hostmacroid = 0;
		hostmacro->macro = zbx_strdup(NULL, masterhostmacro->macro);
		hostmacro->value = zbx_strdup(NULL, masterhostmacro->value);
		hostmacro->value_orig = NULL;
		hostmacro->description = zbx_strdup(NULL, masterhostmacro->description);
		hostmacro->description_orig = NULL;
		hostmacro->type = masterhostmacro->type;
		hostmacro->type_orig = hostmacro->type;
		hostmacro->automatic = masterhostmacro->automatic;
		hostmacro->flags = 0;
		zbx_vector_lld_hostmacro_ptr_append(hostmacros, hostmacro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_hostmacro_make(zbx_vector_lld_hostmacro_ptr_t *hostmacros, zbx_uint64_t hostmacroid,
		const char *macro, const char *value, const char *description, unsigned char type,
		unsigned char automatic)
{
	zbx_lld_hostmacro_t	*hostmacro;
	int			i;

	for (i = 0; i < hostmacros->values_num; i++)
	{
		hostmacro = hostmacros->values[i];

		/* check if host macro has already been added */
		if (0 == hostmacro->hostmacroid && 0 == strcmp(hostmacro->macro, macro))
		{
			hostmacro->hostmacroid = hostmacroid;

			/* do not update manual macros */
			if (ZBX_USERMACRO_MANUAL == automatic)
				return;

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

	/* do not remove manual macros */
	if (ZBX_USERMACRO_MANUAL == automatic)
		return;

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
	hostmacro->automatic = 0;

	zbx_vector_lld_hostmacro_ptr_append(hostmacros, hostmacro);
}

#undef ZBX_USERMACRO_MANUAL
#undef ZBX_USERMACRO_AUTOMATIC

/*******************************************************************************
 *                                                                             *
 * Parameters: hostmacros - [IN] List of host macros which should be present   *
 *                               on each discovered host.                      *
 *             hosts      - [IN/OUT] list of hosts, should be sorted by hostid *
 *             lld_macros - [IN]                                               *
 *                                                                             *
 ******************************************************************************/
static void	lld_hostmacros_make(const zbx_vector_lld_hostmacro_ptr_t *hostmacros, zbx_vector_lld_host_ptr_t *hosts,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostmacroid, hostid;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_lld_hostmacro_ptr_reserve(&host->new_hostmacros, (size_t)hostmacros->values_num);

		for (j = 0; j < hostmacros->values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

			hostmacro->hostmacroid = 0;
			hostmacro->macro = zbx_strdup(NULL, (hostmacros->values[j])->macro);
			hostmacro->value = zbx_strdup(NULL, (hostmacros->values[j])->value);
			hostmacro->value_orig = NULL;
			hostmacro->type = (hostmacros->values[j])->type;
			hostmacro->type_orig = (hostmacros->values[j])->type_orig;
			hostmacro->description = zbx_strdup(NULL, (hostmacros->values[j])->description);
			hostmacro->description_orig = NULL;
			hostmacro->automatic = (hostmacros->values[j])->automatic;
			hostmacro->flags = 0x00;
			zbx_substitute_lld_macros(&hostmacro->value, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_substitute_lld_macros(&hostmacro->description, host->jp_row, lld_macros, ZBX_MACRO_ANY,
					NULL, 0);

			zbx_vector_lld_hostmacro_ptr_append(&host->new_hostmacros, hostmacro);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostmacroid,hostid,macro,value,description,type,automatic"
				" from hostmacro"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = zbx_db_select("%s", sql);

		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			unsigned char	type, automatic;

			ZBX_STR2UINT64(hostid, row[1]);

			zbx_lld_host_t	cmp = {.hostid = hostid};

			if (FAIL == (i = zbx_vector_lld_host_ptr_bsearch(hosts, &cmp, lld_host_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = hosts->values[i];

			ZBX_STR2UINT64(hostmacroid, row[0]);
			ZBX_STR2UCHAR(type, row[5]);
			ZBX_STR2UCHAR(automatic, row[6]);

			lld_hostmacro_make(&host->new_hostmacros, hostmacroid, row[2], row[3], row[4], type, automatic);
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves list of host tags which should be present on each       *
 *          discovered host.                                                  *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype id                         *
 *             tags          - [OUT] list of host tags                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_proto_tags_get(zbx_uint64_t parent_hostid, zbx_vector_db_tag_ptr_t *tags)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_tag_t	*tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select tag,value"
			" from host_tag"
			" where hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		tag = zbx_db_tag_create(row[0], row[1]);
		zbx_vector_db_tag_ptr_append(tags, tag);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/**********************************************************************************
 *                                                                                *
 * Purpose: gets templates from host prototype                                    *
 *                                                                                *
 * Parameters: parent_hostid - [IN] host prototype id                             *
 *             hosts         - [IN/OUT] list of hosts, should be sorted by hostid *
 *                                                                                *
 **********************************************************************************/
static void	lld_templates_make(zbx_uint64_t parent_hostid, zbx_vector_lld_host_ptr_t *hosts)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	templateids, hostids;
	zbx_uint64_t		templateid, hostid;
	zbx_lld_host_t		*host;
	int			i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_create(&hostids);

	/* select templates which should be linked */

	result = zbx_db_select("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, parent_hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		zbx_vector_uint64_append(&templateids, templateid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* select list of already created hosts */

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->lnk_templateids, (size_t)templateids.values_num);
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
				"select hostid,templateid,link_type"
				" from hosts_templates"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = zbx_db_select("%s", sql);

		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			int	link_type;

			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(templateid, row[1]);
			link_type = atoi(row[2]);

			zbx_lld_host_t	cmp = {.hostid = hostid};

			if (FAIL == (i = zbx_vector_lld_host_ptr_bsearch(hosts, &cmp, lld_host_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = hosts->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->lnk_templateids, templateid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* templates which should be unlinked */
				if (ZBX_TEMPLATE_LINK_LLD == link_type)
					zbx_vector_uint64_append(&host->del_templateids, templateid);
			}
			else
			{
				/* templates which are already linked */
				if (ZBX_TEMPLATE_LINK_MANUAL == link_type)
					zbx_vector_uint64_append(&host->del_templateids, templateid);
				else
					zbx_vector_uint64_remove(&host->lnk_templateids, i);
			}
		}
		zbx_db_free_result(result);

		for (i = 0; i < hosts->values_num; i++)
		{
			host = hosts->values[i];

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
 * Purpose: prepares SQL for update record of interface_snmp table            *
 *                                                                            *
 * Parameters: hostid      - [IN]                                             *
 *             interfaceid - [IN] SNMP interface id                           *
 *             snmp        - [IN] SNMP values for update                      *
 *             sql         - [IN/OUT] SQL string                              *
 *             sql_alloc   - [IN/OUT] size of SQL string                      *
 *             sql_offset  - [IN/OUT] offset in SQL string                    *
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

		zbx_audit_host_update_json_update_interface_version(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->version_orig, snmp->version);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sbulk=%d", d, (int)snmp->bulk);
		d = ",";

		zbx_audit_host_update_json_update_interface_bulk(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->bulk_orig, snmp->bulk);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY))
	{
		value_esc = zbx_db_dyn_escape_string(snmp->community);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scommunity='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_community(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->community_orig, snmp->community);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME))
	{
		value_esc = zbx_db_dyn_escape_string(snmp->securityname);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssecurityname='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_securityname(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->securityname_orig, snmp->securityname);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssecuritylevel=%d", d, (int)snmp->securitylevel);
		d = ",";

		zbx_audit_host_update_json_update_interface_securitylevel(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->securitylevel_orig, snmp->securitylevel);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS))
	{
		value_esc = zbx_db_dyn_escape_string(snmp->authpassphrase);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sauthpassphrase='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_authpassphrase(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
						snmp->authpassphrase_orig, snmp->authpassphrase);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS))
	{
		value_esc = zbx_db_dyn_escape_string(snmp->privpassphrase);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sprivpassphrase='%s'", d, value_esc);
		zbx_free(value_esc);
		d = ",";

		zbx_audit_host_update_json_update_interface_privpassphrase(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
						snmp->privpassphrase_orig, snmp->privpassphrase);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sauthprotocol=%d", d, (int)snmp->authprotocol);
		d = ",";

		zbx_audit_host_update_json_update_interface_authprotocol(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->authprotocol_orig, snmp->authprotocol);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sprivprotocol=%d", d, (int)snmp->privprotocol);
		d = ",";

		zbx_audit_host_update_json_update_interface_privprotocol(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->privprotocol_orig, snmp->privprotocol);
	}

	if (0 != (snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT))
	{
		value_esc = zbx_db_dyn_escape_string(snmp->contextname);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scontextname='%s'", d, value_esc);
		zbx_free(value_esc);

		zbx_audit_host_update_json_update_interface_contextname(ZBX_AUDIT_LLD_CONTEXT, hostid, interfaceid,
				snmp->contextname_orig, snmp->contextname);
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where interfaceid=" ZBX_FS_UI64 ";\n", interfaceid);
}

/******************************************************************************
 *                                                                            *
 * Parameters: parent_hostid    - [IN]                                        *
 *             hosts            - [IN]                                        *
 *             host_proto       - [IN]                                        *
 *             monitored_by     - [IN]                                        *
 *             proxyid          - [IN]                                        *
 *             proxy_groupid    - [IN]                                        *
 *             ipmi_authtype    - [IN]                                        *
 *             ipmi_privilege   - [IN]                                        *
 *             ipmi_username    - [IN]                                        *
 *             ipmi_password    - [IN]                                        *
 *             tls_connect      - [IN]                                        *
 *             tls_accept       - [IN]                                        *
 *             tls_issuer       - [IN] TLS cert issuer                        *
 *             tls_subject      - [IN] TLS cert subject                       *
 *             tls_psk_identity - [IN]                                        *
 *             tls_psk          - [IN]                                        *
 *             hgsets           - [IN]                                        *
 *             permissions      - [IN]                                        *
 *             del_hostgroupids - [IN] host groups which should be deleted    *
 *             del_hgsetids     - [IN] host groups sets which should be       *
 *                                     deleted                                *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_save(zbx_uint64_t parent_hostid, zbx_vector_lld_host_ptr_t *hosts, const char *host_proto,
		unsigned char monitored_by, zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid, signed char ipmi_authtype,
		unsigned char ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		unsigned char tls_connect, unsigned char tls_accept, const char *tls_issuer, const char *tls_subject,
		const char *tls_psk_identity, const char *tls_psk, zbx_vector_lld_hgset_ptr_t *hgsets,
		zbx_vector_lld_permission_t *permissions, const zbx_vector_uint64_t *del_hostgroupids,
		const zbx_vector_uint64_t *del_hgsetids)
{
	int			i, j, new_hosts = 0, new_host_inventories = 0, upd_hosts = 0, new_hostgroups = 0,
				new_hostmacros = 0, upd_hostmacros = 0, new_interfaces = 0, upd_interfaces = 0,
				new_snmp = 0, upd_snmp = 0, new_tags = 0, upd_tags = 0, new_hgsets = 0,
				new_host_hgsets = 0, upd_host_hgsets = 0;
	zbx_uint64_t		hosttagid = 0;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro;
	zbx_lld_interface_t	*interface;
	zbx_vector_uint64_t	upd_manual_host_inventory_hostids, upd_auto_host_inventory_hostids,
				del_host_inventory_hostids, del_interfaceids,
				del_snmp_ids, del_hostmacroids, del_tagids, used_hgsetids;
	zbx_uint64_t		hostid = 0, hostgroupid = 0, hostmacroid = 0, interfaceid = 0, hgsetid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *value_esc;
	size_t			sql1_alloc = 0, sql1_offset = 0,
				sql2_alloc = 0, sql2_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_hdiscovery, db_insert_hinventory, db_insert_hgroups,
				db_insert_hmacro, db_insert_interface, db_insert_idiscovery, db_insert_snmp,
				db_insert_tag, db_insert_host_rtdata, db_insert_hgset, db_insert_hgset_group,
				db_insert_host_hgset, db_insert_permission;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&upd_manual_host_inventory_hostids);
	zbx_vector_uint64_create(&upd_auto_host_inventory_hostids);
	zbx_vector_uint64_create(&del_host_inventory_hostids);
	zbx_vector_uint64_create(&del_interfaceids);
	zbx_vector_uint64_create(&del_hostmacroids);
	zbx_vector_uint64_create(&del_snmp_ids);
	zbx_vector_uint64_create(&del_tagids);
	zbx_vector_uint64_create(&used_hgsetids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

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
			zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, host->hostid,
					(NULL == host->host_orig) ? host->host : host->host_orig);

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
				upd_hosts++;

			if (host->inventory_mode_orig != host->inventory_mode)
			{
				zbx_audit_host_update_json_update_inventory_mode(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
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

		if (ZBX_LLD_HOST_HGSET_ACTION_ADD == host->hgset_action)
			new_host_hgsets++;
		else if (ZBX_LLD_HOST_HGSET_ACTION_UPDATE == host->hgset_action)
			upd_host_hgsets++;

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = host->interfaces.values[j];

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

				zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_interface(ZBX_AUDIT_LLD_CONTEXT,
						host->hostid, interface->interfaceid);
			}

			if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_REMOVE))
			{
				zbx_vector_uint64_append(&del_snmp_ids, interface->interfaceid);

				zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_interface(ZBX_AUDIT_LLD_CONTEXT,
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
			hostmacro = host->new_hostmacros.values[j];

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

				zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
						host->hostid, (NULL == host->host_orig) ? host->host : host->host_orig);

				zbx_audit_host_update_json_delete_hostmacro(ZBX_AUDIT_LLD_CONTEXT,
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

				zbx_audit_host_prototype_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
						host->hostid, host->host);
				zbx_audit_host_update_json_delete_tag(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
						host->tags.values[j]->tagid);
			}
		}
	}

	for (i = 0; i < hgsets->values_num; i++)
	{
		zbx_lld_hgset_t	*hgset = hgsets->values[i];

		if (ZBX_LLD_HGSET_OPT_INSERT == hgset->opt)
			new_hgsets++;
		else if (ZBX_LLD_HGSET_OPT_REUSE == hgset->opt)
			zbx_vector_uint64_append(&used_hgsetids, hgset->hgsetid);
	}

	if (0 == new_hosts && 0 == new_host_inventories && 0 == upd_hosts && 0 == upd_interfaces &&
			0 == upd_hostmacros && 0 == new_hostgroups && 0 == new_hostmacros && 0 == new_interfaces &&
			0 == del_hostgroupids->values_num && 0 == del_hostmacroids.values_num &&
			0 == upd_auto_host_inventory_hostids.values_num &&
			0 == upd_manual_host_inventory_hostids.values_num &&
			0 == del_host_inventory_hostids.values_num && 0 == del_interfaceids.values_num &&
			0 == new_snmp && 0 == upd_snmp && 0 == del_snmp_ids.values_num &&
			0 == new_tags && 0 == upd_tags && 0 == del_tagids.values_num &&
			0 == new_hgsets && 0 == del_hgsetids->values_num && 0 == permissions->values_num &&
			0 == new_host_hgsets && 0 == upd_host_hgsets)
	{
		goto out;
	}

	zbx_db_begin();

	if (SUCCEED != zbx_db_lock_hostid(parent_hostid))
	{
		/* the host prototype was removed while processing lld rule */
		zbx_db_rollback();
		goto out;
	}

	if (0 != used_hgsetids.values_num && SUCCEED != zbx_db_lock_hgsetids(&used_hgsetids))
	{
		zbx_db_rollback();
		goto out;
	}

	if (0 != new_hosts)
	{
		hostid = zbx_db_get_maxid_num("hosts", new_hosts);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "host", "name", "proxyid", "proxy_groupid",
				"ipmi_authtype", "ipmi_privilege", "ipmi_username", "ipmi_password", "status", "flags",
				"tls_connect", "tls_accept", "tls_issuer", "tls_subject", "tls_psk_identity", "tls_psk",
				"custom_interfaces", "monitored_by", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_hdiscovery, "host_discovery", "hostid", "parent_hostid", "host",
				(char *)NULL);
		zbx_db_insert_prepare(&db_insert_host_rtdata, "host_rtdata", "hostid", "active_available",
				(char *)NULL);
	}

	if (0 != new_host_hgsets)
	{
		zbx_db_insert_prepare(&db_insert_host_hgset, "host_hgset", "hostid", "hgsetid", (char *)NULL);
	}

	if (0 != new_host_inventories)
	{
		zbx_db_insert_prepare(&db_insert_hinventory, "host_inventory", "hostid", "inventory_mode",
				(char *)NULL);
	}

	if (0 != new_hostgroups)
	{
		hostgroupid = zbx_db_get_maxid_num("hosts_groups", new_hostgroups);

		zbx_db_insert_prepare(&db_insert_hgroups, "hosts_groups", "hostgroupid", "hostid", "groupid",
				(char *)NULL);
	}

	if (0 != new_hgsets)
	{
		hgsetid = zbx_db_get_maxid_num("hgset", new_hgsets);

		zbx_db_insert_prepare(&db_insert_hgset, "hgset", "hgsetid", "hash", (char *)NULL);
		zbx_db_insert_prepare(&db_insert_hgset_group, "hgset_group", "hgsetid", "groupid", (char *)NULL);
	}

	if (0 != permissions->values_num)
	{
		zbx_db_insert_prepare(&db_insert_permission, "permission", "ugsetid", "hgsetid", "permission",
				(char *)NULL);
	}

	if (0 != new_hostmacros)
	{
		hostmacroid = zbx_db_get_maxid_num("hostmacro", new_hostmacros);

		zbx_db_insert_prepare(&db_insert_hmacro, "hostmacro", "hostmacroid", "hostid", "macro", "value",
				"description", "type", "automatic", (char *)NULL);
	}

	if (0 != new_interfaces)
	{
		interfaceid = zbx_db_get_maxid_num("interface", new_interfaces);

		zbx_db_insert_prepare(&db_insert_interface, "interface", "interfaceid", "hostid", "type", "main",
				"useip", "ip", "dns", "port", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_idiscovery, "interface_discovery", "interfaceid",
				"parent_interfaceid", (char *)NULL);
	}

	if (0 != new_snmp)
	{
		zbx_db_insert_prepare(&db_insert_snmp, "interface_snmp", "interfaceid", "version", "bulk", "community",
				"securityname", "securitylevel", "authpassphrase", "privpassphrase", "authprotocol",
				"privprotocol", "contextname", (char *)NULL);
	}

	if (0 != new_tags)
	{
		hosttagid = zbx_db_get_maxid_num("host_tag", new_tags);

		zbx_db_insert_prepare(&db_insert_tag, "host_tag", "hosttagid", "hostid", "tag", "value", "automatic",
				(char *)NULL);
	}

	for (i = 0; i < hgsets->values_num; i++)
	{
		zbx_lld_hgset_t	*hgset = hgsets->values[i];

		if (ZBX_LLD_HGSET_OPT_INSERT != hgset->opt)
			continue;

		hgset->hgsetid = hgsetid++;
		zbx_db_insert_add_values(&db_insert_hgset, hgset->hgsetid, hgset->hash_str);

		for (j = 0; j < hgset->hgroupids.values_num; j++)
			zbx_db_insert_add_values(&db_insert_hgset_group, hgset->hgsetid, hgset->hgroupids.values[j]);
	}

	for (i = 0; i < permissions->values_num; i++)
	{
		zbx_lld_permission_t	*permission = &permissions->values[i];

		zbx_db_insert_add_values(&db_insert_permission, permission->ugsetid, permission->hgset->hgsetid,
				permission->permission);
	}

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
		{
			host->hostid = hostid++;

			zbx_db_insert_add_values(&db_insert, host->hostid, host->host, host->name, proxyid,
					proxy_groupid, (int)ipmi_authtype, (int)ipmi_privilege, ipmi_username,
					ipmi_password, (int)host->status, (int)ZBX_FLAG_DISCOVERY_CREATED,
					(int)tls_connect, (int)tls_accept, tls_issuer, tls_subject, tls_psk_identity,
					tls_psk, (int)host->custom_interfaces, (int)monitored_by);

			zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_ADD, host->hostid,
					host->host);

			zbx_db_insert_add_values(&db_insert_hdiscovery, host->hostid, parent_hostid, host_proto);
			zbx_db_insert_add_values(&db_insert_host_rtdata, host->hostid, ZBX_INTERFACE_AVAILABLE_UNKNOWN);

			if (HOST_INVENTORY_DISABLED != host->inventory_mode)
			{
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid,
						(int)host->inventory_mode);
			}

			zbx_audit_host_update_json_add_details(ZBX_AUDIT_LLD_CONTEXT, host->hostid, host->host,
					monitored_by, proxyid, proxy_groupid, (int)ipmi_authtype, (int)ipmi_privilege,
					ipmi_username, (int)host->status, (int)ZBX_FLAG_DISCOVERY_CREATED,
					(int)tls_connect, (int)tls_accept, tls_issuer, tls_subject,
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
					value_esc = zbx_db_dyn_escape_string(host->host);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "host='%s'", value_esc);
					d = ",";

					zbx_audit_host_update_json_update_host(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							host->host_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
				{
					value_esc = zbx_db_dyn_escape_string(host->name);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sname='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_name(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							host->name_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_MONITORED_BY))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%smonitored_by=%d", d, (int)monitored_by);
					d = ",";

					zbx_audit_host_update_json_update_monitored_by(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, (int)host->monitored_by_orig, (int)monitored_by);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_PROXY))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sproxyid=%s", d, zbx_db_sql_id_ins(proxyid));
					d = ",";

					zbx_audit_host_update_json_update_proxyid(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							host->proxyid_orig, proxyid);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_PROXY_GROUP))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sproxy_groupid=%s", d, zbx_db_sql_id_ins(proxy_groupid));
					d = ",";

					zbx_audit_host_update_json_update_proxy_groupid(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->proxy_groupid_orig, proxy_groupid);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_authtype=%d", d, (int)ipmi_authtype);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_authtype(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, (int)host->ipmi_authtype_orig,
							(int)ipmi_authtype);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_privilege=%d", d, (int)ipmi_privilege);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_privilege(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->ipmi_privilege_orig, (int)ipmi_privilege);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER))
				{
					value_esc = zbx_db_dyn_escape_string(ipmi_username);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_username='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_username(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->ipmi_username_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS))
				{
					value_esc = zbx_db_dyn_escape_string(ipmi_password);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_password='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_ipmi_password(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, (0 == strcmp("", host->ipmi_password_orig) ?
							"" : ZBX_MACRO_SECRET_MASK),
							(0 == strcmp("", ipmi_password) ? "" : ZBX_MACRO_SECRET_MASK));

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_CONNECT))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_connect=%d", d, tls_connect);
					d = ",";

					zbx_audit_host_update_json_update_tls_connect(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->tls_connect_orig, (int)tls_connect);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_ACCEPT))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_accept=%d", d, tls_accept);
					d = ",";

					zbx_audit_host_update_json_update_tls_accept(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->tls_accept_orig, (int)tls_accept);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_ISSUER))
				{
					value_esc = zbx_db_dyn_escape_string(tls_issuer);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_issuer='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_tls_issuer(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->tls_issuer_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_SUBJECT))
				{
					value_esc = zbx_db_dyn_escape_string(tls_subject);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_subject='%s'", d, value_esc);
					d = ",";

					zbx_audit_host_update_json_update_tls_subject(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->tls_subject_orig, value_esc);

					zbx_free(value_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK_IDENTITY))
				{
					value_esc = zbx_db_dyn_escape_string(tls_psk_identity);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_psk_identity='%s'", d, value_esc);
					d = ",";
					zbx_free(value_esc);

					zbx_audit_host_update_json_update_tls_psk_identity(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, (0 == strcmp("", host->tls_psk_identity_orig) ?
							"" : ZBX_MACRO_SECRET_MASK),
							(0 == strcmp("", tls_psk_identity) ?
							"" : ZBX_MACRO_SECRET_MASK));
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_TLS_PSK))
				{
					value_esc = zbx_db_dyn_escape_string(tls_psk);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%stls_psk='%s'", d, value_esc);
					d = ",";
					zbx_free(value_esc);

					zbx_audit_host_update_json_update_tls_psk(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							(0 == strcmp("", host->tls_psk_orig) ?
							"" : ZBX_MACRO_SECRET_MASK),
							(0 == strcmp("", tls_psk) ? "" : ZBX_MACRO_SECRET_MASK));
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_CUSTOM_INTERFACES))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%scustom_interfaces=%d", d, (int)host->custom_interfaces);

					zbx_audit_host_update_json_update_custom_interfaces(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, host->custom_interfaces_orig,
							(int)host->custom_interfaces);
				}

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, " where hostid=" ZBX_FS_UI64 ";\n",
						host->hostid);
			}

			if (host->inventory_mode_orig != host->inventory_mode &&
					HOST_INVENTORY_DISABLED == host->inventory_mode_orig)
			{
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid,
						(int)host->inventory_mode);
			}

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			{
				value_esc = zbx_db_dyn_escape_string(host_proto);

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update host_discovery"
						" set host='%s'"
						" where hostid=" ZBX_FS_UI64 ";\n",
						value_esc, host->hostid);

				zbx_free(value_esc);
			}
		}

		if (ZBX_LLD_HOST_HGSET_ACTION_ADD == host->hgset_action)
		{
			zbx_db_insert_add_values(&db_insert_host_hgset, host->hostid, host->hgset->hgsetid);
		}
		else if (ZBX_LLD_HOST_HGSET_ACTION_UPDATE == host->hgset_action)
		{
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"update host_hgset"
					" set hgsetid=" ZBX_FS_UI64
					" where hostid=" ZBX_FS_UI64 ";\n",
					host->hgset->hgsetid, host->hostid);
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

				zbx_audit_host_update_json_add_interfaces(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
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
					zbx_audit_host_update_json_update_interface_type(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid, interface->type_orig,
							interface->type);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%smain=%d",
							d, (int)interface->main);
					d = ",";
					zbx_audit_host_update_json_update_interface_main(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid, interface->main_orig,
							interface->main);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%suseip=%d",
							d, (int)interface->useip);
					d = ",";
					zbx_audit_host_update_json_update_interface_useip(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid,
							(uint64_t)interface->useip_orig, interface->useip);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_IP))
				{
					value_esc = zbx_db_dyn_escape_string(interface->ip);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sip='%s'", d, value_esc);
					zbx_free(value_esc);
					d = ",";
					zbx_audit_host_update_json_update_interface_ip(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid, interface->ip_orig,
							interface->ip);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS))
				{
					value_esc = zbx_db_dyn_escape_string(interface->dns);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdns='%s'", d,
							value_esc);
					zbx_free(value_esc);
					d = ",";
					zbx_audit_host_update_json_update_interface_dns(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid, interface->dns_orig,
							interface->dns);
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT))
				{
					value_esc = zbx_db_dyn_escape_string(interface->port);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sport='%s'",
							d, value_esc);
					zbx_audit_host_update_json_update_interface_port(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->interfaceid,
							atoi(interface->port_orig), atoi(interface->port));
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
					zbx_audit_host_update_json_add_snmp_interface(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, interface->data.snmp->version,
							interface->data.snmp->bulk,
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
			zbx_audit_hostgroup_update_json_add_group(ZBX_AUDIT_LLD_CONTEXT, host->hostid, hostgroupid,
					host->new_groupids.values[j]);
			hostgroupid++;
		}

		for (j = 0; j < host->new_hostmacros.values_num; j++)
		{
			hostmacro = host->new_hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
			{
				zbx_db_insert_add_values(&db_insert_hmacro, hostmacroid, host->hostid,
						hostmacro->macro, hostmacro->value, hostmacro->description,
						(int)hostmacro->type, (int)hostmacro->automatic);

				zbx_audit_host_update_json_add_hostmacro(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
						ZBX_AUDIT_RESOURCE_HOST, hostmacroid, hostmacro->macro,
						(ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type) ?
						ZBX_MACRO_SECRET_MASK : hostmacro->value, hostmacro->description,
						(int)hostmacro->type, (int)hostmacro->automatic);
				hostmacroid++;
			}
			else if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hostmacro set ");

				zbx_audit_host_update_json_update_hostmacro_create_entry(ZBX_AUDIT_LLD_CONTEXT,
						host->hostid, hostmacro->hostmacroid);

				if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_VALUE))
				{
					value_esc = zbx_db_dyn_escape_string(hostmacro->value);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "value='%s'", value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_update_json_update_hostmacro_value(
							ZBX_AUDIT_LLD_CONTEXT, host->hostid, hostmacro->hostmacroid,
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
					value_esc = zbx_db_dyn_escape_string(hostmacro->description);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdescription='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_update_json_update_hostmacro_description(ZBX_AUDIT_LLD_CONTEXT,
							host->hostid, hostmacro->hostmacroid,
							hostmacro->description_orig, hostmacro->description);
				}
				if (0 != (hostmacro->flags & ZBX_FLAG_LLD_HMACRO_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%stype=%d",
							d, hostmacro->type);

					zbx_audit_host_update_json_update_hostmacro_type(ZBX_AUDIT_LLD_CONTEXT,
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
				zbx_db_insert_add_values(&db_insert_tag, hosttagid, host->hostid, tag->tag, tag->value,
						tag->automatic);
				zbx_audit_host_update_json_add_tag(ZBX_AUDIT_LLD_CONTEXT, host->hostid, hosttagid,
						tag->tag, tag->value, tag->automatic);
				hosttagid++;
			}
			else if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
			{
				char	delim = ' ';

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update host_tag set");

				zbx_audit_host_update_json_update_tag_create_entry(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
						tag->tagid);

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
				{
					value_esc = zbx_db_dyn_escape_string(tag->tag);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%ctag='%s'", delim,
							value_esc);
					delim = ',';

					zbx_audit_host_update_json_update_tag_tag(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							tag->tagid, tag->tag_orig, value_esc);
					zbx_free(value_esc);
				}

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
				{
					value_esc = zbx_db_dyn_escape_string(tag->value);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%cvalue='%s'", delim,
							value_esc);
					delim = ',';

					zbx_audit_host_update_json_update_tag_value(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							tag->tagid, tag->value_orig, value_esc);
					zbx_free(value_esc);
				}

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%cautomatic=%d", delim,
							tag->automatic);

					zbx_audit_host_update_json_update_tag_type(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
							tag->tagid, tag->automatic_orig, tag->automatic);
				}

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where hosttagid=" ZBX_FS_UI64 ";\n", tag->tagid);
			}
		}
	}

	if (0 != new_hgsets)
	{
		zbx_db_insert_execute(&db_insert_hgset);
		zbx_db_insert_clean(&db_insert_hgset);

		zbx_db_insert_execute(&db_insert_hgset_group);
		zbx_db_insert_clean(&db_insert_hgset_group);
	}

	if (0 != permissions->values_num)
	{
		zbx_db_insert_execute(&db_insert_permission);
		zbx_db_insert_clean(&db_insert_permission);
	}

	if (0 != new_hosts)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_hdiscovery);
		zbx_db_insert_clean(&db_insert_hdiscovery);

		zbx_db_insert_execute(&db_insert_host_rtdata);
		zbx_db_insert_clean(&db_insert_host_rtdata);
	}

	if (0 != new_host_hgsets)
	{
		zbx_db_insert_execute(&db_insert_host_hgset);
		zbx_db_insert_clean(&db_insert_host_hgset);
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
		(void)zbx_db_flush_overflowed_sql(sql1, sql1_offset);
		zbx_free(sql1);
	}

	if (0 != del_hostgroupids->values_num || 0 != del_hostmacroids.values_num ||
			0 != upd_auto_host_inventory_hostids.values_num ||
			0 != upd_manual_host_inventory_hostids.values_num ||
			0 != del_host_inventory_hostids.values_num ||
			0 != del_interfaceids.values_num || 0 != del_snmp_ids.values_num ||
			0 != del_tagids.values_num || 0 != del_hgsetids->values_num)
	{

		if (0 != del_hgsetids->values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hgset where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hgsetid",
					del_hgsetids->values, del_hgsetids->values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_hostgroupids->values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hosts_groups where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostgroupid",
					del_hostgroupids->values, del_hostgroupids->values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_hostmacroids.values_num)
		{
			zbx_vector_uint64_sort(&del_hostmacroids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hostmacro where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostmacroid",
					del_hostmacroids.values, del_hostmacroids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != upd_manual_host_inventory_hostids.values_num)
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
				"update host_inventory set inventory_mode=%d where", HOST_INVENTORY_MANUAL);
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					upd_manual_host_inventory_hostids.values,
					upd_manual_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != upd_auto_host_inventory_hostids.values_num)
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
				"update host_inventory set inventory_mode=%d where", HOST_INVENTORY_AUTOMATIC);
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					upd_auto_host_inventory_hostids.values,
					upd_auto_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_host_inventory_hostids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_inventory where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					del_host_inventory_hostids.values, del_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_snmp_ids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface_snmp where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
					del_snmp_ids.values, del_snmp_ids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_interfaceids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
					del_interfaceids.values, del_interfaceids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_tagids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_tag where");
			zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hosttagid", del_tagids.values,
					del_tagids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		zbx_db_execute("%s", sql2);
		zbx_free(sql2);
	}

	zbx_db_commit();
out:
	zbx_vector_uint64_destroy(&used_hgsetids);
	zbx_vector_uint64_destroy(&del_tagids);
	zbx_vector_uint64_destroy(&del_snmp_ids);
	zbx_vector_uint64_destroy(&del_interfaceids);
	zbx_vector_uint64_destroy(&del_hostmacroids);
	zbx_vector_uint64_destroy(&del_host_inventory_hostids);
	zbx_vector_uint64_destroy(&upd_auto_host_inventory_hostids);
	zbx_vector_uint64_destroy(&upd_manual_host_inventory_hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_templates_link(const zbx_vector_lld_host_ptr_t *hosts, char **error)
{
	zbx_lld_host_t	*host;
	char		*err = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_db_begin();

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->del_templateids.values_num)
		{
			if (SUCCEED != zbx_db_delete_template_elements(host->hostid, host->host,
					&host->del_templateids, ZBX_AUDIT_LLD_CONTEXT, &err))
			{
				*error = zbx_strdcatf(*error, "Cannot unlink template from host \"%s\": %s.\n",
						host->name, err);
				zbx_free(err);
			}
		}

		if (0 != host->lnk_templateids.values_num)
		{
			if (SUCCEED != zbx_db_copy_template_elements(host->hostid, &host->lnk_templateids,
					ZBX_TEMPLATE_LINK_LLD, ZBX_AUDIT_LLD_CONTEXT, &err))
			{
				*error = zbx_strdcatf(*error, "Cannot link template(s) to host \"%s\": %s.\n",
						host->name, err);
				zbx_free(err);
			}
		}
	}

	zbx_db_commit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	lld_host_disable_validate(zbx_uint64_t hostid)
{
	int		ret;
	char		*sql;
	zbx_db_result_t	result;

	sql = zbx_dsprintf(NULL, "select null from hosts where status=%d and hostid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED, hostid);
	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;
	else
		ret = FAIL;

	zbx_db_free_result(result);

	return ret;
}

static int	lld_host_enable_validate(zbx_uint64_t hostid)
{
	int		ret;
	char		*sql;
	zbx_db_result_t	result;

	sql = zbx_dsprintf(NULL, "select null"
			" from hosts h"
			" join host_discovery d on h.hostid=d.hostid"
			" where h.status=%d"
				" and d.disable_source=%d"
				" and h.hostid=" ZBX_FS_UI64,
				HOST_STATUS_NOT_MONITORED, ZBX_DISABLE_SOURCE_LLD_LOST, hostid);

	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;
	else
		ret = FAIL;

	zbx_db_free_result(result);

	return ret;
}

static int	lld_host_delete_validate(zbx_uint64_t hostid)
{
	int		ret;
	char		*sql;
	zbx_db_result_t	result;

	sql = zbx_dsprintf(NULL, "select null"
			" from hosts h"
			" join host_discovery d on h.hostid=d.hostid"
			" where h.hostid=" ZBX_FS_UI64
				" and (h.status=%d or d.disable_source=%d)",
				hostid, HOST_STATUS_MONITORED, ZBX_DISABLE_SOURCE_LLD_LOST);

	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;
	else
		ret = FAIL;

	zbx_db_free_result(result);

	return ret;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: Updates host_discovery fields. Removes or disables lost resources. *
 *                                                                             *
 *******************************************************************************/
static void	lld_hosts_remove(const zbx_vector_lld_host_ptr_t *hosts, const zbx_lld_lifetime_t *lifetime,
		const zbx_lld_lifetime_t *enabled_lifetime, int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	const zbx_lld_host_t	*host;
	zbx_vector_uint64_t	del_hostids, lc_hostids, lost_hostids, discovered_hostids, dis_hostids, en_hostids;
	zbx_vector_str_t	del_hosts;
	zbx_hashset_t		ids_names;
	zbx_id_name_pair_t	local_id_name_pair;

	if (0 == hosts->values_num)
		return;

#define	IDS_NAMES_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&ids_names, IDS_NAMES_HASHSET_DEF_SIZE,
			zbx_ids_names_hash_func,
			lld_ids_names_compare_func);
#undef IDS_NAMES_HASHSET_DEF_SIZE

	zbx_vector_uint64_create(&del_hostids);
	zbx_vector_str_create(&del_hosts);
	zbx_vector_uint64_create(&lc_hostids);
	zbx_vector_uint64_create(&lost_hostids);
	zbx_vector_uint64_create(&discovered_hostids);
	zbx_vector_uint64_create(&dis_hostids);
	zbx_vector_uint64_create(&en_hostids);

	zbx_db_begin();

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == host->hostid)
			continue;

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
		{
			int	ts_disable, ts_delete = 0;

			if ((ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type ||
					(ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type && lastcheck > (ts_delete =
					lld_end_of_life(host->lastcheck, lifetime->duration)))) &&
					SUCCEED == lld_host_delete_validate(host->hostid))
			{
				zbx_vector_uint64_append(&del_hostids, host->hostid);
				local_id_name_pair.id = host->hostid;
				local_id_name_pair.name = zbx_strdup(NULL, host->host);
				zbx_hashset_insert(&ids_names, &local_id_name_pair, sizeof(local_id_name_pair));
				continue;
			}

			if (ZBX_LLD_DISCOVERY_STATUS_LOST != host->discovery_status)
				zbx_vector_uint64_append(&lost_hostids, host->hostid);

			if (ZBX_LLD_LIFETIME_TYPE_NEVER == lifetime->type)
				ts_delete = 0;
			else if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type)
				ts_delete = 1;

			if (host->ts_delete != ts_delete)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update host_discovery"
						" set ts_delete=%d"
						" where hostid=" ZBX_FS_UI64 ";\n",
						ts_delete, host->hostid);
			}

			if (ZBX_LLD_LIFETIME_TYPE_NEVER == enabled_lifetime->type)
				ts_disable = 0;
			else if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == enabled_lifetime->type)
				ts_disable = 1;
			else
				ts_disable = lld_end_of_life(host->lastcheck, enabled_lifetime->duration);

			if (host->ts_disable != ts_disable)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update host_discovery"
						" set ts_disable=%d"
						" where hostid=" ZBX_FS_UI64 ";\n",
						ts_disable, host->hostid);
			}

			if ((ZBX_LLD_LIFETIME_TYPE_AFTER == enabled_lifetime->type && lastcheck <= ts_disable) ||
					ZBX_LLD_LIFETIME_TYPE_NEVER == enabled_lifetime->type ||
					HOST_STATUS_NOT_MONITORED == host->status ||
					SUCCEED != zbx_db_lock_hostid(host->hostid) ||
					FAIL == lld_host_disable_validate(host->hostid))
			{
				continue;
			}

			zbx_vector_uint64_append(&dis_hostids, host->hostid);
			zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, host->hostid,
					host->host);
			zbx_audit_host_update_json_update_host_status(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
					HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);
		}
		else
		{
			zbx_vector_uint64_append(&lc_hostids, host->hostid);

			if (ZBX_LLD_DISCOVERY_STATUS_NORMAL != host->discovery_status)
				zbx_vector_uint64_append(&discovered_hostids, host->hostid);

			if (HOST_STATUS_MONITORED == host->status ||
					ZBX_DISABLE_SOURCE_LLD_LOST != host->disable_source ||
					SUCCEED != zbx_db_lock_hostid(host->hostid) ||
					SUCCEED != lld_host_enable_validate(host->hostid))
			{
				continue;
			}

			zbx_vector_uint64_append(&en_hostids, host->hostid);
			zbx_audit_host_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, host->hostid,
					host->host);
			zbx_audit_host_update_json_update_host_status(ZBX_AUDIT_LLD_CONTEXT, host->hostid,
					HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED);
		}
	}

	if (0 != discovered_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set status=%d where",
				ZBX_LLD_DISCOVERY_STATUS_NORMAL);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				discovered_hostids.values, discovered_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lost_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set status=%d where",
				ZBX_LLD_DISCOVERY_STATUS_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				lost_hostids.values, lost_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lc_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set lastcheck=%d where",
				lastcheck);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				lc_hostids.values, lc_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != en_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set status=%d where",
				HOST_STATUS_MONITORED);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				en_hostids.values, en_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != dis_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set disable_source=%d where",
				ZBX_DISABLE_SOURCE_LLD_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				dis_hostids.values, dis_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set status=%d where",
				HOST_STATUS_NOT_MONITORED);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				dis_hostids.values, dis_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_db_commit();

	zbx_free(sql);

	if (0 != del_hostids.values_num)
	{
		zbx_vector_uint64_sort(&del_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (int i = 0; i < del_hostids.values_num; i++)
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

		zbx_db_begin();

		zbx_db_delete_hosts(&del_hostids, &del_hosts, ZBX_AUDIT_LLD_CONTEXT);

		zbx_db_commit();
	}

	zbx_vector_uint64_destroy(&en_hostids);
	zbx_vector_uint64_destroy(&dis_hostids);
	zbx_vector_uint64_destroy(&lost_hostids);
	zbx_vector_uint64_destroy(&discovered_hostids);
	zbx_vector_uint64_destroy(&lc_hostids);
	zbx_vector_uint64_destroy(&del_hostids);
	zbx_vector_str_clear_ext(&del_hosts, zbx_str_free);
	zbx_vector_str_destroy(&del_hosts);
	zbx_hashset_destroy(&ids_names);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates group_discovery fields. Removes lost resources.           *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_remove(const zbx_vector_lld_group_ptr_t *groups, const zbx_lld_lifetime_t *lifetime,
		int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	const zbx_lld_group_t	*group;
	zbx_vector_uint64_t	del_ids, lc_ids, groupids, discovered_ids, lost_ids;
	int			i, j;

	if (0 == groups->values_num)
		return;

	zbx_vector_uint64_create(&del_ids);
	zbx_vector_uint64_create(&lc_ids);
	zbx_vector_uint64_create(&groupids);
	zbx_vector_uint64_create(&discovered_ids);
	zbx_vector_uint64_create(&lost_ids);

	zbx_db_begin();

	for (i = 0; i < groups->values_num; i++)
	{
		group = groups->values[i];

		if (0 == group->discovery.values_num)
		{
			zbx_vector_uint64_append(&groupids, group->groupid);
			continue;
		}

		for (j = 0; j < group->discovery.values_num; j++)
		{
			zbx_lld_group_discovery_t	*discovery = group->discovery.values[j];

			if (0 == discovery->groupdiscoveryid)
				continue;

			if (0 == (discovery->flags & ZBX_FLAG_LLD_GROUP_DISCOVERY_DISCOVERED))
			{
				int	ts_delete = 0;

				if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED) ||
						ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type ||
						(ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type && lastcheck >
						(ts_delete = lld_end_of_life(discovery->lastcheck,
						lifetime->duration))))
				{
					zbx_vector_uint64_append(&del_ids, discovery->groupdiscoveryid);
					zbx_vector_uint64_append(&groupids, group->groupid);
					continue;
				}

				if (ZBX_LLD_DISCOVERY_STATUS_LOST != discovery->discovery_status)
					zbx_vector_uint64_append(&lost_ids, discovery->groupdiscoveryid);

				if (discovery->ts_delete != ts_delete)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"update group_discovery"
							" set ts_delete=%d"
							" where groupdiscoveryid=" ZBX_FS_UI64 ";\n",
							ts_delete, discovery->groupdiscoveryid);
				}
			}
			else
			{
				zbx_vector_uint64_append(&lc_ids, discovery->groupdiscoveryid);

				if (ZBX_LLD_DISCOVERY_STATUS_NORMAL != discovery->discovery_status)
					zbx_vector_uint64_append(&discovered_ids, discovery->groupdiscoveryid);
			}
		}
	}

	if (0 != lc_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set lastcheck=%d where",
				lastcheck);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupdiscoveryid",
				lc_ids.values, lc_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lost_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set status=%d where",
				ZBX_LLD_DISCOVERY_STATUS_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupdiscoveryid",
				lost_ids.values, lost_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != discovered_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set status=%d where",
				ZBX_LLD_DISCOVERY_STATUS_NORMAL);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupdiscoveryid",
				discovered_ids.values, discovered_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != del_ids.values_num)
	{
		zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		/* remove group discovery records */

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from group_discovery where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupdiscoveryid", del_ids.values,
				del_ids.values_num);
		zbx_db_execute("%s", sql);
	}

	/* remove groups without group discovery records */

	if (0 != groupids.values_num)
	{
		zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_db_result_t	result;
		zbx_db_row_t	row;

		zbx_vector_uint64_clear(&del_ids);
		sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select groupid from hstgrp g where");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid", groupids.values,
				groupids.values_num);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" and not exists"
					" (select null from group_discovery gd"
						" where g.groupid=gd.groupid)");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	groupid;

			ZBX_DBROW2UINT64(groupid, row[0]);
			zbx_vector_uint64_append(&del_ids, groupid);
		}
		zbx_db_free_result(result);

		if (0 != del_ids.values_num)
		{
			zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_db_delete_groups(&del_ids);
		}
	}

	zbx_db_commit();

	zbx_free(sql);

	zbx_vector_uint64_destroy(&lost_ids);
	zbx_vector_uint64_destroy(&discovered_ids);
	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_uint64_destroy(&lc_ids);
	zbx_vector_uint64_destroy(&del_ids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves either the list of interfaces from the LLD rule's host  *
 *          or the list of custom interfaces defined for the host prototype.  *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_get(zbx_uint64_t id, zbx_vector_lld_interface_ptr_t *interfaces,
		unsigned char custom_interfaces)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_interface_t	*interface;

	if (ZBX_HOST_PROT_INTERFACES_INHERIT == custom_interfaces)
	{
		result = zbx_db_select(
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
		result = zbx_db_select(
				"select hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,hi.dns,hi.port,s.version,s.bulk,"
				"s.community,s.securityname,s.securitylevel,s.authpassphrase,s.privpassphrase,"
				"s.authprotocol,s.privprotocol,s.contextname"
				" from interface hi"
				" left join interface_snmp s"
					" on hi.interfaceid=s.interfaceid"
				" where hi.hostid=" ZBX_FS_UI64,
				id);
	}

	while (NULL != (row = zbx_db_fetch(result)))
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

		zbx_vector_lld_interface_ptr_append(interfaces, interface);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_interface_ptr_sort(interfaces, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Checks if two interfaces match by comparing all fields (including *
 *          prototype interface id).                                          *
 *                                                                            *
 * Parameters: ifold - [IN] old (existing) interface                          *
 *             ifnew - [IN] new (discovered) interface                        *
 *                                                                            *
 * Return value: The interface fields update bitmask in low 32 bits and       *
 *               SNMP fields update bitmask in high 32 bits.                  *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	lld_interface_compare(const zbx_lld_interface_t *ifold, const zbx_lld_interface_t *ifnew)
{
	zbx_uint64_t	flags = 0, snmp_flags = 0;

	if (ifold->type != ifnew->type)
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE;

	if (ifold->main != ifnew->main)
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;

	if (ifold->useip != ifnew->useip)
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP;

	if (0 != strcmp(ifold->ip, ifnew->ip))
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_IP;

	if (0 != strcmp(ifold->dns, ifnew->dns))
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS;

	if (0 != strcmp(ifold->port, ifnew->port))
		flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT;

	if (ifold->flags != ifnew->flags)
	{
		if (0 == ifold->flags)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE;
		else
			flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_REMOVE;

		/* Add all field update to make snmp type change low priority match. */
		/* When saving create/remove flags are checked before update, so     */
		/* adding update flags won't affect interface saving.                */
		snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE;
	}

	if (INTERFACE_TYPE_SNMP == ifold->type && INTERFACE_TYPE_SNMP == ifnew->type)
	{
		if (ifold->data.snmp->version != ifnew->data.snmp->version)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE;

		if (ifold->data.snmp->bulk != ifnew->data.snmp->bulk)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK;

		if (0 != strcmp(ifold->data.snmp->community, ifnew->data.snmp->community))
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY;

		if (0 != strcmp(ifold->data.snmp->securityname, ifnew->data.snmp->securityname))
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME;

		if (ifold->data.snmp->securitylevel != ifnew->data.snmp->securitylevel)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL;

		if (0 != strcmp(ifold->data.snmp->authpassphrase, ifnew->data.snmp->authpassphrase))
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS;

		if (0 != strcmp(ifold->data.snmp->privpassphrase, ifnew->data.snmp->privpassphrase))
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS;

		if (ifold->data.snmp->authprotocol != ifnew->data.snmp->authprotocol)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL;

		if (ifold->data.snmp->privprotocol != ifnew->data.snmp->privprotocol)
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL;

		if (0 != strcmp(ifold->data.snmp->contextname, ifnew->data.snmp->contextname))
			snmp_flags |= ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT;
	}

	return (snmp_flags << 32) | flags;
}

typedef struct
{
	zbx_lld_interface_t	*ifold;
	zbx_lld_interface_t	*ifnew;
	int			diff_num;
	zbx_uint64_t		flags;
}
zbx_if_update_t;

ZBX_PTR_VECTOR_DECL(if_update, zbx_if_update_t *)
ZBX_PTR_VECTOR_IMPL(if_update, zbx_if_update_t *)

static int	lld_if_update_compare(const void *d1, const void *d2)
{
	const zbx_if_update_t *u1 = *(const zbx_if_update_t * const *)d1;
	const zbx_if_update_t *u2 = *(const zbx_if_update_t * const *)d2;

	return u1->diff_num - u2->diff_num;
}

static int	zbx_popcount64(zbx_uint64_t mask)
{
	mask -= (mask >> 1) & __UINT64_C(0x5555555555555555);
	mask = (mask & __UINT64_C(0x3333333333333333)) + (mask >> 2 & __UINT64_C(0x3333333333333333));
	return (int)(((mask + (mask >> 4)) & __UINT64_C(0xf0f0f0f0f0f0f0f)) * __UINT64_C(0x101010101010101) >> 56);
}

static void	lld_interfaces_link(const zbx_lld_interface_t *ifold, zbx_lld_interface_t *ifnew, zbx_uint64_t flags)
{
	ifnew->interfaceid = ifold->interfaceid;
	ifnew->flags |= (flags & 0xffffffff);

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
		ifnew->type_orig = ifold->type;

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN))
		ifnew->main_orig = ifold->main;

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP))
		ifnew->useip_orig = ifold->useip;

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_IP))
		ifnew->ip_orig = zbx_strdup(NULL, ifold->ip);

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS))
		ifnew->dns_orig = zbx_strdup(NULL, ifold->dns);

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT))
		ifnew->port_orig = zbx_strdup(NULL, ifold->port);

	if (0 != (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS))
	{
		ifnew->data.snmp->flags |= (flags >> 32);

		if (0 == (ifnew->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_CREATE))
		{
			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_TYPE))
				ifnew->data.snmp->version_orig = ifold->data.snmp->version;

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_BULK))
				ifnew->data.snmp->bulk_orig = ifold->data.snmp->bulk;

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_COMMUNITY))
				ifnew->data.snmp->community_orig = zbx_strdup(NULL, ifold->data.snmp->community);

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECNAME))
				ifnew->data.snmp->securityname_orig = zbx_strdup(NULL, ifold->data.snmp->securityname);

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_SECLEVEL))
				ifnew->data.snmp->securitylevel_orig = ifold->data.snmp->securitylevel;

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPASS))
				ifnew->data.snmp->authpassphrase_orig = zbx_strdup(NULL,
						ifold->data.snmp->authpassphrase);

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPASS))
				ifnew->data.snmp->privpassphrase_orig = zbx_strdup(NULL,
						ifold->data.snmp->privpassphrase);

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_AUTHPROTOCOL))
				ifnew->data.snmp->authprotocol_orig = ifold->data.snmp->authprotocol;

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_PRIVPROTOCOL))
				ifnew->data.snmp->privprotocol_orig = ifold->data.snmp->privprotocol;

			if (0 != (ifnew->data.snmp->flags & ZBX_FLAG_LLD_INTERFACE_SNMP_UPDATE_CONTEXT))
				ifnew->data.snmp->contextname_orig = zbx_strdup(NULL, ifold->data.snmp->contextname);
		}
	}
}

static void	lld_host_interfaces_make(zbx_uint64_t hostid, zbx_vector_lld_host_ptr_t *hosts,
		zbx_vector_lld_interface_ptr_t *interfaces)
{
	int			i, j;
	zbx_lld_host_t		*host;
	zbx_if_update_t		*update;
	zbx_vector_if_update_t	updates;
	zbx_lld_host_t		cmp = {.hostid = hostid};

	if (FAIL == (i = zbx_vector_lld_host_ptr_bsearch(hosts, &cmp, lld_host_compare_func)))
	{
		zbx_vector_lld_interface_ptr_clear_ext(interfaces, lld_interface_free);
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	host = hosts->values[i];

	/* prepare old-new interface match matrix as vector, sorted by least number of unmatched fields */

	zbx_vector_if_update_create(&updates);

	for (i = 0; i < host->interfaces.values_num; i++)
	{
		zbx_lld_interface_t	*ifnew = host->interfaces.values[i];

		for (j = 0; j < interfaces->values_num; j++)
		{
			if (ifnew->parent_interfaceid != interfaces->values[j]->parent_interfaceid)
				continue;

			update = (zbx_if_update_t *)zbx_malloc(NULL, sizeof(zbx_if_update_t));
			update->ifnew = ifnew;
			update->ifold = interfaces->values[j];
			update->flags = lld_interface_compare(update->ifold, update->ifnew);
			update->diff_num = zbx_popcount64(update->flags);

			zbx_vector_if_update_append(&updates, update);
		}
	}

	zbx_vector_if_update_sort(&updates, lld_if_update_compare);

	/* update new interface id to matching old interface id and set update flags accordingly */

	while (0 != updates.values_num)
	{
		update = updates.values[0];

		lld_interfaces_link(update->ifold, update->ifnew, update->flags);

		zbx_vector_if_update_remove(&updates, 0);

		for (i = 0; i < updates.values_num;)
		{
			if (update->ifnew == updates.values[i]->ifnew || update->ifold == updates.values[i]->ifold)
			{
				zbx_free(updates.values[i]);
				zbx_vector_if_update_remove(&updates, i);
			}
			else
				i++;
		}

		for (i = 0; i < interfaces->values_num;)
		{
			if (interfaces->values[i] == update->ifold)
			{
				lld_interface_free(interfaces->values[i]);
				zbx_vector_lld_interface_ptr_remove_noorder(interfaces, i);
				break;
			}
			else
				i++;
		}

		zbx_free(update);
	}

	/* mark leftover old interfaces to be removed */

	for (i = 0; i < interfaces->values_num; i++)
		interfaces->values[i]->flags |= ZBX_FLAG_LLD_INTERFACE_REMOVE;

	zbx_vector_lld_interface_ptr_append_array(&host->interfaces, interfaces->values,
			interfaces->values_num);
	zbx_vector_lld_interface_ptr_clear(interfaces);

	zbx_vector_if_update_destroy(&updates);
}

/******************************************************************************
 *                                                                            *
 * Parameters: interfaces - [IN] Sorted list of interfaces which should be    *
 *                               present on each discovered host.             *
 *             hosts      - [IN/OUT] sorted list of hosts                     *
 *             lld_macros - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_make(const zbx_vector_lld_interface_ptr_t *interfaces, zbx_vector_lld_host_ptr_t *hosts,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*new_interface, *interface;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_lld_interface_ptr_reserve(&host->interfaces, (size_t)interfaces->values_num);

		for (j = 0; j < interfaces->values_num; j++)
		{
			interface = interfaces->values[j];

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

			zbx_substitute_lld_macros(&new_interface->ip, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
			zbx_substitute_lld_macros(&new_interface->dns, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL,
					0);
			zbx_substitute_lld_macros(&new_interface->port, host->jp_row, lld_macros, ZBX_MACRO_ANY, NULL,
					0);

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

				zbx_substitute_lld_macros(&snmp->community, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				zbx_substitute_lld_macros(&snmp->securityname, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
				zbx_substitute_lld_macros(&snmp->authpassphrase, host->jp_row, lld_macros,
						ZBX_MACRO_ANY, NULL, 0);
				zbx_substitute_lld_macros(&snmp->privpassphrase, host->jp_row, lld_macros,
						ZBX_MACRO_ANY, NULL, 0);
				zbx_substitute_lld_macros(&snmp->contextname, host->jp_row, lld_macros, ZBX_MACRO_ANY,
						NULL, 0);
			}
			else
			{
				new_interface->flags = 0x00;
				new_interface->data.snmp = NULL;
			}

			zbx_vector_lld_interface_ptr_append(&host->interfaces, new_interface);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char				*sql = NULL;
		size_t				sql_alloc = 0, sql_offset = 0;
		zbx_vector_lld_interface_ptr_t	old_interfaces;
		zbx_uint64_t			last_hostid = 0;

		zbx_vector_lld_interface_ptr_create(&old_interfaces);

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
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.hostid", hostids.values,
				hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hi.hostid");

		result = zbx_db_select("%s", sql);

		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			if (0 != last_hostid && hostid != last_hostid)
				lld_host_interfaces_make(last_hostid, hosts, &old_interfaces);

			last_hostid = hostid;

			interface = (zbx_lld_interface_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_t));
			memset(interface, 0, sizeof(zbx_lld_interface_t));

			ZBX_DBROW2UINT64(interface->parent_interfaceid, row[1]);
			ZBX_DBROW2UINT64(interface->interfaceid, row[2]);
			ZBX_STR2UCHAR(interface->type, row[3]);
			ZBX_STR2UCHAR(interface->main, row[4]);
			ZBX_STR2UCHAR(interface->useip, row[5]);
			interface->ip = zbx_strdup(NULL, row[6]);
			interface->dns = zbx_strdup(NULL, row[7]);
			interface->port = zbx_strdup(NULL, row[8]);

			if (INTERFACE_TYPE_SNMP == interface->type)
			{
				zbx_lld_interface_snmp_t *snmp;

				snmp = (zbx_lld_interface_snmp_t *)zbx_malloc(NULL, sizeof(zbx_lld_interface_snmp_t));
				memset(snmp, 0, sizeof(zbx_lld_interface_snmp_t));

				ZBX_STR2UCHAR(snmp->version, row[9]);
				ZBX_STR2UCHAR(snmp->bulk, row[10]);
				snmp->community = zbx_strdup(NULL, row[11]);
				snmp->securityname = zbx_strdup(NULL, row[12]);
				ZBX_STR2UCHAR(snmp->securitylevel, row[13]);
				snmp->authpassphrase = zbx_strdup(NULL, row[14]);
				snmp->privpassphrase = zbx_strdup(NULL, row[15]);
				ZBX_STR2UCHAR(snmp->authprotocol, row[16]);
				ZBX_STR2UCHAR(snmp->privprotocol, row[17]);
				snmp->contextname = zbx_strdup(NULL, row[18]);

				snmp->flags = 0x00;
				interface->flags = ZBX_FLAG_LLD_INTERFACE_SNMP_DATA_EXISTS;
				interface->data.snmp = snmp;
			}
			else
			{
				interface->flags = 0x00;
				interface->data.snmp = NULL;
			}

			zbx_vector_lld_interface_ptr_append(&old_interfaces, interface);
		}
		zbx_db_free_result(result);

		if (0 != old_interfaces.values_num)
			lld_host_interfaces_make(last_hostid, hosts, &old_interfaces);

		zbx_vector_lld_interface_ptr_destroy(&old_interfaces);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCEED - if interface with same type exists in list of      *
 *                         interfaces                                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: interfaces with ZBX_FLAG_LLD_INTERFACE_REMOVE flag are ignored   *
 *           auxiliary function for lld_interfaces_validate()                 *
 *                                                                            *
 ******************************************************************************/
static int	another_main_interface_exists(const zbx_vector_lld_interface_ptr_t *interfaces,
		const zbx_lld_interface_t *interface)
{
	for (int i = 0; i < interfaces->values_num; i++)
	{
		const zbx_lld_interface_t	*interface_b = interfaces->values[i];

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
 * Parameters: hosts - [IN/OUT]                                               *
 *             error - [OUT]                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_validate(zbx_vector_lld_host_ptr_t *hosts, char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
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

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		for (int j = 0; j < host->interfaces.values_num; j++)
		{
			interface = host->interfaces.values[j];

			if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
				continue;

			zbx_vector_uint64_append(&interfaceids, interface->interfaceid);
		}
	}

	if (0 != interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select interfaceid,type from items where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid,type");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			type = zbx_get_interface_type_by_item_type((unsigned char)atoi(row[1]));

			if (type != INTERFACE_TYPE_ANY && type != INTERFACE_TYPE_UNKNOWN && type != INTERFACE_TYPE_OPT)
			{
				ZBX_STR2UINT64(interfaceid, row[0]);

				for (int i = 0; i < hosts->values_num; i++)
				{
					host = hosts->values[i];

					for (int j = 0; j < host->interfaces.values_num; j++)
					{
						interface = host->interfaces.values[j];

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
		zbx_db_free_result(result);
	}

	/* validate interfaces which should be deleted */

	zbx_vector_uint64_clear(&interfaceids);

	for (int i = 0; i < hosts->values_num; i++)
	{
		host = hosts->values[i];

		for (int j = 0; j < host->interfaces.values_num; j++)
		{
			interface = host->interfaces.values[j];

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
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(interfaceid, row[0]);

			for (int i = 0; i < hosts->values_num; i++)
			{
				host = hosts->values[i];

				for (int j = 0; j < host->interfaces.values_num; j++)
				{
					interface = host->interfaces.values[j];

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
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&interfaceids);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates LLD hosts                                         *
 *                                                                            *
 ******************************************************************************/
void	lld_update_hosts(zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error, zbx_lld_lifetime_t *lifetime,
		zbx_lld_lifetime_t *enabled_lifetime, int lastcheck)
{
	zbx_db_result_t				result;
	zbx_db_row_t				row;
	zbx_vector_lld_host_ptr_t		hosts, hosts_old;
	zbx_vector_lld_group_prototype_ptr_t	group_prototypes;
	zbx_vector_lld_interface_ptr_t		interfaces;
	zbx_vector_lld_hostmacro_ptr_t		masterhostmacros, hostmacros;
	zbx_vector_lld_group_ptr_t		groups, groups_in, groups_out;
	zbx_vector_lld_hgset_ptr_t		hgsets;
	zbx_vector_lld_permission_t		permissions;
	zbx_vector_db_tag_ptr_t			tags;

	/* list of host groups which should be added */
	zbx_vector_uint64_t			groupids;

	/* list of host groups which should be deleted */
	zbx_vector_uint64_t			del_hostgroupids;

	/* list of host groups sets which should be deleted */
	zbx_vector_uint64_t			del_hgsetids;

	zbx_uint64_t				proxyid, proxy_groupid;
	char					*ipmi_username = NULL, *ipmi_password, *tls_issuer, *tls_subject,
						*tls_psk_identity, *tls_psk;
	signed char				ipmi_authtype, inventory_mode_proto;
	unsigned char				ipmi_privilege, tls_connect, tls_accept, monitored_by;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select h.proxyid,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password,"
				"h.tls_connect,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk,"
				"h.proxy_groupid,h.monitored_by"
			" from hosts h,items i"
			" where h.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_DBROW2UINT64(proxyid, row[0]);
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

		ZBX_DBROW2UINT64(proxy_groupid, row[11]);
		ZBX_STR2UCHAR(monitored_by, row[12]);
	}
	zbx_db_free_result(result);

	if (NULL == row)
	{
		*error = zbx_strdcatf(*error, "Cannot process host prototypes: a parent host not found.\n");
		return;
	}

	zbx_vector_lld_host_ptr_create(&hosts);
	zbx_vector_lld_host_ptr_create(&hosts_old);
	zbx_vector_uint64_create(&groupids);
	zbx_vector_lld_group_prototype_ptr_create(&group_prototypes);
	zbx_vector_lld_group_ptr_create(&groups);
	zbx_vector_lld_group_ptr_create(&groups_in);
	zbx_vector_lld_group_ptr_create(&groups_out);
	zbx_vector_lld_hgset_ptr_create(&hgsets);
	zbx_vector_lld_permission_create(&permissions);
	zbx_vector_uint64_create(&del_hostgroupids);
	zbx_vector_uint64_create(&del_hgsetids);
	zbx_vector_lld_interface_ptr_create(&interfaces);
	zbx_vector_lld_hostmacro_ptr_create(&masterhostmacros);
	zbx_vector_lld_hostmacro_ptr_create(&hostmacros);
	zbx_vector_db_tag_ptr_create(&tags);

	lld_interfaces_get(lld_ruleid, &interfaces, 0);
	lld_masterhostmacros_get(lld_ruleid, &masterhostmacros);

	result = zbx_db_select(
			"select h.hostid,h.host,h.name,h.status,h.discover,hi.inventory_mode,h.custom_interfaces"
			" from hosts h,host_discovery hd"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where h.hostid=hd.hostid"
				" and hd.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t			parent_hostid;
		const char			*host_proto, *name_proto;
		zbx_lld_host_t			*host;
		unsigned char			status, discover, use_custom_interfaces;
		zbx_vector_lld_interface_ptr_t	interfaces_custom;

		ZBX_STR2UINT64(parent_hostid, row[0]);
		host_proto = row[1];
		name_proto = row[2];
		ZBX_STR2UCHAR(status, row[3]);
		ZBX_STR2UCHAR(discover, row[4]);
		ZBX_STR2UCHAR(use_custom_interfaces, row[6]);

		if (SUCCEED == zbx_db_is_null(row[5]))
			inventory_mode_proto = HOST_INVENTORY_DISABLED;
		else
			inventory_mode_proto = (signed char)atoi(row[5]);

		lld_hosts_get(parent_hostid, &hosts, monitored_by, proxyid, proxy_groupid, ipmi_authtype, ipmi_privilege,
				ipmi_username, ipmi_password, tls_connect, tls_accept, tls_issuer, tls_subject,
				tls_psk_identity, tls_psk);

		if (0 != hosts.values_num)
		{
			lld_hosts_get_tags(&hosts);
			zbx_vector_lld_host_ptr_append_array(&hosts_old, hosts.values, hosts.values_num);
		}

		lld_proto_tags_get(parent_hostid, &tags);

		lld_simple_groups_get(parent_hostid, &groupids);

		lld_group_prototypes_get(parent_hostid, &group_prototypes);

		lld_groups_get(parent_hostid, &groups);

		lld_hostmacros_get(parent_hostid, &masterhostmacros, &hostmacros);

		for (int i = 0; i < lld_rows->values_num; i++)
		{
			const zbx_lld_row_t	*lld_row = lld_rows->values[i];

			if (NULL == (host = lld_host_make(&hosts, &hosts_old, host_proto, name_proto, inventory_mode_proto,
					status, discover, &tags, lld_row, lld_macro_paths, use_custom_interfaces,
					error)))
			{
				continue;
			}

			lld_groups_make(host, &groups_in, &group_prototypes, &lld_row->jp_row, lld_macro_paths);
		}

		zbx_vector_lld_host_ptr_sort(&hosts, lld_host_compare_func);

		lld_groups_validate(&group_prototypes, &groups, &groups_in, &groups_out, lld_macro_paths, error);
		lld_hosts_validate(&hosts, error);

		if (ZBX_HOST_PROT_INTERFACES_CUSTOM == use_custom_interfaces)
		{
			zbx_vector_lld_interface_ptr_create(&interfaces_custom);
			lld_interfaces_get(parent_hostid, &interfaces_custom, 1);
			lld_interfaces_make(&interfaces_custom, &hosts, lld_macro_paths);
		}
		else
			lld_interfaces_make(&interfaces, &hosts, lld_macro_paths);

		lld_interfaces_validate(&hosts, error);

		/* save groups before making hosts_groups links because groupids could be updated during save */
		lld_groups_save(&groups_out, &group_prototypes, error);

		lld_hostgroups_make(&groupids, &hosts, &groups_out, &del_hostgroupids);

		if (0 != hosts.values_num)
		{
			lld_hgsets_make(parent_hostid, &hosts, &hgsets, &del_hgsetids);
			lld_permissions_make(&permissions, &hgsets);
		}

		lld_templates_make(parent_hostid, &hosts);

		lld_hostmacros_make(&hostmacros, &hosts, lld_macro_paths);

		lld_hosts_save(parent_hostid, &hosts, host_proto, monitored_by, proxyid, proxy_groupid, ipmi_authtype,
				ipmi_privilege, ipmi_username, ipmi_password, tls_connect, tls_accept, tls_issuer,
				tls_subject, tls_psk_identity, tls_psk, &hgsets, &permissions, &del_hostgroupids,
				&del_hgsetids);

		/* linking of the templates */
		lld_templates_link(&hosts, error);

		lld_hosts_remove(&hosts, lifetime, enabled_lifetime, lastcheck);
		lld_groups_remove(&groups_out, lifetime, lastcheck);

		zbx_vector_db_tag_ptr_clear_ext(&tags, zbx_db_tag_free);
		zbx_vector_lld_hostmacro_ptr_clear_ext(&hostmacros, lld_hostmacro_free);
		zbx_vector_lld_hgset_ptr_clear_ext(&hgsets, lld_hgset_free);
		zbx_vector_lld_group_ptr_clear_ext(&groups, lld_group_free);
		zbx_vector_lld_group_ptr_clear_ext(&groups_in, lld_group_free);
		zbx_vector_lld_group_ptr_clear_ext(&groups_out, lld_group_free);
		zbx_vector_lld_group_prototype_ptr_clear_ext(&group_prototypes, lld_group_prototype_free);
		zbx_vector_lld_host_ptr_clear_ext(&hosts, lld_host_free);
		zbx_vector_lld_host_ptr_clear(&hosts_old);

		zbx_vector_uint64_clear(&groupids);
		zbx_vector_uint64_clear(&del_hostgroupids);
		zbx_vector_uint64_clear(&del_hgsetids);
		zbx_vector_lld_permission_clear(&permissions);

		if (ZBX_HOST_PROT_INTERFACES_CUSTOM == use_custom_interfaces)
		{
			zbx_vector_lld_interface_ptr_clear_ext(&interfaces_custom, lld_interface_free);
			zbx_vector_lld_interface_ptr_destroy(&interfaces_custom);
		}
	}
	zbx_db_free_result(result);

	zbx_vector_lld_hostmacro_ptr_clear_ext(&masterhostmacros, lld_hostmacro_free);
	zbx_vector_lld_interface_ptr_clear_ext(&interfaces, lld_interface_free);

	zbx_vector_db_tag_ptr_clear_ext(&tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&tags);
	zbx_vector_lld_hostmacro_ptr_destroy(&hostmacros);
	zbx_vector_lld_hostmacro_ptr_destroy(&masterhostmacros);
	zbx_vector_lld_interface_ptr_destroy(&interfaces);
	zbx_vector_uint64_destroy(&del_hostgroupids);
	zbx_vector_uint64_destroy(&del_hgsetids);
	zbx_vector_lld_permission_destroy(&permissions);
	zbx_vector_lld_hgset_ptr_destroy(&hgsets);
	zbx_vector_lld_group_ptr_destroy(&groups);
	zbx_vector_lld_group_ptr_destroy(&groups_in);
	zbx_vector_lld_group_ptr_destroy(&groups_out);
	zbx_vector_lld_group_prototype_ptr_destroy(&group_prototypes);
	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_lld_host_ptr_destroy(&hosts);
	zbx_vector_lld_host_ptr_destroy(&hosts_old);

	zbx_free(tls_psk);
	zbx_free(tls_psk_identity);
	zbx_free(tls_subject);
	zbx_free(tls_issuer);
	zbx_free(ipmi_password);
	zbx_free(ipmi_username);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
