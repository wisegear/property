<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\USerRoles;
Use Validator;
Use Auth;
Use Image;
Use File;

class UserProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($name_slug)
    {
        //Display a users profile

        $user = User::where('name_slug', $name_slug)->first();

        return view ('profile.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($name)
    {
        if (Auth::user()->name === $name Or Auth::user()->has_user_role('Admin'))
        {
            $user = User::where('name', $name)->first();
            $roles = UserRoles::all();

            return view('profile.edit', compact('user', 'roles'));
        
        } else {

            return back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|max:255',
            'image' => 'image|mimes:jpeg,jpg,gif,png|max:500',
            'bio' => 'max:500',

        ])->validate();

        $user = User::find($id);

        // Check if there is a new avatar

        if ($request->hasFile('image'))
        {

            //Find the old file and delete it unless it is the defailt image

            if ($user->avatar != 'default.png')
            {
                File::delete(public_path() . '/images/avatars/' . $user->avatar);
            }

            //Get the new image and assign it to a variable called $pic

            $pic = $request->file('image');

            //Assign a unique name to the new avatar

            $pic_name = time() . '-' . $pic->getClientOriginalName();

            //Move the file to the avatars directory and rename it.

            $pic->move(public_path() . '/images/avatars/', $pic_name);

            //Crop or upsize the image to fit the 100x100 requirement

            $resize = Image::make(sprintf(public_path() . '/images/avatars/' . '%s', $pic_name))
                ->resize(100,100, function($constraint) {
                    // $constraint->aspectRatio();
                    // $constraint->upsize();
                })
            ->save(public_path() . '/images/avatars/' . $pic_name);

            //Update the avatar name in the user model

            $user->avatar = $pic_name;

        }        

            //$user->name = $request->name;
            $user->email = $request->email;
            $user->website = $request->website;
            $user->location = $request->location;
            $user->bio = $request->bio;
            $user->linkedin = $request->linkedin;
            $user->facebook = $request->facebook;
            $user->x = $request->x;

            //Check if the email is to be displayed.
            if (isset($request->email_visible)) {

                $user->email_visible = 1;

            } else {

                $user->email_visible = 0;

            }

            //Check if the user is trusted.
            if (isset($request->trusted)) {

                $user->trusted = 1;

            } else {

                $user->trusted = 0;

            }


            $user->notes = $request->notes;

            //Sync Roles only if user is Admin

            if (Auth::user()->has_user_role('Admin')) {

                $user->user_roles()->sync($request->roles);

            }

        $user->save();

        return back()->with('status', 'User Profile has been updated.');



    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
