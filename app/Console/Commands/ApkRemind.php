<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApkReminderController;
use App\Mail\ApkReminderMail;
use App\Models\ApkReminder;
use App\Rdw\RdwClient;
use App\Rdw\RdwUnavailableException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ApkRemind extends Command
{
    protected $signature = 'apk:remind {--days=30 : Remind this many days before the APK expires}';

    protected $description = 'Send the APK reminders that are due, delete requests that were never confirmed and delete reminders of cars whose APK has been expired for more than a year';

    public function handle(RdwClient $rdw): int
    {
        // a request that nobody confirmed within a week is deleted
        $deleted = ApkReminder::whereNull('confirmed_at')->where('created_at', '<', now()->subDays(7))->delete();

        $days = (int) $this->option('days');
        $sent = 0;

        // only look up the ones that are (almost) due: the others have a date that is still far away
        $due = ApkReminder::whereNotNull('confirmed_at')
            ->where(fn ($q) => $q->whereNull('apk_expires_at')->orWhere('apk_expires_at', '<=', now()->addDays($days + 1)))
            ->get();

        foreach ($due as $reminder) {
            try {
                $vehicle = $rdw->findVehicle($reminder->license_plate);
            } catch (RdwUnavailableException) {
                continue;   // try again tomorrow
            }

            if ($vehicle === null) {
                $reminder->delete();   // the plate no longer exists

                continue;
            }

            // the APK may have been renewed since: always work with the date the RDW has now
            $reminder->apk_expires_at = ApkReminderController::expiry($vehicle);

            // a year after the APK ran out without a new one the car is probably sold or scrapped: we do not keep the data any longer
            if ($reminder->apk_expires_at?->lt(now()->subYear())) {
                $reminder->delete();

                continue;
            }

            $reminder->save();
            $expires = $reminder->apk_expires_at;
            $isDue = $expires && $expires->isFuture() && $expires->lte(now()->addDays($days)->endOfDay());

            if ($isDue && ! $reminder->last_reminded_for?->equalTo($expires)) {
                Mail::to($reminder->email)->send(new ApkReminderMail($reminder));
                $reminder->update(['last_reminded_for' => $expires]);
                $sent++;
            }
        }

        $this->info("{$sent} reminders sent, {$deleted} unconfirmed requests deleted.");

        return self::SUCCESS;
    }
}
