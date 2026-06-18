document.addEventListener('click', (event) => {
  const copyButton = event.target.closest('[data-copy]');
  if (!copyButton) return;

  const target = document.querySelector(copyButton.dataset.copy);
  if (!target) return;

  navigator.clipboard?.writeText(target.textContent.trim()).then(() => {
    copyButton.textContent = '已复制';
    setTimeout(() => {
      copyButton.textContent = '复制';
    }, 1400);
  });
});
