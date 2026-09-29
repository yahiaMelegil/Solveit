<?php

return [
    // Expand this catalogue only after an owner has defined the evidence and
    // jurisdiction policy for the proposed service. Unknown domains fail closed.
    'regulated_domains' => [
        'legal', 'medical', 'health', 'finance', 'investment', 'tax', 'accounting', 'engineering',
    ],

    'non_regulated_domains' => [
        'technology', 'design', 'marketing', 'career', 'education', 'translation', 'business',
    ],
];
