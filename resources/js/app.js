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
