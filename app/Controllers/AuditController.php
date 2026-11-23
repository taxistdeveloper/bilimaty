<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $date = static function (mixed $v): string {
            $v = (string) $v;
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);

            return $d !== false && $d->format('Y-m-d') === $v ? $v : '';
        };
        $filters = [
            'action' => mb_substr((string) $request->query('action', ''), 0, 100),
            'user' => mb_substr(trim((string) $request->query('user', '')), 0, 100),
            'from' => $date($request->query('from', '')),
            'to' => $date($request->query('to', '')),
        ];
        $repository = new AuditRepository();

        return $this->view('audit/index', [
            'title' => __('audit.title'),
            'filters' => $filters,
            'actions' => $repository->actions(),
            'result' => $repository->paginate($filters, max(1, (int) $request->query('page', 1))),
        ]);
    }
}
