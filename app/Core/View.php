<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * PHP-template renderer with layouts, sections and partials.
 * Templates see $this (View) and the extracted $data. Always escape output with e().
 */
final class View
{
    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    public function __construct(private readonly string $viewsPath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    public function partial(string $view, array $data = []): string
    {
        return $this->renderFile($view, $data);
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('View::stop() called without start().');
        }
        $this->sections[$name] = ($this->sections[$name] ?? '') . (string) ob_get_clean();
    }

    /** Output a section (raw: sections contain already-escaped template HTML). */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $view, array $data): string
    {
        if (!preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#i', $view)) {
            throw new RuntimeException("Invalid view name [{$view}].");
        }
        $file = $this->viewsPath . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
