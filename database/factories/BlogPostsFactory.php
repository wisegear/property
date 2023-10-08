<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlogPosts;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogPosts>
 */
class BlogPostsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BlogPosts::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $title = $this->faker->words(5, true);
        $slug = Str::slug($title, '-');

        return [ 
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->faker->paragraph(5, false),
            'body' => $this->faker->paragraph(20, false),
            'categories_id' => $this->faker->numberBetween(1, 5),
            'user_id' => $this->faker->numberBetween(1, 4),
            'created_at' => $this->faker->dateTimeThisYear(),
            'date' => $this->faker->dateTimeThisYear(),
        ];

    }
}
