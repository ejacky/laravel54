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


function scan($pattern, $redis = null, callable $handle = null)
{
    $keys = array();

    foreach (new Predis\Collection\Iterator\Keyspace($redis, $pattern) as $key)
    {
        $keys[] = $key;
    }

    return null !== $handle ? $handle($keys) : $keys;
}

function scanAllForMatch ($pattern, $cursor=null, $allResults=array()) {

    // Zero means full iteration
    if ($cursor==="0") {
        return $allResults;
    }

    // No $cursor means init
    if ($cursor===null) {
        $cursor = "0";
    }

    // The call
    $result = Redis::scan($cursor, 'match', $pattern);

    // Append results to array
    $allResults = array_merge($allResults, $result[1]);

    // Recursive call until cursor is 0
    return scanAllForMatch($pattern, $result[0], $allResults);
}

Artisan::command('fuck', function () {

    $rr = scanAllForMatch('policy_global_*');

    dump($rr);exit;


    $ret = scan('policy_global_*', (new Redis));
    dump($ret);

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
