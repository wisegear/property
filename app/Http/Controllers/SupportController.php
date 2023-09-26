<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Support;
use App\Models\Comments;
use Auth;
Use Validator;

class SupportController extends Controller
{

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
      
        $tickets = Support::with('comments')
            ->where('user_id', Auth::user()->id)
            ->orderBy('updated_at', 'desc')
            ->paginate(10);
        return view('support.index', compact('tickets'));
        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function create()
    {

        return view('support.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:100',
            'text' => 'required',
        ])->validate();
        $new_ticket = new Support;
        $new_ticket->title = $request->title;
        $new_ticket->text = $request->text;
        $new_ticket->user_id = Auth::user()->id;
        $new_ticket->save();
        return redirect()->action([SupportController::class, 'index']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function show($id)
    {

        //Eager load Support ticket with comments and sort comments by desc order.

        $ticket = Support::with(['comments' => function ($q) {
          $q->orderBy('created_at', 'desc');
        }])->find($id);  

        //Only show tickets to the correct user or an Admin.      

        if ( $ticket->user_id === Auth::user()->id  || Auth::user('IsAdmin') )
        {

            return view('support.show', compact('ticket'));
        
        } else {

            return back();
        }
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
       // In Progress ticket

        if (! empty($request->inProgress))
        {
            $support = Support::find($id);
            $support->status = "In Progress";
            $support->save();
            return redirect()->back();
        }

       // Awaiting Reply

        if (! empty($request->AwaitingReply))
        {
            $support = Support::find($id);
            $support->status = "Awaiting Reply";
            $support->save();
            return redirect()->back();
        }

       // Open ticket

        if (! empty($request->openTicket))
        {
            $support = Support::find($id);
            $support->status = "Open";
            $support->save();
            return redirect()->back();
        }

        // Close ticket

        if (! empty($request->closeTicket))
        {
            $support = Support::find($id);
            $support->status = "Closed";
            $support->save();
            return redirect()->action([SupportController::class, 'index']);
        }

        if (! empty($request->comment))
        {
            $support = Support::find($id);
            $support->status = "Open";
            $support->save();
            $comment = new Comments;
            $comment->body = $request->comment;
            $comment->user_id = Auth::user()->id;
            $support->comments()->save($comment);
            return redirect()->back();
        } elseif (empty($request->comment)) {
            $validator = Validator::make($request->all(), [
            'comment' => 'required',
        ])->validate();
        }
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
