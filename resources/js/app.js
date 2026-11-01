import { attachPlateFormatting } from './plate';

// Laat elementen met de klasse "reveal" (en de balkjes) soepel verschijnen zodra ze in beeld komen.
// Blokken die bij het laden nog onder de vouw staan, verschijnen pas bij het scrollen (op elke pagina, zonder dat de view iets hoeft te doen).
function markBelowTheFold() {
    document.querySelectorAll('#inhoud > *:not(.reveal)').forEach((block) => {
        if (block.querySelector('.reveal') || block.getBoundingClientRect().top < window.innerHeight * 0.9) {
            return;
        }

        block.classList.add('reveal');
    });
}

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

// Getallen met data-count lopen van 0 op naar de echte waarde (die al in de HTML staat) zodra ze in beeld komen.
// Zonder JavaScript, zonder IntersectionObserver of met "minder beweging" blijft de tekst gewoon staan.
function countUp(el) {
    const target = parseFloat(el.dataset.count);
    const finalText = el.textContent;
    // "23,4%" of "€ 12.345": het getal telt, het voorvoegsel (€) en het achtervoegsel (%) blijven staan
    const [, prefix, number, suffix] = finalText.match(/^(\D*)([\d.,]+)(.*)$/);
    const decimals = (number.split(',')[1] || '').length;
    const numberFormat = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const format = { format: (value) => prefix + numberFormat.format(value) + suffix };
    const duration = 1100;
    const start = performance.now();

    // De breedte vastzetten, zodat de tekst ernaast niet heen en weer springt tijdens het tellen
    el.style.display = 'inline-block';
    el.style.minWidth = `${el.offsetWidth}px`;
    el.style.textAlign = 'right';

    const step = (now) => {
        const t = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - t, 4);
        el.textContent = t < 1 ? format.format(target * eased) : finalText;
        if (t < 1) {
            requestAnimationFrame(step);
        }
    };

    el.textContent = format.format(0);
    requestAnimationFrame(step);
}

function observeCounts() {
    const items = document.querySelectorAll('[data-count]:not([data-counted])');

    if (! ('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                countUp(entry.target);
            }
        });
    }, { threshold: 0.6 });

    items.forEach((el) => {
        el.dataset.counted = '1';
        observer.observe(el);
    });
}

function observeAll() {
    if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        markBelowTheFold();
    }
    observeReveals();
    observeCounts();
}

document.addEventListener('DOMContentLoaded', observeAll);
// Livewire ververst delen van de pagina (zoeken, filters): daarna opnieuw kijken.
document.addEventListener('livewire:navigated', observeAll);
document.addEventListener('livewire:initialized', () => {
    window.Livewire.hook('morphed', () => requestAnimationFrame(observeAll));
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

            // the focused button just disappeared: give the focus back to where the visitor was, so a keyboard user does not start at the top
            (lastFocus && document.contains(lastFocus) ? lastFocus : document.getElementById('inhoud'))?.focus();

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

    // "Cookie-instellingen" in the footer opens the banner again: move the focus into it (and back afterwards)
    let lastFocus = null;
    document.querySelectorAll('[data-consent-open]').forEach((link) => {
        link.addEventListener('click', () => {
            lastFocus = link;
            banner.hidden = false;
            banner.querySelector('[data-consent]')?.focus();
        });
    });
});

// A form that was sent back with errors: the focus goes to the block with the errors, so that a screen reader reads it
document.addEventListener('DOMContentLoaded', () => document.getElementById('fouten')?.focus());

// Streepjes in het kenteken terwijl je typt
document.addEventListener('DOMContentLoaded', attachPlateFormatting);
