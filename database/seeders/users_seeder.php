<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class users_seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // For testing

        DB::table('users')->insert([
            'name' => 'Lee Wisener',
            'name_slug' => 'lee-wisener',
            'email' => 'lee@wisener.net',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            ]);

        DB::table('users')->insert([
            'name' => 'Banned Member',
            'name_slug' => 'Banned-Member',
            'email' => 'banned@wisener.net',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            ]);

        DB::table('users')->insert([
            'name' => 'Pending Member',
            'name_slug' => 'Pending-Member',
            'email' => 'pending@wisener.net',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            ]); 

        DB::table('users')->insert([
            'name' => 'Member',
            'name_slug' => 'Member',
            'email' => 'member@wisener.net',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            ]); 
 
  
  }
}
