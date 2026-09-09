<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\User;
use App\Services\TimesheetSessionPresenter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller
{
    private TimesheetSessionPresenter $presenter;

    public function __construct(TimesheetSessionPresenter $presenter)
    {
        $this->presenter = $presenter;
    }

    public function index(Request $request): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('viewAny', TimerSession::class);

        $validated = $request->validate([
            'view' => ['nullable', 'in:week,day'],
            'date' => ['nullable', 'date'],
            'week' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'invoice_status' => ['nullable', 'in:unassigned,draft,finalized,paid'],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User && $user->currentTeam, 403, 'Select a team to continue.');

        $team = $user->currentTeam;
        $timezone = (string) ($team->timezone ?: 'UTC');

        $currentView = (string) ($validated['view'] ?? 'day');
        $targetDate = CarbonImmutable::parse($validated['date'] ?? $validated['week'] ?? 'now', $timezone);
        $selectedDate = $targetDate->toDateString();

        $weekStart = $targetDate
            ->startOfWeek(CarbonInterface::MONDAY)
            ->startOfDay();
        $weekEndExclusive = $weekStart->addWeek();
        $canViewTeamSessions = Gate::allows('viewTeam', TimerSession::class);
        $selectedUserId = $canViewTeamSessions && isset($validated['user_id'])
            ? (int) $validated['user_id']
            : (!$canViewTeamSessions ? (int) $user->id : null);

        $query = TimerSession::query()
            ->where('team_id', $team->id)
            ->where('started_at', '>=', $weekStart->utc())
            ->where('started_at', '<', $weekEndExclusive->utc())
            ->with(TimesheetSessionPresenter::RELATIONS);

        if ($selectedUserId !== null) {
            $query->where(function (Builder $userQuery) use ($selectedUserId): void {
                $userQuery->where('user_id', $selectedUserId)
                    ->orWhere('user_id_snapshot', $selectedUserId);
            });
        }

        if (isset($validated['client_id'])) {
            $clientId = (int) $validated['client_id'];
            $query->where(function (Builder $clientQuery) use ($clientId): void {
                $clientQuery->where('client_id_snapshot', $clientId)
                    ->orWhereHas('task.project', fn (Builder $projectQuery) => $projectQuery->where('client_id', $clientId));
            });
        }

        if (isset($validated['project_id'])) {
            $projectId = (int) $validated['project_id'];
            $query->where(function (Builder $projectQuery) use ($projectId): void {
                $projectQuery->where('project_id_snapshot', $projectId)
                    ->orWhereHas('task', fn (Builder $taskQuery) => $taskQuery->where('project_id', $projectId));
            });
        }

        if (isset($validated['invoice_status'])) {
            $invoiceStatus = (string) $validated['invoice_status'];

            if ($invoiceStatus === 'unassigned') {
                $query->whereNull('invoice_id');
            } else {
                $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('status', $invoiceStatus));
            }
        }

        $generatedAt = now();
        $sessions = $query
            ->orderBy('started_at')
            ->get()
            ->map(fn (TimerSession $session): array => $this->presenter->present($session, $timezone, $generatedAt))
            ->values();

        $activeTimerSession = TimerSession::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereNull('stopped_at')
            ->with(TimesheetSessionPresenter::RELATIONS)
            ->latest('started_at')
            ->first();

        $presentedActiveTimer = $activeTimerSession
            ? $this->presenter->present($activeTimerSession, $timezone, $generatedAt)
            : null;

        $memberIds = DB::table('team_user')->where('team_id', $team->id)->pluck('user_id');
        $memberIds->push($team->user_id);
        $teamMembers = $canViewTeamSessions
            ? User::query()
                ->whereIn('id', $memberIds->unique())
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect([['id' => (int) $user->id, 'name' => (string) $user->name]]);

        return Inertia::render('Timesheets/Index', [
            'view' => $currentView,
            'selectedDate' => $selectedDate,
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEndExclusive->subDay()->toDateString(),
            'timezone' => $timezone,
            'serverNow' => $generatedAt->toIso8601String(),
            'days' => collect(range(0, 6))->map(function (int $offset) use ($weekStart, $selectedDate): array {
                $day = $weekStart->addDays($offset);

                return [
                    'key' => $day->toDateString(),
                    'short_label' => $day->format('D'),
                    'date_label' => $day->format('j M'),
                    'full_label' => $day->format('l, j F'),
                    'is_today' => $day->isToday(),
                    'is_selected' => $day->toDateString() === $selectedDate,
                ];
            })->all(),
            'sessions' => $sessions,
            'activeTimerSession' => $presentedActiveTimer,
            'clients' => Client::query()
                ->where('team_id', $team->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'projects' => Project::query()
                ->where('team_id', $team->id)
                ->orderBy('name')
                ->get(['id', 'client_id', 'name']),
            'tasks' => Task::query()
                ->where('team_id', $team->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'project_id', 'client_id', 'name']),
            'draftInvoices' => Invoice::query()
                ->where('team_id', $team->id)
                ->where('status', 'draft')
                ->orderByDesc('id')
                ->get(['id', 'client_id', 'invoice_number']),
            'canCreateSessions' => Gate::allows('create', TimerSession::class),
            'teamMembers' => $teamMembers,
            'canViewTeamSessions' => $canViewTeamSessions,
            'filters' => [
                'view' => $currentView,
                'date' => $selectedDate,
                'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
                'project_id' => isset($validated['project_id']) ? (int) $validated['project_id'] : null,
                'user_id' => $selectedUserId,
                'invoice_status' => $validated['invoice_status'] ?? null,
            ],
            'navigation' => [
                'previous_week' => $weekStart->subWeek()->toDateString(),
                'current_week' => CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                'next_week' => $weekStart->addWeek()->toDateString(),
            ],
            'dayNavigation' => [
                'previous_day' => $targetDate->subDay()->toDateString(),
                'current_day' => CarbonImmutable::now($timezone)->toDateString(),
                'next_day' => $targetDate->addDay()->toDateString(),
            ],
        ]);
    }
}