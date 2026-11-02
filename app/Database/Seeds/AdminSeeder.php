<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('users')->where('username', 'admin')->countAllResults() > 0) return;
        $password = bin2hex(random_bytes(12));
        $users = auth()->getProvider();
        $user = new User(['username'=>'admin','first_name'=>'System','last_name'=>'Administrator','password'=>$password,'must_set_password'=>1,'active'=>1]);
        $users->save($user); $user = $users->findById($users->getInsertID()); $user->addGroup('admin');
        CLI::write('Initial administrator account (shown once):'); CLI::write('Username: admin'); CLI::write('Password: ' . $password); CLI::write('You must set a new password after first sign-in.');
    }
}
