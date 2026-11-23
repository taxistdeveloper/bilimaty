<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: rendering, JSON, redirects, validation and authorization helpers.
 * Controllers stay thin: queries live in Repositories, rules in Services/Policies.
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app', int $status = 200): Response
    {
        $html = Application::instance()->view()->render($view, $data, $layout);

        return new Response($html, $status);
    }

    /** @param array<string, list<string>>|null $errors */
    protected function json(mixed $data = null, string $message = '', int $status = 200, ?array $errors = null): Response
    {
        return Response::json($data, $message, $status, $errors);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    /** Redirect to the same-origin referer, or to $fallback. */
    protected function back(Request $request, string $fallback = '/'): Response
    {
        return Response::redirect(safe_referer($request) ?? url($fallback));
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     * @throws ValidationException
     */
    protected function validate(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), $request->all());
        }

        return $validator->validated();
    }

    /** @throws HttpException 403 */
    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw new HttpException(403);
        }
    }
}
