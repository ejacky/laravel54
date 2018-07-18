<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// 同步 client_gid 的 client_gid 与 client 的 gid 字段
Artisan::command('sync-gid', function() {
    $items = DB::table('client_gid')
        ->select('client_id', DB::raw('max(client_gid) as max_gid'))
        ->groupBy('client_id')->get();
    foreach ($items as $item) {
        DB::table('client')
            ->where('id', $item->client_id)
            ->update(array('gid' => $item->max_gid));
        echo "update client:" . $item->client_id;
        echo PHP_EOL;
    }
    echo "success";
});

// 创建 number 个分组
Artisan::command('create-group {numbers}', function ($numbers) {
    Faker\Factory::create();
    $db_group = DB::table('client_group');
    $count = 0;
    while ($count < intval($numbers)) {
        $db_group->insert(array(
            'pid' => 0,
            'name' => 'group_' . $count,
            'status' => 1,
            'is_vm_grp' => 0
        ));
        $count++;
    }

});

// 给空用户组分配 $number 个用户
Artisan::command('add_clients_to_group {number}', function ($number) {
    $items = DB::table('client_group')->select('id')->get();
    foreach ($items as $group) {
        $count = DB::table('client_gid')->where('client_gid', $group->id)->count();
        if ($count == 0) {
            $itemIds = DB::table('client')->select('id')->limit(10)->inRandomOrder()->get();
            $insertRows = $itemIds->map(function ($item) use($group){
                return [
                    'client_id' => $item->id,
                    'client_gid' => $group->id
                ];
            });

            DB::table('client_gid')->insert($insertRows->toArray());

            echo "insert 10";
            echo PHP_EOL;
        }
    }
});

// 给终端或组下发策略
Artisan::command('policy-dispatch {type} {times}', function ($type, $times) {
    $times = $times ?:5;

    if ($type == 'client') {
        $items = DB::table('client')
            ->select('mid')
            ->limit(intval($times))
            ->inRandomOrder()
            ->get();

        foreach ($items as $item) {
            Redis::sAdd('policy_dispatch_clients', $item->mid);
        }

        dump($items);
    } else if ($type == 'group') {
        $items = DB::table('client_group')
            ->select('id')
            ->limit(intval($times))
            ->inRandomOrder()
            ->get();

        foreach ($items as $item) {
            Redis::sAdd('policy_dispatch_groups', $item->id);
        }
        dump($items);

    }
});

//批量更新组 ID
Artisan::command('set_client_gid {gid}', function ($gid) {
    DB::table('client_gid')->orderBy('id')->chunk(100, function ($items) use ($gid) {
        foreach ($items as $value) {
            $count = DB::table('client_gid')->where('client_gid', $gid)->where('client_id', $value->client_id)->count();
            if ($count == 0) {
                echo PHP_EOL;
                dump($value);
                echo PHP_EOL;
                DB::table('client_gid')->where('id', $value->id)->update(array('client_gid' => $gid));
            }
        }
    });
});

Artisan::command('test-ldap', function () {
    // using ldap bind
    $ldaprdn  = 'cn=admin,dc=example,dc=org';     // ldap rdn or dn
    $ldappass = 'admin';  // associated password

// connect to ldap server
    $ldapconn = ldap_connect("172.17.0.2")
    or die("Could not connect to LDAP server.");

    if ($ldapconn) {
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        // binding to ldap server
        $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);

        // verify binding
        if ($ldapbind) {
            echo "LDAP bind successful...";
        } else {
            echo "LDAP bind failed...";
        }

    }
});

