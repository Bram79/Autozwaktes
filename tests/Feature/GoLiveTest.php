<?php

use App\Providers\AppServiceProvider;
use App\Site\StatsService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
});

it('shows a helpful 404 page with search and popular models, and keeps it out of Google', function () {
    $this->get('/audi/bestaat-niet')->assertNotFound()
        ->assertSee('Deze pagina bestaat niet')
        ->assertSee('Populaire modellen')
        ->assertSee('/ranglijsten', false)
        ->assertSee('noindex,follow', false);
});

it('shows a plain 500 page without technical details', function () {
    config(['app.debug' => false]);
    // the home page needs the statistics: when they break, the visitor must not see why
    app()->bind(StatsService::class, fn () => throw new RuntimeException('geheime databasefout'));

    $this->withExceptionHandling()->get('/')->assertStatus(500)->assertSee('Er ging iets mis')->assertDontSee('geheime databasefout');
});

it('has a maintenance page', function () {
    expect(view('errors.503')->render())->toContain('We zijn zo terug');
});

it('adds the Search Console tag only when a code is set', function () {
    $this->get('/')->assertDontSee('google-site-verification', false);

    config(['site.google_verification' => 'abc123xyz']);
    $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123xyz">', false);
});

it('sends security headers, and HSTS only over https', function () {
    $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeaderMissing('Strict-Transport-Security');

    $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/')->assertHeader('Strict-Transport-Security');

    // a header that a controller set itself is left alone (the affiliate redirect sends no referrer at all)
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/']);
    $this->get('/go/vehicle_report')->assertHeader('Referrer-Policy', 'no-referrer');
});

it('keeps the admin and the redirects out of the search engines', function () {
    $this->get('/robots.txt')->assertSee('Disallow: /admin/')->assertSee('Disallow: /go/')->assertSee('Sitemap:');
});

it('makes every link https in production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    expect(route('home'))->toStartWith('https://');
    URL::forceScheme('http');
});

it('says what is not ready with site:check, and fails on a real problem', function () {
    $this->artisan('site:check')->assertSuccessful()->expectsOutputToContain('Database bereikbaar')->expectsOutputToContain('MAIL_MAILER is');

    // on the live site, debug mode and a log mailer are not allowed
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => true, 'site.admin_emails' => []]);

    $this->artisan('site:check')->assertFailed()->expectsOutputToContain('APP_DEBUG staat aan')->expectsOutputToContain('ADMIN_EMAILS is leeg');
});

it('is happy with a correct live configuration', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false, 'app.url' => 'https://nuvik.example', 'mail.default' => 'smtp', 'site.admin_emails' => ['ik@example.com'],
        'site.contact_email' => 'info@example.com', 'site.operator_name' => 'Bram Meijer', 'site.google_verification' => 'abc',
        'site.backup_passphrase' => 'een-lange-zin', 'session.secure' => true, 'trustedproxy.proxies' => '10.0.0.1',
    ]);

    $this->artisan('site:check')->assertSuccessful()->expectsOutputToContain('APP_URL is https://nuvik.example');
});

it('backs up the database without the big import tables, without the password on the command line, and keeps only recent ones', function () {
    config(['database.connections.backup_test' => ['host' => 'db.example', 'port' => 3306, 'username' => 'gebruiker', 'password' => 'geheim-wachtwoord', 'database' => 'nuvik']]);
    $directory = storage_path('app/backups');
    @mkdir($directory, 0775, true);
    file_put_contents("{$directory}/2020-01-01_000000.sql.gz", 'old');
    touch("{$directory}/2020-01-01_000000.sql.gz", now()->subDays(30)->getTimestamp());

    $seen = null;
    Process::fake(function ($process) use (&$seen) {
        $seen = $process;
        foreach ($process->command as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, 14), "-- dump\nCREATE TABLE x (id int);\n");
            }
        }

        return Process::result('');
    });

    $this->artisan('backup:database --keep=14 --connection=backup_test')->assertSuccessful();

    $files = glob("{$directory}/*.sql.gz");
    expect($files)->toHaveCount(1);                                   // the new one; the 30-day-old one is gone
    expect(gzdecode(file_get_contents($files[0])))->toContain('CREATE TABLE x');
    expect($seen->command)->toContain('--single-transaction')->toContain('--ignore-table=nuvik.staging_defects')->toContain('--host=db.example');
    expect(implode(' ', $seen->command))->not->toContain('geheim-wachtwoord');
    expect($seen->environment)->toBe(['MYSQL_PWD' => 'geheim-wachtwoord']);

    array_map('unlink', $files);
});

it('keeps backups private: only the owner can open the folder and the files', function () {
    config(['database.connections.backup_test' => ['host' => 'db.example', 'port' => 3306, 'username' => 'gebruiker', 'password' => 'x', 'database' => 'nuvik']]);
    $directory = storage_path('app/backups');
    @mkdir($directory, 0775, true);
    chmod($directory, 0775);
    Process::fake(function ($process) {
        foreach ($process->command as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, 14), '-- dump');
            }
        }

        return Process::result('');
    });

    $this->artisan('backup:database --connection=backup_test')->assertSuccessful();

    $files = glob("{$directory}/*.sql.gz");
    expect(fileperms($directory) & 0777)->toBe(0700);
    expect(fileperms($files[0]) & 0777)->toBe(0600);

    array_map('unlink', $files);
});

