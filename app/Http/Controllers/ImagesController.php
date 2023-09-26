<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Images;
use DB;
use File;
use Image;
use Auth;

class ImagesController extends Controller
{
    // Set media path
    public $media_path = '/images/media/';

    public function __construct()
    {
       $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       $this->authorize('Admin');

        $all_media = Images::orderBy('created_at', 'desc')->get();

        return view('images.index', compact('all_media'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       
       $this->authorize('Admin');

        $new_media = new Images;

        // Establish whether a new post media has been uploaded then manage it.

            if ($request->hasFile('add-media'))
            {                
                // Get the new media and assign it to $file.
                $media = $request->file('add-media');

                // Assign unique file name to the new media.
                $media_name = time() . '-' . $media->getClientOriginalName();

                // Move the file to the blog image directory.
                $media->move(public_path() . $this->media_path, $media_name);

                //Make the image, note I use sprintf, different strokes...
                $make = Image::make(sprintf(public_path() . $this->media_path . '%s', $media_name))
                          ->save(public_path() . $this->media_path . $media_name);

                // Assign the new image name for the database save.
                $new_media->name = $media_name;

                // Create a smaller image constrained by 300px height at 50% quality

                $small = Image::make(public_path() . $this->media_path . $media_name);
                $small->fit(350, 175);
                $small->save(public_path() . $this->media_path . 'small' . '-' . $media_name, 50);

                //$new_media->small = 'small' . '-' . $media_name;

                // Create a medium image constrained by a specific size height at 75% quality

                $medium = Image::make(public_path() . $this->media_path . $media_name);
                $medium->fit(1000, 300);

                $medium->save(public_path() . $this->media_path . 'medium' . '-' . $media_name, 50);

                //$new_media->medium = 'medium' . '-' . $media_name;

                // Create a medium image constrained by a specific size height at 75% quality

                $large = Image::make(public_path() . $this->media_path . $media_name);
                $large->fit(1200, 300);

                $large->save(public_path() . $this->media_path . 'large' . '-' . $media_name, 50);

                //$new_media->large = 'large' . '-' . $media_name;


                $new_media->save();
            }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
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