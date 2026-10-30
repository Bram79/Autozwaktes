<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per click on an affiliate link. On purpose without IP address, browser or any other personal data.
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('partner', 40);       // vehicle_report, insurance, parts, apk_mail
            $table->string('page', 190);         // the page the link was on, e.g. /audi/a4/b8
            $table->string('position', 60);      // where on that page, e.g. zijbalk or gebrek: Remmen
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // A defect that matches one of the keywords gets a link to the parts shop of that category.
        Schema::create('parts_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('keywords', 300);     // comma separated, matched in the defect description
            $table->string('url', 1000);         // may contain {merk}, {model} and {jaar}
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Someone who wants an e-mail before the APK of their car expires.
        Schema::create('apk_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('license_plate', 10);
            $table->date('apk_expires_at')->nullable();
            $table->string('token', 64)->unique();      // for the confirm and unsubscribe links
            $table->timestamp('confirmed_at')->nullable();
            $table->date('last_reminded_for')->nullable();   // the expiry date the last mail was about
            $table->timestamps();

            $table->unique(['email', 'license_plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apk_reminders');
        Schema::dropIfExists('parts_categories');
        Schema::dropIfExists('affiliate_clicks');
    }
};
