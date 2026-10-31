<?php

namespace App\Site;

use RuntimeException;

/** Another process is building the sitemap right now. */
class SitemapBusyException extends RuntimeException {}
