<?php

return [
    /*
    | Chatnachrichten sind betriebliche Kurzzeitkommunikation. Die konkrete
    | Frist muss der Verantwortliche mit Datenschutz/Personalvertretung
    | festlegen. Sie kann ohne Codeaenderung ueber die Umgebung gesetzt werden.
    */
    'chat_retention_days' => (int) env('INTERNAL_CHAT_RETENTION_DAYS', 365),

    'max_attachment_kilobytes' => (int) env('INTERNAL_CHAT_MAX_ATTACHMENT_KB', 10240),

    'allowed_attachment_mimes' => [
        'jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'csv',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    ],
];
