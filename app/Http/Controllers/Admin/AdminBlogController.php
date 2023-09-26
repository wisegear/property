<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlogPosts;

class AdminBlogController extends Controller
{
    public function index()
    {

        $posts = BlogPosts::with('comments')->orderBy('created_at', 'desc')->paginate(10);

        return view('admin.blog.index')->with('posts', $posts);

    }
}
