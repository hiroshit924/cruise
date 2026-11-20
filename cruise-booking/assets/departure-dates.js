(() => {
  'use strict';
  const form = document.querySelector('#c365-departure-dates');
  if (!form) return;

  const start = form.elements.start_date;
  const end = form.elements.end_date;
  const button = form.querySelector('[type=submit]');
  const status = document.querySelector('#c365-departure-result');

  start.addEventListener('change', () => {
    if (!start.value) return;
    end.min = start.value;
    if (!end.value || end.value < start.value) end.value = start.value;
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (button.disabled) return;
    const data = new FormData(form);
    const totals = { created: 0, existing: 0, closed: 0, failed: 0 };
    const summary = () => `${totals.created}件を公開、既存${totals.existing}件、受付終了${totals.closed}件、追加できなかった枠${totals.failed}件。`;
    button.disabled = true;
    status.textContent = '出航枠を追加しています…';
    try {
      let next = start.value;
      while (next) {
        data.set('start_date', next);
        const response = await fetch(window.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(typeof result.data === 'string' ? result.data : '出航枠を追加できませんでした。');
        }
        for (const key of Object.keys(totals)) totals[key] += result.data[key];
        next = result.data.next_date;
        status.textContent = (next ? '出航枠を追加しています… ' : '') + summary();
      }
      if (totals.failed) status.textContent += '同じ期間で再実行すると、追加できなかった枠を再試行できます。';
    } catch (error) {
      status.textContent = summary() + '処理を中断しました。同じ期間で再実行してください。';
      if (error instanceof Error) status.textContent += ' ' + error.message;
    } finally {
      button.disabled = false;
    }
  });
})();
