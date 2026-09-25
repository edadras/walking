<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $orgs) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->orgs->overview($request->user())]);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12'], 'department' => ['nullable', 'string', 'max:60']]);
        $this->orgs->join($request->user(), $data['code'], $data['department'] ?? null);

        return response()->json(['data' => $this->orgs->overview($request->user())], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['department' => ['required', 'string', 'max:60']]);
        $this->orgs->setDepartment($request->user(), $data['department']);

        return $this->show($request);
    }

    public function leave(Request $request): Response
    {
        $this->orgs->leave($request->user());

        return response()->noContent();
    }
}
