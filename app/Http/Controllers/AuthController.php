<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponser;

    public function register(Request $request)
    {
        $rules = [
            'username' => 'required|string|max:255|unique:users,username',
            'name' => 'required|string|max:255|',
            'surname' => 'required|string|max:255|',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed|regex:/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^\w\d\s:])([^\s]){8,16}$/'
        ];

        $messages = [
            'password.regex' => "Contraseña inválida",
            'email.unique' => "El email ya existe",
            'username.unique' => "El nombre de usuario ya existe"
        ];

        $attr = $this->validate($request, $rules, $messages);


        $user = User::create([
            'username' => $attr['username'],
            'name' => $attr['name'],
            'surname' => $attr['surname'],
            'password' => bcrypt($attr['password']),
            'email' => $attr['email']
        ]);

        return $this->success([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    public function login(Request $request)
    {
        $attr = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:6'
        ]);

        if (!Auth::attempt($attr)) {
            return $this->error('E-mail o contraseña incorrectos', 401);
        }

        return $this->success([
            'token' => auth()->user()->createToken('API Token')->plainTextToken,
            'name' => auth()->user()->name
        ]);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();

        return [
            'message' => 'Tokens Revoked'
        ];
    }
}
