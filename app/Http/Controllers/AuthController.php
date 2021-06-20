<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(title="API CFDSM", version="1.0")
 * 
 *  @OA\Server(url="https://cfdsm.es")
 */
class AuthController extends Controller
{   
    /**
     * @OA\Get(
     *  path="/api/me",
     *  summary="Datos del usuario",
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve los datos del usuario"
     *  ),
     *  @OA\Response(
     *      response=500,
     *      description="Datos para del usuario (token) incorrectos"
     *  )
     *  
     * )
     */
    use ApiResponser;
    /**
     * @OA\Post(
     *  path="/api/auth/register",
     *  summary="Registro de usuarios",
     *  @OA\Parameter(
     *      name = "username",
     *      description = "Nombre de usuario para el registro",
     *      required = true, 
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "name",
     *      description = "Nombre real del usuario para el registro",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "surname",
     *      description = "Apellido(s) del usuario para el registro",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "email",
     *      description = "Correo electrónico del usuario para el registro",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="E-mail"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "password",
     *      description = "Contraseña del usuario para el registro",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "password_confirmation",
     *      description = "Confirmación de contraseña del usuario para el registro",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Registro de usuario"
     *  )
     *  ,
     *  @OA\Response(
     *      response=429,
     *      description="Datos para el registro inválidos"
     *  )
     * )
     */
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
            'email' => $attr['email'],
            'wallet' => 10000
        ]);

        return $this->success([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    /**
     * @OA\Post(
     *  path="/api/auth/login",
     *  summary="Inicio de sesión de usuarios",
     *  @OA\Parameter(
     *      name = "email",
     *      description = "Correo electrónico del usuario",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="E-mail"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "password",
     *      description = "Contraseña del usuario",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Inicio de sesión correcto"
     *  ),
     *  @OA\Response(
     *      response=401,
     *      description="Datos para el inicio de sesión incorrectos"
     *  )
     *  ,
     *  @OA\Response(
     *      response=429,
     *      description="Datos para el inicio de sesión inválidos"
     *  )
     * )
     */
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

    /**
     * @OA\Post(
     *  path="/api/auth/logout",
     *  summary="Cierre de sesión de usuarios",
     *  @OA\Response(
     *      response=200,
     *      description="Cierre de sesión correcto"
     *  ),
     *  @OA\Response(
     *      response=500,
     *      description="Datos para el cierre de sesión incorrectos"
     *  )
     *  
     * )
     */
    public function logout()
    {
        auth()->user()->tokens()->delete();

        return [
            'message' => 'Tokens Revoked'
        ];
    }

}
