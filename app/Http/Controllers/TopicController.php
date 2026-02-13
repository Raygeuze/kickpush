<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TopicController extends Controller
{
    public function index()
    {
        $approvedTopics = \App\Models\Topic::where('approved', true)
            ->with('createdBy')
            ->get();

        $unapprovedTopics = \App\Models\Topic::where('approved', false)
            ->with('createdBy')
            ->get();

        return inertia('Topic/Index', [
            'approvedTopics' => $approvedTopics,
            'unapprovedTopics' => $unapprovedTopics
        ]);
    }

    public function approve($id)
    {
        $topic = \App\Models\Topic::findOrFail($id);
        $user = Auth::user();

        if ($user->is_admin) {
            $topic->approved = true;
            $topic->approved_by = $user->id;
            $topic->save();

            Log::info('Topic approved', ['topic_id' => $topic->id, 'user_id' => $user->id]);

            return response()->json([
                'flashStatus' => 'success', 
                'message' => 'Topic approved successfully', 
                'topic' => $topic->load('createdBy', 'approvedBy')
            ]);
        } else {
            Log::warning('Unauthorized topic approval attempt', ['topic_id' => $topic->id, 'user_id' => $user->id]);
            return response()->json(['flashStatus' => 'error', 'message' => 'Unauthorized'], 403);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = Auth::user();

        $topic = new \App\Models\Topic();
        $topic->topic = $request->input('topic');
        $topic->description = $request->input('description');
        $topic->created_by = $user->id;
        $topic->save();

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Topic created successfully',
            'topic' => $topic->load('createdBy'),
        ]);
    }

    public function create()
    {
        return inertia('Topic/Create');
    }

    public function update(Request $request, $id)
    {
        $topic = \App\Models\Topic::findOrFail($id);
        $topic->topic = $request->input('topic');
        $topic->description = $request->input('description');
        $topic->save();

        return response()->json([
            'flashStatus' => 'success', 
            'message' => 'Topic updated successfully', 
            'topic' => $topic->load('createdBy', 'approvedBy')
        ]);
    }
}
