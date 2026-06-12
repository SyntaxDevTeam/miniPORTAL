const navigation = document.querySelector("[data-site-nav]");

const updateNavigation = () => {
  navigation?.classList.toggle("is-scrolled", window.scrollY > 16);
};

updateNavigation();
window.addEventListener("scroll", updateNavigation, { passive: true });

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", () => {
    const menu = document.querySelector(".navbar-collapse.show");

    if (menu && window.bootstrap) {
      window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
    }
  });
});

const revealElements = document.querySelectorAll(".reveal");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (reduceMotion || !("IntersectionObserver" in window)) {
  revealElements.forEach((element) => element.classList.add("is-visible"));
} else {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.12 }
  );

  revealElements.forEach((element) => observer.observe(element));
}
