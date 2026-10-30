<?php

namespace App\Console\Commands;

use App\Models\CarModel;
use App\Models\ImportRun;
use App\Site\AffiliateLink;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SiteCheck extends Command
{
    protected $signature = 'site:check';

    protected $description = 'Check whether the site is ready to go live (settings, database, data, mail, affiliate links)';

    public function handle(): int
    {
        $production = app()->environment('production');
        $checks = [];

        // [status, text]: ok, warn (should be fixed) or fail (must be fixed)
        $add = function (string $status, string $text) use (&$checks) {
            $checks[] = [$status, $text];
        };

        $production ? $add('ok', 'APP_ENV is production') : $add('warn', 'APP_ENV is "'.app()->environment().'" (zet het op production voor je live gaat)');
        config('app.debug') ? $add($production ? 'fail' : 'warn', 'APP_DEBUG staat aan: foutmeldingen met code zijn dan zichtbaar voor bezoekers') : $add('ok', 'APP_DEBUG staat uit');
        filled(config('app.key')) ? $add('ok', 'APP_KEY is ingesteld') : $add('fail', 'APP_KEY ontbreekt (php artisan key:generate)');

        $url = (string) config('app.url');
        if (! str_starts_with($url, 'https://') || preg_match('/localhost|127\.0\.0\.1|\.test$|\.local/', $url)) {
            $add($production ? 'fail' : 'warn', "APP_URL is \"{$url}\": gebruik je echte domein met https://");
        } else {
            $add('ok', "APP_URL is {$url}");
        }

        try {
            DB::connection()->getPdo();
            $add('ok', 'Database bereikbaar');
        } catch (Throwable $e) {
            $add('fail', 'Database niet bereikbaar: '.$e->getMessage());
        }

        in_array(config('mail.default'), ['log', 'array'], true)
            ? $add($production ? 'fail' : 'warn', 'MAIL_MAILER is "'.config('mail.default').'": APK-herinneringen worden dan niet echt verstuurd')
            : $add('ok', 'Mail verstuurt via '.config('mail.default'));
        config('queue.default') === 'sync' ? $add('warn', 'QUEUE_CONNECTION is sync: de RDW-import heeft een echte wachtrij nodig (database of redis)') : $add('ok', 'Wachtrij: '.config('queue.default'));
        config('cache.default') === 'array' ? $add('warn', 'CACHE_STORE is array: de cache blijft dan niet bewaard') : $add('ok', 'Cache: '.config('cache.default'));

        config('site.admin_emails') !== [] ? $add('ok', 'ADMIN_EMAILS is ingesteld') : $add($production ? 'fail' : 'warn', 'ADMIN_EMAILS is leeg: buiten de lokale omgeving kan niemand in /admin');
        filled(config('site.contact_email')) ? $add('ok', 'CONTACT_EMAIL staat op de privacypagina') : $add($production ? 'fail' : 'warn', 'CONTACT_EMAIL ontbreekt: de privacyverklaring noemt geen contactadres');
        filled(config('site.operator_name')) ? $add('ok', 'OPERATOR_NAME staat op de privacypagina') : $add($production ? 'fail' : 'warn', 'OPERATOR_NAME ontbreekt: de privacyverklaring zegt niet wie verantwoordelijk is');

        file_exists(public_path('build/manifest.json')) ? $add('ok', 'Vite-assets zijn gebouwd') : $add('fail', 'public/build ontbreekt (npm run build)');
        is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')) ? $add('ok', 'storage en bootstrap/cache zijn schrijfbaar') : $add('fail', 'storage of bootstrap/cache is niet schrijfbaar');

        try {
            $online = CarModel::withStats()->count();
            $online > 0 ? $add('ok', "{$online} modellen hebben cijfers") : $add('warn', 'Nog geen enkel model heeft cijfers (draai de import en rdw:calculate)');

            $last = ImportRun::where('status', 'finished')->latest('finished_at')->first();
            if ($last === null) {
                $add('warn', 'Er is nog geen import afgerond');
            } else {
                Carbon::parse($last->finished_at)->lt(now()->subDays(14))
                    ? $add('warn', 'De laatste import is ouder dan 14 dagen (draait de scheduler?)')
                    : $add('ok', 'Laatste import: '.Carbon::parse($last->finished_at)->diffForHumans());
            }
        } catch (Throwable) {
            $add('fail', 'De tabellen ontbreken (php artisan migrate --force)');
        }

        $affiliates = collect(config('site.affiliates'))->keys()->filter(fn ($type) => AffiliateLink::target($type) !== null)->count();
        $affiliates > 0 ? $add('ok', "{$affiliates} affiliate-partner(s) ingesteld") : $add('warn', 'Er is nog geen affiliate-partner ingesteld: de site verdient dan niets');

        filled(config('site.google_verification')) ? $add('ok', 'Search Console-verificatie staat aan') : $add('warn', 'GOOGLE_SITE_VERIFICATION ontbreekt (nodig voor Search Console)');
        config('site.ads.enabled') ? $add('ok', 'Advertenties staan aan (met cookiebanner)') : $add('ok', 'Advertenties staan uit');

        foreach ($checks as [$status, $text]) {
            $this->line(match ($status) {
                'ok' => "<info>  OK    </info> {$text}",
                'warn' => "<comment>  LET OP</comment> {$text}",
                default => "<error>  FOUT  </error> {$text}",
            });
        }

        $fails = count(array_filter($checks, fn ($c) => $c[0] === 'fail'));
        $warns = count(array_filter($checks, fn ($c) => $c[0] === 'warn'));
        $this->newLine();
        $this->line($fails === 0 ? "Geen fouten, {$warns} aandachtspunt(en)." : "{$fails} fout(en) en {$warns} aandachtspunt(en): los de fouten eerst op.");

        return $fails === 0 ? self::SUCCESS : self::FAILURE;
    }
}
