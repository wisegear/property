<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Support;
use App\Models\BlogPosts;
use App\Models\UserRolesPivot;

class AdminController extends Controller
{
    public function index() {

        // User info
        $users = User::all();
        $logged = User::orderBy('last_login_at', 'desc')->take(5);

        // Ridiculous workaround, count users, roles then add 1 as admin has two roles!!
        $users_count = User::all()->count();
        $roles_count = UserRolesPivot::all()->Count();
        $users_pending = UserRolesPivot::all()->where('role_id', '===', 2)->count();
        $users_banned = UserRolesPivot::all()->where('role_id', '===', 1)->count();
        $users_active = UserRolesPivot::all()->where('role_id', '===', 3)->count();

        //Support info
        $tickets = Support::all();
        $awaiting_reply = Support::all()->where('status', '===', 'Awaiting Reply')->count();
        $open_tickets = Support::all()->where('status', '===', 'Open')->count();
        $in_progress_tickets = Support::all()->where('status', '===', 'In Progress')->count();

        //Blog info
        $blogposts = BlogPosts::all();
        $blogunpublished = BlogPosts::where('published', false)->get();

        $data = array(

            'users' => $users,
            'users_pending' => $users_pending,
            'users_active' => $users_active,
            'users_banned' => $users_banned,
            'tickets' => $tickets,
            'awaiting_reply' => $awaiting_reply,
            'open_tickets' => $open_tickets,
            'in_progress_tickets' => $in_progress_tickets,
            'blogposts' => $blogposts,
            'blogunpublished' => $blogunpublished,
        );

        return view ('admin.index')->with($data);
    }
}
