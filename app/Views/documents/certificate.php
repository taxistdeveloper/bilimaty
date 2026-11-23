<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $d generated document */
/** @var array<string, mixed> $s snapshot (facts frozen at issue) */
/** @var string $qr SVG from App\Helpers\QrCode (no user input inside) */
/** @var string $verifyUrl */
/** @var bool $canRevoke */
$kk = locale() === 'kk';
$st = $s['student'];
$study = $s['study'];
$college = $s['college'];
$collegeName = (string) ($kk ? ($college['name_kk'] ?: $college['name_ru']) : $college['name_ru']);
$female = ($st['gender'] ?? '') === 'female';
$fields = [
    'name' => full_name($st),
    'birth_date' => $st['birth_date'] !== null ? format_date((string) $st['birth_date']) : '—',
    'iin_part' => !empty($st['iin']) ? __('documents.cert.iin', ['iin' => $st['iin']]) : '',
    'college' => $collegeName,
    'course' => (string) ($study['course'] ?? '—'),
    'specialty' => trim(($study['specialty_code'] ?? '') . ' ' . ($kk ? ($study['specialty_kk'] ?? '') : ($study['specialty_ru'] ?? ''))),
    'qualification' => (string) ($kk ? ($study['qualification_kk'] ?? '') : ($study['qualification_ru'] ?? '')),
    'form' => mb_strtolower(__('groups.forms.' . ($study['study_form'] ?? 'full_time'))),
    'funding' => __('documents.cert.funding.' . ($study['funding'] ?? 'paid')),
    'group' => (string) ($study['group'] ?? '—'),
];
$signer = (string) ($kk ? ($college['signer_position_kk'] ?: $college['signer_position_ru']) : $college['signer_position_ru']);
$address = (string) ($kk ? ($college['address_kk'] ?: $college['address_ru']) : $college['address_ru']);
?>
<style>.cert p { font-size: 12pt; line-height: 1.6; text-align: left; } .cert-grid { display: grid; grid-template-columns: 1fr 32mm; gap: 6mm; align-items: start; } .qr svg { width: 32mm; height: 32mm; } .muted-sm { font-size: 8pt; color: #64748b; word-break: break-all; } .revoked { border: 2px solid #b91c1c; color: #b91c1c; padding: 3mm; text-align: center; font-weight: 700; margin-bottom: 4mm; }</style>
<?php if ($d['status'] === 'revoked'): ?>
    <div class="revoked"><?= e(__('transcripts.revoked_banner', ['date' => format_date((string) $d['revoked_at'])])) ?><?= $d['revoke_reason'] ? ': ' . e($d['revoke_reason']) : '' ?></div>
<?php endif; ?>
<header class="doc-header">
    <?php if ($collegeName !== ''): ?><div class="doc-org"><?= e($collegeName) ?></div><?php endif; ?>
    <?php if ($address !== ''): ?><div class="doc-meta"><?= e($address) ?></div><?php endif; ?>
    <h1><?= e(__('documents.cert.title')) ?> № <?= e($d['number']) ?></h1>
    <div class="doc-meta"><?= e(format_date((string) $s['issued_on'])) ?></div>
</header>

<div class="cert-grid cert">
    <div>
        <p><?= e(__('documents.cert.body', $fields)) ?><?= $fields['qualification'] !== '' ? ' ' . e(__('documents.cert.qualification', $fields)) : '' ?></p>
        <p><?= e(__('documents.cert.group', $fields)) ?>
            <?php if (!empty($study['enrollment_date'])): ?>
                <?= e(__($female ? 'documents.cert.enrolled_f' : 'documents.cert.enrolled_m', ['date' => format_date((string) $study['enrollment_date'])])) ?><?= !empty($study['enrollment_order']) ? ' ' . e(__('documents.cert.order', ['order' => ltrim((string) $study['enrollment_order'], '№ ')])) : '' ?>.
            <?php endif; ?>
        </p>
        <?php if (!empty($s['purpose'])): ?><p><?= e(__('documents.cert.purpose', ['purpose' => $s['purpose']])) ?></p><?php endif; ?>
    </div>
    <div class="qr"><?= $qr ?><div class="muted-sm"><?= e(__('transcripts.qr_hint')) ?></div></div>
</div>

<p style="margin-top: 16mm"><?= e($signer !== '' ? $signer : __('transcripts.signature')) ?> ____________________ <?= e((string) ($college['signer_name'] ?? '')) ?> &nbsp;&nbsp; <?= e(__('transcripts.stamp')) ?></p>
<footer class="doc-footer">
    <?= e(__('documents.cert.request', ['number' => (string) ($s['request_number'] ?? '')])) ?> · <?= e(__('transcripts.fingerprint')) ?>: <?= e(strtoupper(substr((string) $d['fingerprint'], 0, 16))) ?> · <?= e(__('transcripts.verify_at')) ?> <?= e($verifyUrl) ?>
</footer>

<?php if ($canRevoke): ?>
    <form method="post" action="<?= e(url('/documents/' . $d['request_id'] . '/certificate/revoke')) ?>" class="print-toolbar" style="position: static; margin-top: 8mm; border-radius: 6px">
        <?= csrf_field() ?>
        <label for="revokeReason"><?= e(__('transcripts.revoke')) ?>:</label>
        <input id="revokeReason" name="reason" maxlength="255" required placeholder="<?= e(__('schedule.reason')) ?>" style="padding: 6px; border-radius: 4px; border: 0; min-width: 240px">
        <button type="submit"><?= e(__('transcripts.revoke')) ?></button>
    </form>
<?php endif; ?>
