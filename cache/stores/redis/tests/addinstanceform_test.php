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
 * Redis cache test - add instance form.
 *
 * @package   cachestore_redis
 * @author    Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright 2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../addinstanceform.php');

/**
 * Redis cache test - add instance form.
 *
 * @package   cachestore_redis
 * @author    Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright 2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_redis_addinstanceform_test extends advanced_testcase {
    private function mock_redis_cluster_availability($value) {
        // Hack property to make redis cluster enabled/disabled or let it auto-detect (null).
        $object = new ReflectionClass(cachestore_redis::class);
        $property = $object->getProperty('clusteravailable');
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    public function check_successfull_submit(array $data = []) {
        list($form, $errors) = $this->mock_submit($data);

        self::assertEmpty($errors, 'Errors found: '.implode(',', array_keys($errors)));
        self::assertTrue($form->is_validated());
        self::assertNotNull($form->get_data());
    }

    public function check_failed_submit(array $data, $expectederror) {
        list($form, $errors) = $this->mock_submit($data);
        $expected = [$expectederror];

        self::assertSame($expected, array_keys($errors));
        self::assertFalse($form->is_validated());
        self::assertNull($form->get_data());
    }

    /**
     * @param array $data
     * @return array
     */
    public function mock_submit(array $data) {
        $defaults = [
            'name'   => 'redis_test',
            'server' => 'something.test:1234',
        ];
        $data = array_merge($defaults, $data);
        cachestore_redis_addinstance_form::mock_submit($data);

        $form = new cachestore_redis_addinstance_form();
        $errors = $form->configuration_validation($data, [], []);

        return [$form, $errors];
    }

    public function setUp() {
        parent::setUp();
        // Even if RedisCluster is not available, pretend it is.
        $this->mock_redis_cluster_availability(true);
    }

    public function tearDown() {
        // Set back availability to null (auto-detect).
        $this->mock_redis_cluster_availability(null);
        parent::tearDown();
    }

    public function test_it_works() {
        $this->check_successfull_submit();
    }

    public function test_it_requires_rediscluster_for_clustered_mode() {
        $this->mock_redis_cluster_availability(false);
        $this->check_failed_submit(['clustermode' => '1'], 'clustermode');
    }

    public function test_it_does_not_require_a_port_number_for_single_mode() {
        $this->check_successfull_submit(['server' => 'something.test']);
    }

    public function test_it_requires_a_port_number_for_cluster_servers() {
        $this->check_failed_submit([
                                       'clustermode' => '1',
                                       'server'      => 'somewhere1.test',
                                   ], 'server');
    }

    public function test_it_can_have_only_one_server_in_single_mode() {
        $this->check_failed_submit([
                                       'clustermode' => '0',
                                       'server'      => "redis1.test:123\nredis2.test:456",
                                   ], 'server');
    }
}