it('encrypts the backup when a passphrase is set, with the passphrase in the environment and not on the command line', function () {
    config([
        'database.connections.backup_test' => ['host' => 'db.example', 'port' => 3306, 'username' => 'gebruiker', 'password' => 'x', 'database' => 'nuvik'],
        'site.backup_passphrase' => 'een-lange-geheime-zin',
    ]);
    $directory = storage_path('app/backups');
    @mkdir($directory, 0700, true);

    $commands = [];
    Process::fake(function ($process) use (&$commands) {
        $commands[] = $process;
        foreach ($process->command as $i => $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, 14), '-- dump');
            }
            if ($argument === '-out') {
                file_put_contents($process->command[$i + 1], 'encrypted');   // what openssl would have written
            }
        }

        return Process::result('');
    });

    $this->artisan('backup:database --connection=backup_test')->assertSuccessful();

    $encrypted = glob("{$directory}/*.sql.gz.enc");
    expect($encrypted)->toHaveCount(1);
    expect(glob("{$directory}/*.sql.gz"))->toBe([]);                    // the readable copy is gone
    expect(fileperms($encrypted[0]) & 0777)->toBe(0600);

    $openssl = collect($commands)->first(fn ($p) => $p->command[0] === 'openssl');
    expect($openssl->command)->toContain('-aes-256-cbc')->toContain('-pbkdf2');
    expect(implode(' ', $openssl->command))->not->toContain('een-lange-geheime-zin');
    expect($openssl->environment)->toBe(['BACKUP_PASSPHRASE' => 'een-lange-geheime-zin']);

    array_map('unlink', $encrypted);
});

it('stops the deploy script from leaving the site in maintenance mode when a step fails', function () {
    $script = file_get_contents(base_path('scripts/deploy.sh'));

    expect(strpos($script, 'trap '))->toBeInt()->toBeLessThan(strpos($script, 'php artisan down'));
    // after the new code is pulled, a failure keeps the site in maintenance mode: new code on an old database must not be live
    expect($script)->toContain('CODE_CHANGED');
    expect(Process::run(['bash', '-n', base_path('scripts/deploy.sh')])->successful())->toBeTrue();
});

it('does not let a live site start without an encrypted backup, a secure session cookie and a known proxy', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false, 'app.url' => 'https://nuvik.example', 'mail.default' => 'smtp', 'site.admin_emails' => ['ik@example.com'],
        'site.contact_email' => 'info@example.com', 'site.operator_name' => 'Bram Meijer', 'site.google_verification' => 'abc',
        'site.backup_passphrase' => null, 'session.secure' => false, 'trustedproxy.proxies' => null, 'site.csp' => 'off',
    ]);

    $this->artisan('site:check')->assertFailed()
        ->expectsOutputToContain('BACKUP_PASSPHRASE ontbreekt')
        ->expectsOutputToContain('SESSION_SECURE_COOKIE staat uit')
        ->expectsOutputToContain('TRUSTED_PROXIES is leeg')
        ->expectsOutputToContain('CSP_MODE staat uit');
});

it('warns about a backup without passphrase and still makes the backup', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['database.connections.backup_test' => ['host' => 'db.example', 'port' => 3306, 'username' => 'gebruiker', 'password' => 'x', 'database' => 'nuvik'], 'site.backup_passphrase' => null]);
    @mkdir(storage_path('app/backups'), 0700, true);
    Process::fake(function ($process) {
        foreach ($process->command as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, 14), '-- dump');
            }
        }

        return Process::result('');
    });

    $this->artisan('backup:database --connection=backup_test')->assertSuccessful()->expectsOutputToContain('niet versleuteld');

    array_map('unlink', glob(storage_path('app/backups').'/*.sql.gz'));
});

it('reports a failed backup', function () {
    config(['database.connections.backup_test' => ['host' => 'db.example', 'port' => 3306, 'username' => 'gebruiker', 'password' => 'x', 'database' => 'nuvik']]);
    Process::fake(['*' => Process::result('', 'Access denied', 2)]);

    $this->artisan('backup:database --connection=backup_test')->assertFailed();
});

it('documents every setting that the site reads from .env', function () {
    $config = file_get_contents(config_path('site.php'));
    preg_match_all("/env\('([A-Z_]+)'/", $config, $matches);
    $example = file_get_contents(base_path('.env.production.example'));

    $missing = array_values(array_filter(array_unique($matches[1]), fn ($key) => ! str_contains($example, $key.'=')));

    expect($missing)->toBe([]);
});

it('has a deploy script and a go-live checklist', function () {
    expect(is_executable(base_path('scripts/deploy.sh')))->toBeTrue();
    expect(file_get_contents(base_path('docs/LIVEGANG.md')))->toContain('Search Console')->toContain('Backups')->toContain('supervisor');
});
