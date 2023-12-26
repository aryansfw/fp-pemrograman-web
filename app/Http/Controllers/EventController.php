<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\EventServices\CreateEventService;
use App\Http\Services\UserGroupServices\GetGroupPermisionService;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Services\EventServices\DeleteEventService;
use App\Http\Services\EventServices\GetSomeEventService;
use App\Http\Services\EventServices\FindEventService;
use App\Http\Services\EventServices\GetEventByUserIDService;
use App\Http\Services\EventServices\JoinEventService;
use App\Http\Services\CommentaryServices\CreateCommentService;
use App\Http\Services\CommentaryServices\GetCommentaryService;
use App\Http\Services\EventServices\GetEventByIDService;
use App\Http\Services\EventServices\GetEventUserCountService;
class EventController extends Controller
{
    
    public function eventindex(string $group_id){
        return view('createevent', compact('group_id'));
    }
    public function GetSomeEventLists(GetSomeEventService $getSomeEventService){
        $results = $getSomeEventService->execute(3);
        return view('event', ['results' => $results]);
    }

    public function CreateEvent(string $group_id, Request $request, CreateEventService $createEventService, GetGroupPermisionService $getGroupPermisionService){
        $request->validate([
            'e_name'        => 'required|string|max:255',
            'e_description' => 'required|string',
            'e_place'       => 'required|string|max:255',
            'e_image'       => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'e_date'        => 'required|date',
        ]);
        $uid = Auth::user()->id;
        $data = $getGroupPermisionService->execute($uid, $group_id);
        if(!isset($data->role)){
            return redirect()->route('event.eventindex',['status'=>'anda tidak memiliki akses untuk membuat event', 'group_id'=>$group_id]);
        }
        if($data->role == 3){
            return redirect()->route('event.eventindex',['status'=>'andat tidak memiliki akses untuk membuat event', 'group_id'=>$group_id]);
        }
        else{
            $ug_id = $data->ug_id;
        }

        $imageName = time() . '_' . $request->file('e_image')->getClientOriginalName();
        $imagePath = $request->file('e_image')->storeAs('/public/eventimages', $imageName);
        $event = new Event(
            Str::uuid(),
            $request->input('e_name'),
            $request->input('e_description'),
            $request->input('e_place'),
            '/'.$imagePath,
            $request->input('e_date'),
            $group_id,
            $ug_id,
        );
        DB::beginTransaction();
        try{
            $createEventService->execute($event);
        }
        catch(\Exception $e){
            DB::rollback();
            throw $e;
        }
        DB::commit();
        return redirect()->route('group.getgroupbyid', ['status'=>'success membuat event', 'group_id'=>$group_id]);
    }
    public function SearchEvent(Request $request, FindEventService $findEventService){
        if($request->input('search') == null){
            return redirect()->route('event.getsearch');
        }
        $event = $request->input('search');
        $results = $findEventService->execute($event);
        return view('event',['results'=>$results]);

    }
    public function UpdateEvent(string $group_id, string $event_id, CreateEventService $createEventService, GetGroupPermisionService $getGroupPermisionService, Request $request){
        $request->validate([
            'e_name'        => 'required|string|max:255',
            'e_description' => 'required|string',
            'e_place'       => 'required|string|max:255',
            'e_image'       => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'e_date'        => 'required|date',
        ]);
        $uid = Auth::user()->id;
        $data = $getGroupPermisionService->execute($uid, $group_id);

        if(!isset($data->ug_id)){
            return view('createevent', ['status'=>'anda tidak memiliki akses untuk membuat event']);
        }
        if($data->role == 3){
            return view('createevent', ['status'=>'anda tidak memiliki akses untuk membuat event']);
        }
        else{
            $ug_id = $data->ug_id;
        }
        $imagePath = "";
        if ($request->e_image != null) {
            $imageName = time() . '_' . $request->file('e_image')->getClientOriginalName();
            $imagePath = $request->file('e_image')->storeAs('/public/eventimages', $imageName);
            $imagePath = '/'.$imagePath;
        } else {
            $imagePath = $request->input('e_image_original');
        }

        $event = new Event(
            $event_id,
            $request->input('e_name'),
            $request->input('e_description'),
            $request->input('e_place'),
            $imagePath,
            $request->input('e_date'),
            $group_id,
            $ug_id,
        );
        
        DB::beginTransaction();
        try{
            $createEventService->execute($event);
        }
        catch(\Exception $e){
            DB::rollback();
            throw $e;
        }
        DB::commit();

        return redirect()->route('group.getgroupbyid', ['group_id' => $group_id]);
    }
    public function DeleteEvent(string $group_id, string $event_id, DeleteEventService $deleteEventService){
        DB::beginTransaction();
        try{
            $deleteEventService->execute($event_id);
        }
        catch(\Exception $e){
            DB::rollback();
            throw $e;
        }
        DB::commit();
        
        return redirect()->route('group.getgroupbyid', ['group_id' => $group_id]);
    }

    public function getEventByUserID(GetEventByUserIDService $getEventByUserIDService){
        $results = $getEventByUserIDService->execute(Auth::user()->id);
        return view('myevent', ['results' => $results]);
    }

    public function joinEvent(string $group_id, string $event_id, JoinEventService $joinEventService){
        $user_id = Auth::user()->id;
        DB::beginTransaction();
        try{
            $info = $joinEventService->execute($user_id, $event_id);
        }catch(\Exception $e)
        {
            DB::rollback();
            return redirect()->route('event.getsearch',['status'=>$info]);
        }
        DB::commit();
        return redirect()->route('event.getsearch',['status'=>$info]);
    }

    public function AddEventCommentary(Request $request, string $event_id, CreateCommentService $createCommentService)
    {
        $request->validate([
            'commentary' => 'string|required'
        ]);
        $userId = Auth::user()->id;
        DB::beginTransaction();
        try{
            $createCommentService->execute($event_id, $userId, $request->input('commentary'));
        }
        catch(\Exception $e)
        {
            DB::rollback();
            return "Tidak bisa mengirim komentar";
        }
        DB::commit();
        return "Sukses menambahkan komentar";
    }

    public function GetEventComment(string $event_id, GetCommentaryService $getCommentaryService){
        $comments = $getCommentaryService->execute($event_id);
        return $comments;
    }

    public function getEventByID(string $group_id, string $event_id, GetEventByIDService $getEventByIDService, GetEventUserCountService $getEventUserCountService){
        $event = $getEventByIDService->execute($event_id);
        $total = $getEventUserCountService->execute($event_id);
        return view('eventdetail', ['result' => $event, 'total' => $total->total, 'group_id' => $group_id, 'event_id' => $event_id]);
    }

}
