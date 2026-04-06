/**
 * Main.js - Homepage scripts
 */

document.addEventListener("DOMContentLoaded", () => {
  // Check if user is already logged in
  checkExistingSession();

  // Smooth scroll for feature cards
  const featureCards = document.querySelectorAll(".feature-card");
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = "1";
          entry.target.style.transform = "translateY(0)";
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

  // Nav scroll effect
  const nav = document.getElementById("main-nav");
  if (nav) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) {
        nav.style.boxShadow = "0 2px 20px rgba(0,0,0,0.08)";
      } else {
        nav.style.boxShadow = "none";
      }
    });
  }
});
