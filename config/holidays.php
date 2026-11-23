<?php

declare(strict_types=1);

/**
 * Public holidays of the Republic of Kazakhstan with FIXED dates, used by the
 * "Add public holidays" action of the academic calendar.
 *
 * Verify this list against the current Law "On holidays in the Republic of Kazakhstan"
 * before use and edit it here if the law changes. Holidays with a moving date
 * (Kurban ait) and government transfers of days off are added manually.
 *
 * month-day => [title_ru, title_kk, days]
 */
return [
    '01-01' => ['Новый год', 'Жаңа жыл', 2],
    '01-07' => ['Православное Рождество', 'Православиелік Рождество', 1],
    '03-08' => ['Международный женский день', 'Халықаралық әйелдер күні', 1],
    '03-21' => ['Наурыз мейрамы', 'Наурыз мейрамы', 3],
    '05-01' => ['Праздник единства народа Казахстана', 'Қазақстан халқының бірлігі мерекесі', 1],
    '05-07' => ['День защитника Отечества', 'Отан қорғаушы күні', 1],
    '05-09' => ['День Победы', 'Жеңіс күні', 1],
    '07-06' => ['День Столицы', 'Астана күні', 1],
    '08-30' => ['День Конституции', 'Конституция күні', 1],
    '10-25' => ['День Республики', 'Республика күні', 1],
    '12-16' => ['День Независимости', 'Тәуелсіздік күні', 1],
];
