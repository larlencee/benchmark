<?php


define('N', 300000);
define('C', 4);

if (empty($argv[1])) {
    echo "Usage: php {$argv[0]} [test_func]\n";
    exit(0);
} else {
    $test_func = trim($argv[1]);
    $test_func();
}

function create_big_table() {
    $table = new swoole_table(8 * 1024 * 1024);
    $table->column('id', swoole_table::TYPE_INT, 4);
    $table->column('name', swoole_table::TYPE_STRING, 256);
    $table->column('num', swoole_table::TYPE_FLOAT);
    $table->create();
}

function test1()
{
    $table = create_big_table();

    /**
     * table_size = 1M
     */
    $s = microtime(true);
    $n = N;
    while ($n--) {
        $table->set(
            'key_' . $n,
            array('id' => $n, 'name' => "swoole, value=$n\r\n", 'num' => 3.1415 * rand(10000, 99999))
        );
    }
    echo "set " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
}

function test2()
{
    $table = create_big_table();
    $n = N;
    $s = microtime(true);
    while ($n--) {
        $key = rand(0, N);
        $data = $table->get('key_' . $key);
    }
    echo "get " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
}

function test3()
{
    $table = create_big_table();
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i, $table) {
                $n = N;
                $s = microtime(true);
                while ($n--) {
                    $key = rand(0, N);
                    $data = $table->get('key_' . $key);
                }
                echo "[Worker#$i]get " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
            }
        ))->start();
    }
    for ($i = C; $i--;) {
        swoole_process::wait();
    }
}

function test4()
{
    $table = create_big_table();
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i, $table) {
                $n = N;
                $s = microtime(true);
                while ($n--) {
                    $key = rand(0, N);
                    $table->set(
                        'key_' . $key,
                        array('id' => $key, 'name' => "php, value=$n\r\n", 'num' => 3.1415 * rand(10000, 99999))
                    );
                }
                echo "[Worker#$i]set " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
            }
        ))->start();
    }
    for ($i = C; $i--;) {
        swoole_process::wait();
    }
}

function table_random_read()
{
    $table = create_big_table();

    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = 'key_' . $n;
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => "swoole, value=$n\r\n", 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
        }
    }
    $s2 = microtime(true);

    echo "Table::set() time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = rand(0, $n);
        $str = $table->get('key_' . $i);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['id'] != $i) {
            var_dump($i, $str);
        }
        assert($str['id'] == $i);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_key], time=" . ($s3 - $s2) . "s\n";

    $n = N;
    $i = rand(0, N);
    while ($n--) {
        $str = $table->get('key_' . $i);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['id'] != $i) {
            var_dump($i, $str);
        }
        assert($str['id'] == $i);
    }

    $s4 = microtime(true);
    echo "Table::get() [fixed_key], time=" . ($s4 - $s3) . "s\n";
}

/**
 * @throws Exception
 */
function table_random_key()
{
    $table = create_big_table();

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = random_bytes(rand(1, 63));
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => $k, 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
        }
        $keys[] = $k;
    }
    $s2 = microtime(true);
    echo "Table::set() time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = array_rand($keys);
        $k = $keys[$i];
        $str = $table->get($k);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($i, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_key], time=" . ($s3 - $s2) . "s\n";
}

/**
 * @throws Exception
 */
function array_random_key()
{
    $keys = [];
    $array = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = random_bytes(rand(1, 63));
        $array[$k] = array('id' => $n, 'name' => $k, 'num' => 3.1415);
        $keys[] = $k;
    }
    $s2 = microtime(true);
    echo "Array::set() [random_key], time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = array_rand($keys);
        $k = $keys[$i];
        $str =  $array[$k];
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($i, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Array::get() [random_key], time=" . ($s3 - $s2) . "s\n";
}

/**
 * @throws Exception
 */
function table_random_int_key()
{
    $table = create_big_table();

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = rand(1, 1000000000);
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => $k, 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
        }
        $keys[] = $k;
    }
    $s2 = microtime(true);
    echo "Table::set() time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = array_rand($keys);
        $k = $keys[$i];
        $str = $table->get($k);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($i, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_key], time=" . ($s3 - $s2) . "s\n";
}

function shuffle_assoc(&$array) {
    $keys = array_keys($array);

    shuffle($keys);

    foreach($keys as $key) {
        $new[$key] = $array[$key];
    }

    $array = $new;

    return true;
}

/**
 * @throws Exception
 */
function table_random_int_key_delete()
{
    $table = create_big_table();

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    /**
     * 插入数据
     */
    echo "SET ".N." keys\n";
    while ($n--) {
        $k = rand(1, 1000000000);
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => $k, 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
            continue;
        }
        $keys[$k] = true;
    }
    $s2 = microtime(true);
    echo "Table::set() [random_int_key], time=" . ($s2 - $s1) . "s\n";

    var_dump(count($keys), $table->count());

    /**
     * 获取数据
     */
    echo "GET ".N." keys\n";
    shuffle_assoc($keys);
    foreach ($keys as $k => $v) {
        $str = $table->get($k);
        if ($str == false) {
            echo "key[$k] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($k, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_int_key], time=" . ($s3 - $s2) . "s\n";

    /**
     * 删除数据
     */
    $n = N / 10;
    echo "DEL ".$n." keys\n";
    $del_keys = [];
    while ($n--) {
        $k = array_rand($keys);
        if ($table->del($k) == false) {
            echo "[DEL] key[$k] not exists\n";
            var_dump(array_key_exists($k, $keys), $table->exists($k));
        } else {
            unset($keys[$k]);
            $del_keys[] = $k;
        }
    }

    echo 'DEL='.count($del_keys).', KEYS='.count($keys).', COUNT='.$table->count()."\n";

    $s4 = microtime(true);
    echo "Table::del() [random_int_key], time=" . ($s4 - $s3) . "s\n";
}

function table_delete_and_incr()
{
    $table = new \Swoole\Table(256 * 1024);
    $table->column('request_count', swoole_table::TYPE_INT);
    $table->column('howlong', swoole_table::TYPE_FLOAT);
    $table->create();

    var_dump($table->getMemorySize());
    $KEY_COUNT = 100000;

    // Init Table
    for ($i = 0; $i < $KEY_COUNT; $i++) {
        $key = 'key_' . $i;
        $table->set($key, ['request_count' => rand(1000, 9999), 'howlong' => rand(1000, 9999)]);
    }

    var_dump($table->count());

    $del_keys = [];
    $n = 1000_0000;
    while ($n--) {
        $key = 'key_' . rand(0, $KEY_COUNT);
        // Del Key
        if (rand(0, 99999) % 10 == 1) {
            $table->del($key);
            $del_keys[$key] = 1;
        } else {
            // create or delete
            if (rand(0, 99999) % 5 == 1 and count($del_keys) > 0) {
                $key = array_rand($del_keys);
                unset($del_keys[$key]);
            }
            $table->incr($key, 'request_count');
        }
    }
}