<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comments;
use App\Models\BlogPosts;
use Auth;

class CommentsController extends Controller
{
    // Using the same comments table throughout the site, polymorphic relationship, using the model.

    public function update(Request $request, $id)
    {
        if (isset($request->comment) && $this->authorize('Member'))

        {
            $post = BlogPosts::find($id);
            $comment = new Comments;
            $comment->body = $request->comment;
            $comment->user_id = Auth::user()->id;
            $post->comments()->save($comment);

            return redirect()->back();

        } else {

            $validator = Validator::make($request->all(), [
            'comment' => 'required',
            ])->validate();
        }

        return back();
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {

        $this->authorize('Admin');
        
        Comments::destroy($id);
        return back();
    }
}
