<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Converts PHP errors to exceptions, logs every unhandled failure and renders
 * a safe error page / JSON without SQL, stack traces or file paths (unless APP_DEBUG).
 */
final class ErrorHandler
{
    private static bool $debug = false;
    private static string $logPath = '';

    public static function register(bool $debug, string $logPath): void
    {
        self::$debug = $debug;
        self::$logPath = rtrim($logPath, '/');

        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::report($e);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, '[' . $e::class . '] ' . $e->getMessage() . PHP_EOL);
                if ($e instanceof ValidationException) {
                    foreach ($e->errors as $field => $messages) {
                        fwrite(STDERR, '  ' . $field . ': ' . implode(' ', $messages) . PHP_EOL);
                    }
                }
                exit(1);
            }
            self::render($e, null)->send();
        });
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function report(Throwable $e): void
    {
        if ($e instanceof HttpException && $e->status < 500) {
            return; // expected client errors are not logged as failures
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        $previous = $e->getPrevious();
        while ($previous !== null) {
            $entry .= sprintf("Caused by %s: %s in %s:%d\n", $previous::class, $previous->getMessage(), $previous->getFile(), $previous->getLine());
            $previous = $previous->getPrevious();
        }

        $file = self::$logPath . '/app-' . date('Y-m-d') . '.log';
        if (self::$logPath !== '' && is_dir(self::$logPath) && is_writable(self::$logPath)) {
            error_log($entry, 3, $file);
        } else {
            error_log($entry);
        }
    }

    public static function render(Throwable $e, ?Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        $headers = $e instanceof HttpException ? $e->headers : [];
        $wantsJson = $request?->expectsJson() ?? str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api');

        $message = Translator::get('errors.' . $status . '.message');
        if ($message === 'errors.' . $status . '.message') {
            $message = Translator::get('errors.500.message');
        }

        if ($wantsJson) {
            $data = self::$debug && $status >= 500 ? ['exception' => $e::class, 'detail' => $e->getMessage()] : null;
            $response = Response::json($data, $message, $status);
        } else {
            try {
                $html = Application::instance()->view()->render('errors/error', [
                    'status' => $status,
                    'message' => $message,
                    'debug' => self::$debug && $status >= 500 ? $e : null,
                ], 'layouts/error');
            } catch (Throwable) {
                $html = '<!doctype html><meta charset="utf-8"><title>' . $status . '</title><h1>' . $status . '</h1>';
            }
            $response = new Response($html, $status);
        }

        foreach ($headers as $name => $value) {
            $response->withHeader($name, $value);
        }

        return $response;
    }
}
