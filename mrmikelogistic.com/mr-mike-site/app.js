const menuButton = document.querySelector('.menu-toggle');
if (menuButton) { menuButton.addEventListener('click', () => { const wrap = document.querySelector('.nav-wrap'); wrap.classList.toggle('menu-open'); menuButton.setAttribute('aria-expanded', wrap.classList.contains('menu-open') ? 'true' : 'false') }); }
document.querySelectorAll('.nav-wrap nav a').forEach(link => link.addEventListener('click', () => document.querySelector('.nav-wrap')?.classList.remove('menu-open')));
const dateField = document.querySelector('input[name="travel_date"]');
if (dateField) { const today = new Date(); const offset = today.getTimezoneOffset(); dateField.min = new Date(today.getTime() - offset * 60000).toISOString().slice(0, 10); }
