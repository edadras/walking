<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StorePresenter;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function __construct(private readonly StorePresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $list = Address::query()->where('user_id', $request->user()->id)->orderByDesc('is_default')->orderByDesc('id')->get();

        return response()->json(['data' => $list->map(fn (Address $a) => $this->present->address($a))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(Address::query()->where('user_id', $user->id)->count() >= 10, 422, 'Too many addresses');
        $address = DB::transaction(function () use ($request, $user) {
            $a = new Address($this->validated($request));
            $a->user_id = $user->id;
            $a->is_default = $a->is_default || Address::query()->where('user_id', $user->id)->doesntExist();
            $a->save();
            $this->single($a);

            return $a;
        });

        return response()->json(['data' => $this->present->address($address)], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $address) {
            $address->fill($this->validated($request, partial: true))->save();
            $this->single($address);
        });

        return response()->json(['data' => $this->present->address($address)]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();

        return response()->json(['data' => null]);
    }

    private function single(Address $a): void
    {
        if ($a->is_default) {
            Address::query()->where('user_id', $a->user_id)->whereKeyNot($a->id)->update(['is_default' => false]);
        }
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $r = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => ['nullable', 'string', 'max:50'],
            'recipient' => [$r, 'string', 'max:100'],
            'phone' => [$r, 'string', 'regex:/^0\d{10}$/'],
            'province' => [$r, 'string', 'max:50'],
            'city' => [$r, 'string', 'max:50'],
            'line' => [$r, 'string', 'max:300'],
            'postal_code' => [$r, 'string', 'regex:/^\d{10}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
