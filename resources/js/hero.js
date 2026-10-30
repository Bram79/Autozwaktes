// Small pointer effects for the homepage. They only run with a mouse or trackpad (hover and a fine pointer)
// and never when the visitor asked for less motion. Without them the page looks and works the same.
function pointerEffectsAllowed() {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches
        && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// A soft light that slowly follows the mouse inside the hero.
function attachSpotlight() {
    document.querySelectorAll('[data-spotlight]:not([data-bound])').forEach((hero) => {
        const glow = hero.querySelector('[data-spotlight-glow]');

        if (! glow) {
            return;
        }

        hero.dataset.bound = '1';
        let frame = null;

        hero.addEventListener('pointermove', (event) => {
            if (frame) {
                return;
            }

            frame = requestAnimationFrame(() => {
                const rect = hero.getBoundingClientRect();
                glow.style.transform = `translate3d(${event.clientX - rect.left}px, ${event.clientY - rect.top}px, 0) translate(-50%, -50%)`;
                glow.classList.add('is-active');
                frame = null;
            });
        });

        hero.addEventListener('pointerleave', () => glow.classList.remove('is-active'));
    });
}

// Cards lean a few degrees towards the pointer.
function attachTilt() {
    document.querySelectorAll('[data-tilt]:not([data-bound])').forEach((card) => {
        card.dataset.bound = '1';

        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            card.style.setProperty('--ry', `${((x - 0.5) * 5).toFixed(2)}deg`);
            card.style.setProperty('--rx', `${((0.5 - y) * 5).toFixed(2)}deg`);
            card.style.setProperty('--gx', `${(x * 100).toFixed(1)}%`);
            card.style.setProperty('--gy', `${(y * 100).toFixed(1)}%`);
        });

        card.addEventListener('pointerleave', () => {
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
        });
    });
}

export function attachPointerEffects() {
    if (! pointerEffectsAllowed()) {
        return;
    }

    attachSpotlight();
    attachTilt();
}
