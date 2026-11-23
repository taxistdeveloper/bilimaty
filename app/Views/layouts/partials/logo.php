<?php
/**
 * BILIMATY brand mark (shield with the monogram from the mascot's hoodie) in a blue tile.
 *
 * @var string|null $size '' | 'lg'
 */
$size ??= '';
?>
<span class="app-logo<?= $size === 'lg' ? ' app-logo--lg' : '' ?>" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        <path d="M12 2.8 19.6 5.6v5.9c0 4.6-3.2 8.1-7.6 9.7-4.4-1.6-7.6-5.1-7.6-9.7V5.6Z"/>
        <path d="M8.2 9.2 12 11.6l3.8-2.4v3.4c0 2.1-1.7 3.7-3.8 4.3-2.1-.6-3.8-2.2-3.8-4.3Z"/>
    </svg>
</span>
