<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class ChatkitController extends Controller
{
    private $chatkit;
    private $roomId;
    public function __construct()
    {
        $this->chatkit = app('ChatKit');
        $this->roomId = env('CHATKIT_GENERAL_ROOM_ID');
    }
    /**
     * Show the welcome page.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $userId = $request->session()->get('chatkit_id')[0];
        if(!is_null($userId)) {
            // Redirect user to Chat Page
            return redirect(route('chat'));
        }
        return view('welcome');
    }
    /**
     * The user joins chat room.
     *
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function join(Request $request)
    {
        $chatkit_id = strtolower(str_random(5));
        // Create User account on ChatKit
        $this->chatkit->createUser([
            'id' =>  $chatkit_id,
            'name' => $request->username,
        ]);
        $this->chatkit->addUsersToRoom([
            'room_id' => $this->roomId,
            'user_ids' => [$chatkit_id],
        ]);
        // Add User details to session
        $request->session()->push('chatkit_id', $chatkit_id);
        // Redirect user to Chat Page
        return redirect(route('chat'));
    }
    /**
     * Show the application chat room.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function chat(Request $request)
    {
        $roomId = $this->roomId;
        $userId = $request->session()->get('chatkit_id')[0];
        if(is_null($userId)) {
            $request->session()->flash('status', 'Join to access chat room!');
            return redirect(url('/'));
        }
        // Get messages via Chatkit
        $fetchMessages = $this->chatkit->getRoomMessages([
            'room_id' => $roomId,
            'direction' => 'newer',
            'limit' => 100
        ]);
        $messages = collect($fetchMessages['body'])->map(function ($message) {
            return [
                'id' => $message['id'],
                'senderId' => $message['user_id'],
                'text' => $message['text'],
                'timestamp' => $message['created_at']
            ];
        });
        return view('chat')->with(compact('messages', 'roomId', 'userId'));
    }
    /**
     * Receives a client request and provides a new token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function authenticate(Request $request)
    {
        $response = $this->chatkit->authenticate([
            'user_id' => $request->user_id,
        ]);
        return response()
            ->json(
                $response['body'],
                $response['status']
            );
    }
     /**
     * Send user message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function sendMessage(Request $request)
    {
        $message = $this->chatkit->sendSimpleMessage([
            'sender_id' => $request->user,
            'room_id' => $this->roomId,
            'text' => $request->message
        ]);
        return response($message);
    }
    /**
     * Get all users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function getUsers()
    {
        $users = $this->chatkit->getUsers();
        return response($users);
    }
    /**
     * Get all users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect(url('/'));
    }
}
