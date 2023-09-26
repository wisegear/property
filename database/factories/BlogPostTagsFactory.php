<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlogPostTags;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogPostTags>
 */
class BlogPostTagsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BlogPostTags::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [ 
            'tag_id' => $this->faker->numberBetween(1, 100),
            'post_id' => $this->faker->numberBetween(1, 50),
        ];
    }
}
