<?php
/**
 * Tabs of the HR section.
 *
 * @var string $active index|employees|orders|leaves|qualification
 */
$tabs = ['index' => ['/hr', 'hr.overview', 'bi-speedometer2'], 'employees' => ['/hr/employees', 'hr.employees', 'bi-person-vcard'], 'orders' => ['/hr/orders', 'hr.orders', 'bi-file-earmark-ruled'],
    'leaves' => ['/hr/leaves', 'hr.leaves', 'bi-calendar2-x'], 'qualification' => ['/hr/qualification', 'hr.qualification', 'bi-award']];
?>
<nav class="mb-3" aria-label="<?= e(__('hr.title')) ?>">
    <ul class="nav nav-pills flex-nowrap overflow-auto gap-1">
        <?php foreach ($tabs as $key => [$path, $label, $icon]): ?>
            <li class="nav-item"><a class="nav-link text-nowrap<?= $active === $key ? ' active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?> href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e(__($label)) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
