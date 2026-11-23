<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\JournalRepository;
use App\Services\JournalService;

/**
 * Student's own grades: results by subject and the journal of one subject (marks and
 * attendance by date). A student sees only their own row and only journals of their groups.
 */
final class GradeController extends Controller
{
    private JournalRepository $journals;

    public function __construct()
    {
        $this->journals = new JournalRepository();
    }

    public function index(): Response
    {
        $studentId = $this->journals->studentIdOfUser((int) Auth::id()) ?? throw new HttpException(403);
        $service = new JournalService($this->journals);
        $items = [];
        foreach ($this->journals->journalsOfStudent($studentId) as $journal) {
            $items[] = ['journal' => $journal, 'result' => $service->studentResult($journal, $studentId), 'scheme' => $service->scheme($journal)];
        }

        return $this->view('grades/index', ['title' => __('journal.my_title'), 'items' => $items]);
    }

    public function show(int $id): Response
    {
        $studentId = $this->journals->studentIdOfUser((int) Auth::id()) ?? throw new HttpException(403);
        $journal = $this->journals->find($id) ?? throw new HttpException(404);
        if (!$this->journals->studentBelongs($journal, $studentId)) {
            throw new HttpException(403);
        }
        $grid = (new JournalService($this->journals))->grid($journal);
        $own = null;
        foreach ($grid['students'] as $s) {
            if ((int) $s['id'] === $studentId) {
                $own = $s;
            }
        }

        return $this->view('grades/show', [
            'title' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]),
            'journal' => $journal,
            'lessons' => $grid['lessons'],
            'components' => $grid['components'],
            'scheme' => $grid['scheme'],
            'student' => $own ?? throw new HttpException(404),
        ]);
    }
}
