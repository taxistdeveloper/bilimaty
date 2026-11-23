<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\DictionaryRepository;
use App\Repositories\OptionsRepository;
use App\Services\AuditLogger;

/**
 * Generic CRUD for config/dictionaries.php (departments, specialties, qualifications,
 * subjects, classrooms). Route access: dictionaries.manage.
 */
final class DictionaryController extends Controller
{
    public function hub(): Response
    {
        $cards = [];
        foreach ((array) Config::get('dictionaries', []) as $name => $definition) {
            $cards[] = [
                'name' => $name,
                'icon' => $definition['icon'],
                'count' => (new DictionaryRepository($definition))->count(),
            ];
        }

        return $this->view('dictionaries/hub', ['title' => __('nav.dictionaries'), 'cards' => $cards]);
    }

    public function index(Request $request, string $dictionary): Response
    {
        $definition = $this->definition($dictionary);
        $filters = ['q' => mb_substr(trim((string) $request->query('q', '')), 0, 100)];
        foreach ($definition['fields'] as $name => $field) {
            if (!empty($field['filter'])) {
                $value = (string) $request->query($name, '');
                $filters[$name] = preg_match('/^[a-z0-9_]{1,30}$/i', $value) ? $value : '';
            }
        }

        return $this->view('dictionaries/index', [
            'title' => __('dictionaries.' . $dictionary . '.title'),
            'dictionary' => $dictionary,
            'definition' => $definition,
            'options' => $this->options($definition),
            'filters' => $filters,
            'result' => (new DictionaryRepository($definition))->paginate($filters, max(1, (int) $request->query('page', 1))),
        ]);
    }

    public function create(Request $request, string $dictionary): Response
    {
        $definition = $this->definition($dictionary);
        // Prefill a select from the current list filter (e.g. qualification of a chosen specialty).
        $prefill = ['is_active' => 1];
        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] === 'select' && ctype_digit((string) $request->query($name, ''))) {
                $prefill[$name] = (int) $request->query($name);
            }
        }

        return $this->form($dictionary, $definition, null, $prefill);
    }

    public function store(Request $request, string $dictionary): Response
    {
        $definition = $this->definition($dictionary);
        $data = $this->validated($request, $definition, null);
        $id = (new DictionaryRepository($definition))->create($data);

        AuditLogger::log('dictionaries.created', $definition['table'], $id, null, $data);
        Session::flash('success', __('dictionaries.created'));

        return $this->redirect('/dictionaries/' . $dictionary);
    }

    public function edit(string $dictionary, int $id): Response
    {
        $definition = $this->definition($dictionary);
        $record = (new DictionaryRepository($definition))->find($id) ?? throw new HttpException(404);

        return $this->form($dictionary, $definition, $record, $record);
    }

    public function update(Request $request, string $dictionary, int $id): Response
    {
        $definition = $this->definition($dictionary);
        $repository = new DictionaryRepository($definition);
        $record = $repository->find($id) ?? throw new HttpException(404);

        $data = $this->validated($request, $definition, $id);
        $repository->update($id, $data);

        $changed = array_keys(array_diff_assoc(
            array_map('strval', $data),
            array_map('strval', array_intersect_key($record, $data))
        ));
        if ($changed !== []) {
            $keys = array_flip($changed);
            AuditLogger::log('dictionaries.updated', $definition['table'], $id, array_intersect_key($record, $keys), array_intersect_key($data, $keys));
        }
        Session::flash('success', __('dictionaries.updated'));

        return $this->redirect('/dictionaries/' . $dictionary);
    }

    public function destroy(string $dictionary, int $id): Response
    {
        $definition = $this->definition($dictionary);
        $repository = new DictionaryRepository($definition);
        $record = $repository->find($id) ?? throw new HttpException(404);

        if ($repository->usageCount($id) > 0) {
            Session::flash('error', __('dictionaries.in_use'));

            return $this->redirect('/dictionaries/' . $dictionary . '/' . $id . '/edit');
        }

        $repository->delete($id);
        AuditLogger::log('dictionaries.deleted', $definition['table'], $id, $record);
        Session::flash('success', __('dictionaries.deleted'));

        return $this->redirect('/dictionaries/' . $dictionary);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed>|null $record
     * @param array<string, mixed> $values
     */
    private function form(string $dictionary, array $definition, ?array $record, array $values): Response
    {
        $repository = new DictionaryRepository($definition);

        return $this->view('dictionaries/form', [
            'title' => __('dictionaries.' . $dictionary . '.title'),
            'dictionary' => $dictionary,
            'definition' => $definition,
            'options' => $this->options($definition),
            'record' => $record,
            'values' => $values,
            'usage' => $record !== null ? $repository->usageCount((int) $record['id']) : 0,
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $definition, ?int $id): array
    {
        $rules = [];
        foreach ($definition['fields'] as $name => $field) {
            $rules[$name] = str_replace(',{id}', $id !== null ? ',' . $id : '', $field['rules']);
        }
        $data = $this->validate($request, $rules);

        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] === 'switch') {
                $data[$name] = in_array($data[$name] ?? null, [true, 1, '1', 'on'], true) ? 1 : 0;
            } elseif ($field['type'] === 'select' && isset($data[$name]) && ctype_digit((string) $data[$name])) {
                $data[$name] = (int) $data[$name];
            }
        }
        if (array_key_exists('building', $definition['fields'])) {
            $data['building'] = (string) ($data['building'] ?? ''); // NOT NULL column, '' = main building
        }

        $together = $definition['unique_together'] ?? [];
        if ($together !== []) {
            $values = array_intersect_key($data, array_flip($together));
            if ((new DictionaryRepository($definition))->existsTogether($values, $id)) {
                throw new ValidationException([end($together) => [__('validation.unique', ['field' => __('fields.' . end($together))])]], $request->all());
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<int|string, string>>
     */
    private function options(array $definition): array
    {
        $repository = new OptionsRepository();
        $options = [];
        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] !== 'select') {
                continue;
            }
            $source = (string) $field['options'];
            if (str_starts_with($source, 'enum:')) {
                $prefix = substr($source, 5);
                preg_match('/in:([^|]+)/', $field['rules'], $m);
                foreach (explode(',', $m[1] ?? '') as $value) {
                    $options[$name][$value] = __('dictionaries.' . $prefix . '.' . $value);
                }
                continue;
            }
            $options[$name] = match ($source) {
                'departments' => $repository->departments(),
                'specialties' => $repository->specialties(),
                'teachers' => $repository->teachers(),
                default => throw new \RuntimeException("Unknown options source [{$source}]."),
            };
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private function definition(string $dictionary): array
    {
        $definition = Config::get('dictionaries.' . $dictionary);
        if (!is_array($definition)) {
            throw new HttpException(404);
        }

        return $definition;
    }
}
