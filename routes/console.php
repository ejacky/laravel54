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

Artisan::command('fuck', function () {
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