Artisan::command('autodiscovery_upload_info {type}', function ($type) {
    $faker = Faker\Factory::create();
    $pheanstalk = new \Pheanstalk\Pheanstalk('127.0.0.1');


    if ($type == 'host') {
        $file = 'nac_hostinfo.json';
        $queue_name = 'work_queue_nac_hostinfo';

        $content = file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . $file);
        $cont_arr = json_decode($content, true);
    } else if ($type == 'device') {
        $file = 'nac_deviceinfo.json';
        $queue_name = 'work_queue_nac_device_info';
        $content = file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . $file);
        $cont_arr = json_decode($content, true);

        $mids = DB::table('client')->select(['mid'])->limit(20)->get()->toArray();
        $cnt = DB::table('client')->count();

        //dump(array_rand($mids, 1));exit;
        $i = 0;
        while ($i < 500000) {
            $rand_index = array_rand($mids, 1);
            if ($cnt < 20) {
                $mid = $faker->uuid;
                DB::table('client')->updateOrInsert(['mid' => $mid, 'ip' => $faker->ip, 'mac' => $faker->macAddress, 'report_ip' => $faker->ipv4]);
            } else {
                $mid = $mids[$rand_index]->mid;
            }
            $cont_arr['mid'] = $mid;
            $cont_arr['ip'] = $faker->ipv4;
            $cont_arr['mac'] = $faker->macAddress;
            echo $cont_arr['ip'];
            echo PHP_EOL;
            $pheanstalk->useTube($queue_name)->put(json_encode($cont_arr));
            $i++;
            if ((int)$i % 10000 === 0) {
                echo "sleep 1 second" . PHP_EOL;
                sleep(1);
            }
        }
    } else {
        echo "参数错误";
        return ;
    }


    dump('finished');
});

// 模拟安检日志
Artisan::command('mock_seccheck_log', function () {
    $file = 'sec_check_log.json';
    $queue_name = 'work_queue_ngx2php';
    $content = file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . $file);
    $cont_arr = json_decode($content, true);
    $client = DB::table('client')->select(['mid', 'ip'])->limit(2000)->get()->toArray();

    $i = 0;
    while ($i < 20000) {
        $rand_index = array_rand($client, 1);
        $cont_arr['data'][0]['mid'] = $client[$rand_index]->mid;
        $cont_arr['data'][0]['addr'] = $client[$rand_index]->ip;
        //$cont_arr['data'][0]['data'] = '';
        $pheanstalk = new \Pheanstalk\Pheanstalk('127.0.0.1');
        $pheanstalk->useTube($queue_name)->put(json_encode($cont_arr));
        echo "report log from:" . $client[$rand_index]->ip . PHP_EOL;
        $i++;
    }
    echo "finished";
});

Artisan::command('mock_upload_report', function () {
    $i = 0;
    while ($i < 2000) {
        $mids = ["738b3fee389e497d831318d346682744", "ccba0000000000000000000000000001"];
        $insert_arg = '{
  "event_time": "' . date('Y-m-d H:i:s') .'",
  "mid": "' . $mids[rand(0, 1)] .'",
  "gid": 1,
  "templet_id": 12,
  "check_num": 12,
  "fault_num": 4,
  "check_result": 0,
  "detail": "[{\"id\":1000,\"pass\":1,\"key_item\":0},{\"id\":1001,\"pass\":1,\"key_item\":0},{\"id\":1002,\"pass\":1,\"key_item\":0}]"
}';
        $i++;
        Redis::connection('skylarminide')->executeRaw(['insert', 'security_check', $insert_arg]);
        echo $i;
        echo PHP_EOL;
    }
});



