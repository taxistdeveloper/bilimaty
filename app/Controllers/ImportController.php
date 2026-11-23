<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Csv;
use App\Services\ImportService;

/**
 * Bulk import of students / teachers: upload → preview with checks → import → credentials.
 * students needs students.manage, teachers needs teachers.manage.
 */
final class ImportController extends Controller
{
    private const MAX_FILE = 5 * 1024 * 1024;

    public function form(string $type): Response
    {
        $this->authorizeType($type);

        return $this->view('import/form', ['title' => __('import.title_' . $type), 'type' => $type, 'columns' => ImportService::columns($type)]);
    }

    public function template(string $type): Response
    {
        $this->authorizeType($type);
        $headings = array_map(static fn (array $names): string => ImportService::heading($names[0]), ImportService::columns($type));

        return Csv::download([array_values($headings)], 'import-' . $type . '-template.csv');
    }

    public function preview(Request $request, string $type): Response
    {
        $this->authorizeType($type);
        $file = $request->file('file');
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException(['file' => [__('import.file_required')]]);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true) || (int) $file['size'] > self::MAX_FILE) {
            throw new ValidationException(['file' => [__('import.file_type')]]);
        }
        $result = (new ImportService())->preview($type, (string) $file['tmp_name'], (string) $file['name']);
        Session::flash('import_unknown', $result['unknown']);

        return $this->redirect('/import/' . $type . '/preview/' . $result['token']);
    }

    public function show(string $type, string $token): Response
    {
        $this->authorizeType($type);
        $data = (new ImportService())->load($token, $type);
        if ($data === null) {
            Session::flash('error', __('import.expired'));

            return $this->redirect('/import/' . $type);
        }

        return $this->view('import/preview', [
            'title' => __('import.preview'),
            'type' => $type,
            'token' => $token,
            'rows' => $data['rows'],
            'unknown' => (array) Session::getFlash('import_unknown', []),
            'columns' => ImportService::columns($type),
        ]);
    }

    public function commit(string $type, string $token): Response
    {
        $this->authorizeType($type);
        $result = (new ImportService())->commit($token, $type);
        // Shown on the result page and downloadable once; removed after download or the next import.
        Session::set('import_result', ['type' => $type] + $result);

        return $this->redirect('/import/' . $type . '/result');
    }

    public function result(string $type): Response
    {
        $this->authorizeType($type);
        $result = Session::get('import_result');
        if (!is_array($result) || $result['type'] !== $type) {
            return $this->redirect('/import/' . $type);
        }

        return $this->view('import/result', ['title' => __('import.result'), 'type' => $type, 'result' => $result]);
    }

    public function download(string $type): Response
    {
        $this->authorizeType($type);
        $result = Session::get('import_result');
        if (!is_array($result) || $result['type'] !== $type) {
            throw new HttpException(404);
        }
        Session::remove('import_result');
        $rows = [[__('users.name'), __($type === 'students' ? 'fields.group_id' : 'fields.department_id'), __('fields.login'), __('import.temp_password')]];
        foreach ($result['created'] as $c) {
            $rows[] = [$c['name'], $c['extra'], $c['login'], $c['password']];
        }

        return Csv::download($rows, 'credentials-' . $type . '-' . date('Ymd-His') . '.csv')->withHeader('Cache-Control', 'no-store');
    }

    private function authorizeType(string $type): void
    {
        if (!in_array($type, ImportService::TYPES, true)) {
            throw new HttpException(404);
        }
        if (!Auth::can($type === 'students' ? 'students.manage' : 'teachers.manage')) {
            throw new HttpException(403);
        }
    }
}
