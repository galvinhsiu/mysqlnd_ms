--TEST--
Fabric: sharding.lookup_servers command + warn about trx boundaries
--XFAIL--
No trx boundary warning
--SKIPIF--
<?php
require_once('skipif.inc');
require_once("connect.inc");
require_once("util.inc");

if (!getenv("MYSQL_TEST_FABRIC")) {
	die(sprintf("SKIP Fabric - set MYSQL_TEST_FABRIC=1 (config.inc) to enable\n"));
}

if ($error = mst_mysqli_create_test_table($emulated_master_host_only, $user, $passwd, $db, $emulated_master_port, $emulated_master_socket, $tablename = "test")) {
  die(sprintf("SKIP Failed to create test table %s\n", $error));
}

$settings = array(
	"myapp" => array(
		'fabric' => array(
			'hosts' => array(
				array('host' => getenv("MYSQL_TEST_FABRIC_EMULATOR_HOST"), 'port' => getenv("MYSQL_TEST_FABRIC_EMULATOR_PORT"))
			),
			'timeout' => 2,
			'trx_warn_serverlist_changes' => 1,
		),
		'trx_stickiness' => 'on',
	),
);
if ($error = mst_create_config("test_mysqlnd_ms_fabric_warn_trx_boundaries_on.ini", $settings))
  die(sprintf("SKIP %s\n", $error));
?>
--INI--
mysqlnd_ms.enable=1
mysqlnd_ms.config_file=test_mysqlnd_ms_fabric_warn_trx_boundaries_on.ini
mysqlnd_ms.collect_statistics=1
--FILE--
<?php
	require_once("connect.inc");
	require_once("util.inc");

	$stats = mysqlnd_ms_get_stats();

	$link = mst_mysqli_connect("myapp", $user, $passwd, $db, $port, $socket);
	if (0 !== mysqli_connect_errno())
		printf("[001] [%d] %s\n", mysqli_connect_errno(), mysqli_connect_error());

	/* Would be surprised if anybody set a mapping for table=DUAL... */
	@mysqlnd_ms_fabric_select_global($link, 'DUAL');

	@$link->query("DROP TABLE IF EXISTS test");
	printf("[002] [%d/%s] '%s'\n", $link->errno, $link->sqlstate, $link->error);

	mysqlnd_ms_fabric_select_global($link, 'fabric_sharding.test');

	$link->query("DROP TABLE IF EXISTS test");
	printf("[003] [%d/%s] '%s'\n", $link->errno, $link->sqlstate, $link->error);

	$now = mysqlnd_ms_get_stats();

	foreach ($stats as $k => $v) {
		if ($now[$k] != $v) {
			printf("%s: %s -> %s\n", $k, $v, $now[$k]);
		}
	}
	print "done!";
?>
--CLEAN--
<?php
	require_once("util.inc");
	if ($error = mst_mysqli_drop_test_table($emulated_master_host_only, $user, $passwd, $db, $emulated_master_port, $emulated_master_socket, $tablename = "test"))
		printf("[clean] Cannot remove test table, %s\n", $error);

	if (!unlink("test_mysqlnd_ms_fabric_warn_trx_boundaries_on.ini"))
		printf("[clean] Cannot unlink ini file 'test_mysqlnd_ms_fabric_warn_trx_boundaries_on.ini'.\n");
?>
--EXPECTF--
Warning: mysqlnd_ms_fabric_select_global(): (mysqlnd_ms) Fabric server exchange in the middle of a transaction in %s on line %d
%A
done!
