/**
 * Main.js - Homepage scripts
 * Animations for feature cards and nav scroll effect.
 */

document.addEventListener("DOMContentLoaded", () => {
  // Redirect to dashboard if user is already logged in
  checkExistingSession();

  // ----- Animate feature cards as they scroll into view -----
  const featureCards = document.querySelectorAll(".feature-card");
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
        }
      });
    },
    { threshold: 0.1 },
  );

  featureCards.forEach((card, index) => {
    card.style.opacity = "0";
    card.style.transform = "translateY(30px)";
    card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
    observer.observe(card);
  });

  // ----- Add shadow to nav bar on scroll -----
  const nav = document.getElementById("main-nav");
  if (nav) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) {
        nav.classList.add("nav-scrolled");
      } else {
        nav.classList.remove("nav-scrolled");
      }
    });
  }
});
