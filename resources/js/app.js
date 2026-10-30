import { attachPlateFormatting } from './plate';
import { attachPointerEffects } from './hero';

// Laat elementen met de klasse "reveal" (en de balkjes) soepel verschijnen zodra ze in beeld komen.
function observeReveals() {
    const items = document.querySelectorAll('.reveal:not(.is-visible), .bar-wrap:not(.is-visible)');

    if (! ('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    items.forEach((el) => observer.observe(el));
}

document.addEventListener('DOMContentLoaded', observeReveals);
// Livewire ververst delen van de pagina (zoeken, filters): daarna opnieuw kijken.
document.addEventListener('livewire:navigated', observeReveals);
document.addEventListener('livewire:initialized', () => {
    window.Livewire.hook('morphed', () => requestAnimationFrame(observeReveals));
});

// Cookie banner. Advertising is only loaded after the visitor says yes.
const CONSENT_COOKIE = 'az_consent';

function consentValue() {
    const match = document.cookie.match(new RegExp(`(?:^|; )${CONSENT_COOKIE}=([^;]*)`));

    return match ? match[1] : null;
}

function saveConsent(value) {
    // six months, as a plain preference cookie (it only remembers this choice)
    document.cookie = `${CONSENT_COOKIE}=${value}; max-age=${60 * 60 * 24 * 180}; path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
}

function loadAds() {
    const url = document.querySelector('meta[name="ads-script"]')?.content;

    if (! url || window.adsLoaded) {
        return;
    }

    window.adsLoaded = true;
    const script = document.createElement('script');
    script.src = url;
    script.async = true;
    document.head.appendChild(script);
    document.querySelectorAll('[data-ad-slot]').forEach((slot) => { slot.hidden = false; });
}

document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('consent-banner');

    if (! banner) {
        return;
    }

    // no choice made yet: ask; accepted before: load the ads right away
    banner.hidden = consentValue() !== null;
    if (consentValue() === 'accepted') {
        loadAds();
    }

    banner.querySelectorAll('[data-consent]').forEach((button) => {
        button.addEventListener('click', () => {
            saveConsent(button.dataset.consent);
            banner.hidden = true;

            if (button.dataset.consent === 'accepted') {
                loadAds();
            } else {
                // choosing "only necessary" after accepting: reload so that the ad script is gone
                if (window.adsLoaded) {
                    location.reload();
                }
            }
        });
    });

    document.querySelectorAll('[data-consent-open]').forEach((link) => {
        link.addEventListener('click', () => { banner.hidden = false; });
    });
});

// Streepjes in het kenteken terwijl je typt
document.addEventListener('DOMContentLoaded', attachPlateFormatting);

// Lichtvlek in de hero en kantelende kaarten (alleen met muis, niet bij minder beweging)
document.addEventListener('DOMContentLoaded', attachPointerEffects);
document.addEventListener('livewire:navigated', attachPointerEffects);
