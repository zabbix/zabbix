
# HashiCorp Vault by HTTP

## Overview

The template to monitor HashiCorp Vault by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Vault by HTTP` — collects metrics by HTTP agent from `/sys/metrics` API endpoint.
See https://www.vaultproject.io/api-docs/system/metrics.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Vault 1.6 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Configure Vault API. See [Vault Configuration](https://www.vaultproject.io/docs/configuration).
Create a Vault service token and set it to the macro `{$VAULT.TOKEN}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VAULT.API.PORT}|<p>Vault port.</p>|`8200`|
|{$VAULT.API.SCHEME}|<p>Vault API scheme.</p>|`http`|
|{$VAULT.HOST}|<p>Vault host name.</p>||
|{$VAULT.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors for trigger expression.</p>|`90`|
|{$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN}|<p>Maximum number of Vault leadership setup failed.</p>|`5`|
|{$VAULT.LEADERSHIP.LOSSES.MAX.WARN}|<p>Maximum number of Vault leadership losses.</p>|`5`|
|{$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN}|<p>Maximum number of Vault leadership step downs.</p>|`5`|
|{$VAULT.LLD.FILTER.STORAGE.MATCHES}|<p>Filter of discoverable storage backends.</p>|`.+`|
|{$VAULT.TOKEN}|<p>Vault auth token.</p>||
|{$VAULT.TOKEN.ACCESSORS}|<p>Vault accessors separated by spaces for monitoring token expiration time.</p>||
|{$VAULT.TOKEN.TTL.MIN.CRIT}|<p>Token TTL critical threshold.</p>|`3d`|
|{$VAULT.TOKEN.TTL.MIN.WARN}|<p>Token TTL warning threshold.</p>|`7d`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get health||HTTP agent|vault.get_health<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"healthcheck": 0}`</p></li></ul>|
|Get leader||HTTP agent|vault.get_leader<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics||HTTP agent|vault.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Clear metrics||Dependent item|vault.clear_metrics<p>**Preprocessing**</p><ul><li><p>Check for error in JSON: `$.errors`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get tokens|<p>Get information about tokens via their accessors. Accessors are defined in the macro "{$VAULT.TOKEN.ACCESSORS}".</p>|Script|vault.get_tokens|
|Check WAL discovery||Dependent item|vault.check_wal_discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"^vault_wal_(?:.+)$"}`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Check replication discovery||Dependent item|vault.check_replication_discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"^replication_(?:.+)$"}`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Check storage discovery||Dependent item|vault.check_storage_discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"^vault_(?:.+)_(?:get|put|list|delete)_count$"}`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Check mountpoint discovery||Dependent item|vault.check_mountpoint_discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"^vault_rollback_attempt_(?:.+?)_count$"}`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Initialized|<p>Initialization status.</p>|Dependent item|vault.health.initialized<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.initialized`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sealed|<p>Seal status.</p>|Dependent item|vault.health.sealed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sealed`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Standby|<p>Standby status.</p>|Dependent item|vault.health.standby<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.standby`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Performance standby|<p>Performance standby status.</p>|Dependent item|vault.health.performance_standby<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_standby`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Performance replication|<p>Performance replication mode</p><p>https://www.vaultproject.io/docs/enterprise/replication</p>|Dependent item|vault.health.replication_performance_mode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replication_performance_mode`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disaster Recovery replication|<p>Disaster recovery replication mode</p><p>https://www.vaultproject.io/docs/enterprise/replication</p>|Dependent item|vault.health.replication_dr_mode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replication_dr_mode`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Version|<p>Server version.</p>|Dependent item|vault.health.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Healthcheck|<p>Vault healthcheck.</p>|Dependent item|vault.health.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.healthcheck`</p><p>⛔️Custom on fail: Set value to: `1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HA enabled|<p>HA enabled status.</p>|Dependent item|vault.leader.ha_enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ha_enabled`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Is leader|<p>Leader status.</p>|Dependent item|vault.leader.is_self<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_self`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get metrics error|<p>Get metrics error.</p>|Dependent item|vault.get_metrics.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors[0]`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Process CPU seconds, total|<p>Total user and system CPU time spent in seconds.</p>|Dependent item|vault.metrics.process.cpu.seconds.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Open file descriptors, max|<p>Maximum number of open file descriptors.</p>|Dependent item|vault.metrics.process.max.fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Open file descriptors, current|<p>Number of open file descriptors.</p>|Dependent item|vault.metrics.process.open.fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process resident memory|<p>Resident memory size in bytes.</p>|Dependent item|vault.metrics.process.resident_memory.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Uptime|<p>Server uptime.</p>|Dependent item|vault.metrics.process.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_start_time_seconds)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Process virtual memory, current|<p>Virtual memory size in bytes.</p>|Dependent item|vault.metrics.process.virtual_memory.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process virtual memory, max|<p>Maximum amount of virtual memory available in bytes.</p>|Dependent item|vault.metrics.process.virtual_memory.max.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_max_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Audit log requests, rate|<p>Number of all audit log requests across all audit log devices.</p>|Dependent item|vault.metrics.audit.log.request.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_audit_log_request_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Audit log request failures, rate|<p>Number of audit log request failures.</p>|Dependent item|vault.metrics.audit.log.request.failure.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_audit_log_request_failure)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Audit log response, rate|<p>Number of audit log responses across all audit log devices.</p>|Dependent item|vault.metrics.audit.log.response.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_audit_log_response_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Audit log response failures, rate|<p>Number of audit log response failures.</p>|Dependent item|vault.metrics.audit.log.response.failure.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_audit_log_response_failure)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Barrier DELETE ops, rate|<p>Number of DELETE operations at the barrier.</p>|Dependent item|vault.metrics.barrier.delete.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_barrier_delete_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Barrier GET ops, rate|<p>Number of GET operations at the barrier.</p>|Dependent item|vault.metrics.vault.barrier.get.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_barrier_get_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Barrier LIST ops, rate|<p>Number of LIST operations at the barrier.</p>|Dependent item|vault.metrics.barrier.list.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_barrier_list_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Barrier PUT ops, rate|<p>Number of PUT operations at the barrier.</p>|Dependent item|vault.metrics.barrier.put.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_barrier_put_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache hit, rate|<p>Number of times a value was retrieved from the LRU cache.</p>|Dependent item|vault.metrics.cache.hit.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_cache_hit)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache miss, rate|<p>Number of times a value was not in the LRU cache. The results in a read from the configured storage.</p>|Dependent item|vault.metrics.cache.miss.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_cache_miss)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache write, rate|<p>Number of times a value was written to the LRU cache.</p>|Dependent item|vault.metrics.cache.write.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_cache_write)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Check token, rate|<p>Number of token checks handled by Vault core.</p>|Dependent item|vault.metrics.core.check.token.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_check_token_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Fetch ACL and token, rate|<p>Number of ACL and corresponding token entry fetches handled by Vault core.</p>|Dependent item|vault.metrics.core.fetch.acl_and_token<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_fetch_acl_and_token_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Requests, rate|<p>Number of requests handled by Vault core.</p>|Dependent item|vault.metrics.core.handle.request<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_handle_request_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Leadership setup failed, counter|<p>Cluster leadership setup failures which have occurred in a highly available Vault cluster.</p>|Dependent item|vault.metrics.core.leadership.setup_failed<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_core_leadership_setup_failed`</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Leadership setup lost, counter|<p>Cluster leadership losses which have occurred in a highly available Vault cluster.</p>|Dependent item|vault.metrics.core.leadership_lost<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_core_leadership_lost_count`</p></li><li><p>JSON Path: `$[?(@.name=="vault_core_leadership_lost_count")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Post-unseal ops, counter|<p>Duration of time taken by post-unseal operations handled by Vault core.</p>|Dependent item|vault.metrics.core.post_unseal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_post_unseal_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pre-seal ops, counter|<p>Duration of time taken by pre-seal operations.</p>|Dependent item|vault.metrics.core.pre_seal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_pre_seal_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Requested seal ops, counter|<p>Duration of time taken by requested seal operations.</p>|Dependent item|vault.metrics.core.seal_with_request<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_seal_with_request_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Seal ops, counter|<p>Duration of time taken by seal operations.</p>|Dependent item|vault.metrics.core.seal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_seal_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Internal seal ops, counter|<p>Duration of time taken by internal seal operations.</p>|Dependent item|vault.metrics.core.seal_internal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_seal_internal_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Leadership step downs, counter|<p>Cluster leadership step down.</p>|Dependent item|vault.metrics.core.step_down<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_core_step_down_count`</p></li><li><p>JSON Path: `$[?(@.name=="vault_core_step_down_count")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Unseal ops, counter|<p>Duration of time taken by unseal operations.</p>|Dependent item|vault.metrics.core.unseal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_core_unseal_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fetch lease times, counter|<p>Time taken to fetch lease times.</p>|Dependent item|vault.metrics.expire.fetch.lease.times<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_fetch_lease_times_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fetch lease times by token, counter|<p>Time taken to fetch lease times by token.</p>|Dependent item|vault.metrics.expire.fetch.lease.times.by_token<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_fetch_lease_times_by_token_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Number of expiring leases|<p>Number of all leases which are eligible for eventual expiry.</p>|Dependent item|vault.metrics.expire.num_leases<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_num_leases)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Expire revoke, count|<p>Time taken to revoke a token.</p>|Dependent item|vault.metrics.expire.revoke<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_revoke_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Expire revoke force, count|<p>Time taken to forcibly revoke a token.</p>|Dependent item|vault.metrics.expire.revoke.force<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_revoke_force_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Expire revoke prefix, count|<p>Tokens revoke on a prefix.</p>|Dependent item|vault.metrics.expire.revoke.prefix<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_revoke_prefix_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Revoke secrets by token, count|<p>Time taken to revoke all secrets issued with a given token.</p>|Dependent item|vault.metrics.expire.revoke.by_token<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_revoke_by_token_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Expire renew, count|<p>Time taken to renew a lease.</p>|Dependent item|vault.metrics.expire.renew<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_renew_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Renew token, count|<p>Time taken to renew a token which does not need to invoke a logical backend.</p>|Dependent item|vault.metrics.expire.renew_token<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_renew_token_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Register ops, count|<p>Time taken for register operations.</p>|Dependent item|vault.metrics.expire.register<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_register_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Register auth ops, count|<p>Time taken for register authentication operations which create lease entries without lease ID.</p>|Dependent item|vault.metrics.expire.register.auth<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_expire_register_auth_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Policy GET ops, rate|<p>Number of operations to get a policy.</p>|Dependent item|vault.metrics.policy.get_policy.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_policy_get_policy_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Policy LIST ops, rate|<p>Number of operations to list policies.</p>|Dependent item|vault.metrics.policy.list_policies.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_policy_list_policies_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Policy DELETE ops, rate|<p>Number of operations to delete a policy.</p>|Dependent item|vault.metrics.policy.delete_policy.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_policy_delete_policy_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Policy SET ops, rate|<p>Number of operations to set a policy.</p>|Dependent item|vault.metrics.policy.set_policy.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_policy_set_policy_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Token create, count|<p>The time taken to create a token.</p>|Dependent item|vault.metrics.token.create<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_create_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Token createAccessor, count|<p>The time taken to create a token accessor.</p>|Dependent item|vault.metrics.token.createAccessor<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_createAccessor_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Token lookup, rate|<p>Number of token look up.</p>|Dependent item|vault.metrics.token.lookup.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_lookup_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Token revoke, count|<p>The time taken to look up a token.</p>|Dependent item|vault.metrics.token.revoke<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_revoke_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Token revoke tree, count|<p>Time taken to revoke a token tree.</p>|Dependent item|vault.metrics.token.revoke.tree<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_revoke_tree_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Token store, count|<p>Time taken to store an updated token entry without writing to the secondary index.</p>|Dependent item|vault.metrics.token.store<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_token_store_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime allocated bytes|<p>Number of bytes allocated by the Vault process. This could burst from time to time, but should return to a steady state value.</p>|Dependent item|vault.metrics.runtime.alloc.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_alloc_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime freed objects|<p>Number of freed objects.</p>|Dependent item|vault.metrics.runtime.free.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_free_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime heap objects|<p>Number of objects on the heap. This is a good general memory pressure indicator worth establishing a baseline and thresholds for alerting.</p>|Dependent item|vault.metrics.runtime.heap.objects<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_heap_objects)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime malloc count|<p>Cumulative count of allocated heap objects.</p>|Dependent item|vault.metrics.runtime.malloc.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_malloc_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime num goroutines|<p>Number of goroutines. This serves as a general system load indicator worth establishing a baseline and thresholds for alerting.</p>|Dependent item|vault.metrics.runtime.num_goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_num_goroutines)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime sys bytes|<p>Number of bytes allocated to Vault. This includes what is being used by Vault's heap and what has been reclaimed but not given back to the operating system.</p>|Dependent item|vault.metrics.runtime.sys.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_sys_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime GC pause, total|<p>The total garbage collector pause time since Vault was last started.</p>|Dependent item|vault.metrics.total.gc.pause<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_total_gc_pause_ns)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Runtime GC runs, total|<p>Total number of garbage collection runs since Vault was last started.</p>|Dependent item|vault.metrics.runtime.total.gc.runs<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_runtime_total_gc_runs)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Token count, total|<p>Total number of service tokens available for use; counts all un-expired and un-revoked tokens in Vault's token store. This measurement is performed every 10 minutes.</p>|Dependent item|vault.metrics.token<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_token_count`</p></li><li><p>JSON Path: `$[?(@.name=="vault_token_count")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Token count by auth, total|<p>Total number of service tokens that were created by an auth method.</p>|Dependent item|vault.metrics.token.by_auth<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_token_count_by_auth`</p></li><li><p>JSON Path: `$[?(@.name=="vault_token_count_by_auth")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Token count by policy, total|<p>Total number of service tokens that have a policy attached.</p>|Dependent item|vault.metrics.token.by_policy<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_token_count_by_policy`</p></li><li><p>JSON Path: `$[?(@.name=="vault_token_count_by_policy")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Token count by ttl, total|<p>Number of service tokens, grouped by the TTL range they were assigned at creation.</p>|Dependent item|vault.metrics.token.by_ttl<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_token_count_by_ttl`</p></li><li><p>JSON Path: `$[?(@.name=="vault_token_count_by_ttl")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Token creation, rate|<p>Number of service or batch tokens created.</p>|Dependent item|vault.metrics.token.creation.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_token_creation`</p></li><li><p>JSON Path: `$[?(@.name=="vault_token_creation")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Secret kv entries|<p>Number of entries in each key-value secret engine.</p>|Dependent item|vault.metrics.secret.kv.count<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_secret_kv_count`</p></li><li><p>JSON Path: `$[?(@.name=="vault_secret_kv_count")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Token secret lease creation, rate|<p>Counts the number of leases created by secret engines.</p>|Dependent item|vault.metrics.secret.lease.creation.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `vault_secret_lease_creation`</p></li><li><p>JSON Path: `$[?(@.name=="vault_secret_lease_creation")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Vault: Vault server is sealed|<p>https://www.vaultproject.io/docs/concepts/seal</p>|`last(/HashiCorp Vault by HTTP/vault.health.sealed)=1`|Average||
|HashiCorp Vault: Version has changed|<p>Vault version has changed. Acknowledge to close the problem manually.</p>|`last(/HashiCorp Vault by HTTP/vault.health.version,#1)<>last(/HashiCorp Vault by HTTP/vault.health.version,#2) and length(last(/HashiCorp Vault by HTTP/vault.health.version))>0`|Info|**Manual close**: Yes|
|HashiCorp Vault: Vault server is not responding||`last(/HashiCorp Vault by HTTP/vault.health.check)=0`|High||
|HashiCorp Vault: Failed to get metrics||`length(last(/HashiCorp Vault by HTTP/vault.get_metrics.error))>0`|Warning|**Depends on**:<br><ul><li>HashiCorp Vault: Vault server is sealed</li></ul>|
|HashiCorp Vault: Current number of open files is too high||`min(/HashiCorp Vault by HTTP/vault.metrics.process.open.fds,5m)/last(/HashiCorp Vault by HTTP/vault.metrics.process.max.fds)*100>{$VAULT.OPEN.FDS.MAX.WARN}`|Warning||
|HashiCorp Vault: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/HashiCorp Vault by HTTP/vault.metrics.process.uptime)<10m`|Info|**Manual close**: Yes|
|HashiCorp Vault: High frequency of leadership setup failures|<p>There have been more than {$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN} Vault leadership setup failures in the past 1h.</p>|`(max(/HashiCorp Vault by HTTP/vault.metrics.core.leadership.setup_failed,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.leadership.setup_failed,1h))>{$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN}`|Average||
|HashiCorp Vault: High frequency of leadership losses|<p>There have been more than {$VAULT.LEADERSHIP.LOSSES.MAX.WARN} Vault leadership losses in the past 1h.</p>|`(max(/HashiCorp Vault by HTTP/vault.metrics.core.leadership_lost,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.leadership_lost,1h))>{$VAULT.LEADERSHIP.LOSSES.MAX.WARN}`|Average||
|HashiCorp Vault: High frequency of leadership step downs|<p>There have been more than {$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN} Vault leadership step downs in the past 1h.</p>|`(max(/HashiCorp Vault by HTTP/vault.metrics.core.step_down,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.step_down,1h))>{$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN}`|Average||

### LLD rule Storage metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage metrics discovery|<p>Storage backend metrics discovery.</p>|Dependent item|vault.storage.discovery|

### Item prototypes for Storage metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#STORAGE}] {#OPERATION} ops, rate|<p>Number of a {#OPERATION} operation against the {#STORAGE} storage backend.</p>|Dependent item|vault.metrics.storage.rate[{#STORAGE}, {#OPERATION}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({#PATTERN_C})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Mountpoint metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mountpoint metrics discovery|<p>Mountpoint metrics discovery.</p>|Dependent item|vault.mountpoint.discovery|

### Item prototypes for Mountpoint metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Rollback attempt [{#MOUNTPOINT}] ops, rate|<p>Number of operations to perform a rollback operation on the given mount point.</p>|Dependent item|vault.metrics.rollback.attempt.rate[{#MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({#PATTERN_C})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Route rollback [{#MOUNTPOINT}] ops, rate|<p>Number of operations to dispatch a rollback operation to a backend, and for that backend to process it. Rollback operations are automatically scheduled to clean up partial errors.</p>|Dependent item|vault.metrics.route.rollback.rate[{#MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({#PATTERN_C})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule WAL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL metrics discovery|<p>Discovery for WAL metrics.</p>|Dependent item|vault.wal.discovery|

### Item prototypes for WAL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Delete WALs, count{#SINGLETON}|<p>Time taken to delete a Write Ahead Log (WAL).</p>|Dependent item|vault.metrics.wal.deletewals[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_deletewals_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GC deleted WAL{#SINGLETON}|<p>Number of Write Ahead Logs (WAL) deleted during each garbage collection run.</p>|Dependent item|vault.metrics.wal.gc.deleted[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_gc_deleted)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|WALs on disk, total{#SINGLETON}|<p>Total Number of Write Ahead Logs (WAL) on disk.</p>|Dependent item|vault.metrics.wal.gc.total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_gc_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Load WALs, count{#SINGLETON}|<p>Time taken to load a Write Ahead Log (WAL).</p>|Dependent item|vault.metrics.wal.loadWAL[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_loadWAL_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Persist WALs, count{#SINGLETON}|<p>Time taken to persist a Write Ahead Log (WAL).</p>|Dependent item|vault.metrics.wal.persistwals[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_persistwals_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Flush ready WAL, count{#SINGLETON}|<p>Time taken to flush a ready Write Ahead Log (WAL) to storage.</p>|Dependent item|vault.metrics.wal.flushready[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(vault_wal_flushready_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication metrics discovery|<p>Discovery for replication metrics.</p>|Dependent item|vault.replication.discovery|

### Item prototypes for Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Stream WAL missing guard, count{#SINGLETON}|<p>Number of incidences where the starting Merkle Tree index used to begin streaming WAL entries is not matched/found.</p>|Dependent item|vault.metrics.logshipper.streamWALs.missing_guard[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(logshipper_streamWALs_missing_guard)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Stream WAL guard found, count{#SINGLETON}|<p>Number of incidences where the starting Merkle Tree index used to begin streaming WAL entries is matched/found.</p>|Dependent item|vault.metrics.logshipper.streamWALs.guard_found[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(logshipper_streamWALs_guard_found)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Merkle commit index{#SINGLETON}|<p>The last committed index in the Merkle Tree.</p>|Dependent item|vault.metrics.replication.merkle.commit_index[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replication_merkle_commit_index)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Last WAL{#SINGLETON}|<p>The index of the last WAL.</p>|Dependent item|vault.metrics.replication.wal.last_wal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replication_wal_last_wal)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Last DR WAL{#SINGLETON}|<p>The index of the last DR WAL.</p>|Dependent item|vault.metrics.replication.wal.last_dr_wal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replication_wal_last_dr_wal)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Last performance WAL{#SINGLETON}|<p>The index of the last Performance WAL.</p>|Dependent item|vault.metrics.replication.wal.last_performance_wal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replication_wal_last_performance_wal)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Last remote WAL{#SINGLETON}|<p>The index of the last remote WAL.</p>|Dependent item|vault.metrics.replication.fsm.last_remote_wal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replication_fsm_last_remote_wal)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Token metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Token metrics discovery|<p>Tokens metrics discovery.</p>|Dependent item|vault.tokens.discovery|

### Item prototypes for Token metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Token [{#TOKEN_NAME}] error|<p>Token lookup error text.</p>|Dependent item|vault.token_via_accessor.error["{#ACCESSOR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.accessor == "{#ACCESSOR}")].error.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Token [{#TOKEN_NAME}] has TTL|<p>The Token has TTL.</p>|Dependent item|vault.token_via_accessor.has_ttl["{#ACCESSOR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.accessor == "{#ACCESSOR}")].has_ttl.first()`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Token [{#TOKEN_NAME}] TTL|<p>The TTL period of the token.</p>|Dependent item|vault.token_via_accessor.ttl["{#ACCESSOR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.accessor == "{#ACCESSOR}")].ttl.first()`</p></li></ul>|

### Trigger prototypes for Token metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Vault: Token [{#TOKEN_NAME}] lookup error occurred||`length(last(/HashiCorp Vault by HTTP/vault.token_via_accessor.error["{#ACCESSOR}"]))>0`|Warning|**Depends on**:<br><ul><li>HashiCorp Vault: Vault server is sealed</li></ul>|
|HashiCorp Vault: Token [{#TOKEN_NAME}] will expire soon||`last(/HashiCorp Vault by HTTP/vault.token_via_accessor.has_ttl["{#ACCESSOR}"])=1 and last(/HashiCorp Vault by HTTP/vault.token_via_accessor.ttl["{#ACCESSOR}"])<{$VAULT.TOKEN.TTL.MIN.CRIT}`|Average||
|HashiCorp Vault: Token [{#TOKEN_NAME}] will expire soon||`last(/HashiCorp Vault by HTTP/vault.token_via_accessor.has_ttl["{#ACCESSOR}"])=1 and last(/HashiCorp Vault by HTTP/vault.token_via_accessor.ttl["{#ACCESSOR}"])<{$VAULT.TOKEN.TTL.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>HashiCorp Vault: Token [{#TOKEN_NAME}] will expire soon</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

