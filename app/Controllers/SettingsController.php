<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SettingsRepository;
use App\Services\AuditLogger;
use App\Services\Settings;

final class SettingsController extends Controller
{
    public function edit(): Response
    {
        return $this->view('settings/index', [
            'title' => __('settings.title'),
            'schema' => (array) Config::get('settings', []),
            'values' => (new SettingsRepository())->all(),
        ]);
    }

    public function update(Request $request): Response
    {
        $schema = (array) Config::get('settings', []);
        $data = $this->validate($request, array_map(static fn (array $s): string => $s[1], $schema));

        $repository = new SettingsRepository();
        $before = $repository->all();
        $changes = [];
        Database::transaction(function () use ($schema, $data, $repository, $before, &$changes): void {
            foreach ($schema as $key => [$type]) {
                $value = $type === 'bool'
                    ? (in_array($data[$key] ?? null, [true, 1, '1', 'on'], true) ? '1' : '0')
                    : (isset($data[$key]) ? (string) $data[$key] : null);
                if (($before[$key] ?? null) !== $value) {
                    $repository->set($key, $value, $type, Auth::id());
                    $changes[$key] = [$before[$key] ?? null, $value];
                }
            }
        });

        if ($changes !== []) {
            AuditLogger::log(
                'settings.updated',
                'settings',
                null,
                array_map(static fn (array $c) => $c[0], $changes),
                array_map(static fn (array $c) => $c[1], $changes)
            );
        }
        Settings::flush();
        Session::flash('success', __('settings.saved'));

        return $this->redirect('/settings');
    }
}
