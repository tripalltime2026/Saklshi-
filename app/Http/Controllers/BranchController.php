<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => $this->branchPayload($branch));

        return response()->json(['data' => $branches])->header('Cache-Control', 'no-store');
    }

    public function show(Branch $branch): JsonResponse
    {
        abort_unless($branch->active, 404);

        $branch->load(['bookingSettings', 'hours', 'specialHours']);

        return response()->json([
            'data' => $this->branchPayload($branch) + [
                'booking' => $branch->bookingSettings ? [
                    'capacity' => $branch->bookingSettings->capacity,
                    'max_party_size' => $branch->bookingSettings->max_party_size,
                    'duration_minutes' => $branch->bookingSettings->duration_minutes,
                    'buffer_minutes' => $branch->bookingSettings->buffer_minutes,
                    'active' => $branch->bookingSettings->active,
                ] : null,
                'hours' => $branch->hours->map(fn ($row) => [
                    'weekday' => $row->weekday,
                    'opens_at' => $row->opens_at,
                    'closes_at' => $row->closes_at,
                    'closed' => $row->closed,
                ]),
                'special_hours' => $branch->specialHours->map(fn ($row) => [
                    'date' => $row->date->format('Y-m-d'),
                    'opens_at' => $row->opens_at,
                    'closes_at' => $row->closes_at,
                    'closed' => $row->closed,
                    'capacity_override' => $row->capacity_override,
                    'note' => $row->note,
                ]),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'city' => $branch->city,
            'address' => $branch->address,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'phone' => $branch->phone,
            'timezone' => $branch->timezone,
            'description' => $branch->description,
        ];
    }
}
