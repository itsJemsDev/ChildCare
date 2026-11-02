<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

final class UserModel extends ShieldUserModel
{
    protected function initialize(): void
    {
        parent::initialize();
        $this->allowedFields = array_values(array_unique(array_merge($this->allowedFields, [
            'first_name', 'middle_name', 'last_name', 'sex', 'birthdate', 'mobile_no', 'address', 'purok_id', 'avatar_path',
            'must_set_password', 'last_active_at',
        ])));
    }

    public function findByMobile(string $mobile): ?\CodeIgniter\Shield\Entities\User
    {
        return $this->where('mobile_no', $mobile)->first();
    }
}
