<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlogPosts;

class PagesController extends Controller
{
    
    public function home() {

        $posts = BlogPosts::where('published', true)->orderBy('date', 'desc')->limit(4)->get();
        $recent_posts = BlogPosts::where('published', true)->orderBy('date', 'desc')->limit(6)->get();

        return view ('home', compact('posts', 'recent_posts'));
    }

    public function about() {

        return view('about');
    }

    public function contact() {

        return view('contact');
    }

    public function important() {

        return view('important');
    }

}
