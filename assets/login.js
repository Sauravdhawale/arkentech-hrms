const password = document.getElementById('login-password');
const passwordToggle = document.querySelector('.password-toggle');
if (password && passwordToggle) {
 passwordToggle.hidden = false;
 passwordToggle.addEventListener('click', () => {
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  passwordToggle.setAttribute('aria-pressed', String(visible));
  passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
 });
}
const motionToggle = document.querySelector('.motion-toggle');
const visual = document.querySelector('.login-visual');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let paused = false;
function updateMotion() {
 if (!motionToggle || !visual) return;
 motionToggle.hidden = reducedMotion.matches;
 visual.classList.toggle('motion-paused', paused || document.hidden);
 motionToggle.setAttribute('aria-pressed', String(paused));
 motionToggle.textContent = paused ? 'Resume motion' : 'Pause motion';
}
motionToggle?.addEventListener('click', () => { paused = !paused; updateMotion(); });
reducedMotion.addEventListener('change', updateMotion);
document.addEventListener('visibilitychange', updateMotion);
updateMotion();
