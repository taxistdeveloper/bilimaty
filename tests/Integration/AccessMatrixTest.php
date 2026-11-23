<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Config;

/**
 * Every page (GET route without parameters) opened by every role:
 *  - anonymous visitors are sent to the login page;
 *  - a role without any of the route's permissions gets 403;
 *  - a role with a permission gets the page (200) or a redirect to its own page (302).
 * Pages tied to a personal profile (a student's or a teacher's own data) are listed in PROFILE.
 */
final class AccessMatrixTest extends IntegrationTestCase
{
    /** Pages that also need a profile of the user (not only a permission): route => roles that have it. */
    private const PROFILE = [
        '/grades' => ['student'],
        '/exams/my' => ['student'],
        '/transcripts/my' => ['student'],
        '/documents/create' => ['student'], // requests are filed by students; staff only process them
        '/groups/my' => ['teacher'],          // a teacher's own groups and subjects
        '/journal/subjects' => ['teacher'],
    ];

    /** @return array<string, list<string>> login => role slugs */
    private function users(): array
    {
        return [
            $this->user('admin') => ['super_admin'],
            $this->user('t.admin', 'admin') => ['admin'],
            $this->user('t.deputy', 'deputy_director') => ['deputy_director'],
            $this->user('t.office', 'academic_office') => ['academic_office'],
            $this->user('t.hr', 'hr') => ['hr'],
            $this->user('seitqali.ab') => ['department_head', 'teacher'],
            $this->user('petrov.av') => ['teacher', 'curator'],
            $this->user('qasymova.i') => ['student'],
            $this->user('t.parent', 'parent') => ['parent'],
        ];
    }

    /** @return list<string> GET pages without parameters */
    private function pages(): array
    {
        $pages = [];
        foreach (Application::instance()->routes() as $r) {
            if ($r['method'] === 'GET' && !str_contains($r['uri'], '{') && !str_starts_with($r['uri'], '/api')
                && !str_contains($r['uri'], 'export') && !str_contains($r['uri'], 'print')) {
                $pages[$r['uri']] = $r['middleware'];
            }
        }

        return $pages;
    }

    public function testAnonymousVisitorsAreSentToLogin(): void
    {
        $anon = new Http();
        foreach ($this->pages() as $uri => $middleware) {
            $r = $anon->get($uri);
            if (in_array('auth', $middleware, true)) {
                $this->assertSame('/login', $r['location'], "Anonymous {$uri} → {$r['status']}");
            } else {
                $this->assertContains($r['status'], [200, 302], "Public page {$uri}");
            }
        }
    }

    public function testEveryRoleGetsExactlyItsPages(): void
    {
        $roles = (array) Config::get('permissions.roles');
        $all = array_keys((array) Config::get('permissions.permissions'));
        $wrong = [];
        foreach ($this->users() as $login => $slugs) {
            $perms = in_array('super_admin', $slugs, true) ? $all : array_merge(...array_map(static fn (string $s): array => $roles[$s][2], $slugs));
            $http = (new Http())->login($login);
            foreach ($this->pages() as $uri => $middleware) {
                if (!in_array('auth', $middleware, true)) {
                    continue;
                }
                $need = [];
                foreach ($middleware as $mw) {
                    if (str_starts_with($mw, 'can:')) {
                        $need[] = explode(',', substr($mw, 4));
                    }
                }
                // every can: middleware on the route (group + route) must be satisfied by one permission
                $allowed = array_reduce($need, static fn (bool $ok, array $any): bool => $ok && array_intersect($any, $perms) !== [], true);
                if ($allowed && isset(self::PROFILE[$uri])) {
                    $allowed = array_intersect(self::PROFILE[$uri], $slugs) !== [];
                }
                $status = $http->get($uri)['status'];
                if ($allowed ? !in_array($status, [200, 302], true) : $status !== 403) {
                    $wrong[] = implode('+', $slugs) . " {$uri}: got {$status}, expected " . ($allowed ? '200/302' : '403');
                }
            }
        }
        $this->assertSame([], $wrong, implode("\n     ", $wrong));
        $this->check(true, '');
    }

    public function testRecordsOfOtherDepartmentsAreHidden(): void
    {
        $itStudent = $this->studentId('qasymova.i');
        $ecoStudent = $this->studentId('volkova.m');
        $headIt = $this->as('seitqali.ab');
        $this->assertSame(200, $headIt->get("/students/{$itStudent}")['status']);
        $this->assertSame(403, $headIt->get("/students/{$ecoStudent}")['status'], 'Department head sees only own department');
        $student = $this->as('qasymova.i');
        $this->assertSame(403, $student->get("/students/{$itStudent}")['status'], 'Students do not open student cards');
        $other = (int) $this->value("SELECT a.id FROM teacher_subject_assignments a JOIN `groups` g ON g.id = a.group_id WHERE g.code = 'УА-26-1' LIMIT 1");
        $this->assertSame(403, $student->get("/grades/{$other}")['status'], 'Journal of another group');
    }
}
