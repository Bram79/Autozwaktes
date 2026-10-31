<?php

namespace App\Site;

/** The face of the site: the name, and the logo if there is a logo file. */
class Brand
{
    /** Put your own logo in public/images as logo.svg (or logo.png): it replaces the name with the yellow dot in the header. */
    public static function logoUrl(): ?string
    {
        foreach (['svg', 'png'] as $extension) {
            if (is_file(public_path("images/logo.{$extension}"))) {
                return asset("images/logo.{$extension}");
            }
        }

        return null;
    }
}
