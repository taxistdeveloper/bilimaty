<?php
/** Strings used by app.js. JSON data block: not executed, so allowed by the CSP. */
$strings = [
    'notificationsEmpty' => __('notifications.empty'),
    'notificationsError' => __('notifications.load_error'),
    'loading' => __('common.loading'),
];
?>
<script type="application/json" id="app-i18n"><?= json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
