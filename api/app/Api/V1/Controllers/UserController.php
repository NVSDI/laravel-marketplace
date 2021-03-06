<?php

namespace App\Api\V1\Controllers;

use Auth;
use App\Api\V1\Requests\LoginRequest;
use App\Repositories\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserProfileRequest;
use App\Http\Requests\UserPasswordRequest;
use App\Http\Requests\UserAccountRequest;
use App\Mail\UserAccountUpdatedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * UserController
 */
class UserController extends Controller
{
    /**
     * @var App\Repositories\UserRepository
     */
    protected $user;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct(UserRepository $user)
    {
        $protected = ['me', 'update', 'password', 'account'];

        $this->middleware('jwt.auth')->only($protected);
        $this->middleware('auth:api')->only($protected);

        $this->user = $user;
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return new UserResource(Auth::guard()->user());
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return UserResource::collection($this->user->paginate());
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return new UserResource($this->user->findOrFail($id));
    }

    /**
     * @param int $id
     * @param App\Http\Requests\UserProfileRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, UserProfileRequest $request)
    {
        $user = $this->user->findOrFail($id);

        $this->authorize('update', $user);

        $user->update($request->only(['avatar', 'name']));

        return new UserResource($this->user->findOrFail($id));
    }

    /**
     * @param int $id
     * @param App\Http\Requests\UserPasswordRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Illuminate\Auth\Access\AuthorizationException
     */
    public function password($id, UserPasswordRequest $request)
    {
        $user = $this->user->findOrFail($id);

        if (!$user->isPasswordValid($request->get('password'))) {
            throw new AuthorizationException('Password did not match against the account.');
        }

        $this->authorize('update', $user);

        $user->update([
            'password' => $request->get('password')
        ]);

        return new UserResource($this->user->findOrFail($id));
    }

    /**
     * @param int $id
     * @param App\Http\Requests\UserAccountRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function account($id, UserAccountRequest $request)
    {
        $user = $this->user->findOrFail($id);

        $this->authorize('update', $user);

        $user->update([
            'email_update' => $request->get('email'),
            'activation_code' => str_random(40),
        ]);

        Mail::to($user->email)->send(new UserAccountUpdatedMail($user));

        return new UserResource($this->user->findOrFail($id));
    }
}
