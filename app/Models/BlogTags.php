<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogTags extends Model
{
    use HasFactory;
    protected $table = 'blog_tags';

    public static function storeTags($post_tags, $slug)
    {

        // Explode tags, removing the hyphen.  
        $exploded_tags = explode('-', $post_tags);
        
        // Find post and remove existing tags, if it's an edit we won't know which are right.
        $blog_post = BlogPosts::where('slug', $slug)->first();
        $blog_post->BlogTags()->detach();
        
        // Take each tag in turn and check if it exists, if not create a new tag and assign to post.
        foreach ($exploded_tags as $tag)
        {
            $found_tag = BlogTags::where('name', $tag)->first();
            
            if ( ! empty($tag) && ! isset($found_tag->name))
            {
                $new_tag = new BlogTags;
                  $new_tag->name = $tag;
                  $new_tag->save();
                
                  // Attach the new tag to the post
                  $blog_post->BlogTags()->attach($new_tag->id);
            
             } else {
            
                    // if the tag already existed and not attached to the post, attach it.
                    if (isset($found_tag))
                        {
                            $blog_post->BlogTags()->attach($found_tag->id);
                        }
                    }
          }       
      }
      
      public static function TagsForEdit($id)
      {
         
        $post = BlogPosts::all()->find($id);
        // Prepare a new empty array.
        $items = array();
        // Loop through each tag and store in the array.
        foreach ($post->BlogTags as $tag)
        {
          $items[] = $tag->name;
        }
        // Now convert that array into a string, seperating each tag with a comma to allow the correct 
        // format to be displayed on the edit form.
        $tag_string = implode('-', $items);
        // Pass the new string of tags back to the controller requesting it.
        return $tag_string;
      }
}