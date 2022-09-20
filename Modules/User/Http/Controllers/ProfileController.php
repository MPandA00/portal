<?php

namespace Modules\User\Http\Controllers;

use Modules\User\Contracts\ProfileServiceContract;
use Modules\User\Entities\User;
use Modules\Operations\Entities\OfficeLocation;
use Modules\HR\Http\Requests\ProfileEditRequest;

class ProfileController extends ModuleBaseController
{
    protected $service;

    public function __construct(ProfileServiceContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('user::profile.index', $this->service->index());
    }

    public function update(ProfileEditRequest $request, User $user, OfficeLocation $officelocation)
    {
        $user->name = $request->name;
        $user->nickname = $request->nickName;
        $user->employee->designation = $request->designation;
        $user->employee->officelocation->location = $request->location;
        $user->employee->name = $request->name;
        $user->employee->domain_id = $request->domainId;

        $user->push();

        return back();
    }
}
