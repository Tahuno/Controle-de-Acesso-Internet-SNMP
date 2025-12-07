<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class AuthController extends BaseController
{
    public function login()
    {
        $request = $this->request;
        $session = session();

        if ($session->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        if ($request->getMethod() == 'POST') {
            $username = trim((string) $request->getPost('username'));
            $password = (string) $request->getPost('password');

            $validUser = 'admin';
            $validPass = 'admin123';

            if ($username === $validUser && $password === $validPass) {
                $session->set([
                    'isLoggedIn' => true,
                    'username'   => $username,
                    'role'       => 'room_manager',
                ]);

                return redirect()->to('/');
            }

            $session->setFlashdata('error', 'Usuário ou senha inválidos.');
            return redirect()->back()->withInput();
        }

        return view('auth/login');
    }

    public function logout()
    {
        $session = session();
        $session->destroy();

        return redirect()->to('/login');
    }
}
