<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\BlogPosts;
use App\Models\BlogCategories;
use App\Models\BlogTags;
use App\Models\BlogPostTags;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(users_seeder::class);
        $this->call(users_roles_seeder::class);
        BlogCategories::factory(6)->create();
        BlogPosts::factory(50)->create();
        BlogTags::factory(100)->create();
        BlogPostTags::factory(200)->create();

    }
}
