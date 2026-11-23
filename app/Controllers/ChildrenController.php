<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\ExamRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\JournalRepository;
use App\Repositories\StudentRepository;
use App\Services\JournalService;

/**
 * Parent's area: the children linked to the parent and, for each child, grades by subject,
 * the subject journal (marks and attendance), ratings and exams. Read only; a parent can open
 * only the students linked to them.
 */
final class ChildrenController extends Controller
{
    private GuardianRepository $guardians;
    private JournalRepository $journals;

    public function __construct()
    {
        $this->guardians = new GuardianRepository();
        $this->journals = new JournalRepository();
    }

    public function index(): Response
    {
        $children = $this->guardians->childrenOf((int) Auth::id());
        if (count($children) === 1) {
            return $this->redirect('/children/' . $children[0]['id']);
        }

        return $this->view('children/index', ['title' => __('children.title'), 'children' => $children]);
    }

    public function show(int $id): Response
    {
        $child = $this->child($id);
        $service = new JournalService($this->journals);
        $items = [];
        $absent = 0;
        $excused = 0;
        foreach ($this->journals->journalsOfStudent($id) as $journal) {
            $result = $service->studentResult($journal, $id);
            $items[] = ['journal' => $journal, 'result' => $result, 'scheme' => $service->scheme($journal)];
            $absent += (int) ($result['absent'] ?? 0);
            $excused += (int) ($result['excused'] ?? 0);
        }

        return $this->view('children/show', [
            'title' => full_name($child),
            'child' => $child,
            'items' => $items,
            'absent' => $absent,
            'excused' => $excused,
            'exams' => (new ExamRepository())->forStudent($id),
            'children' => $this->guardians->childrenOf((int) Auth::id()),
        ]);
    }

    public function journal(int $id, int $journalId): Response
    {
        $child = $this->child($id);
        $journal = $this->journals->find($journalId) ?? throw new HttpException(404);
        if (!$this->journals->studentBelongs($journal, $id)) {
            throw new HttpException(403);
        }
        $grid = (new JournalService($this->journals))->grid($journal);
        $row = null;
        foreach ($grid['students'] as $s) {
            if ((int) $s['id'] === $id) {
                $row = $s;
            }
        }

        return $this->view('grades/show', [
            'title' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]),
            'journal' => $journal,
            'lessons' => $grid['lessons'],
            'components' => $grid['components'],
            'scheme' => $grid['scheme'],
            'student' => $row ?? throw new HttpException(404),
            'back' => '/children/' . $id,
            'childName' => full_name($child, true),
        ]);
    }

    /** @return array<string, mixed> the student, only if linked to the signed-in parent */
    private function child(int $id): array
    {
        if (!$this->guardians->isGuardian((int) Auth::id(), $id)) {
            throw new HttpException(403);
        }

        return (new StudentRepository())->find($id) ?? throw new HttpException(404);
    }
}
