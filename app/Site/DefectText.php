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
        return self::cleanCut(self::spellOut($description));
    }

    /** Writes out the gas abbreviations. */
    private static function spellOut(string $description): string
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

    /**
     * The RDW cuts every description off at 60 characters ("...onveilige afvoer uitlaatgasse"). A few cuts can only mean one thing
     * and are completed; for any other cut we stop at the last whole word and add an ellipsis, so we never show half a word
     * and never guess the rest. A description that ends like a sentence is left alone.
     */
    private static function cleanCut(string $text): string
    {
        $fixed = preg_replace(
            [
                '/^(Waarschuwingsinrichting .+?) (geeft|gee)( de)?$/u',   // "... geeft de" => "... geeft defect aan"
                '/^(Waarschuwingsinrichting .+? geeft defect)$/u',
                '/uitlaatgasse$/u',
                '/trommel\/schij$/u',
                '/ \(go$/u',
            ],
            ['$1 geeft defect aan', '$1 aan', 'uitlaatgassen', 'trommel/schijf', ' (goed)'],
            $text,
        );

        if ($fixed !== $text || mb_strlen($text) < 59 || preg_match('/(\)|\.| aan| goed| niet| defect)$/u', $text)) {
            return $fixed;
        }

        $lastSpace = mb_strrpos($text, ' ');

        return $lastSpace === false ? $text : mb_substr($text, 0, $lastSpace + 1).'…';
    }
}
