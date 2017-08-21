<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Redis cache test - cluster.
 *
 * If you wish to use these unit tests all you need to do is add the following definition to
 * your config.php file:
 *
 * define('TEST_CACHESTORE_REDIS_TESTSERVERSCLUSTER', 'localhost:7000,localhost:7001');
 *
 * @package   cachestore_redis
 * @author    Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright 2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../tests/fixtures/stores.php');
require_once(__DIR__ . '/../lib.php');

/**
 * Redis cache test - cluster.
 *
 * @package   cachestore_redis
 * @author    Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright 2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_redis_cluster_test extends advanced_testcase {
    /**
     * @return \cachestore_redis
     */
    public function create_store() {
        global $DB;
        /** @var cache_definition $definition */
        $definition = cache_definition::load_adhoc(cache_store::MODE_APPLICATION, 'cachestore_redis', 'phpunit_test');
        $servers = str_replace(',', "\n", TEST_CACHESTORE_REDIS_TESTSERVERSCLUSTER);
        $config = [
            'server'      => $servers,
            'prefix'      => $DB->get_prefix(),
            'clustermode' => true,
        ];
        $store = new cachestore_redis('TestCluster', $config);
        $store->initialise($definition);
        $store->purge();

        return $store;
    }

    public function setUp() {
        if (!cachestore_redis::are_requirements_met()) {
            self::markTestSkipped('Could not test cachestore_redis with cluster, missing requirements.');
        }
        if (!class_exists('RedisCluster')) {
            self::markTestSkipped('Could not test cachestore_redis with cluster, class RedisCluster not available.');
        }
        if (!defined('TEST_CACHESTORE_REDIS_TESTSERVERSCLUSTER')) {
            self::markTestSkipped(
                'Could not test cachestore_redis with cluster, missing configuration. '.
                'Check tests/travis directory for an example on how to setup redis. '.
                'Example to add in configuration: '.
                "define('TEST_CACHESTORE_REDIS_TESTSERVERSCLUSTER', 'localhost:7000,localhost:7001,localhost:7002');"
            );
        }
    }

    public function test_it_can_create() {
        $store = $this->create_store();
        self::assertNotNull($store);
        self::assertTrue($store->is_ready());
    }

    public function test_it_trims_server_names() {
        global $DB;

        // Add a time before and spaces after the first server. Also adds a blank line before second server.
        $servers = explode(',', TEST_CACHESTORE_REDIS_TESTSERVERSCLUSTER);
        $servers[0] = "\t" . $servers[0] . "  \n";
        $servers = implode("\n", $servers);

        $config = [
            'server'      => $servers,
            'prefix'      => $DB->get_prefix(),
            'clustermode' => true,
        ];

        $store = new cachestore_redis('TestCluster', $config);

        self::assertTrue($store->is_ready());
    }

    public function test_it_can_setget() {
        $store = $this->create_store();
        $store->set('the key', 'the value');
        $actual = $store->get('the key');

        self::assertSame('the value', $actual);
    }

    public function test_it_can_setget_many() {
        $store = $this->create_store();

        // Create values.
        $values = [];
        $keys = [];
        $expected = [];
        for ($i = 0; $i < 10; $i++) {
            $key = "getkey_{$i}";
            $value = "getvalue #{$i}";
            $keys[] = $key;
            $values[] = [
                'key'   => $key,
                'value' => $value,
            ];
            $expected[$key] = $value;
        }

        $store->set_many($values);
        $actual = $store->get_many($keys);
        self::assertSame($expected, $actual);
    }

    public function test_it_is_marked_not_ready_if_failed_to_connect() {
        global $DB;

        $config = [
            'server'      => "abc:123",
            'prefix'      => $DB->get_prefix(),
            'clustermode' => true,
        ];
        $store = new cachestore_redis('TestCluster', $config);

        // Failed to connect should show a debugging message.
        self::assertCount(1, phpunit_util::get_debugging_messages() );
        phpunit_util::reset_debugging();

        self::assertFalse($store->is_ready());
    }

    public function test_it_does_not_purge_caches_if_not_ready() {
        global $DB;

        $config = [
            'server'      => "abc:123",
            'prefix'      => $DB->get_prefix(),
            'clustermode' => true,
        ];
        $store = new cachestore_redis('TestCluster', $config);

        // Failed to connect should show a debugging message.
        self::assertCount(1, phpunit_util::get_debugging_messages() );
        phpunit_util::reset_debugging();

        self::assertFalse($store->purge());
    }

    public function test_it_deletes_instance_even_if_not_connected() {
        global $DB;

        $config = [
            'server'      => "abc:123",
            'prefix'      => $DB->get_prefix(),
            'clustermode' => true,
        ];
        $store = new cachestore_redis('TestCluster', $config);

        // Failed to connect should show a debugging message.
        self::assertCount(1, phpunit_util::get_debugging_messages() );
        phpunit_util::reset_debugging();

        // This command should abort the execution.
        $store->instance_deleted();

        // It should show a warning because the instance was not ready.
        self::assertCount(1, phpunit_util::get_debugging_messages() );
        phpunit_util::reset_debugging();
    }
}
