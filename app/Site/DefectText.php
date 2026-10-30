<?php

namespace App\Site;

/**
 * The RDW names its defects with abbreviations that most people do not know ("CNG-/LNG- tank heeft deuk(en)").
 * This writes them out for the visitor. The imported data stays as the RDW sent it; only what is shown changes.
 * (LPG is left alone: most drivers know "LPG".)
 */
class DefectText
{
    public static function plain(string $description): string
    {
        // CNG is compressed natural gas, LNG is liquefied natural gas: for a car owner both are simply "aardgas"
        $plain = preg_replace(
            [
                '/CNG-?\/\s?LNG-?\s?tank/',                       // "CNG-/LNG- tank" => one word
                '/\s*CNG-?\/\s?LNG-?\s?onder$/',                  // the RDW cuts "onderdelen" off at 60 characters
                '/(slangen|Vulaansluiting|behuizing) CNG-?\/\s?LNG-?/', // "Vulaansluiting CNG/LNG" => "Vulaansluiting voor aardgas"
                '/CNG-?\/\s?LNG-?/',                              // any other mention
            ],
            ['aardgastank', ' aan aardgasonderdelen', '$1 voor aardgas', 'aardgas'],
            $description,
        );

        // keep the capital letter at the start of the sentence
        return ($plain !== $description && ctype_upper(mb_substr($description, 0, 1))) ? mb_strtoupper(mb_substr($plain, 0, 1)).mb_substr($plain, 1) : $plain;
    }
}
