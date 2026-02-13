<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContactSubmission;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactSubmissionMail;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        // Save to database
        $contactSubmission = ContactSubmission::create(array_merge($validated, [
            'ip_address' => $request->ip(),
            'user_id' => $user ? $user->id : null,
        ]));
        // Email admin
        Mail::to(config('app.admin_email'))->send(new ContactSubmissionMail($contactSubmission));

        return response()->json(['message' => 'Thank you for contacting us!'], 200);
    }
}