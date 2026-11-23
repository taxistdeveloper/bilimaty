<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Application kernel: bootstraps config, errors, session, locale and routes,
 * then turns the current Request into a Response.
 */
final class Application
{
    private static ?self $instance = null;

    private Router $router;
    private ?View $view = null;
    private ?Request $request = null;

    public function __construct(public readonly string $basePath)
    {
        self::$instance = $this;

        Env::load($basePath . '/.env');
        Config::init($basePath . '/config');
        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Almaty'));
        mb_internal_encoding('UTF-8');
        ErrorHandler::register((bool) Config::get('app.debug', false), $basePath . '/storage/logs');

        Translator::init(
            $basePath . '/app/Lang',
            (string) Config::get('app.locale', 'ru'),
            (string) Config::get('app.fallback_locale', 'ru'),
            (array) Config::get('app.locales', ['kk', 'ru'])
        );

        $this->router = new Router((array) Config::get('app.middleware_aliases', []));
        $this->router->setGlobalMiddleware((array) Config::get('app.global_middleware', []));
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been bootstrapped.');
        }

        return self::$instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function view(): View
    {
        return $this->view ??= new View($this->basePath . '/app/Views');
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    /** All application routes (loads the route files once). @return list<array<string, mixed>> */
    public function routes(): array
    {
        if ($this->router->routes() === []) {
            $this->loadRoutes();
        }

        return $this->router->routes();
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;

        try {
            if (Tenant::enabled()) {
                $resolved = \App\Platform\TenantResolver::resolve($request);
                if ($resolved instanceof Response) {
                    return $resolved;
                }
                $this->request = $request = $resolved;
            }

            Session::start([
                'name' => (string) Config::get('app.session.name', 'collegeos_session'),
                'lifetime' => (int) Config::get('app.session.lifetime', 30),
                'path' => $request->baseUrl !== '' ? $request->baseUrl . '/' : '/',
                'save_path' => Config::get('app.session.save_path'),
            ], (bool) Config::get('app.session.secure', false) || $request->isSecure());

            if (Tenant::enabled()) {
                // A session belongs to one college (or to the owner's panel): a cookie copied
                // from another college never authenticates here.
                if (Session::get('_tenant') !== Tenant::sessionKey()) {
                    Session::detach();
                    Session::set('_tenant', Tenant::sessionKey());
                }
            }

            $this->resolveLocale();
            $this->loadRoutes();

            if (Tenant::enabled() && !Tenant::isPlatform() && ($blocked = \App\Platform\SubscriptionGate::check($request)) !== null) {
                return $blocked;
            }

            return $this->router->dispatch($request);
        } catch (ValidationException $e) {
            return $this->renderValidation($e, $request);
        } catch (Throwable $e) {
            ErrorHandler::report($e);

            return ErrorHandler::render($e, $request);
        }
    }

    private function resolveLocale(): void
    {
        $locale = Session::get('locale');
        if (!is_string($locale) && Auth::id() !== null) {
            $locale = Auth::user()['locale'] ?? null;
        }
        if (!is_string($locale)) {
            // College-wide default chosen by the administrator (settings), else config.
            $locale = \App\Services\Settings::get('default_locale');
        }
        if (is_string($locale)) {
            Translator::setLocale($locale);
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        if (Tenant::isPlatform()) {
            require $this->basePath . '/routes/platform.php';

            return;
        }
        require $this->basePath . '/routes/web.php';
        $router->group(['prefix' => 'api'], function (Router $router): void {
            require $this->basePath . '/routes/api.php';
        });
    }

    private function renderValidation(ValidationException $e, Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json(null, Translator::get('validation.failed'), 422, $e->errors);
        }

        // Never flash secrets back into the form.
        $old = array_diff_key($e->oldInput, array_flip(['password', 'password_confirmation', '_token', '_method']));
        Session::flash('errors', $e->errors);
        Session::flash('old', $old);

        return Response::redirect(safe_referer($request) ?? url($request->path));
    }
}
