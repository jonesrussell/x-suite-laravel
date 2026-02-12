<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use JonesRussell\XSuite\Models\XPost;

final class XPostPolicy
{
    protected function isAdmin(Authenticatable $user): bool
    {
        $attribute = config('x-suite.admin_attribute', 'is_admin');

        return ($user->{$attribute} ?? false) === true;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user) && $xPost->canEdit();
    }

    public function delete(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user) && $xPost->canEdit();
    }

    public function publish(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user) && $xPost->canPublish();
    }

    public function schedule(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user) && $xPost->canSchedule();
    }

    public function cancel(Authenticatable $user, XPost $xPost): bool
    {
        return $this->isAdmin($user) && $xPost->canCancel();
    }
}
