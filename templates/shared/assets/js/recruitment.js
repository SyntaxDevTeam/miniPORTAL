'use strict';
document.querySelectorAll('[data-recruitment]').forEach(root => {
  const form = root.querySelector('form');
  if (!form) return;
  const required = Array.from(form.querySelectorAll('[required]')).filter(el => el.type !== 'hidden');
  const update = () => {
    const filled = required.filter(el => el.type === 'checkbox' ? el.checked : el.value.trim() !== '').length;
    const percent = required.length ? Math.round(filled / required.length * 100) : 100;
    root.querySelector('progress').value = percent;
    root.querySelector('[data-recruitment-progress]').textContent = `${filled} z ${required.length} wymaganych pól · ${percent}%`;
  };
  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
});
