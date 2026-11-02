<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('auth/login', ['title' => 'Log in']);
    }

    public function styleGuide(): string
    {
        if (ENVIRONMENT === 'production') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('dev/ui', ['title' => 'UI style guide']);
    }
}
