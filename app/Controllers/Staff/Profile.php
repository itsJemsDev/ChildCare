<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
final class Profile extends BaseController
{
    public function index(): string {return view('staff/profile',['title'=>'Profile Settings','user'=>auth()->user()]);}
}
