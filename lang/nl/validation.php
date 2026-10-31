<?php

// Dutch texts for the validation rules that the site and the admin use. A rule that is not here falls back to the English text.
return [
    'accepted' => ':Attribute moet worden geaccepteerd.',
    'array' => ':Attribute moet een lijst zijn.',
    'between' => [
        'numeric' => ':Attribute moet tussen :min en :max liggen.',
        'string' => ':Attribute moet tussen :min en :max tekens lang zijn.',
    ],
    'boolean' => ':Attribute moet ja of nee zijn.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'date' => ':Attribute is geen geldige datum.',
    'digits' => ':Attribute moet uit :digits cijfers bestaan.',
    'email' => ':Attribute is geen geldig e-mailadres.',
    'exists' => 'De gekozen waarde voor :attribute is ongeldig.',
    'in' => 'De gekozen waarde voor :attribute is ongeldig.',
    'integer' => ':Attribute moet een geheel getal zijn.',
    'max' => [
        'numeric' => ':Attribute mag niet groter zijn dan :max.',
        'string' => ':Attribute mag niet langer zijn dan :max tekens.',
    ],
    'min' => [
        'numeric' => ':Attribute moet minstens :min zijn.',
        'string' => ':Attribute moet minstens :min tekens lang zijn.',
    ],
    'numeric' => ':Attribute moet een getal zijn.',
    'regex' => 'De opmaak van :attribute is ongeldig. Een webadres moet met http:// of https:// beginnen.',
    'required' => ':Attribute is verplicht.',
    'string' => ':Attribute moet tekst zijn.',
    'unique' => ':Attribute is al in gebruik.',
    'url' => ':Attribute is geen geldig webadres.',

    'attributes' => [],
];
