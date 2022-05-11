
# HashiCorp Vault by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor HashiCorp Vault by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Vault by HTTP` — collects metrics by HTTP agent from `/sys/metrics` API endpoint.
See https://www.vaultproject.io/api-docs/system/metrics.



This template was tested on:

- Vault, version 1.6

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Configure Vault API. See [Vault Configuration](https://www.vaultproject.io/docs/configuration).
Create a Vault service token and set it to the macro `{$VAULT.TOKEN}`.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VAULT.API.PORT} |<p>Vault port.</p> |`8200` |
|{$VAULT.API.SCHEME} |<p>Vault API scheme.</p> |`http` |
|{$VAULT.HOST} |<p>Vault host name.</p> |`<PUT YOUR VAULT HOST>` |
|{$VAULT.LEADERSHIP.LOSSES.MAX.WARN} |<p>Maximum number of Vault leadership losses.</p> |`5` |
|{$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN} |<p>Maximum number of Vault leadership setup failed.</p> |`5` |
|{$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN} |<p>Maximum number of Vault leadership step downs.</p> |`5` |
|{$VAULT.LLD.FILTER.STORAGE.MATCHES} |<p>Filter of discoverable storage backends.</p> |`.+` |
|{$VAULT.OPEN.FDS.MAX.WARN} |<p>Maximum percentage of used file descriptors for trigger expression.</p> |`90` |
|{$VAULT.TOKEN.ACCESSORS} |<p>Vault accessors separated by spaces for monitoring token expiration time.</p> |`` |
|{$VAULT.TOKEN.TTL.MIN.CRIT} |<p>Token TTL critical threshold.</p> |`3d` |
|{$VAULT.TOKEN.TTL.MIN.WARN} |<p>Token TTL warning threshold.</p> |`7d` |
|{$VAULT.TOKEN} |<p>Vault auth token.</p> |`<PUT YOUR AUTH TOKEN>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mountpoint metrics discovery |<p>Mountpoint metrics discovery.</p> |DEPENDENT |vault.mountpoint.discovery |
|Replication metrics discovery |<p>Discovery for replication metrics.</p> |DEPENDENT |vault.replication.discovery |
|Storage metrics discovery |<p>Storage backend metrics discovery.</p> |DEPENDENT |vault.storage.discovery<p>**Filter**:</p>AND <p>- {#STORAGE} MATCHES_REGEX `{$VAULT.LLD.FILTER.STORAGE.MATCHES}`</p> |
|Token metrics discovery |<p>Tokens metrics discovery.</p> |DEPENDENT |vault.tokens.discovery |
|WAL metrics discovery |<p>Discovery for WAL metrics.</p> |DEPENDENT |vault.wal.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Vault |Vault: Initialized |<p>Initialization status.</p> |DEPENDENT |vault.health.initialized<p>**Preprocessing**:</p><p>- JSONPATH: `$.initialized`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Sealed |<p>Seal status.</p> |DEPENDENT |vault.health.sealed<p>**Preprocessing**:</p><p>- JSONPATH: `$.sealed`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Standby |<p>Standby status.</p> |DEPENDENT |vault.health.standby<p>**Preprocessing**:</p><p>- JSONPATH: `$.standby`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Performance standby |<p>Performance standby status.</p> |DEPENDENT |vault.health.performance_standby<p>**Preprocessing**:</p><p>- JSONPATH: `$.performance_standby`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Performance replication |<p>Performance replication mode</p><p>https://www.vaultproject.io/docs/enterprise/replication</p> |DEPENDENT |vault.health.replication_performance_mode<p>**Preprocessing**:</p><p>- JSONPATH: `$.replication_performance_mode`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Disaster Recovery replication |<p>Disaster recovery replication mode</p><p>https://www.vaultproject.io/docs/enterprise/replication</p> |DEPENDENT |vault.health.replication_dr_mode<p>**Preprocessing**:</p><p>- JSONPATH: `$.replication_dr_mode`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Version |<p>Server version.</p> |DEPENDENT |vault.health.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Healthcheck |<p>Vault healthcheck.</p> |DEPENDENT |vault.health.check<p>**Preprocessing**:</p><p>- JSONPATH: `$.healthcheck`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: HA enabled |<p>HA enabled status.</p> |DEPENDENT |vault.leader.ha_enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.ha_enabled`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Is leader |<p>Leader status.</p> |DEPENDENT |vault.leader.is_self<p>**Preprocessing**:</p><p>- JSONPATH: `$.is_self`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Get metrics error |<p>Get metrics error.</p> |DEPENDENT |vault.get_metrics.error<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors[0]`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Process CPU seconds, total |<p>Total user and system CPU time spent in seconds.</p> |DEPENDENT |vault.metrics.process.cpu.seconds.total<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_cpu_seconds_total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Open file descriptors, max |<p>Maximum number of open file descriptors.</p> |DEPENDENT |vault.metrics.process.max.fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Open file descriptors, current |<p>Number of open file descriptors.</p> |DEPENDENT |vault.metrics.process.open.fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Process resident memory |<p>Resident memory size in bytes.</p> |DEPENDENT |vault.metrics.process.resident_memory.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Uptime |<p>Server uptime.</p> |DEPENDENT |vault.metrics.process.uptime<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_start_time_seconds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return Math.floor(Date.now()/1000 - Number(value));`</p> |
|Vault |Vault: Process virtual memory, current |<p>Virtual memory size in bytes.</p> |DEPENDENT |vault.metrics.process.virtual_memory.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Process virtual memory, max |<p>Maximum amount of virtual memory available in bytes.</p> |DEPENDENT |vault.metrics.process.virtual_memory.max.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_max_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Audit log requests, rate |<p>Number of all audit log requests across all audit log devices.</p> |DEPENDENT |vault.metrics.audit.log.request.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_audit_log_request_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Audit log request failures, rate |<p>Number of audit log request failures.</p> |DEPENDENT |vault.metrics.audit.log.request.failure.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_audit_log_request_failure`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Audit log response, rate |<p>Number of audit log responses across all audit log devices.</p> |DEPENDENT |vault.metrics.audit.log.response.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_audit_log_response_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Audit log response failures, rate |<p>Number of audit log response failures.</p> |DEPENDENT |vault.metrics.audit.log.response.failure.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_audit_log_response_failure`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Barrier DELETE ops, rate |<p>Number of DELETE operations at the barrier.</p> |DEPENDENT |vault.metrics.barrier.delete.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_barrier_delete_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Barrier GET ops, rate |<p>Number of GET operations at the barrier.</p> |DEPENDENT |vault.metrics.vault.barrier.get.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_barrier_get_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Barrier LIST ops, rate |<p>Number of LIST operations at the barrier.</p> |DEPENDENT |vault.metrics.barrier.list.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_barrier_list_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Barrier PUT ops, rate |<p>Number of PUT operations at the barrier.</p> |DEPENDENT |vault.metrics.barrier.put.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_barrier_put_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Cache hit, rate |<p>Number of times a value was retrieved from the LRU cache.</p> |DEPENDENT |vault.metrics.cache.hit.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_cache_hit`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Cache miss, rate |<p>Number of times a value was not in the LRU cache. The results in a read from the configured storage.</p> |DEPENDENT |vault.metrics.cache.miss.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_cache_miss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Cache write, rate |<p>Number of times a value was written to the LRU cache.</p> |DEPENDENT |vault.metrics.cache.write.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_cache_write`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Check token, rate |<p>Number of token checks handled by Vault core.</p> |DEPENDENT |vault.metrics.core.check.token.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_check_token_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Fetch ACL and token, rate |<p>Number of ACL and corresponding token entry fetches handled by Vault core.</p> |DEPENDENT |vault.metrics.core.fetch.acl_and_token<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_fetch_acl_and_token_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Requests, rate |<p>Number of requests handled by Vault core.</p> |DEPENDENT |vault.metrics.core.handle.request<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_handle_request_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Leadership setup failed, counter |<p>Cluster leadership setup failures which have occurred in a highly available Vault cluster.</p> |DEPENDENT |vault.metrics.core.leadership.setup_failed<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_core_leadership_setup_failed`</p><p>- JSONPATH: `$[?(@.name=="vault_core_leadership_setup_failed")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Leadership setup lost, counter |<p>Cluster leadership losses which have occurred in a highly available Vault cluster.</p> |DEPENDENT |vault.metrics.core.leadership_lost<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_core_leadership_lost_count`</p><p>- JSONPATH: `$[?(@.name=="vault_core_leadership_lost_count")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Post-unseal ops, counter |<p>Duration of time taken by post-unseal operations handled by Vault core.</p> |DEPENDENT |vault.metrics.core.post_unseal<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_post_unseal_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Pre-seal ops, counter |<p>Duration of time taken by pre-seal operations.</p> |DEPENDENT |vault.metrics.core.pre_seal<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_pre_seal_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Requested seal ops, counter |<p>Duration of time taken by requested seal operations.</p> |DEPENDENT |vault.metrics.core.seal_with_request<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_seal_with_request_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Seal ops, counter |<p>Duration of time taken by seal operations.</p> |DEPENDENT |vault.metrics.core.seal<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_seal_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Internal seal ops, counter |<p>Duration of time taken by internal seal operations.</p> |DEPENDENT |vault.metrics.core.seal_internal<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_seal_internal_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Leadership step downs, counter |<p>Cluster leadership step down.</p> |DEPENDENT |vault.metrics.core.step_down<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_core_step_down_count`</p><p>- JSONPATH: `$[?(@.name=="vault_core_step_down_count")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Unseal ops, counter |<p>Duration of time taken by unseal operations.</p> |DEPENDENT |vault.metrics.core.unseal<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_core_unseal_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Fetch lease times, counter |<p>Time taken to fetch lease times.</p> |DEPENDENT |vault.metrics.expire.fetch.lease.times<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_fetch_lease_times_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Fetch lease times by token, counter |<p>Time taken to fetch lease times by token.</p> |DEPENDENT |vault.metrics.expire.fetch.lease.times.by_token<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_fetch_lease_times_by_token_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Number of expiring leases |<p>Number of all leases which are eligible for eventual expiry.</p> |DEPENDENT |vault.metrics.expire.num_leases<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_num_leases`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Expire revoke, count |<p>Time taken to revoke a token.</p> |DEPENDENT |vault.metrics.expire.revoke<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_revoke_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Expire revoke force, count |<p>Time taken to forcibly revoke a token.</p> |DEPENDENT |vault.metrics.expire.revoke.force<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_revoke_force_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Expire revoke prefix, count |<p>Tokens revoke on a prefix.</p> |DEPENDENT |vault.metrics.expire.revoke.prefix<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_revoke_prefix_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Revoke secrets by token, count |<p>Time taken to revoke all secrets issued with a given token.</p> |DEPENDENT |vault.metrics.expire.revoke.by_token<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_revoke_by_token_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Expire renew, count |<p>Time taken to renew a lease.</p> |DEPENDENT |vault.metrics.expire.renew<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_renew_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Renew token, count |<p>Time taken to renew a token which does not need to invoke a logical backend.</p> |DEPENDENT |vault.metrics.expire.renew_token<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_renew_token_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Register ops, count |<p>Time taken for register operations.</p> |DEPENDENT |vault.metrics.expire.register<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_register_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Register auth ops, count |<p>Time taken for register authentication operations which create lease entries without lease ID.</p> |DEPENDENT |vault.metrics.expire.register.auth<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_expire_register_auth_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Policy GET ops, rate |<p>Number of operations to get a policy.</p> |DEPENDENT |vault.metrics.policy.get_policy.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_policy_get_policy_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Policy LIST ops, rate |<p>Number of operations to list policies.</p> |DEPENDENT |vault.metrics.policy.list_policies.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_policy_list_policies_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Policy DELETE ops, rate |<p>Number of operations to delete a policy.</p> |DEPENDENT |vault.metrics.policy.delete_policy.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_policy_delete_policy_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Policy SET ops, rate |<p>Number of operations to set a policy.</p> |DEPENDENT |vault.metrics.policy.set_policy.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_policy_set_policy_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Token create, count |<p>The time taken to create a token.</p> |DEPENDENT |vault.metrics.token.create<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_create_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token createAccessor, count |<p>The time taken to create a token accessor.</p> |DEPENDENT |vault.metrics.token.createAccessor<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_createAccessor_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token lookup, rate |<p>Number of token look up.</p> |DEPENDENT |vault.metrics.token.lookup.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_lookup_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Token revoke, count |<p>The time taken to look up a token.</p> |DEPENDENT |vault.metrics.token.revoke<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_revoke_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token revoke tree, count |<p>Time taken to revoke a token tree.</p> |DEPENDENT |vault.metrics.token.revoke.tree<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_revoke_tree_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token store, count |<p>Time taken to store an updated token entry without writing to the secondary index.</p> |DEPENDENT |vault.metrics.token.store<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_token_store_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime allocated bytes |<p>Number of bytes allocated by the Vault process. This could burst from time to time, but should return to a steady state value.</p> |DEPENDENT |vault.metrics.runtime.alloc.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_alloc_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime freed objects |<p>Number of freed objects.</p> |DEPENDENT |vault.metrics.runtime.free.count<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_free_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime heap objects |<p>Number of objects on the heap. This is a good general memory pressure indicator worth establishing a baseline and thresholds for alerting.</p> |DEPENDENT |vault.metrics.runtime.heap.objects<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_heap_objects`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime malloc count |<p>Cumulative count of allocated heap objects.</p> |DEPENDENT |vault.metrics.runtime.malloc.count<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_malloc_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime num goroutines |<p>Number of goroutines. This serves as a general system load indicator worth establishing a baseline and thresholds for alerting.</p> |DEPENDENT |vault.metrics.runtime.num_goroutines<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_num_goroutines`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime sys bytes |<p>Number of bytes allocated to Vault. This includes what is being used by Vault's heap and what has been reclaimed but not given back to the operating system.</p> |DEPENDENT |vault.metrics.runtime.sys.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_sys_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Runtime GC pause, total |<p>The total garbage collector pause time since Vault was last started.</p> |DEPENDENT |vault.metrics.total.gc.pause<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_total_gc_pause_ns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `1.0E-9`</p> |
|Vault |Vault: Runtime GC runs, total |<p>Total number of garbage collection runs since Vault was last started.</p> |DEPENDENT |vault.metrics.runtime.total.gc.runs<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_runtime_total_gc_runs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token count, total |<p>Total number of service tokens available for use; counts all un-expired and un-revoked tokens in Vault's token store. This measurement is performed every 10 minutes.</p> |DEPENDENT |vault.metrics.token<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_token_count`</p><p>- JSONPATH: `$[?(@.name=="vault_token_count")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Token count by auth, total |<p>Total number of service tokens that were created by a auth method.</p> |DEPENDENT |vault.metrics.token.by_auth<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_token_count_by_auth`</p><p>- JSONPATH: `$[?(@.name=="vault_token_count_by_auth")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Token count by policy, total |<p>Total number of service tokens that have a policy attached.</p> |DEPENDENT |vault.metrics.token.by_policy<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_token_count_by_policy`</p><p>- JSONPATH: `$[?(@.name=="vault_token_count_by_policy")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Token count by ttl, total |<p>Number of service tokens, grouped by the TTL range they were assigned at creation.</p> |DEPENDENT |vault.metrics.token.by_ttl<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_token_count_by_ttl`</p><p>- JSONPATH: `$[?(@.name=="vault_token_count_by_ttl")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Token creation, rate |<p>Number of service or batch tokens created.</p> |DEPENDENT |vault.metrics.token.creation.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_token_creation`</p><p>- JSONPATH: `$[?(@.name=="vault_token_creation")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Secret kv entries |<p>Number of entries in each key-value secret engine.</p> |DEPENDENT |vault.metrics.secret.kv.count<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_secret_kv_count`</p><p>- JSONPATH: `$[?(@.name=="vault_secret_kv_count")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Vault |Vault: Token secret lease creation, rate |<p>Counts the number of leases created by secret engines.</p> |DEPENDENT |vault.metrics.secret.lease.creation.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `vault_secret_lease_creation`</p><p>- JSONPATH: `$[?(@.name=="vault_secret_lease_creation")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Storage [{#STORAGE}] {#OPERATION} ops, rate |<p>Number of a {#OPERATION} operation against the {#STORAGE} storage backend.</p> |DEPENDENT |vault.metrics.storage.rate[{#STORAGE}, {#OPERATION}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{#PATTERN_C}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Rollback attempt [{#MOUNTPOINT}] ops, rate |<p>Number of operations to perform a rollback operation on the given mount point.</p> |DEPENDENT |vault.metrics.rollback.attempt.rate[{#MOUNTPOINT}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{#PATTERN_C}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Route rollback [{#MOUNTPOINT}] ops, rate |<p>Number of operations to dispatch a rollback operation to a backend, and for that backend to process it. Rollback operations are automatically scheduled to clean up partial errors.</p> |DEPENDENT |vault.metrics.route.rollback.rate[{#MOUNTPOINT}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{#PATTERN_C}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Vault |Vault: Delete WALs, count{#SINGLETON} |<p>Time taken to delete a Write Ahead Log (WAL).</p> |DEPENDENT |vault.metrics.wal.deletewals[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_deletewals_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: GC deleted WAL{#SINGLETON} |<p>Number of Write Ahead Logs (WAL) deleted during each garbage collection run.</p> |DEPENDENT |vault.metrics.wal.gc.deleted[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_gc_deleted`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: WALs on disk, total{#SINGLETON} |<p>Total Number of Write Ahead Logs (WAL) on disk.</p> |DEPENDENT |vault.metrics.wal.gc.total[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_gc_total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Load WALs, count{#SINGLETON} |<p>Time taken to load a Write Ahead Log (WAL).</p> |DEPENDENT |vault.metrics.wal.loadWAL[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_loadWAL_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Persist WALs, count{#SINGLETON} |<p>Time taken to persist a Write Ahead Log (WAL).</p> |DEPENDENT |vault.metrics.wal.persistwals[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_persistwals_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Flush ready WAL, count{#SINGLETON} |<p>Time taken to flush a ready Write Ahead Log (WAL) to storage.</p> |DEPENDENT |vault.metrics.wal.flushready[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `vault_wal_flushready_count`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Stream WAL missing guard, count{#SINGLETON} |<p>Number of incidences where the starting Merkle Tree index used to begin streaming WAL entries is not matched/found.</p> |DEPENDENT |vault.metrics.logshipper.streamWALs.missing_guard[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `logshipper_streamWALs_missing_guard`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Stream WAL guard found, count{#SINGLETON} |<p>Number of incidences where the starting Merkle Tree index used to begin streaming WAL entries is matched/found.</p> |DEPENDENT |vault.metrics.logshipper.streamWALs.guard_found[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `logshipper_streamWALs_guard_found`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Merkle commit index{#SINGLETON} |<p>The last committed index in the Merkle Tree.</p> |DEPENDENT |vault.metrics.replication.merkle.commit_index[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replication_merkle_commit_index`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Last WAL{#SINGLETON} |<p>The index of the last WAL.</p> |DEPENDENT |vault.metrics.replication.wal.last_wal[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replication_wal_last_wal`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Last DR WAL{#SINGLETON} |<p>The index of the last DR WAL.</p> |DEPENDENT |vault.metrics.replication.wal.last_dr_wal[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replication_wal_last_dr_wal`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Last performance WAL{#SINGLETON} |<p>The index of the last Performance WAL.</p> |DEPENDENT |vault.metrics.replication.wal.last_performance_wal[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replication_wal_last_performance_wal`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Last remote WAL{#SINGLETON} |<p>The index of the last remote WAL.</p> |DEPENDENT |vault.metrics.replication.fsm.last_remote_wal[{#SINGLETON}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replication_fsm_last_remote_wal`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Vault |Vault: Token [{#TOKEN_NAME}] error |<p>Token lookup error text.</p> |DEPENDENT |vault.token_via_accessor.error["{#ACCESSOR}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.accessor == "{#ACCESSOR}")].error.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Token [{#TOKEN_NAME}] has TTL |<p>The Token has TTL.</p> |DEPENDENT |vault.token_via_accessor.has_ttl["{#ACCESSOR}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.accessor == "{#ACCESSOR}")].has_ttl.first()`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Vault |Vault: Token [{#TOKEN_NAME}] TTL |<p>The TTL period of the token.</p> |DEPENDENT |vault.token_via_accessor.ttl["{#ACCESSOR}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.accessor == "{#ACCESSOR}")].ttl.first()`</p> |
|Zabbix raw items |Vault: Get health |<p>-</p> |HTTP_AGENT |vault.get_health<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"healthcheck": 0}`</p> |
|Zabbix raw items |Vault: Get leader |<p>-</p> |HTTP_AGENT |vault.get_leader<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p> |
|Zabbix raw items |Vault: Get metrics |<p>-</p> |HTTP_AGENT |vault.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p> |
|Zabbix raw items |Vault: Clear metrics |<p>-</p> |DEPENDENT |vault.clear_metrics<p>**Preprocessing**:</p><p>- CHECK_JSON_ERROR: `$.errors`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |Vault: Get tokens |<p>Get information about tokens via their accessors. Accessors are defined in the macro "{$VAULT.TOKEN.ACCESSORS}".</p> |SCRIPT |vault.get_tokens<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Vault: Check WAL discovery |<p>-</p> |DEPENDENT |vault.check_wal_discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^vault_wal_(?:.+)$"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return JSON.stringify(value !== "[]" ? [{'{#SINGLETON}': ''}] : []);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Zabbix raw items |Vault: Check replication discovery |<p>-</p> |DEPENDENT |vault.check_replication_discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^replication_(?:.+)$"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return JSON.stringify(value !== "[]" ? [{'{#SINGLETON}': ''}] : []);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Zabbix raw items |Vault: Check storage discovery |<p>-</p> |DEPENDENT |vault.check_storage_discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^vault_(?:.+)_(?:get|put|list|delete)_count$"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Zabbix raw items |Vault: Check mountpoint discovery |<p>-</p> |DEPENDENT |vault.check_mountpoint_discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^vault_rollback_attempt_(?:.+?)_count$"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Vault: Vault server is sealed |<p>https://www.vaultproject.io/docs/concepts/seal</p> |`last(/HashiCorp Vault by HTTP/vault.health.sealed)=1` |AVERAGE | |
|Vault: Version has changed |<p>Vault version has changed. Ack to close.</p> |`last(/HashiCorp Vault by HTTP/vault.health.version,#1)<>last(/HashiCorp Vault by HTTP/vault.health.version,#2) and length(last(/HashiCorp Vault by HTTP/vault.health.version))>0` |INFO |<p>Manual close: YES</p> |
|Vault: Vault server is not responding |<p>-</p> |`last(/HashiCorp Vault by HTTP/vault.health.check)=0` |HIGH | |
|Vault: Failed to get metrics |<p>-</p> |`length(last(/HashiCorp Vault by HTTP/vault.get_metrics.error))>0` |WARNING |<p>**Depends on**:</p><p>- Vault: Vault server is sealed</p> |
|Vault: Current number of open files is too high |<p>-</p> |`min(/HashiCorp Vault by HTTP/vault.metrics.process.open.fds,5m)/last(/HashiCorp Vault by HTTP/vault.metrics.process.max.fds)*100>{$VAULT.OPEN.FDS.MAX.WARN}` |WARNING | |
|Vault: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/HashiCorp Vault by HTTP/vault.metrics.process.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Vault: High frequency of leadership setup failures |<p>There have been more than {$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN} Vault leadership setup failures in the past 1h.</p> |`(max(/HashiCorp Vault by HTTP/vault.metrics.core.leadership.setup_failed,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.leadership.setup_failed,1h))>{$VAULT.LEADERSHIP.SETUP.FAILED.MAX.WARN}` |AVERAGE | |
|Vault: High frequency of leadership losses |<p>There have been more than {$VAULT.LEADERSHIP.LOSSES.MAX.WARN} Vault leadership losses in the past 1h.</p> |`(max(/HashiCorp Vault by HTTP/vault.metrics.core.leadership_lost,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.leadership_lost,1h))>{$VAULT.LEADERSHIP.LOSSES.MAX.WARN}` |AVERAGE | |
|Vault: High frequency of leadership step downs |<p>There have been more than {$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN} Vault leadership step downs in the past 1h.</p> |`(max(/HashiCorp Vault by HTTP/vault.metrics.core.step_down,1h)-min(/HashiCorp Vault by HTTP/vault.metrics.core.step_down,1h))>{$VAULT.LEADERSHIP.STEPDOWNS.MAX.WARN}` |AVERAGE | |
|Vault: Token [{#TOKEN_NAME}] lookup error occurred |<p>-</p> |`length(last(/HashiCorp Vault by HTTP/vault.token_via_accessor.error["{#ACCESSOR}"]))>0` |WARNING |<p>**Depends on**:</p><p>- Vault: Vault server is sealed</p> |
|Vault: Token [{#TOKEN_NAME}] will expire soon |<p>-</p> |`last(/HashiCorp Vault by HTTP/vault.token_via_accessor.has_ttl["{#ACCESSOR}"])=1 and last(/HashiCorp Vault by HTTP/vault.token_via_accessor.ttl["{#ACCESSOR}"])<{$VAULT.TOKEN.TTL.MIN.CRIT}` |AVERAGE | |
|Vault: Token [{#TOKEN_NAME}] will expire soon |<p>-</p> |`last(/HashiCorp Vault by HTTP/vault.token_via_accessor.has_ttl["{#ACCESSOR}"])=1 and last(/HashiCorp Vault by HTTP/vault.token_via_accessor.ttl["{#ACCESSOR}"])<{$VAULT.TOKEN.TTL.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Vault: Token [{#TOKEN_NAME}] will expire soon</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

