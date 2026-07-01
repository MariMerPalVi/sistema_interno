<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $user?->registerFailedLogin();
            app(AuditLogService::class)->record('login_fallido', 'Intento de inicio de sesión fallido.', [
                'username' => $credentials['username'],
                'user_id_attempted' => $user?->id,
            ]);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'El usuario o la contraseña no son válidos.']);
        }

        if (!$user->active) {
            app(AuditLogService::class)->record('login_usuario_inactivo', 'Intento de ingreso con usuario inactivo.', [
                'username' => $user->username,
                'user_id_attempted' => $user->id,
            ]);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'El usuario se encuentra inactivo. Solicite soporte al administrador.']);
        }

        if ($user->isLocked()) {
            app(AuditLogService::class)->record('login_usuario_bloqueado', 'Intento de ingreso con usuario bloqueado temporalmente.', [
                'username' => $user->username,
                'user_id_attempted' => $user->id,
                'locked_until' => $user->locked_until?->toDateTimeString(),
            ]);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'El usuario está bloqueado temporalmente por intentos fallidos. Intente más tarde o solicite soporte al administrador.']);
        }

        $user->clearLoginLock();
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        app(AuditLogService::class)->record('login_exitoso', 'Inicio de sesión correcto.', [
            'username' => $user->username,
        ]);

        if ($user->must_change_password) {
            return redirect()->route('password.edit')
                ->with('success', 'Debe cambiar su contraseña temporal antes de continuar.');
        }

        return redirect()->intended(route('processes.index'));
    }

    public function destroy(Request $request)
    {
        app(AuditLogService::class)->record('logout', 'Cierre de sesión.');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        app(AuditLogService::class)->record('cambiar_contrasena', 'Usuario actualizó su contraseña.');

        $request->session()->regenerate();

        return redirect()->route('processes.index')->with('success', 'Contraseña actualizada correctamente.');
    }

    public function resetTemporaryPassword(Request $request, User $user)
    {
        abort_unless($request->user()?->isAdministrator(), 403, 'Solo el administrador puede restablecer contraseñas.');

        $temporaryPassword = Str::random(10).random_int(10, 99);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'password_changed_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(60),
        ])->save();

        app(AuditLogService::class)->record('restablecer_contrasena_temporal', 'Administrador generó contraseña temporal.', [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
        ]);

        return back()->with('success', "Contraseña temporal generada para {$user->username}: {$temporaryPassword}");
    }
}
