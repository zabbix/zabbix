<?php
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Regression test for "Zabbix server hangs indefinitely during shutdown, leaving multiple child
 * processes alive" (systemctl restart/stop never completes). Runs against a completely stock,
 * unmodified server binary - no diagnostic hooks or other code changes involved.
 *
 * Root cause: zbx_threads_kill_and_wait() -> zbx_thread_wait() in
 * src/libs/zbxthreads/threads.c blocks on waitpid() with no timeout. If a single child process
 * does not notice/act on its shutdown request for any reason, the whole multi-process server
 * waits forever, with no escalation to a forced kill.
 *
 * Reproduction technique: neither database driver Zabbix uses sets any read/query timeout
 * (confirmed by reading src/libs/zbxdb/db.c - PQconnectdbParams() and mysql_options() only set
 * TLS-related options, nothing timeout-related). So a write query blocked waiting for a table
 * lock genuinely never times out on either side - it has nothing to wake it up until the lock is
 * released. Confirmed on Postgres (statement_timeout/lock_timeout/
 * idle_in_transaction_session_timeout all disabled by default) and on MySQL (LOCK TABLES is
 * governed by lock_wait_timeout, not innodb_lock_wait_timeout, and MySQL 8.0 defaults that to
 * 31536000 seconds).
 *
 * This test opens a plain DB session (as the same DB user zabbix_server itself uses, over the
 * same connection parameters - no elevated privileges or OS-level process control needed) and
 * holds a table-level write lock on history_uint - the table the history syncer writes incoming
 * numeric values to. A trapper value is then sent for a completely ordinary item (no special
 * preprocessing at all), which the history syncer will try to flush into that now-locked table
 * and block indefinitely - "a thread stuck in a blocking call with no timeout of its own",
 * produced entirely through external orchestration, matching the actual reported bug (which
 * itself never involved any crash). See acquireHistoryLock() for the Postgres/MySQL-specific
 * mechanics.
 *
 * This test is expected to currently FAIL against an unpatched server - that failure is the
 * reproduction. It should pass once the missing-timeout fix in zbx_threads_kill_and_wait() lands.
 *
 * @backup hosts
 */
class testServerRestartHang extends CIntegrationTest {

	const HOST_NAME = 'test_restart_hang';
	const LOCK_TABLE = 'history_uint';

	// generous bound: comfortably below CIntegrationTest's own ~60s give-up point, so it cleanly
	// distinguishes "fixed" (shutdown completes once the bounded-timeout escalation kicks in)
	// from "still broken" (never completes on its own)
	const MAX_ACCEPTABLE_SHUTDOWN_SECONDS = 30;

	private static $hostid;
	private static $itemid;

	/** @var resource|\PgSql\Connection|\mysqli|null */
	private $lockConn = null;

	/** @var string|null ZBX_DB_POSTGRESQL or ZBX_DB_MYSQL - which branch acquired/must release the lock */
	private $lockDbType = null;

	/**
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'StartPreprocessors' => 1,
				'StartDBSyncers' => 1
			]
		];
	}

	/**
	 * Create a host with one completely ordinary trapper item - no preprocessing, nothing
	 * special about it at all.
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [
				['groupid' => 4]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'plain_uint',
			'key_' => 'plain_uint',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$itemid = $response['result']['itemids'][0];

		return true;
	}

	/**
	 * Open a plain DB session using the same credentials zabbix_server itself uses, and hold a
	 * table-level write lock on the history table. This blocks any write to that table from any
	 * other session - including the history syncer - until releaseHistoryLock() releases it or
	 * the connection is closed.
	 *
	 * Postgres: LOCK TABLE ... IN ACCESS EXCLUSIVE MODE under an open transaction. Confirmed
	 * unbounded on a default instance (statement_timeout/lock_timeout/
	 * idle_in_transaction_session_timeout all disabled).
	 *
	 * MySQL: LOCK TABLES ... WRITE. This is a metadata/table lock governed by the session's
	 * lock_wait_timeout, NOT innodb_lock_wait_timeout (which only applies to row-level InnoDB
	 * locks such as SELECT ... FOR UPDATE) - MySQL 8.0's default for lock_wait_timeout is
	 * 31536000 seconds (365 days), i.e. effectively unbounded for this test's purposes, matching
	 * the Postgres behavior above. This path has not been exercised against a live MySQL
	 * integration environment the way the Postgres path has - the mechanism is sound (confirmed
	 * against MySQL's own documented locking/timeout semantics) but treat it as less
	 * battle-tested than the Postgres path until it's actually been run once for real.
	 */
	private function acquireHistoryLock() {
		global $DB;

		$this->lockDbType = $DB['TYPE'];

		switch ($this->lockDbType) {
			case ZBX_DB_POSTGRESQL:
				$conn_string = 'host='.($DB['SERVER'] ?: '127.0.0.1').' port='.($DB['PORT'] ?: 5432).
						' dbname='.$DB['DATABASE'].' user='.$DB['USER'].' password='.$DB['PASSWORD'];

				$this->lockConn = pg_connect($conn_string);
				$this->assertNotFalse($this->lockConn,
						'Failed to open direct Postgres connection for lock hold.');

				$res = pg_query($this->lockConn, 'BEGIN');
				$this->assertNotFalse($res, 'BEGIN failed: '.pg_last_error($this->lockConn));

				$res = pg_query($this->lockConn, 'LOCK TABLE '.self::LOCK_TABLE.
						' IN ACCESS EXCLUSIVE MODE');
				$this->assertNotFalse($res, 'LOCK TABLE failed: '.pg_last_error($this->lockConn));
				break;

			case ZBX_DB_MYSQL:
				$this->lockConn = mysqli_connect($DB['SERVER'] ?: '127.0.0.1', $DB['USER'],
						$DB['PASSWORD'], $DB['DATABASE'], (int) ($DB['PORT'] ?: 3306));
				$this->assertNotFalse($this->lockConn,
						'Failed to open direct MySQL connection for lock hold.');

				$res = mysqli_query($this->lockConn, 'LOCK TABLES '.self::LOCK_TABLE.' WRITE');
				$this->assertNotFalse($res, 'LOCK TABLES failed: '.mysqli_error($this->lockConn));
				break;

			default:
				$this->markTestSkipped('This test only supports PostgreSQL and MySQL - DB type '.
						'configured for this run is: '.$this->lockDbType);
		}

		fwrite(STDERR, "testServerRestartHang: holding write lock on ".self::LOCK_TABLE.
				" (".$this->lockDbType.")\n");
	}

