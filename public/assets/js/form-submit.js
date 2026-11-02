document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (form.dataset.submitting === 'true') {
    event.preventDefault();
    return;
  }

  form.dataset.submitting = 'true';
  form.setAttribute('aria-busy', 'true');
  form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]').forEach((button) => {
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
  });
});
