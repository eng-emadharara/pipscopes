// [data-reveal] elements fade in as they scroll into view; [data-reveal="stagger"] cascades its children.
// The hidden state only applies under .reveal-ready, so nothing stays invisible if this never runs.
const targets = document.querySelectorAll('[data-reveal]');

if (targets.length && 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    document.documentElement.classList.add('reveal-ready');
    targets.forEach((el) => observer.observe(el));
}
