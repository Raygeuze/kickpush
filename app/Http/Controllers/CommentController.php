<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use \App\Models\CommentLike;
use \App\Models\CommentDislike;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function index()
    {
        $comments = Comment::with(['user', 'children'])->whereNull('parent_id')->get();
        return response()->json($comments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|exists:submissions,id',
            'user_id' => 'required|exists:users,id',
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:comments,id',
            'replying_to_id' => 'nullable|exists:users,id',
        ]);

        $comment = Comment::create($validated);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Comment created successfully.',
            'comment' => $comment->load(['user', 'children', 'isReplyingTo', 'likes', 'dislikes']),
        ]);
    }

    public function show(Comment $comment)
    {
        return response()->json($comment->load(['user', 'children']));
    }

    public function update(Request $request, Comment $comment)
    {
        $comment = Comment::where([
            'id' =>  $request->input('comment_id'),
            'user_id' => $request->input('user_id'),
        ])->first();

        if(!$comment) {
            return response()->json([
                'flashStatus' => 'error',
                'message' => 'You can only edit your own comments.',
            ]);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $comment->update($validated);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Comment updated successfully.', 
            'comment' => $comment->load(['user', 'likes', 'dislikes', 'children']),
        ]);
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();
        return response()->json(null, 204);
    }

    public function like(Request $request, $commentId)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $like = CommentLike::where([
            'comment_id' => $commentId,
            'user_id' => $validated['user_id'],
        ])->first();

        if($like) {
            $like->delete();

            $comment = Comment::withCount('likes')
                ->withCount('dislikes')
                ->findOrFail($commentId);

            return response()->json([
                'flashStatus' => 'error',
                'message' => 'Like removed.',
                'comment' => $comment->load('likes', 'dislikes')
            ]);
        }
        else {
            // Remove dislike if exists
            CommentDislike::where([
                'comment_id' => $commentId,
                'user_id' => $validated['user_id'],
            ])->delete();

            $like = CommentLike::firstOrCreate([
                'comment_id' => $commentId,
                'user_id' => $validated['user_id'],
            ]);
        }

        $comment = Comment::withCount('likes')
            ->withCount('dislikes')
            ->findOrFail($commentId);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Comment liked.', 
            'comment' => $comment->load('likes', 'dislikes'),
        ]);
    }

    public function dislike(Request $request, $commentId)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $dislike = CommentDislike::where([
            'comment_id' => $commentId,
            'user_id' => $validated['user_id'],
        ])->first();

        if($dislike) {
            $dislike->delete();

            $comment = Comment::withCount('likes')
                ->withCount('dislikes')
                ->findOrFail($commentId);

            return response()->json([
                'flashStatus' => 'error',
                'message' => 'Dislike removed.',
                'comment' => $comment->load('likes', 'dislikes'),
            ]);
        }
        else {
            // Remove like if exists
            CommentLike::where([
                'comment_id' => $commentId,
                'user_id' => $validated['user_id'],
            ])->delete();

            $dislike = CommentDislike::Create([
                'comment_id' => $commentId,
                'user_id' => $validated['user_id'],
            ]);
        }
        $comment = Comment::withCount('likes')
            ->withCount('dislikes')
            ->findOrFail($commentId);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Comment disliked.', 
            'comment' => $comment->load('likes', 'dislikes')
        ]);
    }

    
    public function commentHistory($id)
    {
        $comment = \App\Models\Comment::findOrFail($id);

        return Inertia::render('Submission/Comments/History', [
            'audits' => $comment->audits,
            'comment' => $comment
        ]);
    }

}
