<?php

namespace App\Http\Controllers;

use App\Models\BehaviourReport;
use Illuminate\Http\Request;
use App\Models\Submission;
use App\Models\Comment;
use Inertia\Inertia;

class BehaviourReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch all behaviour reports
        $submission_reports = BehaviourReport::where('submission_id', '!=', null)
            ->where('is_resolved', false)
            ->with('submission', 'reportedBy', 'reportedUser', 'resolvedBy')
            ->get();

        $comment_reports = BehaviourReport::where('comment_id', '!=', null)
            ->where('is_resolved', false)
            ->with('comment', 'reportedBy', 'reportedUser', 'resolvedBy')
            ->get();

        return inertia('BehaviourReports/Index', [
            'submission_reports' => $submission_reports, 
            'comment_reports' => $comment_reports
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function createForSubmissions($id)
    {
        $submission = Submission::with('user')->findOrFail($id);
        return inertia('BehaviourReports/Submissions/Create', [
            'submission' => $submission
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createForComments($id)
    {
        $comment = Comment::with('user')->findOrFail($id);
        return inertia('BehaviourReports/Comments/Create', [
            'comment' => $comment
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeForSubmissions(Request $request)
    {
        //check not reporting themselves
        if ($request->user()->id === $request->input('reported_user_id')) {
            return response()->json([
                'flashStatus' => 'error', 
                'message' => 'You cannot report yourself.',
            ]);
        }

        // only allow one report per submission per user
        $existingReport = BehaviourReport::where('submission_id', $request->input('submission_id'))
            ->where('reported_by', $request->user()->id)
            ->first();

        if ($existingReport) {
            return response()->json([
                'flashStatus' => 'error',
                'message' => 'You have already reported this submission.',
            ]);
        }

        $request->validate([
            'reason' => 'required|string|max:255',
            'details' => 'required|string',
            'submission_id' => 'required|exists:submissions,id',
            'reported_user_id' => 'required|exists:users,id',
        ]);

        $submission = Submission::findOrFail($request->input('submission_id'));

        $report = BehaviourReport::create([
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'reported_by' => $request->user()->id,
            'reported_user_id' => $request->input('reported_user_id'),
        ]);

        $report->submission()->associate($submission);
        $report->save();

        $this->handleIfUserShouldBeDisabled($report->reportedUser);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Report submitted successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeForComments(Request $request)
    {

        //check not reporting themselves
        if ($request->user()->id === $request->input('reported_user_id')) {
            return response()->json([
                'flashStatus' => 'error', 
                'message' => 'You cannot report yourself.',
            ]);
        }

        // only allow one report per submission per user
        $existingReport = BehaviourReport::where('comment_id', $request->input('comment_id'))
            ->where('reported_by', $request->user()->id)
            ->first();

        if ($existingReport) {
            return response()->json([
                'flashStatus' => 'error',
                'message' => 'You have already reported this comment.',
            ]);
        }

        $request->validate([
            'reason' => 'required|string|max:255',
            'details' => 'required|string',
            'comment_id' => 'required|exists:comments,id',
            'reported_user_id' => 'required|exists:users,id',
        ]);

        $comment = Comment::findOrFail($request->input('comment_id'));

        $report = BehaviourReport::create([
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'reported_by' => $request->user()->id,
            'reported_user_id' => $request->input('reported_user_id'),
        ]);

        $report->comment()->associate($comment);
        $report->save();

        $this->handleIfUserShouldBeDisabled($report->reportedUser);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Report submitted successfully.',
        ]);
    }

    private function handleIfUserShouldBeDisabled($reportedUser)
    {
        if ($reportedUser->totalBehaviourReports() >= 3 && !$reportedUser->disabled) {
            $reportedUser->disabled = true;
            $reportedUser->save();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(BehaviourReport $behaviourReport)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BehaviourReport $behaviourReport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BehaviourReport $behaviourReport)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BehaviourReport $behaviourReport)
    {
        //
    }

    /**
     * Resolve the specified resource from storage.
     */
    public function resolve(Request $request, $id)
    {
        $report = BehaviourReport::findOrFail($id);
        $report->is_resolved = $request->input('is_resolved', false);
        $report->resolved_by = $request->user()->id;
        $report->resolution_details = $request->input('resolution_details', '');
        $report->save();

        return response()->json([
            'flashStatus' => 'success', 
            'message' => 'Report submitted successfully.',
            'report' => $report,
        ]);
    }
}