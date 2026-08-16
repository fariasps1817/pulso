<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\Acesso\PorteiroDoLogin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * A autenticacao passa pelo porteiro: e ele que conta as tentativas
         * por e-mail e por IP, registra a trilha e fecha a porta.
         *
         * O limitador do Fortify continua ativo por cima (5 por minuto) — ele
         * segura a rajada; o porteiro segura a insistencia ao longo de
         * quinze minutos, que e o ataque que de fato acontece.
         */
        Fortify::authenticateUsing(function (Request $request): ?User {
            /*
             * O Fortify chama isto DUAS VEZES no mesmo pedido — uma no
             * RedirectIfTwoFactorAuthenticatable e outra no
             * AttemptToAuthenticate. Sem memorizar, cada senha errada
             * contaria por duas, e a porta fecharia com três tentativas em
             * vez de cinco. O resultado fica no próprio objeto da requisição,
             * que é o mesmo nas duas passagens.
             */
            if ($request->attributes->has('pulso.autenticado')) {
                return $request->attributes->get('pulso.autenticado');
            }

            $porteiro = app(PorteiroDoLogin::class);

            $email = (string) $request->input(Fortify::username(), '');
            $ip = (string) $request->ip();

            $usuario = $porteiro->autenticar(
                $email,
                (string) $request->input('password', ''),
                $ip,
                $request->userAgent(),
            );

            $request->attributes->set('pulso.autenticado', $usuario);

            if ($usuario !== null) {
                return $usuario;
            }

            /*
             * A MESMA mensagem para bloqueio por e-mail, por IP e para e-mail
             * inexistente. Diferenciar transformaria a tela de login num
             * confirmador de contas.
             */
            if ($porteiro->bloqueado(mb_strtolower(trim($email)), $ip)) {
                $minutos = $porteiro->minutosParaLiberar(mb_strtolower(trim($email)), $ip);

                throw ValidationException::withMessages([
                    Fortify::username() => "Muitas tentativas. Tente de novo em {$minutos} minuto"
                        .($minutos === 1 ? '' : 's').'.',
                ]);
            }

            return null;
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        /*
         * O Fortify entra headless: ele cuida da regra (limite de tentativas,
         * 2FA, troca de senha) e nos entregamos as telas, para que a
         * autenticacao siga a identidade visual em vez de trazer layout pronto.
         */
        Fortify::loginView(fn () => view('acesso.entrar'));
        Fortify::requestPasswordResetLinkView(fn () => view('acesso.esqueci-a-senha'));
        Fortify::resetPasswordView(fn (Request $request) => view('acesso.redefinir-senha', ['request' => $request]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
