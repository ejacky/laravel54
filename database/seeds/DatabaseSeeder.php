<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $passwd = Hash::make('admin123');
        $user = \App\User::create(
            [
                'password' => $passwd,
                'name' => 'admin',
                'email' => 'admin@admin.com'
            ]);
        var_dump($user);
         //$this->call(UsersTableSeeder::class);
    }
}
