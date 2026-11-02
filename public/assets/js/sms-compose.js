(() => {
  const message = document.getElementById('body');
  const count = document.getElementById('segment-count');
  const template = document.getElementById('template_id');
  const type = document.getElementById('message_type');
  const form = message?.closest('form');
  const recipientCount = document.getElementById('recipient-count');
  const preview = document.getElementById('message-preview');
  let segmentTotal = 1;
  let countController = null;
  const calculate = () => {
    if (!message || !count) return;
    const value = message.value;
    const basic = /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà]*$/u.test(value);
    const units = basic ? [...value].reduce((n, c) => n + ('^{}\\[~]|€'.includes(c) ? 2 : 1), 0) : [...value].length;
    const limit = basic ? (units <= 160 ? 160 : 153) : (units <= 70 ? 70 : 67);
    segmentTotal = Math.max(1, Math.ceil(units / limit));
    count.textContent = `${[...value].length} characters · ${segmentTotal} SMS segment${segmentTotal === 1 ? '' : 's'}`;
  };
  const refreshRecipientCount = async () => {
    if (!form || !recipientCount) return;
    if (countController) countController.abort();
    countController = new AbortController();
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(form).entries()) {
      if (key === 'csrf_test_name' || key === 'send_mode' || key === 'scheduled_date' || key === 'scheduled_time') continue;
      params.append(key, value);
    }
    params.set('body_segments', String(segmentTotal));
    recipientCount.textContent = 'Counting…';
    try {
      const url = new URL(form.dataset.recipientCountUrl, window.location.href);
      url.search = params.toString();
      const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: countController.signal});
      if (!response.ok) throw new Error('Count failed');
      const result = await response.json();
      recipientCount.textContent = result.count + ' SMS · about ' + result.segments + ' credits';
      if (preview) preview.textContent = result.preview || 'No eligible recipients to preview.';
    } catch (error) {
      if (error.name !== 'AbortError') recipientCount.textContent = 'Recipient count unavailable';
    }
  };
  message?.addEventListener('input', calculate);
  message?.addEventListener('input', refreshRecipientCount);
  template?.addEventListener('change', () => {
    const selected = template.selectedOptions[0];
    if (selected?.dataset.body) message.value = selected.dataset.body;
    if (type) type.value = selected?.dataset.type || 'general';
    if (selected?.dataset.type && type) type.value = selected.dataset.type;
    calculate();
    refreshRecipientCount();
  });
  document.querySelectorAll('[data-placeholder]').forEach((button) => button.addEventListener('click', () => {
    const text = button.dataset.placeholder || '';
    const start = message.selectionStart; const end = message.selectionEnd;
    message.setRangeText(text, start, end, 'end'); message.focus(); calculate();
  }));
  form?.querySelectorAll('[name="recipient_type"], [name="purok_id"], [name="category"], [name="due_from"], [name="due_to"], [name="child_ids_text"]').forEach((field) => {
    field.addEventListener('change', refreshRecipientCount);
    field.addEventListener('input', refreshRecipientCount);
  });
  calculate();
  refreshRecipientCount();
})();
