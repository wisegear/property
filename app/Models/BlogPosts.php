<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPosts extends Model
{
    use HasFactory;

    protected $table = 'blog_posts';

    protected $fillable = [
        'subscriber_only',
    ];

    protected $casts = [
        'date' => 'date', // or 'datetime' depending on your needs
        'subscriber_only' => 'boolean',
    ];

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function Users()
    {

        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function blogCategories()
    {
        return $this->hasOne(BlogCategories::class, 'id', 'categories_id');
    }

    public function blogTags()
    {
        return $this->belongsToMany(BlogTags::class, 'blog_post_tags', 'post_id', 'tag_id');
    }

    public static function getCategories($category)
    {

        return BlogPosts::whereHas('BlogCategories', function ($query) use ($category) {
            $query->where('name', $category);
        })
            ->where('published', true)
            ->with('blogCategories', 'Users', 'BlogTags')
            ->orderBy('created_at', 'desc')
            ->paginate(6);
    }

    public static function GetTags($tag)
    {

        return BlogPosts::whereHas('BlogTags', function ($query) use ($tag) {
            $query->where('name', $tag);
        })

            ->where('published', true)
            ->with('BlogCategories', 'Users', 'BlogTags')
            ->orderBy('created_at', 'desc');
    }

    // Used to create table of contents for the blog posts.

    public function getBodyHeadings($tag = 'h2')
    {
        $dom = new \DOMDocument;

        // Suppress warnings and add proper HTML structure
        @$dom->loadHTML('<html><body>'.$this->body.'</body></html>');

        $headings = [];

        foreach ($dom->getElementsByTagName($tag) as $heading) {
            $headings[] = $heading->nodeValue;
        }

        return $headings;
    }

    // add anchors to the post h2 headings so that you can scroll to that point.

    public function addAnchorLinksToHeadings()
    {
        $content = $this->body;

        // Use regular expressions to find and replace H2 headings with anchor links
        $contentWithAnchors = preg_replace_callback(
            '/<h2[^>]*>(.*?)<\/h2>/i',
            function ($matches) {
                $headingText = strip_tags($matches[1]); // Get the heading text
                $slug = Str::slug($headingText); // Generate a slug from heading text

                // Create an anchor link
                return "<h2 id=\"$slug\">$headingText <a href=\"#$slug\" class=\"anchor-link\"></a></h2>";
            },
            $content
        );

        return $contentWithAnchors;
    }

    public function featuredImageUrl(string $size): ?string
    {
        if (! $this->original_image) {
            return null;
        }

        $path = "assets/images/uploads/{$size}_{$this->original_image}";

        return Storage::disk('public')->url($path);
    }

    public static function galleryImageUrl(string $filename): string
    {
        $path = "assets/images/uploads/galleries/{$filename}";

        return Storage::disk('public')->url($path);
    }

    public static function contentImageUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        if (Str::startsWith($normalizedPath, 'storage/')) {
            $normalizedPath = Str::after($normalizedPath, 'storage/');
        }

        if (Str::startsWith($normalizedPath, 'assets/')) {
            return Storage::disk('public')->url($normalizedPath);
        }

        $path = 'assets/images/uploads/'.$normalizedPath;

        return Storage::disk('public')->url($path);
    }
}
