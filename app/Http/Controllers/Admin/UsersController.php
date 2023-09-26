<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserRoles;
use Validator;

class UsersController extends Controller
{
    public function index()
    {

        if (!empty($_GET['field'])) {

            $users = User::orderBy($_GET['field'], $_GET['order'])->Paginate(15);
        
        } else {
       
            $users = User::orderBy('id', 'desc')->Paginate(15);

        }       
        

        return view('admin.users.index', compact('users'));
    }
}