	/**
	 * Release the lock (COMMIT is enough - we never wrote anything) and close the connection.
	 * Must always be called, even if the test itself failed/hung, or the lock stays held forever
	 * and breaks every other session on this database.
	 */
	private function releaseHistoryLock() {
		if (null === $this->lockConn) {
			return;
		}

		fwrite(STDERR, "testServerRestartHang: releasing lock on ".self::LOCK_TABLE."\n");

		switch ($this->lockDbType) {
			case ZBX_DB_POSTGRESQL:
				@pg_query($this->lockConn, 'COMMIT');
				pg_close($this->lockConn);
				break;

			case ZBX_DB_MYSQL:
				@mysqli_query($this->lockConn, 'UNLOCK TABLES');
				mysqli_close($this->lockConn);
				break;
		}

		$this->lockConn = null;
		$this->lockDbType = null;
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testServerRestartHang_lockedHistoryTableBoundedShutdown() {
		$this->reloadConfigurationCache();

		try {
			$this->acquireHistoryLock();

			fwrite(STDERR, "testServerRestartHang: sending value that the history syncer will".
					" block trying to write\n");

			try {
				$this->sendSenderValue(self::HOST_NAME, 'plain_uint', '1');
			}
			catch (Exception $e) {
				fwrite(STDERR, "testServerRestartHang: sendSenderValue: ".$e->getMessage()."\n");
			}

			// give the history syncer time to actually pick up the value and block on the lock
			sleep(5);

			fwrite(STDERR, "testServerRestartHang: attempting graceful stop with the history table".
					" locked (no C code involved at all)\n");

			$start = microtime(true);
			$hung = false;

			try {
				self::stopComponent(self::COMPONENT_SERVER);
			}
			catch (Exception $e) {
				$hung = true;
				fwrite(STDERR, "testServerRestartHang: stop did not complete: ".$e->getMessage()."\n");
			}

			$elapsed = microtime(true) - $start;
			fwrite(STDERR, "testServerRestartHang: stop attempt took ".round($elapsed, 1)."s, hung=".
					($hung ? 'yes' : 'no')."\n");

			if ($hung) {
				try {
					self::killComponent(self::COMPONENT_SERVER);
				}
				catch (Exception $e) {
					fwrite(STDERR, "testServerRestartHang: killComponent cleanup: ".
							$e->getMessage()."\n");
				}
			}
		}
		finally {
			// this MUST run even if the assertions below fail, or the lock is held forever
			$this->releaseHistoryLock();
		}

		if ($hung) {
			$this->fail('Server did not shut down within CIntegrationTest\'s own timeout ('.
					round($elapsed, 1).'s) while the history syncer was blocked on a locked table '.
					'- matches "hangs indefinitely during shutdown, leaving multiple child '.
					'processes alive", reproduced with zero code changes against a stock binary.');
		}

		$this->assertLessThan(self::MAX_ACCEPTABLE_SHUTDOWN_SECONDS, $elapsed,
				'Server took '.round($elapsed, 1).'s to shut down with the history table locked - '.
				'expected the bounded-timeout escalation to force it within a few seconds of the '.
				'configured threshold.');
	}
}
