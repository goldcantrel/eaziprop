<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);
        
        return response()->json(User::all());
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:superuser,landlord,tenant',
            'phone' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended'
        ]);

        $user = User::create([
            ...$request->validated(),
            'password' => Hash::make($request->password)
        ]);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        
        return response()->json($user->load([
            'properties',
            'rentals.property',
            'maintenanceRequests'
        ]));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $request->validate([
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'in:superuser,landlord,tenant',
            'phone' => 'nullable|string',
            'status' => 'in:active,inactive,suspended'
        ]);

        $data = $request->except('password');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();
        return response()->json(null, 204);
    }

    public function profile()
    {
        $user = auth()->user();
        return response()->json($user->load([
            'properties',
            'rentals.property',
            'maintenanceRequests'
        ]));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string'
        ]);

        $data = $request->except('password');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        return response()->json($user);
    }
}