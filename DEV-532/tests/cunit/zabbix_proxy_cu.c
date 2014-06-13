#include "zbxcunit.c"

#define daemon_start(arg, user)	zabbix_server_entry()

void	intialize_cu_tests();
void	run_cu_tests();

int	zabbix_server_entry()
{
	zbx_free_config();

	zabbix_open_log(0, CONFIG_LOG_LEVEL, NULL);

	init_database_cache();
	init_configuration_cache();
	init_selfmon_collector();

	/* initialize vmware support */
	if (0 != CONFIG_VMWARE_FORKS)
		zbx_vmware_init();

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	/* make initial configuration sync */
	DCsync_configuration();

	if (CUE_SUCCESS != CU_initialize_registry())
	{
		fprintf(stderr, "Error while initializing CUnit registry: %s\n", CU_get_error_msg());
		goto out;
	}

	/* CUnit tests */
	if (CUE_SUCCESS != initialize_cu_tests())
	{
		fprintf(stderr, "Error while initializing CUnit tests: %s\n", CU_get_error_msg());
		goto out;
	}

	run_cu_tests();
out:
	CU_cleanup_registry();

	DBclose();

	return CU_get_error();
}


