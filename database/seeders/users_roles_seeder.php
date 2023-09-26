<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class users_roles_seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('user_roles')->insert(
            [
                'name' => 'Banned',
            ]);

        DB::table('user_roles')->insert(
            [
                'name' => 'Contributor',
            ]);

        DB::table('user_roles')->insert(
            [
                'name' => 'Member',
            ]);
    
        DB::table('user_roles')->insert(
            [
                'name' => 'Admin',
            ]);

        // Entries for pivot table 
        
        DB::table('user_roles_pivot')->insert(
            [
                'role_id' => '3',
                'user_id' => '1',
            ]);

        DB::table('user_roles_pivot')->insert(
            [
                'role_id' => '4',
                'user_id' => '1',
            ]);

        DB::table('user_roles_pivot')->insert(
            [
                'role_id' => '1',
                'user_id' => '2',
            ]);

        DB::table('user_roles_pivot')->insert(
            [
                'role_id' => '2',
                'user_id' => '3',
            ]);

        DB::table('user_roles_pivot')->insert(
            [
                'role_id' => '3',
                'user_id' => '4',
            ]);
    }
}
