<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscrepancyActionRequest;
use App\Models\Discrepancy;
use App\Models\DiscrepancyAction;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DiscrepancyActionController extends Controller
{
    use ApiResponse;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(string $discrepancyId)
    {
        $actions = DiscrepancyAction::where('ID_discrepancy', $discrepancyId)->with('user')->get();
        return $this->success($actions);
    }

    public function store(DiscrepancyActionRequest $request, string $discrepancyId)
    {
        $discrepancy = Discrepancy::findOrFail($discrepancyId);

        $action = DiscrepancyAction::create([
            'ID_discrepancy' => $discrepancyId,
            'action_type' => $request->action_type,
            'action_by' => $request->user()->ID_user,
            'notes' => $request->notes,
            'status_action' => 'done',
        ]);

        // Notify supervisor
        $supervisors = \App\Models\User::where('role', 'supervisor')->get();
        foreach ($supervisors as $supervisor) {
            $this->notificationService->send(
                $supervisor->ID_user,
                'Discrepancy Action Taken',
                'Aksi [' . $action->action_type . '] diambil untuk discrepancy ID ' . $discrepancyId . ' oleh ' . $request->user()->nama,
                'discrepancy',
                $discrepancyId
            );
        }

        return $this->success($action, 'Action created successfully', 201);
    }
}