Artisan::command('fuck', function () {

    $dataJson = Redis::connection('skylarminide')->executeRaw(['list', 'security_check', 1 ,15]);

    $datas = json_decode($dataJson, true);
    if (!empty($datas)) {
        foreach ($datas as $data) {
            $value = json_decode($data['value'], true);
            echo $value['mid'];
            echo "||";
            echo $value['event_time'];
            echo PHP_EOL;
        }
    } else {
        echo "parse minide json error";
    }



    exit;

    $file = 'upload_client_log.json';
    $content = file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . $file);
    $client = new \GuzzleHttp\Client();
    $r = $client->request('POST', 'http://10.98.2.163/api/upload_client_log.json?mid=738b3fee389e497d831318d346682744', [
        'body' => $content
    ]);

    echo ($r->getBody());
    exit;

    $faker = Faker\Factory::create();
    DB::table('client')->updateOrInsert(['mid' => '738b3fee389e497d831318d346682744', 'ip' => $faker->ipv4, 'mac' => $faker->macAddress, 'report_ip' => $faker->ipv4]);
    exit;

    $white = ['name' => 'z'];
    $t = $white['os'];
    if (empty($white['os'])) {
        echo "no";
    } else {
        echo "yes";
    }
    exit;
    $faker = Faker\Factory::create();
    $i = 0;
    while ($i < 10000) {
        $state = strtoupper(substr($faker->city, 0, 2));
        DB::connection('pg_my')->table('capitals')->insert(['name' => $faker->city, 'state' => $state]);
        $i++;
        echo $state; echo PHP_EOL;
        echo $i; echo PHP_EOL;
    }
    exit;
    $db = DB::connection();

    //dump($db);
    //exit;
    $faker = Faker\Factory::create();
    for ($i = 0; $i < 20; $i++) {
        $insert = DB::table('client')->updateOrInsert(['mid' => $faker->uuid, 'ip' => $faker->ipv4, 'mac' => $faker->macAddress, 'report_ip' => $faker->ipv4]);
        dump($insert);
        echo "insert success, number: " . $i;
        echo PHP_EOL;
    }
    echo 'finised';





//    $a = false;
//    var_dump($a['detail']);
//    if (empty($a['detail'])) {
//        echo "ss";
//        $a['detail'] = [];
//    }
//    var_dump($a['detail']);
//    exit;
//    $faker = Faker\Factory::create();
//    dump($faker->ipv4);
//    exit;
//    dump(class_exists('Tideways\Profiler'));
    \Tideways\Profiler::start();
    \Tideways\Profiler::setTransactionName('fuck.php');
    $uuid = \Faker\Provider\Uuid::uuid();
    dump($uuid);
    \Tideways\Profiler::stop();
    exit;


    $models = DB::table('policy_history')
        ->where('mid', 'mid')
        ->update(['']);
    foreach ($models as $model) {
        if ($model->mid == 'mid_1') {
            $ret = $model->update(['mid' => 'e6cca89c21504603ac5f3b543533e00b']);
            if ($ret) {
                echo $model->id;
                echo PHP_EOL;
            }
        }
    }

    exit;
    $faker = Faker\Factory::create();
    $i = 0;
    while ($i < 100) {
        //DB::connection('pg_my')->table('test1')->insert(array('id' => $faker->buildingNumber, 'content' =>  $faker->text));

        $minor = $faker->numberBetween(50);
        DB::connection('pg_my')->table('test2')->insert(array('minor' => $faker->numberBetween(50), 'major' =>  $minor + $faker->randomDigit, 'name' => $faker->name));
        $i++;
    }
    exit;
    $faker = Faker\Factory::create();
    $pheanstalk = new \Pheanstalk\Pheanstalk('127.0.0.1');

    $i = 0;
    while ($i < 5) {
        $content = file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . 'nac_deviceinfo.json');
        $cont_arr = json_decode($content, true);
        $cont_arr['mac'] = $faker->macAddress;
        $pheanstalk->useTube('work_queue_nac_device_info')->put(json_encode($cont_arr));
        $i++;
    }

    dump(file_get_contents(storage_path() .  DIRECTORY_SEPARATOR  . 'app' . DIRECTORY_SEPARATOR . 'nac_deviceinfo.json'));
    exit;
    $users = Adldap::search()->users()->get();
    dump($users);
    $user = Adldap::make()->user([
        'cn' => 'John Doe',
        'dc' => 'example',
        'dc' => 'org'
    ]);

    $user->save();
    dump($users);

    exit;

    dispatch((new \App\Jobs\MyTestJob())->onQueue('my-job-test'));
    exit;
    $task_4100_keys = Redis::keys("task_4100_*");
    foreach ($task_4100_keys as $key) {
        Redis::del($key);
        echo "delete key:" . $key . PHP_EOL;
    }

    echo "delete finished";

    exit;

    $items = DB::table('client')->select('id')->limit(10)->inRandomOrder()->get();
    $news = $items->map(function ($item) {
        return [
            'client_id' => $item->id,
            'client_gid' => 2
            ];
    });
    dump($news->toArray());

    exit;

    $a = array('a' => 1, 'b' => 2, 'c', 'd', 'e');
    $b = array_chunk($a, 2, true);
    dump($b);



    exit;
    $red = Redis::hget('client_policy', 'e6cca89c21504603ac5f3b543533e00b');
    dump($red);

    dump(date('Y-m-d H:i:s', '1512465685'));
    dump(strtotime('2017-12-05 17:21:25'));

    dump(date('Y-m-d H:i:s', '1512463573'));



    exit;

    exit;
    $ret = DB::table('weather')->get();
    dump($ret);
    echo "hello , fuck!";
});

Artisan::command('check-file', function () {
    $allFolder = Storage::allFiles("./");
    dump($allFolder);

});
