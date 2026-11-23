<?php

declare(strict_types=1);

/**
 * Editable system settings: key => [type, validation rules, label key].
 */
return [
    'college_name_ru' => ['string', 'required|string|max:255', 'settings.college_name_ru'],
    'college_name_kk' => ['string', 'required|string|max:255', 'settings.college_name_kk'],
    'default_locale' => ['string', 'required|in:kk,ru', 'settings.default_locale'],
    'journal_lock_after_approval' => ['bool', 'nullable|boolean', 'settings.journal_lock_after_approval'],
    'college_address_ru' => ['string', 'nullable|string|max:255', 'settings.college_address_ru'],
    'college_address_kk' => ['string', 'nullable|string|max:255', 'settings.college_address_kk'],
    'document_signer_position_ru' => ['string', 'nullable|string|max:150', 'settings.document_signer_position_ru'],
    'document_signer_position_kk' => ['string', 'nullable|string|max:150', 'settings.document_signer_position_kk'],
    'document_signer_name' => ['string', 'nullable|string|max:150', 'settings.document_signer_name'],
];
