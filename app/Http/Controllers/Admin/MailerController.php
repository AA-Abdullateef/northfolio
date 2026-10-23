<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CustomMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailerController extends Controller
{
    public function index()
    {
        $users = User::customers()->with('profile')->orderBy('email')->get();
        return view('admin.mailer.index', compact('users'));
    }

    /**
     * Send to a single user or all users.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string'],
            'user_id'  => ['nullable', 'uuid', 'exists:users,id'],  // null = send to all
        ]);

        if (! empty($data['user_id'])) {
            $user = User::findOrFail($data['user_id']);
            Mail::to($user->email)->send(
                new CustomMail($data['subject'], $data['message'])
            );

            return back()->with('success', "Email sent to {$user->email}.");
        }

        // Bulk — chunk to avoid memory issues on large user lists
        User::customers()->select('id', 'email')->chunk(100, function ($users) use ($data) {
            foreach ($users as $user) {
                Mail::to($user->email)->send(
                    new CustomMail($data['subject'], $data['message'])
                );
            }
        });

        return back()->with('success', 'Bulk email sent to all customers.');
    }
}
