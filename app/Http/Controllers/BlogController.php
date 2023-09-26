<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlogPosts;
use App\Models\BlogCategories;
use App\Models\BlogTags;
use Illuminate\Support\Facades\Storage;
use DB; use File; use Image; use Auth; use Validator; use Str;

class BlogController extends Controller
{

    // Set media path
    public $media_path = '/images/media/';

    public function __construct()
    {

        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        // If the visitor uses the search box.

        if (isset($_GET['search']))
        {

            $posts = BlogPosts::where(function ($query) {
                $query->where('title', 'LIKE', '%' . $_GET['search'] . '%')
                      ->orWhere('body', 'LIKE', '%' . $_GET['search'] . '%');            

        })
            ->paginate(6);

       }  elseif (isset($_GET['category'])) {

            $posts = BlogPosts::GetCategories($_GET['category']);
       
       } elseif (isset($_GET['tag'])) {

        $posts = BlogPosts::GetTags($_GET['tag']);

       } else {

        $posts = BlogPosts::with('BlogCategories', 'BlogTags', 'Users')
                 ->where('published', true)
                 ->orderBy('created_at', 'desc')
                 ->paginate(6);

        }

        $categories = BlogCategories::all();

        $popular_tags = DB::table('post_tags')
        ->leftjoin('blog_tags', 'blog_tags.id', '=', 'post_tags.tag_id')
        ->select('post_tags.tag_id', 'name', DB::raw('count(*) as total'))
        ->groupBy('post_tags.tag_id', 'name')
        ->orderBy('total', 'desc')
        ->limit(15)
        ->get();

        $unpublished = \App\Models\BlogPosts::where('published', false)->get();

        return view('blog.index', compact('posts', 'categories', 'popular_tags', 'unpublished'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $this->authorize('Admin');

        $categories = BlogCategories::all();

        return view('blog.create', compact('categories'));

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $this->authorize('Admin');

        $post = new BlogPosts;

        $post->title = $request->title;
        $post->excerpt = $request->excerpt;
        $post->image = $request->imageInput;
        $post->slug = Str::slug($post->title, '-');
        $post->body = $request->body;
        $post->user_id = Auth::user()->id;
        $post->categories_id = $request->category;

        // Check if the post is to be published

        if ($request->published === 'on') {
            
            $post->published = 1; } else {
                $post->published = 0;
        }

        // Check whether post is featured

        if ($request->featured === 'on') {
            
            $post->featured = 1; } else {
                $post->featured = 0;
        }

        // Save to database

        $post->save();

        BlogTags::StoreTags($request->tags, $post->slug);

        return redirect()->action([BlogController::class, 'index']);
    }
    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $post = BlogPosts::with('BlogCategories', 'Users', 'BlogTags')->where('slug', $slug)->first();

        return view('blog.show', compact('post'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('Admin');

        $post = BlogPosts::find($id);
        $categories = BlogCategories::all();
        $split_tags = BlogTags::TagsForEdit($id);

        return view('blog.edit', compact('post', 'categories', 'split_tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $this->authorize('Admin');

            $validator = Validator::make($request->all(), [
                'title' => 'required|max:100',
                'body' => 'required|min:1',
            ])->validate();

                $post = BlogPosts::find($id);

            if(isset($request->imageInput)) {

                $post->image = $request->imageInput;

            }
                
                $post->title = $request->title;
                $post->slug = Str::slug($post->title, '-');
                $post->excerpt = $request->excerpt;
                $post->body = $request->body;
                $post->categories_id = $request->category;

                if($request->published === 'on')
                {
                   $post->published = 1;

                } else {

                    $post->published = 0;
                }

                if($request->featured === 'on')
                {
                    
                    $post->featured = 1;

                } else {

                    $post->featured = 0;
                }
      
                $post->save();
                BlogTags::storeTags($request->tags, $post->slug);
                return redirect()->action([BlogController::class, 'index']);
            
        }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
         {

            $this->authorize('Admin');

            $post = BlogPosts::find($id);
      
            BlogPosts::destroy($id);
            return back();
        }


}
