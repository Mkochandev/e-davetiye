<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMeRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateMeRequest $request): UserResource
    {
        $user = $request->user();

        $user->name = $request->string('name')->trim()->toString();

        if ($request->boolean('kvkk_accepted')) {
            $user->kvkk_accepted_at = now();
            $user->kvkk_version = config('zarf.kvkk_version');
        }

        $user->save();

        return new UserResource($user);
    }
}
